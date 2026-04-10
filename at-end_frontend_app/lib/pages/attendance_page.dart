import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/attendance_record.dart';
import '../models/qr_submit_result.dart';
import '../models/student.dart';
import '../services/api_service.dart';
import '../services/attendance_local_notify.dart';
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
  bool _rangeChecked = false;
  bool _checkingRange = false;
  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _isCheckingOut = false;
  bool _autoCheckoutScheduled = false;
  String? _error;
  bool _showSuccessOverlay = false;
  String _successSubtitle = 'You have successfully marked attendance';
  /// Local DB + optional API `already_marked` on session.
  bool _alreadyMarkedForSession = false;

  DateTime? _sessionEndTime;
  String _remainingText = '--:--';
  bool _sessionEnded = false;
  Timer? _countTimer;

  AttendanceFlowMode get _mode => resolveAttendanceFlowMode(_session);
  bool get _isCheckInCheckoutMode =>
      (_session?['attendance_mode']?.toString() ?? '') == 'checkin_checkout' ||
      ApiService.attendanceMode == ApiService.attendanceModeCheckInCheckout;
  bool get _isCheckedIn => (_session?['check_in_time']?.toString().isNotEmpty ?? false);
  bool get _isCheckedOut => (_session?['check_out_time']?.toString().isNotEmpty ?? false);
  bool get _canCheckOut =>
      _isCheckInCheckoutMode &&
      ((_session?['checkout_enabled'] == true ||
              _session?['can_check_out'] == true) ||
          _sessionEnded) &&
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
    final raw =
        session['end_time'] ??
        session['ends_at'] ??
        session['closed_at'] ??
        session['closed_time'] ??
        session['expected_end_time'];
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
      _scheduleAutoCheckoutIfEligible();
      return;
    }
    setState(() {
      _sessionEnded = false;
      _remainingText = _formatDurationRemaining(difference);
    });
  }

  void _scheduleAutoCheckoutIfEligible() {
    if (_autoCheckoutScheduled || !_canCheckOut || _isCheckingOut) return;
    _autoCheckoutScheduled = true;
    Future<void>.delayed(const Duration(minutes: 1), () async {
      if (!mounted) return;
      if (_canCheckOut && !_isCheckingOut) {
        await _submitCheckout();
      }
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

  Map<String, dynamic>? _pickSessionById(
    List<Map<String, dynamic>> sessions, {
    int? preferredId,
  }) {
    if (sessions.isEmpty) return null;
    if (preferredId == null) return sessions.first;
    for (final s in sessions) {
      if (parseSessionId(Map<String, dynamic>.from(s)) == preferredId) {
        return s;
      }
    }
    return sessions.first;
  }

  Future<Map<String, dynamic>?> _loadCachedSession(
    Student? student, {
    int? preferredId,
  }) async {
    if (student == null) return null;
    final cached = await SessionCachePrefs.getActiveSessions(student.indexNumber);
    return _pickSessionById(cached, preferredId: preferredId);
  }

  Future<void> _load() async {
    try {
      await ApiService.loadAppSettings();
    } catch (_) {}
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
    });

    Student? loadedStudent;
    try {
      loadedStudent = await OfflineService.getCurrentStudent();
      if (widget.session != null) {
        final s = Map<String, dynamic>.from(widget.session!);
        debugPrint('activeSession (from home): $s');
        if (loadedStudent != null) {
          final cached = await SessionCachePrefs.getActiveSessions(
            loadedStudent.indexNumber,
          );
          final sid = parseSessionId(s);
          final merged = <Map<String, dynamic>>[s];
          for (final row in cached) {
            final rid = parseSessionId(Map<String, dynamic>.from(row));
            if (sid != null && rid == sid) continue;
            merged.add(Map<String, dynamic>.from(row));
          }
          await SessionCachePrefs.saveActiveSessions(
            loadedStudent.indexNumber,
            merged,
          );
        }
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
        if (loadedStudent != null && sessions.isNotEmpty) {
          await SessionCachePrefs.saveActiveSessions(
            loadedStudent.indexNumber,
            sessions,
          );
        }
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
            if (apiErr.isNotEmpty) {
              final cached = await _loadCachedSession(loadedStudent);
              if (!mounted) return;
              if (cached != null) {
                setState(() {
                  _session = cached;
                  _error = 'Showing last synced session (server issue).';
                });
                _startSessionCountdown();
              } else {
                setState(() {
                  _session = null;
                  _error = apiErr;
                });
              }
            } else {
              setState(() {
                _session = null;
                _error = 'No active session.';
              });
            }
          }
        }
      }
    } catch (e) {
      final cached = await _loadCachedSession(loadedStudent);
      if (cached != null && mounted) {
        setState(() {
          _session = cached;
          _error = 'Offline — showing last synced session.';
        });
        _startSessionCountdown();
      } else if (Constants.useDemoActiveSessionWhenEmpty && mounted) {
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
      if (st != null && list.isNotEmpty) {
        await SessionCachePrefs.saveActiveSessions(st.indexNumber, list);
      }
      if (!mounted) return;
      var found = false;
      for (final raw in list) {
        final s = Map<String, dynamic>.from(raw);
        if (parseSessionId(s) == wantId) {
          found = true;
          setState(() => _session = s);
          _startSessionCountdown();
          break;
        }
      }
      if (found) {
        return;
      }
      final cached = await _loadCachedSession(st, preferredId: wantId);
      if (!mounted || cached == null) return;
      setState(() => _session = cached);
      _startSessionCountdown();
    } catch (_) {
      final st = await OfflineService.getCurrentStudent();
      final cached = await _loadCachedSession(st, preferredId: wantId);
      if (!mounted || cached == null) return;
      setState(() => _session = cached);
      _startSessionCountdown();
    }
  }

  /// Updates [_rangeChecked], [_withinRange]. Returns whether in range.
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
      final allowed = LocationService.adjustedRangeMeters(baseRange, position.accuracy);
      final distance = LocationService.calculateDistance(
        position.latitude,
        position.longitude,
        sessionLat,
        sessionLng,
      );
      final within = distance <= allowed;

      if (!mounted) return false;
      setState(() {
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
    final rawSession = Map<String, dynamic>.from(_session!);
    final sessionId = parseSessionId(rawSession);
    final courseId = parseOptionalCourseId(rawSession);
    final weekId = parseOptionalWeekId(rawSession);
    final ts = DateTime.now().toIso8601String();
    double? lat;
    double? lng;

    Future<void> queueOfflineCheckout() async {
      if (lat == null || lng == null) return;
      final record = AttendanceRecord(
        sessionId: sessionId,
        endpoint: 'attendance/checkout',
        studentIndex: AppState.studentIndex ?? _student!.indexNumber,
        courseId: courseId ?? 0,
        weekId: weekId ?? 0,
        lat: lat,
        lng: lng,
        qrCode: null,
        timestamp: ts,
        faceDescriptor: ApiService.attachFaceDescriptorToAttendance
            ? _student!.faceDescriptor
            : null,
      );
      final dip = await DeviceService.getIp();
      await OfflineService.insert(record, deviceIp: dip);
      if (mounted) {
        _showSuccessSnackBar(
          'Checkout saved offline',
          'Will sync automatically when internet returns.',
        );
      }
    }

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
      lat = position.latitude;
      lng = position.longitude;
      final payload = buildAttendancePostBody(
        indexNumber: AppState.studentIndex ?? _student!.indexNumber,
        sessionId: sessionId,
        courseId: courseId,
        weekId: weekId,
        lat: lat,
        lng: lng,
        includeLocation: true,
        timestamp: ts,
      );
      final res = await ApiService.post('attendance/checkout', payload);
      if (ApiService.isSuccessfulHttp(res.statusCode)) {
        await _refreshSessionFromActiveList();
        if (!mounted) return;
        await SuccessChime.celebrateAttendanceMarked(playChime: true);
        _showSuccessSnackBar(
          'Checked out',
          'Your attendance for this session is complete.',
        );
        unawaited(
          AttendanceLocalNotify.notifyCheckedOut(
            _softCourseLine() ?? 'your class',
          ),
        );
      } else {
        final msg = ApiService.messageFromHttpResponse(res);
        if (mounted) setState(() => _error = msg.isEmpty ? 'Checkout failed.' : msg);
      }
    } on TimeoutException {
      await queueOfflineCheckout();
    } catch (_) {
      await queueOfflineCheckout();
    } finally {
      if (mounted) setState(() => _isCheckingOut = false);
    }
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
        if (token.isEmpty &&
            codeTrim != null &&
            codeTrim.isNotEmpty) {
          return QrSubmitResult.fail(
            null,
            'Request timed out. Session code attendance needs a stable connection.',
          );
        }
        final dip = await DeviceService.getIp();
        await OfflineService.insert(record, deviceIp: dip);
        await _persistAttendanceLog(ts);
        return QrSubmitResult.ok(200);
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
      if (_isCheckInCheckoutMode) {
        await _refreshSessionFromActiveList();
        if (!mounted) return;
        if (code == 409) {
          _showInfoSnackBar('Already checked in for this session.');
        } else {
          await SuccessChime.celebrateAttendanceMarked(playChime: false);
          _showSuccessSnackBar(
            'Checked in',
            'We will notify you when it is time to check out.',
          );
          unawaited(
            AttendanceLocalNotify.notifyCheckedIn(
              _softCourseLine() ?? 'your class',
            ),
          );
        }
      } else {
        _presentSuccessAndPop(
          subtitle: code == 409
              ? 'Your attendance was already recorded.'
              : 'You have successfully marked attendance',
          playCelebrationFeedback: false,
        );
      }
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
            if (already) {
              _showInfoSnackBar('Already checked in for this session.');
            } else {
              await SuccessChime.celebrateAttendanceMarked(playChime: true);
              _showSuccessSnackBar(
                'Checked in',
                'We will notify you when it is time to check out.',
              );
              unawaited(
                AttendanceLocalNotify.notifyCheckedIn(
                  _softCourseLine() ?? 'your class',
                ),
              );
            }
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
            _showInfoSnackBar('Already checked in for this session.');
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
        final dip = await DeviceService.getIp();
        await OfflineService.insert(record, deviceIp: dip);
        await _persistAttendanceLog(ts);
        if (mounted) {
          if (_isCheckInCheckoutMode) {
            _showSuccessSnackBar(
              'Check-in saved offline',
              'Will sync when you are back online.',
            );
          } else {
            _presentSuccessAndPop(
              subtitle: 'Weak network detected. Attendance saved offline and will sync.',
            );
          }
        }
      } catch (_) {
        final dip = await DeviceService.getIp();
        await OfflineService.insert(record, deviceIp: dip);
        await _persistAttendanceLog(ts);
        if (mounted) {
          if (_isCheckInCheckoutMode) {
            _showSuccessSnackBar(
              'Check-in saved offline',
              'Will sync when you are back online.',
            );
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

  double _snackBarBottomMargin() {
    final media = MediaQuery.of(context);
    final h = media.size.height;
    final topSafe = media.viewPadding.top;
    const snackBarEstimate = 52.0;
    return (h - topSafe - 8 - snackBarEstimate).clamp(0.0, double.infinity);
  }

  /// Errors / non-success feedback only (not used for successful attendance).
  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
        margin: EdgeInsets.only(
          left: 16,
          right: 16,
          bottom: _snackBarBottomMargin(),
        ),
        dismissDirection: DismissDirection.up,
      ),
    );
  }

  void _showSuccessSnackBar(String title, String subtitle) {
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: Colors.green.shade800,
        duration: const Duration(seconds: 5),
        margin: EdgeInsets.only(
          left: 16,
          right: 16,
          bottom: _snackBarBottomMargin(),
        ),
        dismissDirection: DismissDirection.up,
        content: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.check_circle_rounded, color: Colors.white, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.95),
                      fontSize: 14,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showInfoSnackBar(String message) {
    final cs = Theme.of(context).colorScheme;
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
        backgroundColor: cs.secondaryContainer,
        margin: EdgeInsets.only(
          left: 16,
          right: 16,
          bottom: _snackBarBottomMargin(),
        ),
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

  Widget _buildSoftLocationStatusForCard(BuildContext context) {
    if (_checkingRange || _isSubmitting || _isCheckingOut) {
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
            _isCheckingOut
                ? 'Processing checkout…'
                : (_isSubmitting ? 'Processing…' : 'Checking your location…'),
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF616161),
                ),
          ),
        ],
      );
    }
    String line;
    if (_isCheckInCheckoutMode) {
      if (_isCheckedOut) {
        line = 'Checked out.';
      } else if (_isCheckedIn) {
        line = _canCheckOut
            ? 'Checkout is now available.'
            : 'Checked in. Waiting for checkout to open.';
      } else {
        line = 'Tap Check in to start attendance.';
      }
    } else {
      line = _alreadyMarkedForSession
          ? 'Attendance already recorded.'
          : 'Tap Check in to record attendance.';
    }
    return Text(
      line,
      textAlign: TextAlign.center,
      style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: const Color(0xFF757575),
            height: 1.4,
            fontWeight: FontWeight.w600,
          ),
    );
  }

  Future<void> _handleSoftPrimaryAction() async {
    if (_isCheckInCheckoutMode && _canCheckOut) {
      await _submitCheckout();
      return;
    }
    if (_mode == AttendanceFlowMode.location) {
      await _runLocationOnlyMarkAndSubmit();
      return;
    }
    if (_mode == AttendanceFlowMode.qr) {
      await _openQrAndSubmit();
      return;
    }
    final within = (_rangeChecked && _withinRange)
        ? true
        : await _measureRangeAndUpdateState();
    if (!within || !mounted) return;
    await _openQrAndSubmit();
  }

  String _softPrimaryActionLabel() {
    if (_isCheckInCheckoutMode) {
      if (_isCheckedOut) return 'Checked out';
      if (_canCheckOut) return 'Check out';
      if (_isCheckedIn) return 'Checked in';
      return 'Check in';
    }
    if (_alreadyMarkedForSession) return 'Done';
    if (_mode == AttendanceFlowMode.qr) return 'Scan QR';
    return 'Check in';
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
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (!hasVenueCoords)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(24, 0, 24, 10),
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
                        Expanded(
                          child: Center(
                            child: SoftLocationCheckInCard(
                              dateLabel: _formatSoftCardDate(ref),
                              countdownRemainingText: _remainingText,
                              venueLine: _softVenueLine(),
                              courseLine: _softCourseLine(),
                              statusWidget: _buildSoftLocationStatusForCard(context),
                              onCheckIn: _handleSoftPrimaryAction,
                              checkInEnabled: _student != null &&
                                  hasVenueCoords &&
                                  !_checkingRange &&
                                  !_isSubmitting &&
                                  !_isCheckingOut &&
                                  (_isCheckInCheckoutMode
                                      ? (_isCheckedOut
                                          ? false
                                          : (_isCheckedIn
                                              ? _canCheckOut
                                              : !_sessionEnded))
                                      : (!_alreadyMarkedForSession &&
                                          !_sessionEnded)),
                              checkInBusy: false,
                              checkInLabel: _softPrimaryActionLabel(),
                              onCheckOut: null,
                              checkOutEnabled: false,
                              checkOutBusy: false,
                              checkOutLabel: '',
                              onHistory: null,
                              onRefreshLocation: null,
                              onScheduleInfo: null,
                              showQuickActions: false,
                            ),
                          ),
                        ),
                        if (_student == null)
                          Padding(
                            padding: const EdgeInsets.all(12),
                            child: Text(
                              'No student profile loaded. Log in again.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ),
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(24, 8, 24, 8),
                            child: Text(
                              _error!,
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ),
                      ],
                    ),
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

    return _buildSoftLocationModeScaffold(context);
  }
}
