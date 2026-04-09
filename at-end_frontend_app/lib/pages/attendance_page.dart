import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import '../widgets/course_book_icon.dart';

import '../models/attendance_record.dart';
import '../models/qr_submit_result.dart';
import '../models/student.dart';
import '../services/api_service.dart';
import '../services/device_service.dart';
import '../services/location_service.dart';
import '../services/offline_service.dart';
import '../services/last_attendance_prefs.dart';
import '../services/session_cache_prefs.dart';
import '../services/session_qr_host_guard.dart';
import '../services/success_chime.dart';
import '../services/sync_service.dart';
import '../utils/app_selectable_scope.dart';
import '../utils/app_state.dart';
import '../utils/connectivity_util.dart';
import '../utils/attendance_flow_mode.dart';
import '../utils/constants.dart';
import '../utils/session_attendance_payload.dart';
import '../widgets/attendance_soft_location_panel.dart';
import 'attendance_history_page.dart';
import 'qr_scan_page.dart';

/// Step 1: range check → Step 2 (if QR required): scan → submit.
/// Pass [session] from the home list; if null, loads from [ApiService.getActiveSessions] (first item).
class AttendancePage extends StatefulWidget {
  const AttendancePage({
    super.key,
    this.session,
    this.autoOpenQr = false,
  });

  /// Selected active session (from multi-session home list).
  final Map<String, dynamic>? session;

  /// When true and session [mode] is QR-only, opens the scanner immediately after load.
  final bool autoOpenQr;

  @override
  State<AttendancePage> createState() => _AttendancePageState();
}

class _AttendancePageState extends State<AttendancePage> {
  final _sessionCodeController = TextEditingController();
  Map<String, dynamic>? _session;
  Student? _student;
  bool _withinRange = false;
  double? _distanceMeters;
  /// After check: session range + GPS accuracy margin (see [LocationService.adjustedRangeMeters]).
  double? _effectiveRangeMeters;
  double? _gpsAccuracyMeters;
  bool _rangeChecked = false;
  bool _checkingRange = false;
  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _isCheckingOut = false;
  String? _error;
  bool _showSuccessOverlay = false;
  String _successSubtitle = 'You have successfully marked attendance';
  /// Local DB + optional API `already_marked` on session.
  bool _alreadyMarkedForSession = false;

  DateTime? _sessionEndTime;
  String _remainingText = '--:--';
  bool _sessionEnded = false;
  Timer? _countTimer;

  /// Soft location UI: 0 = today check-in, 1 = recent list.
  int _softLocationTab = 0;

  AttendanceFlowMode get _mode => resolveAttendanceFlowMode(_session);
  bool get _isCheckInCheckoutMode =>
      (_session?['attendance_mode']?.toString() ?? '') == 'checkin_checkout' ||
      ApiService.attendanceMode == ApiService.attendanceModeCheckInCheckout;
  bool get _isCheckedIn => (_session?['check_in_time']?.toString().isNotEmpty ?? false);
  bool get _isCheckedOut => (_session?['check_out_time']?.toString().isNotEmpty ?? false);
  bool get _canCheckOut => _isCheckInCheckoutMode &&
      (_session?['checkout_enabled'] == true || _session?['can_check_out'] == true) &&
      _isCheckedIn &&
      !_isCheckedOut;

  double get _allowedRangeMeters {
    final r = (_session?['range_meters'] as num?)?.toDouble();
    return r ?? Constants.defaultRangeMeters;
  }

  /// Time shown on the soft location card (session start or “now”).
  DateTime _sessionReferenceDateTime() {
    final s = _session;
    if (s == null) return DateTime.now();
    for (final k in [
      'start_time',
      'starts_at',
      'scheduled_at',
      'opened_at',
      'created_at',
    ]) {
      final v = s[k];
      if (v == null) continue;
      final str = v.toString();
      if (str.contains(':') &&
          !str.contains('T') &&
          str.length <= 12) {
        try {
          final parts = str.split(':');
          final h = int.parse(parts[0].trim());
          final m = int.parse(
            parts[1].replaceAll(RegExp(r'[^0-9]'), ''),
          );
          final n = DateTime.now();
          return DateTime(n.year, n.month, n.day, h, m);
        } catch (_) {}
      }
      try {
        return DateTime.parse(str);
      } catch (_) {}
    }
    return DateTime.now();
  }

  String _formatSoftCardDate(DateTime d) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${d.day} ${months[d.month - 1]}, ${d.year}';
  }

  String _softVenueLine() {
    final v = (_session?['venue']?.toString() ?? '').trim();
    if (v.isNotEmpty) return v;
    final venue = _sessionVenueLatLng();
    if (venue != null) {
      return 'Class location · ${venue.lat.toStringAsFixed(4)}, ${venue.lng.toStringAsFixed(4)}';
    }
    return 'Venue will appear here when the session includes an address.';
  }

  String? _softCourseLine() {
    final s = _session;
    if (s == null) return null;
    final code = (s['course_code']?.toString() ?? '').trim();
    final name = (s['course_name'] ?? s['course_title'] ?? '').toString().trim();
    if (code.isNotEmpty && name.isNotEmpty) return '$code · $name';
    if (name.isNotEmpty) return name;
    if (code.isNotEmpty) return code;
    return null;
  }

  /// Venue point from session (supports `lat`/`lng` or `latitude`/`longitude`, string or num).
  ({double lat, double lng})? _sessionVenueLatLng() {
    final s = _session;
    if (s == null) return null;
    final lat = LocationService.parseCoordinate(
      s['lat'] ?? s['latitude'],
    );
    final lng = LocationService.parseCoordinate(
      s['lng'] ?? s['longitude'],
    );
    if (lat == null || lng == null) return null;
    if (!lat.isFinite || !lng.isFinite) return null;
    return (lat: lat, lng: lng);
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _sessionCodeController.dispose();
    _countTimer?.cancel();
    super.dispose();
  }

  DateTime? _parseSessionEndTime(Map<String, dynamic> session) {
    final raw = session['end_time'] ?? session['ends_at'];
    if (raw != null) {
      try {
        return DateTime.parse(raw.toString());
      } catch (_) {}
    }
    final rm = session['remaining_minutes'];
    int? mins;
    if (rm is int) {
      mins = rm;
    } else if (rm is num) {
      mins = rm.round();
    } else {
      mins = int.tryParse(rm?.toString() ?? '');
    }
    if (mins != null && mins > 0) {
      return DateTime.now().add(Duration(minutes: mins));
    }
    return null;
  }

  String _formatDurationRemaining(Duration difference) {
    if (difference.isNegative) return 'Session ended';
    final h = difference.inHours;
    final m = difference.inMinutes.remainder(60);
    final s = difference.inSeconds.remainder(60);
    if (h > 0) {
      return '${h.toString().padLeft(2, '0')}:'
          '${m.toString().padLeft(2, '0')}:'
          '${s.toString().padLeft(2, '0')}';
    }
    return '${m.toString().padLeft(2, '0')}:'
        '${s.toString().padLeft(2, '0')}';
  }

  void _tickRemaining() {
    if (!mounted || _session == null) return;
    final end = _sessionEndTime ?? _parseSessionEndTime(_session!);
    if (end == null) return;
    final now = DateTime.now();
    final difference = end.difference(now);
    if (difference.isNegative) {
      setState(() {
        _sessionEnded = true;
        _remainingText = 'Session ended';
      });
      _countTimer?.cancel();
      _countTimer = null;
      return;
    }
    setState(() {
      _sessionEnded = false;
      _remainingText = _formatDurationRemaining(difference);
    });
  }

  void _startSessionCountdown() {
    _countTimer?.cancel();
    _countTimer = null;
    if (_session == null) return;
    _sessionEndTime = _parseSessionEndTime(_session!);
    if (_sessionEndTime == null) {
      setState(() => _remainingText = '--:--');
      return;
    }
    _tickRemaining();
    _countTimer = Timer.periodic(const Duration(seconds: 1), (_) => _tickRemaining());
  }

  Future<void> _load() async {
    try {
      await ApiService.loadAppSettings();
    } catch (_) {}
    await SessionCachePrefs.clear();
    if (await hasInternetConnectivity()) {
      await OfflineService.hasPasswordOrApiToken();
    }
    setState(() {
      _isLoading = true;
      _error = null;
      _session = null;
      _alreadyMarkedForSession = false;
      _rangeChecked = false;
      _withinRange = false;
      _distanceMeters = null;
      _effectiveRangeMeters = null;
      _gpsAccuracyMeters = null;
    });

    Student? loadedStudent;
    try {
      loadedStudent = await OfflineService.getCurrentStudent();
      if (widget.session != null) {
        final s = Map<String, dynamic>.from(widget.session!);
        debugPrint('activeSession (from home): $s');
        if (mounted) {
          setState(() {
            _session = s;
            _error = null;
          });
          _startSessionCountdown();
        }
      } else {
        final sessions = await ApiService.getActiveSessions(
          indexNumber: loadedStudent?.indexNumber,
        );
        if (mounted) {
          if (sessions.isNotEmpty) {
            debugPrint('activeSession (first of ${sessions.length}): ${sessions.first}');
            setState(() {
              _session = sessions.first;
              _error = null;
            });
            _startSessionCountdown();
          } else if (Constants.useDemoActiveSessionWhenEmpty) {
            final demo = Map<String, dynamic>.from(Constants.demoActiveSession);
            debugPrint('activeSession: $demo');
            setState(() {
              _session = demo;
              _error = null;
            });
            _startSessionCountdown();
          } else {
            final apiErr = ApiService.lastActiveSessionErrorMessage;
            setState(() {
              _session = null;
              _error = apiErr.isNotEmpty ? apiErr : 'No active session.';
            });
          }
        }
      }
    } catch (e) {
      if (Constants.useDemoActiveSessionWhenEmpty && mounted) {
        final demo = Map<String, dynamic>.from(Constants.demoActiveSession);
        debugPrint('activeSession: $demo');
        setState(() {
          _session = demo;
          _error = null;
        });
        _startSessionCountdown();
      } else if (mounted) {
        setState(() => _error = 'Cannot load session.');
      }
    }

    if (_session != null) {
      await _refreshSessionFromActiveList();
    }

    loadedStudent ??= await OfflineService.getCurrentStudent();

    var alreadyMarked = false;
    if (loadedStudent != null && _session != null) {
      final sid = parseSessionId(Map<String, dynamic>.from(_session!));
      if (sid != null) {
        alreadyMarked = await OfflineService.hasMarkedSessionToday(
          indexNumber: loadedStudent.indexNumber,
          sessionId: sid,
        );
      }
      if (_session!['already_marked'] == true) {
        alreadyMarked = true;
      }
    }

    if (mounted) {
      setState(() {
        _isLoading = false;
        _student = loadedStudent;
        _alreadyMarkedForSession = alreadyMarked;
      });
    }
    _maybeAutoOpenQr();
  }

  void _maybeAutoOpenQr() {
    if (!widget.autoOpenQr || !mounted || _session == null) return;
    if (_alreadyMarkedForSession || _student == null) return;
    if (resolveAttendanceFlowMode(_session) != AttendanceFlowMode.qr) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || _alreadyMarkedForSession) return;
      _openQrAndSubmit();
    });
  }

  /// Re-fetch GET /sessions/active and replace [_session] with the same [id] so
  /// [course_id] / [week_id] / [qr_token] are never stale after app start.
  Future<void> _refreshSessionFromActiveList() async {
    final cur = _session;
    if (cur == null) return;
    final wantId = parseSessionId(Map<String, dynamic>.from(cur));
    if (wantId == null) return;
    try {
      if (await hasInternetConnectivity()) {
        await OfflineService.hasPasswordOrApiToken();
      }
      final st = await OfflineService.getCurrentStudent();
      final list = await ApiService.getActiveSessions(
        indexNumber: st?.indexNumber,
      );
      if (!mounted) return;
      for (final raw in list) {
        final s = Map<String, dynamic>.from(raw);
        if (parseSessionId(s) == wantId) {
          setState(() => _session = s);
          _startSessionCountdown();
          break;
        }
      }
    } catch (_) {}
  }

  /// Updates [_rangeChecked], [_withinRange], [_distanceMeters]. Returns whether in range.
  Future<bool> _measureRangeAndUpdateState() async {
    if (_session == null) return false;
    setState(() {
      _checkingRange = true;
      _error = null;
    });

    try {
      final venue = _sessionVenueLatLng();
      if (venue == null) {
        if (mounted) {
          setState(() {
            _checkingRange = false;
            _error =
                'Session venue is missing coordinates. Set lat/lng on the server.';
            _withinRange = false;
            _rangeChecked = true;
          });
        }
        return false;
      }
      final position = await LocationService.getRefinedPositionForAttendance();
      final sessionLat = venue.lat;
      final sessionLng = venue.lng;
      final baseRange = _allowedRangeMeters;
      final acc = position.accuracy;
      final allowed = LocationService.adjustedRangeMeters(baseRange, acc);
      final distance = LocationService.calculateDistance(
        position.latitude,
        position.longitude,
        sessionLat,
        sessionLng,
      );
      final within = distance <= allowed;

      if (!mounted) return false;
      setState(() {
        _distanceMeters = distance;
        _effectiveRangeMeters = allowed;
        _gpsAccuracyMeters = acc;
        _withinRange = within;
        _rangeChecked = true;
        _checkingRange = false;
      });
      return within;
    } catch (e) {
      if (mounted) {
        setState(() {
          _checkingRange = false;
          _error = 'Location: $e';
          _withinRange = false;
          _rangeChecked = true;
        });
      }
      return false;
    }
  }

  /// Hybrid: range check only — does not open QR or submit.
  Future<void> _checkRange() async {
    if (_session == null) return;
    if (_alreadyMarkedForSession) {
      _showErrorSnackBar('Attendance already marked');
      return;
    }
    final within = await _measureRangeAndUpdateState();
    if (!within && mounted) {
      _showErrorSnackBar(
        'You are outside the attendance range. Move closer to mark attendance.',
      );
    }
  }

  /// Location-only: measure range, then submit (no QR, no camera).
  Future<void> _runLocationOnlyMarkAndSubmit() async {
    if (_session == null || _student == null) return;
    if (_alreadyMarkedForSession) {
      _showErrorSnackBar('Attendance already marked');
      return;
    }
    final within = await _measureRangeAndUpdateState();
    if (!mounted) return;
    if (!within) {
      _showErrorSnackBar(
        'You are outside the attendance range. Move closer to mark attendance.',
      );
      return;
    }
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('✅ You are within range'),
        behavior: SnackBarBehavior.floating,
        backgroundColor: Colors.green.shade700,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 80),
      ),
    );
    await Future.delayed(const Duration(milliseconds: 450));
    if (!mounted) return;
    await _submitAttendance();
  }

  Future<void> _submitCheckout() async {
    if (_session == null || _student == null || _isCheckingOut) return;
    setState(() {
      _isCheckingOut = true;
      _error = null;
    });
    try {
      final venue = _sessionVenueLatLng();
      if (venue == null) {
        setState(() {
          _isCheckingOut = false;
          _error = 'Session venue is missing coordinates.';
        });
        return;
      }
      final position = await LocationService.getCurrentLocation();
      final payload = buildAttendancePostBody(
        indexNumber: AppState.studentIndex ?? _student!.indexNumber,
        sessionId: parseSessionId(Map<String, dynamic>.from(_session!)),
        courseId: parseOptionalCourseId(Map<String, dynamic>.from(_session!)),
        weekId: parseOptionalWeekId(Map<String, dynamic>.from(_session!)),
        lat: position.latitude,
        lng: position.longitude,
        includeLocation: true,
        timestamp: DateTime.now().toIso8601String(),
      );
      final res = await ApiService.post('attendance/checkout', payload);
      if (ApiService.isSuccessfulHttp(res.statusCode)) {
        await _refreshSessionFromActiveList();
        if (!mounted) return;
        _showErrorSnackBar('Checkout recorded.');
      } else {
        final msg = ApiService.messageFromHttpResponse(res);
        if (mounted) setState(() => _error = msg.isEmpty ? 'Checkout failed.' : msg);
      }
    } catch (e) {
      if (mounted) setState(() => _error = 'Checkout error: $e');
    } finally {
      if (mounted) setState(() => _isCheckingOut = false);
    }
  }

  Future<void> _proceedToQr() async {
    if (_mode != AttendanceFlowMode.hybrid) return;
    if (!_rangeChecked || !_withinRange || _session == null) return;
    await _openQrAndSubmit();
  }

  /// POST after QR scan or manual [sessionCode]. Skips [Geolocator] when [sessionLocationRequired] is false.
  /// [requireRange] false = QR-only mode (no range gate before scan).
  Future<QrSubmitResult> _submitQrFromScanner(
    String token, {
    bool requireRange = true,
    String? sessionCode,
  }) async {
    if (_session == null || _student == null) {
      return QrSubmitResult.fail(500, 'Missing session');
    }
    final rawSession = Map<String, dynamic>.from(_session!);
    final sessionId = parseSessionId(rawSession);
    final locationRequired = sessionLocationRequired(rawSession);
    final needProof = sessionRequiresQrProof(rawSession);
    final codeTrim = sessionCode?.trim();
    if (needProof &&
        token.isEmpty &&
        (codeTrim == null || codeTrim.isEmpty)) {
      return QrSubmitResult.fail(
        400,
        'Scan the session QR or enter the session code.',
      );
    }
    if (requireRange && (!_rangeChecked || !_withinRange)) {
      return QrSubmitResult.fail(400, 'You must be within range first');
    }
    // At least one of session_id, course_id, or qr_code (contract).
    if (sessionId == null && parseOptionalCourseId(rawSession) == null && token.isEmpty) {
      return QrSubmitResult.fail(400, 'Invalid session (missing id and course)');
    }

    final courseId = parseOptionalCourseId(rawSession);
    final weekId = parseOptionalWeekId(rawSession);
    final sessionTok = sessionQrToken(rawSession);
    final ts = DateTime.now().toIso8601String();
    final index = AppState.studentIndex ?? _student!.indexNumber;
    final faceForPayload =
        ApiService.attachFaceDescriptorToAttendance ? _student!.faceDescriptor : null;

    var lat = 0.0;
    var lng = 0.0;

    try {
      if (locationRequired) {
        final venue = _sessionVenueLatLng();
        if (venue == null) {
          return QrSubmitResult.fail(
            400,
            'Session venue missing coordinates. Set lat/lng on the server.',
          );
        }
        final position = await LocationService.getCurrentLocation();
        lat = position.latitude;
        lng = position.longitude;
        if (requireRange &&
            !LocationService.isWithinRange(
          position,
          venue.lat,
          venue.lng,
          _allowedRangeMeters,
        )) {
          return QrSubmitResult.fail(
            400,
            'You are outside the attendance range. Move closer and try again.',
          );
        }
      }

      final record = AttendanceRecord(
        sessionId: sessionId,
        studentIndex: index,
        courseId: courseId ?? 0,
        weekId: weekId ?? 0,
        lat: lat,
        lng: lng,
        qrCode: token,
        timestamp: ts,
        faceDescriptor: faceForPayload,
      );

      final deviceIp = await DeviceService.getIp();
      final deviceId = await DeviceService.getDeviceId();

      final payload = buildAttendancePostBody(
        indexNumber: index,
        sessionId: sessionId,
        courseId: courseId,
        weekId: weekId,
        lat: lat,
        lng: lng,
        includeLocation: locationRequired,
        qrCode: token.isNotEmpty ? token : null,
        sessionToken: sessionTok,
        sessionCode: codeTrim,
        faceDescriptor: faceForPayload,
        deviceIp: deviceIp,
        deviceId: deviceId,
        timestamp: ts,
      );

      try {
        final res = await ApiService.post('attendance', payload);
        final msg = ApiService.messageFromHttpResponse(res);
        if (ApiService.isSuccessfulHttp(res.statusCode)) {
          await _persistAttendanceLog(ts);
          try {
            await SyncService.syncAttendance();
          } catch (_) {}
          return QrSubmitResult.ok(res.statusCode);
        }
        if (res.statusCode == 409) {
          await _persistAttendanceLog(ts);
          try {
            await SyncService.syncAttendance();
          } catch (_) {}
          return QrSubmitResult.ok(res.statusCode);
        }
        final err = msg.isNotEmpty
            ? msg
            : 'Could not submit attendance (${res.statusCode})';
        return QrSubmitResult.fail(res.statusCode, err);
      } on TimeoutException {
        return QrSubmitResult.fail(null, 'Request timed out. Check your connection.');
      } catch (_) {
        if (token.isEmpty &&
            codeTrim != null &&
            codeTrim.isNotEmpty) {
          return QrSubmitResult.fail(
            null,
            'No connection. Session code requires an online connection.',
          );
        }
        final dip = await DeviceService.getIp();
        await OfflineService.insert(record, deviceIp: dip);
        await _persistAttendanceLog(ts);
        return QrSubmitResult.ok(200);
      }
    } catch (e) {
      return QrSubmitResult.fail(500, e.toString());
    }
  }

  Future<void> _submitSessionCodeOnly() async {
    if (!mounted) return;
    if (_alreadyMarkedForSession) {
      _showErrorSnackBar('Attendance already marked');
      return;
    }
    final code = _sessionCodeController.text.trim();
    if (code.isEmpty) {
      setState(() => _error = 'Enter the session code or scan the QR.');
      return;
    }
    setState(() {
      _isSubmitting = true;
      _error = null;
    });
    final requireRange = _mode != AttendanceFlowMode.qr;
    final result = await _submitQrFromScanner(
      '',
      requireRange: requireRange,
      sessionCode: code,
    );
    if (!mounted) return;
    setState(() => _isSubmitting = false);
    if (result.success) {
      final httpC = result.httpStatus ?? 200;
      await _presentSuccessAndPop(
        subtitle: httpC == 409
            ? 'Your attendance was already recorded.'
            : 'You have successfully marked attendance',
      );
    } else {
      setState(() => _error = result.message ?? 'Could not submit attendance');
    }
  }

  Future<void> _openQrAndSubmit() async {
    if (!mounted) return;
    if (_alreadyMarkedForSession) {
      _showErrorSnackBar('Attendance already marked');
      return;
    }
    final requireRange = _mode != AttendanceFlowMode.qr;
    final sid = parseSessionId(Map<String, dynamic>.from(_session!));
    final sameDeviceBlocked =
        sid != null && SessionQrHostGuard.isHostingSession(sid);
    final result = await Navigator.of(context).push<QrSubmitResult>(
      MaterialPageRoute(
        builder: (_) => appSelectableScope(
          QRScanPage(
            activeSession: _session!,
            sameDeviceBlocked: sameDeviceBlocked,
            onSubmitToken: (token) =>
                _submitQrFromScanner(token, requireRange: requireRange),
          ),
        ),
      ),
    );
    if (!mounted) return;
    if (result != null && result.success) {
      final code = result.httpStatus ?? 200;
      _presentSuccessAndPop(
        subtitle: code == 409
            ? 'Your attendance was already recorded.'
            : 'You have successfully marked attendance',
        playCelebrationFeedback: false,
      );
    } else if (result != null && !result.success) {
      setState(() => _error = result.message ?? 'Could not submit attendance');
    } else {
      setState(() => _error = 'QR scan cancelled.');
    }
  }

  Future<void> _submitAttendance() async {
    if (_session == null || _student == null) return;
    if (_mode != AttendanceFlowMode.location) return;
    if (_alreadyMarkedForSession) {
      _showErrorSnackBar('Attendance already marked');
      return;
    }
    if (!_rangeChecked || !_withinRange) return;
    final rawSession = Map<String, dynamic>.from(_session!);
    final sessionId = parseSessionId(rawSession);
    if (sessionId == null) {
      setState(() => _error = 'Invalid session (missing id).');
      return;
    }

    setState(() {
      _isSubmitting = true;
      _error = null;
    });

    try {
      final venue = _sessionVenueLatLng();
      if (venue == null) {
        if (mounted) {
          setState(() {
            _isSubmitting = false;
            _error =
                'Session venue is missing coordinates. Set lat/lng on the server.';
          });
        }
        return;
      }
      final position = await LocationService.getCurrentLocation();
      final sessionLat = venue.lat;
      final sessionLng = venue.lng;
      if (!LocationService.isWithinRange(
        position,
        sessionLat,
        sessionLng,
        _allowedRangeMeters,
      )) {
        if (mounted) {
          setState(() {
            _isSubmitting = false;
            _withinRange = false;
          });
          _showErrorSnackBar(
            'You are outside the attendance range. Move closer to mark attendance.',
          );
        }
        return;
      }

      final faceForPayload =
          ApiService.attachFaceDescriptorToAttendance ? _student!.faceDescriptor : null;

      final courseId = parseOptionalCourseId(rawSession);
      final weekId = parseOptionalWeekId(rawSession);
      final ts = DateTime.now().toIso8601String();

      final record = AttendanceRecord(
        sessionId: sessionId,
        studentIndex: AppState.studentIndex ?? _student!.indexNumber,
        courseId: courseId ?? 0,
        weekId: weekId ?? 0,
        lat: position.latitude,
        lng: position.longitude,
        qrCode: null,
        timestamp: ts,
        faceDescriptor: faceForPayload,
      );

      final deviceIp = await DeviceService.getIp();
      final deviceId = await DeviceService.getDeviceId();

      final payload = buildAttendancePostBody(
        indexNumber: AppState.studentIndex ?? _student!.indexNumber,
        sessionId: sessionId,
        courseId: courseId,
        weekId: weekId,
        lat: position.latitude,
        lng: position.longitude,
        includeLocation: true,
        qrCode: null,
        sessionToken: null,
        faceDescriptor: faceForPayload,
        deviceIp: deviceIp,
        deviceId: deviceId,
        timestamp: ts,
      );

      try {
        final res = await ApiService.post('attendance', payload);

        if (ApiService.isSuccessfulHttp(res.statusCode) && mounted) {
          Map<String, dynamic>? body;
          try {
            body = jsonDecode(res.body) as Map<String, dynamic>;
          } catch (_) {}
          final status = body?['status']?.toString();
          await _persistAttendanceLog(ts);
          try {
            await SyncService.syncAttendance();
          } catch (_) {}
          if (!mounted) return;
          final already = status == 'already_marked' ||
              body?['already_marked'] == true;
          if (_isCheckInCheckoutMode) {
            await _refreshSessionFromActiveList();
            if (!mounted) return;
            _showErrorSnackBar(
              already
                  ? 'Already checked in for this session.'
                  : 'Check-in recorded. Wait for checkout to open.',
            );
          } else {
            _presentSuccessAndPop(
              subtitle: already
                  ? 'Your attendance was already recorded.'
                  : 'You have successfully marked attendance',
            );
          }
        } else if (res.statusCode == 409 && mounted) {
          await _persistAttendanceLog(ts);
          try {
            await SyncService.syncAttendance();
          } catch (_) {}
          if (!mounted) return;
          if (_isCheckInCheckoutMode) {
            await _refreshSessionFromActiveList();
            if (!mounted) return;
            _showErrorSnackBar('Already checked in for this session.');
          } else {
            _presentSuccessAndPop(
              subtitle: 'Your attendance was already recorded.',
            );
          }
        } else if (mounted) {
          String msg = 'Attendance failed';
          try {
            final body = jsonDecode(res.body) as Map<String, dynamic>;
            msg = body['message']?.toString() ?? res.body;
          } catch (_) {
            msg = 'Attendance failed: ${res.body}';
          }
          _showErrorSnackBar(msg);
        }
      } on TimeoutException {
        if (mounted) {
          _showErrorSnackBar('Request timed out. Check your connection and try again.');
        }
      } catch (_) {
        final dip = await DeviceService.getIp();
        await OfflineService.insert(record, deviceIp: dip);
        await _persistAttendanceLog(ts);
        if (mounted) {
          if (_isCheckInCheckoutMode) {
            _showErrorSnackBar('Saved offline. Will sync when online.');
          } else {
            _presentSuccessAndPop(
              subtitle: 'Saved offline. Will sync when online.',
            );
          }
        }
      }
    } catch (e) {
      if (mounted) setState(() => _error = 'Error: $e');
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _persistAttendanceLog(String markedAt) async {
    if (_student == null || _session == null) return;
    final sid = parseSessionId(Map<String, dynamic>.from(_session!));
    await OfflineService.saveAttendanceLogMark(
      indexNumber: _student!.indexNumber,
      courseCode: _session!['course_code']?.toString(),
      sessionId: sid,
      markedAt: markedAt,
    );
  }

  /// Errors / non-success feedback only (not used for successful attendance).
  void _showErrorSnackBar(String message) {
    final media = MediaQuery.of(context);
    final h = media.size.height;
    final topSafe = media.viewPadding.top;
    const snackBarEstimate = 52.0;
    final bottomMargin = (h - topSafe - 8 - snackBarEstimate).clamp(0.0, double.infinity);

    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
        margin: EdgeInsets.only(left: 16, right: 16, bottom: bottomMargin),
        dismissDirection: DismissDirection.up,
      ),
    );
  }

  Future<void> _saveLastAttendanceForHomeDashboard() async {
    final s = _session;
    if (s == null) return;
    final id = s['id'];
    final sid = id is int
        ? id
        : id is num
            ? id.toInt()
            : int.tryParse(id.toString());
    if (sid == null) return;
    final course =
        (s['course_name'] ?? s['course_title'] ?? 'Course').toString();
    await LastAttendancePrefs.save(sessionId: sid, courseName: course);
  }

  /// Full-screen dim + white card; then pop to home. No SnackBar.
  /// [playCelebrationFeedback] is false after QR success — chime + haptics already ran in the scanner.
  Future<void> _presentSuccessAndPop({
    String? subtitle,
    bool playCelebrationFeedback = true,
  }) async {
    if (!mounted) return;
    await _saveLastAttendanceForHomeDashboard();
    if (playCelebrationFeedback) {
      await SuccessChime.celebrateAttendanceMarked();
    }
    setState(() {
      _showSuccessOverlay = true;
      _isSubmitting = false;
      if (subtitle != null) _successSubtitle = subtitle;
    });
    Future.delayed(const Duration(seconds: 2), () {
      if (!mounted) return;
      setState(() {
        _showSuccessOverlay = false;
      });
      Navigator.of(context).pop(true);
    });
  }

  /// Shared GPS result UI (location + hybrid modes).
  List<Widget> _buildRangeFeedbackWidgets(BuildContext context) {
    return [
      const Icon(Icons.location_searching, size: 60),
      const SizedBox(height: 10),
      Text(
        'Checking your location…',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.titleSmall,
      ),
      const SizedBox(height: 16),
      if (_checkingRange) const Center(child: CircularProgressIndicator()),
      if (_rangeChecked && _distanceMeters != null) ...[
        const SizedBox(height: 12),
        RadioGroup<bool>(
          groupValue: _withinRange,
          // Display-only radios: we still need an onChanged callback because
          // RadioGroup's API requires it.
          onChanged: (_) {},
          child: Column(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Radio<bool>(
                    value: true,
                    enabled: false,
                    fillColor: WidgetStateProperty.resolveWith((states) {
                      if (states.contains(WidgetState.selected)) {
                        return const Color(0xFF2E7D32);
                      }
                      return Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.55);
                    }),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Within range',
                            style: Theme.of(context)
                                .textTheme
                                .titleSmall
                                ?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: _withinRange
                                      ? const Color(0xFF1B5E20)
                                      : Theme.of(context)
                                          .colorScheme
                                          .onSurface,
                                ),
                          ),
                          if (_withinRange) ...[
                            const SizedBox(height: 6),
                            Row(
                              crossAxisAlignment:
                                  CrossAxisAlignment.start,
                              children: [
                                Icon(
                                  Icons.check_circle_rounded,
                                  size: 18,
                                  color: Colors.green.shade700,
                                ),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    "Great — you're in range.",
                                    style: TextStyle(
                                      fontSize: 14,
                                      height: 1.35,
                                      color: Colors.green.shade800,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Radio<bool>(
                    value: false,
                    enabled: false,
                    fillColor: WidgetStateProperty.resolveWith((states) {
                      if (states.contains(WidgetState.selected)) {
                        return const Color(0xFFE65100);
                      }
                      return Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.55);
                    }),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(
                        'Out of range',
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: !_withinRange
                                  ? const Color(0xFFB71C1C)
                                  : Theme.of(context).colorScheme.onSurface,
                            ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        Text(
          'Distance: ${_distanceMeters!.toStringAsFixed(1)} m · '
          'allowed ${(_effectiveRangeMeters ?? _allowedRangeMeters).toStringAsFixed(0)} m'
          '${_gpsAccuracyMeters != null ? ' · GPS ±${_gpsAccuracyMeters!.toStringAsFixed(0)} m' : ''}',
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
        ),
        const SizedBox(height: 10),
        Text(
          _withinRange
              ? 'You are within the class radius (about ${_distanceMeters!.round()} m from the point the server uses).'
              : 'You are about ${_distanceMeters!.round()} m from the class location — move closer to mark.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w500,
              ),
        ),
        if (!_withinRange) ...[
          const SizedBox(height: 6),
          Text(
            'Allowed distance (including GPS margin): '
            '${(_effectiveRangeMeters ?? _allowedRangeMeters).toStringAsFixed(0)} m.',
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
          ),
        ],
        if (_gpsAccuracyMeters != null && _gpsAccuracyMeters! > 30) ...[
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.amber.shade50,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.amber.shade200),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.gps_not_fixed_rounded, color: Colors.amber.shade900, size: 22),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'GPS accuracy is moderate (±${_gpsAccuracyMeters!.toStringAsFixed(0)} m). '
                    'Stand still outdoors or wait a few seconds for a tighter fix if this seems wrong.',
                    style: TextStyle(
                      fontSize: 13,
                      height: 1.35,
                      color: Colors.amber.shade900,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        if (_effectiveRangeMeters != null &&
            _effectiveRangeMeters! > _allowedRangeMeters + 0.5) ...[
          const SizedBox(height: 6),
          Text(
            'GPS uncertainty is added to the allowed radius so you are not penalized for weak signal.',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Theme.of(context).colorScheme.tertiary,
                ),
          ),
        ],
      ],
    ];
  }

  List<Widget> _buildQrModeBody(BuildContext context) {
    final sid = parseSessionId(Map<String, dynamic>.from(_session!));
    final hosting =
        sid != null && SessionQrHostGuard.isHostingSession(sid);
    return [
      Icon(
        Icons.qr_code_scanner_rounded,
        size: 72,
        color: Theme.of(context).colorScheme.primary,
      ),
      const SizedBox(height: 16),
      Text(
        'Scan the session QR',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
      ),
      const SizedBox(height: 6),
      Text(
        hosting
            ? 'This phone is showing the class QR — use another device or the code below.'
            : 'Point the camera at the lecturer’s QR (another device works best).',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              height: 1.35,
            ),
      ),
      const SizedBox(height: 28),
      FilledButton.icon(
        onPressed: _student != null &&
                !_isSubmitting &&
                !hosting
            ? _openQrAndSubmit
            : null,
        icon: const Icon(Icons.qr_code_2),
        label: const Text('Scan QR'),
      ),
      const SizedBox(height: 20),
      Text(
        'Session code',
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
      ),
      const SizedBox(height: 8),
      TextField(
        controller: _sessionCodeController,
        textCapitalization: TextCapitalization.characters,
        decoration: const InputDecoration(
          labelText: 'Session code',
          hintText: 'e.g. CSC101-4821',
          border: OutlineInputBorder(),
          isDense: true,
        ),
        onSubmitted: (_) => _submitSessionCodeOnly(),
      ),
      const SizedBox(height: 12),
      OutlinedButton.icon(
        onPressed:
            _student != null && !_isSubmitting ? _submitSessionCodeOnly : null,
        icon: const Icon(Icons.keyboard_alt_outlined),
        label: const Text('Submit code'),
      ),
    ];
  }

  List<Widget> _buildHybridModeBody(BuildContext context) {
    return [
      Text(
        'Step 1 · Location',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: Theme.of(context).colorScheme.primary,
              fontWeight: FontWeight.w700,
            ),
      ),
      const SizedBox(height: 8),
      Text(
        'Confirm you are in range, then scan the QR code.',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
      ),
      const SizedBox(height: 16),
      ..._buildRangeFeedbackWidgets(context),
      const SizedBox(height: 20),
      ElevatedButton(
        onPressed: _student != null && !_checkingRange && !_isSubmitting
            ? _checkRange
            : null,
        child: Text(_checkingRange ? 'Checking…' : 'Check range'),
      ),
      if (_rangeChecked && _withinRange) ...[
        const SizedBox(height: 20),
        Text(
          'Scan QR',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: !_isSubmitting ? _proceedToQr : null,
          icon: const Icon(Icons.qr_code_scanner_outlined),
          label: const Text('Open scanner'),
        ),
        const SizedBox(height: 16),
        Text(
          'Or code',
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
                fontWeight: FontWeight.w600,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _sessionCodeController,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Session code',
            hintText: 'e.g. CSC101-4821',
            border: OutlineInputBorder(),
            isDense: true,
          ),
          onSubmitted: (_) => _submitSessionCodeOnly(),
        ),
        const SizedBox(height: 10),
        OutlinedButton.icon(
          onPressed:
              _student != null && !_isSubmitting ? _submitSessionCodeOnly : null,
          icon: const Icon(Icons.keyboard_alt_outlined),
          label: const Text('Submit code'),
        ),
      ],
    ];
  }

  void _openSoftAttendanceHistory() {
    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => appSelectableScope(const AttendanceHistoryPage()),
      ),
    );
  }

  Future<void> _refreshSoftLocation() async {
    if (_session == null) return;
    await _measureRangeAndUpdateState();
    if (!mounted) return;
    if (_rangeChecked && _withinRange) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('You are within the class radius.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _showSoftSessionScheduleInfo() {
    if (_session == null) return;
    final end = _sessionEndTime ?? _parseSessionEndTime(_session!);
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Session'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (end != null)
              Text(
                'Ends: ${end.toLocal()}',
                style: const TextStyle(height: 1.35),
              ),
            const SizedBox(height: 8),
            Text(
              _sessionEnded
                  ? 'Session has ended.'
                  : 'Time left: $_remainingText',
              style: const TextStyle(height: 1.35),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  Widget _buildSoftLocationStatusForCard(BuildContext context) {
    if (_checkingRange) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: AttendanceSoftPalette.orange,
            ),
          ),
          const SizedBox(width: 12),
          Text(
            'Checking your location…',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF616161),
                ),
          ),
        ],
      );
    }
    if (!_rangeChecked) {
      return Text(
        'Tap Check in to confirm you are at the class location.',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: const Color(0xFF757575),
              height: 1.45,
            ),
      );
    }
    if (_distanceMeters != null) {
      final ok = _withinRange;
      return Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            decoration: BoxDecoration(
              color: ok
                  ? AttendanceSoftPalette.green.withValues(alpha: 0.12)
                  : const Color(0xFFC62828).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Text(
              ok
                  ? 'Within range · ${_distanceMeters!.round()} m from class point'
                  : 'Outside range · ${_distanceMeters!.round()} m away',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: ok ? const Color(0xFF1B5E20) : const Color(0xFFB71C1C),
                  ),
            ),
          ),
          if (_effectiveRangeMeters != null) ...[
            const SizedBox(height: 6),
            Text(
              'Allowed radius (with GPS margin): '
              '${_effectiveRangeMeters!.toStringAsFixed(0)} m',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: const Color(0xFF9E9E9E),
                  ),
            ),
          ],
        ],
      );
    }
    return const SizedBox.shrink();
  }

  Widget _buildSoftLocationListTab(BuildContext context) {
    final st = _student;
    if (st == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            'Sign in to see your attendance list.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
        ),
      );
    }
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: OfflineService.getAllAttendanceLogsForIndex(st.indexNumber),
      builder: (context, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Center(
            child: CircularProgressIndicator(color: AttendanceSoftPalette.orange),
          );
        }
        final logs = snap.data ?? [];
        if (logs.isEmpty) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Text(
                'No saved marks yet. They will appear here after you check in.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: const Color(0xFF616161),
                      height: 1.4,
                    ),
              ),
            ),
          );
        }
        final show = logs.length > 30 ? logs.sublist(0, 30) : logs;
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
          itemCount: show.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, i) {
            final row = show[i];
            final at = row['marked_at']?.toString() ?? '—';
            final course = row['course_code']?.toString() ?? 'Course';
            return Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.92),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: Colors.black.withValues(alpha: 0.06),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.check_circle_outline_rounded,
                    color: AttendanceSoftPalette.green.withValues(alpha: 0.85),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          course,
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          at,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: const Color(0xFF757575),
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _successOverlayLayer() {
    if (!_showSuccessOverlay) return const SizedBox.shrink();
    return Positioned.fill(
      child: Container(
        color: Colors.black.withValues(alpha: 0.4),
        child: Center(
          child: Container(
            padding: const EdgeInsets.all(24),
            margin: const EdgeInsets.symmetric(horizontal: 30),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.check_circle, color: Colors.green, size: 80),
                const SizedBox(height: 15),
                const Text(
                  'Attendance Recorded',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF111111),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  _successSubtitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 15,
                    color: Color(0xFF212121),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSoftLocationModeScaffold(BuildContext context) {
    final ref = _sessionReferenceDateTime();
    final hasVenueCoords = _sessionVenueLatLng() != null;

    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          AttendanceSoftLocationBackground(
            child: SafeArea(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(4, 2, 8, 0),
                    child: Row(
                      children: [
                        IconButton(
                          onPressed: () => Navigator.of(context).maybePop(),
                          icon: const Icon(Icons.arrow_back_ios_new_rounded),
                          color: const Color(0xFF424242),
                        ),
                        Expanded(
                          child: Text(
                            'Attendance',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.3,
                                ),
                          ),
                        ),
                        const SizedBox(width: 48),
                      ],
                    ),
                  ),
                  AttendancePillTabBar(
                    selectedIndex: _softLocationTab,
                    onSelect: (i) => setState(() => _softLocationTab = i),
                  ),
                  Expanded(
                    child: _softLocationTab == 0
                        ? SingleChildScrollView(
                            padding: const EdgeInsets.only(top: 2, bottom: 28),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                if (!hasVenueCoords)
                                  Padding(
                                    padding: const EdgeInsets.fromLTRB(
                                      24,
                                      0,
                                      24,
                                      12,
                                    ),
                                    child: Text(
                                      'This session has no map coordinates yet. '
                                      'Ask your lecturer to set a venue location.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        color: Theme.of(context).colorScheme.error,
                                        height: 1.35,
                                      ),
                                    ),
                                  ),
                                SoftLocationCheckInCard(
                                  dateLabel: _formatSoftCardDate(ref),
                                  countdownRemainingText: _remainingText,
                                  venueLine: _softVenueLine(),
                                  courseLine: _softCourseLine(),
                                  statusWidget:
                                      _buildSoftLocationStatusForCard(context),
                                  onCheckIn: _isCheckInCheckoutMode
                                      ? _runLocationOnlyMarkAndSubmit
                                      : _runLocationOnlyMarkAndSubmit,
                                  checkInEnabled: _student != null &&
                                      (!_isCheckInCheckoutMode
                                          ? (!_alreadyMarkedForSession && !_sessionEnded)
                                          : (!_isCheckedIn && !_sessionEnded)) &&
                                      hasVenueCoords,
                                  checkInBusy:
                                      _checkingRange || _isSubmitting,
                                  checkInLabel: _isCheckInCheckoutMode
                                      ? (_isCheckedIn ? 'Checked in' : 'Check-in')
                                      : (_alreadyMarkedForSession
                                          ? 'Done'
                                          : (_sessionEnded
                                              ? 'Ended'
                                              : 'Check in')),
                                  onCheckOut: _isCheckInCheckoutMode ? _submitCheckout : null,
                                  checkOutEnabled: _student != null &&
                                      _canCheckOut &&
                                      hasVenueCoords,
                                  checkOutBusy: _isCheckingOut,
                                  checkOutLabel: _isCheckedOut ? 'Done' : 'Check-out',
                                  onHistory: _openSoftAttendanceHistory,
                                  onRefreshLocation: () =>
                                      unawaited(_refreshSoftLocation()),
                                  onScheduleInfo: _showSoftSessionScheduleInfo,
                                ),
                                if (_student == null)
                                  Padding(
                                    padding: const EdgeInsets.all(16),
                                    child: Text(
                                      'No student profile loaded. Log in again.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .error,
                                      ),
                                    ),
                                  ),
                                if (_error != null)
                                  Padding(
                                    padding: const EdgeInsets.fromLTRB(
                                      24,
                                      10,
                                      24,
                                      0,
                                    ),
                                    child: Text(
                                      _error!,
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .error,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          )
                        : _buildSoftLocationListTab(context),
                  ),
                ],
              ),
            ),
          ),
          _successOverlayLayer(),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Mark Attendance')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_session == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Mark Attendance')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(_error ?? 'No active session.'),
          ),
        ),
      );
    }

    if (_mode == AttendanceFlowMode.location) {
      return _buildSoftLocationModeScaffold(context);
    }

    final hasEnd = _parseSessionEndTime(_session!) != null;
    final showTime = hasEnd && !_sessionEnded && _remainingText != '--:--';

    return Scaffold(
      appBar: AppBar(title: const Text('Mark Attendance')),
      body: Stack(
        fit: StackFit.expand,
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Card(
                  elevation: 0,
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CourseBookIcon(
                              size: 18,
                              color: Colors.green.shade700,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                '${_session!['course_name'] ?? _session!['course_title'] ?? ''}',
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ),
                        if ((_session!['course_code']?.toString() ?? '')
                            .trim()
                            .isNotEmpty)
                          Text('Code: ${_session!['course_code']}'),
                        if ((_session!['venue']?.toString() ?? '').trim().isNotEmpty)
                          Text('Venue: ${_session!['venue']}'),
                        if ((_session!['lecturer_name']?.toString() ?? '')
                            .trim()
                            .isNotEmpty)
                          Text('Lecturer: ${_session!['lecturer_name']}'),
                        if (showTime || _sessionEnded)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              'Time left: ${_sessionEnded ? 'Session ended' : _remainingText}',
                              style: TextStyle(
                                fontWeight: FontWeight.w600,
                                color: _sessionEnded
                                    ? Theme.of(context).colorScheme.error
                                    : Theme.of(context).colorScheme.primary,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                if (_mode == AttendanceFlowMode.qr) ..._buildQrModeBody(context),
                if (_mode == AttendanceFlowMode.hybrid) ..._buildHybridModeBody(context),
                if (_student == null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(
                      'No student profile loaded. Log in again.',
                      style: TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      _error!,
                      style: TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ),
                if (_isSubmitting)
                  const Padding(
                    padding: EdgeInsets.only(top: 24),
                    child: Center(child: CircularProgressIndicator()),
                  ),
              ],
            ),
          ),
          _successOverlayLayer(),
        ],
      ),
    );
  }
}
