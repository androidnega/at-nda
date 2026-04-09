import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../models/rep_course.dart';
import '../services/api_service.dart';
import '../services/session_qr_host_guard.dart';
import '../services/notification_bridge.dart';
import '../services/offline_service.dart';
import '../theme/dashboard_surfaces.dart';
import '../theme/soft_ui.dart';
import '../utils/constants.dart';
import '../widgets/modern_pull_to_refresh.dart';

/// Class rep tools: list class courses, open live attendance, show QR, close session.
class RepSessionPage extends StatefulWidget {
  const RepSessionPage({super.key});

  @override
  State<RepSessionPage> createState() => _RepSessionPageState();
}

class _RepSessionPageState extends State<RepSessionPage> {
  Timer? _pollTimer;
  bool _loading = true;
  String? _error;
  List<RepCourse> _courses = [];
  /// Selected row in [_courses]; open / QR / close apply only to this course.
  int? _selectedIndex;
  Map<String, dynamic>? _classActiveSession;
  int _statsTotalStudents = 0;
  int _statsPresent = 0;

  @override
  void initState() {
    super.initState();
    _refresh();
    _pollTimer = Timer.periodic(const Duration(seconds: 8), (_) => _refresh());
  }

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final student = await OfflineService.getCurrentStudent();
    if (student == null || !await OfflineService.hasPasswordOrApiToken()) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Sign in online once so the app can verify your account.';
          _courses = [];
        });
      }
      return;
    }
    try {
      final pwd = await OfflineService.getApiSessionPassword();
      final res = await ApiService.repCourses(
        indexNumber: student.indexNumber,
        password: pwd ?? '',
      );
      if (res.statusCode == 403) {
        if (mounted) {
          setState(() {
            _loading = false;
            _error =
                'This account is not a class rep (or rep access was removed).';
            _courses = [];
          });
        }
        return;
      }
      if (res.statusCode != 200) {
        if (mounted) {
          setState(() {
            _loading = false;
            _error = ApiService.messageFromHttpResponse(res).isEmpty
                ? 'Could not load rep courses (${res.statusCode}).'
                : ApiService.messageFromHttpResponse(res);
            _courses = [];
          });
        }
        return;
      }
      final body = jsonDecode(res.body);
      if (body is! Map) {
        if (mounted) {
          setState(() {
            _loading = false;
            _error = 'Invalid response from server.';
            _courses = [];
          });
        }
        return;
      }
      final raw = body['courses'];
      final list = <RepCourse>[];
      if (raw is List) {
        for (final item in raw) {
          if (item is Map) {
            list.add(RepCourse.fromJson(Map<String, dynamic>.from(item)));
          }
        }
      }
      if (mounted) {
        setState(() {
          _loading = false;
          _courses = list;
          _error = null;
          if (_selectedIndex != null && _selectedIndex! >= _courses.length) {
            _selectedIndex = null;
          }
        });
      }
      await _refreshClassActiveSessionAndStats(student.indexNumber, pwd ?? '');
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Network error: $e';
          _courses = [];
        });
      }
    }
  }

  Future<void> _refreshClassActiveSessionAndStats(
    String indexNumber,
    String password,
  ) async {
    if (password.isEmpty) return;
    try {
      final activeRes = await ApiService.classActiveSession(
        indexNumber: indexNumber,
        password: password,
      );
      if (activeRes.statusCode != 200) return;
      final activeBody = jsonDecode(activeRes.body);
      if (activeBody is! Map) return;
      if (activeBody['has_active_session'] != true) {
        if (!mounted) return;
        setState(() {
          _classActiveSession = null;
          _statsTotalStudents = 0;
          _statsPresent = 0;
        });
        return;
      }
      final active = activeBody['session'];
      int? activeSessionId;
      if (active is Map) {
        activeSessionId = int.tryParse('${active['id']}');
      }
      int totalStudents = int.tryParse('${activeBody['total_students']}') ?? 0;
      int present = int.tryParse('${activeBody['total_present']}') ?? 0;

      if (activeSessionId != null && activeSessionId > 0) {
        final statsRes = await ApiService.sessionStats(
          sessionId: activeSessionId,
          indexNumber: indexNumber,
          password: password,
        );
        if (statsRes.statusCode == 200) {
          final statsBody = jsonDecode(statsRes.body);
          if (statsBody is Map) {
            totalStudents =
                int.tryParse('${statsBody['total_students']}') ?? totalStudents;
            present = int.tryParse('${statsBody['total_present']}') ?? present;
          }
        }
      }

      if (!mounted) return;
      setState(() {
        _classActiveSession =
            active is Map ? Map<String, dynamic>.from(active) : null;
        _statsTotalStudents = totalStudents;
        _statsPresent = present;
      });
    } catch (_) {}
  }

  Future<void> _openSession(RepCourse course) async {
    final student = await OfflineService.getCurrentStudent();
    if (student == null || !await OfflineService.hasPasswordOrApiToken()) {
      return;
    }
    final pwd = await OfflineService.getApiSessionPassword();
    if (!mounted) return;

    try {
      await ApiService.loadAppSettings();
    } catch (_) {}
    String mode = 'qr';
    final isCheckInCheckout =
        ApiService.attendanceMode == ApiService.attendanceModeCheckInCheckout;
    if (isCheckInCheckout) {
      mode = 'location';
    } else {
      mode = switch (ApiService.instantModeType) {
        ApiService.instantModeLocation => 'location',
        ApiService.instantModeWifi => 'wifi',
        _ => 'hybrid',
      };
    }
    String lecturerStatus = 'present';
    int durationMinutes = 60;
    String weekNumberText = '';
    final wifiCtrl = TextEditingController();
    double? lat;
    double? lng;
    var rangeM = Constants.defaultRangeMeters.round();

    final ok = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setLocal) {
          Future<void> captureGps() async {
            if (kIsWeb) {
              NotificationBridge.showSnackBar(
                const SnackBar(
                  content: Text('On web, enter coordinates manually if required.'),
                ),
              );
              return;
            }
            try {
              final enabled = await Geolocator.isLocationServiceEnabled();
              if (!enabled) {
                if (context.mounted) {
                  NotificationBridge.showSnackBar(
                    const SnackBar(content: Text('Turn on location services.')),
                  );
                }
                return;
              }
              var perm = await Geolocator.checkPermission();
              if (perm == LocationPermission.denied) {
                perm = await Geolocator.requestPermission();
              }
              if (perm == LocationPermission.denied ||
                  perm == LocationPermission.deniedForever) {
                if (context.mounted) {
                  NotificationBridge.showSnackBar(
                    const SnackBar(content: Text('Location permission denied.')),
                  );
                }
                return;
              }
              final pos = await Geolocator.getCurrentPosition(
                desiredAccuracy: LocationAccuracy.high,
              );
              setLocal(() {
                lat = pos.latitude;
                lng = pos.longitude;
              });
              if (context.mounted) {
                NotificationBridge.showSnackBar(
                  SnackBar(
                    content: Text(
                      'GPS: ${lat!.toStringAsFixed(5)}, ${lng!.toStringAsFixed(5)}',
                    ),
                  ),
                );
              }
            } catch (e) {
              if (context.mounted) {
                NotificationBridge.showSnackBar(
                  SnackBar(content: Text('GPS error: $e')),
                );
              }
            }
          }

          final needsGps =
              mode == 'location' || mode == 'hybrid';
          final needsWifi = mode == 'wifi';

          return AlertDialog(
            title: Text('Open session · ${course.courseName}'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Attendance mode'),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    key: ValueKey<String>('open-session-mode-$mode'),
                    initialValue: mode,
                    items: [
                      DropdownMenuItem(
                        value: mode,
                        child: Text(
                          mode == 'location'
                              ? 'Location only'
                              : (mode == 'wifi'
                                  ? 'Wi‑Fi SSID'
                                  : 'Hybrid (GPS + QR)'),
                        ),
                      ),
                    ],
                    onChanged: null,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    isCheckInCheckout
                        ? 'Admin set Check-in/Check-out mode: this session is location-only.'
                        : 'Mode is locked by admin Instant Mode setting.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text('Lecturer'),
                  DropdownButtonFormField<String>(
                    key: ValueKey<String>('lecturer-$lecturerStatus'),
                    initialValue: lecturerStatus,
                    items: const [
                      DropdownMenuItem(value: 'present', child: Text('Present')),
                      DropdownMenuItem(value: 'absent', child: Text('Absent')),
                    ],
                    onChanged: (v) {
                      if (v != null) setLocal(() => lecturerStatus = v);
                    },
                  ),
                  const SizedBox(height: 12),
                  const Text('Duration (minutes)'),
                  DropdownButtonFormField<int>(
                    key: ValueKey<int>(durationMinutes),
                    initialValue: durationMinutes,
                    items: [30, 45, 60, 90, 120]
                        .map(
                          (m) => DropdownMenuItem(
                            value: m,
                            child: Text('$m min'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) {
                      if (v != null) setLocal(() => durationMinutes = v);
                    },
                  ),
                  const SizedBox(height: 12),
                  const Text('Week number (optional)'),
                  TextField(
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      hintText: 'Leave blank for default',
                      border: OutlineInputBorder(),
                    ),
                    onChanged: (v) => weekNumberText = v,
                  ),
                  if (needsWifi) ...[
                    const SizedBox(height: 12),
                    TextField(
                      controller: wifiCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Allowed Wi‑Fi SSID',
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ],
                  if (needsGps) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: captureGps,
                      icon: const Icon(Icons.my_location),
                      label: const Text('Use current GPS'),
                    ),
                    const SizedBox(height: 12),
                    const Text('Attendance radius (meters)'),
                    const SizedBox(height: 6),
                    DropdownButtonFormField<int>(
                      key: ValueKey<int>(rangeM),
                      initialValue: rangeM,
                      items: [25, 50, 75, 100, 150, 200, 300, 500]
                          .map(
                            (m) => DropdownMenuItem(
                              value: m,
                              child: Text('$m m'),
                            ),
                          )
                          .toList(),
                      onChanged: (v) {
                        if (v != null) setLocal(() => rangeM = v);
                      },
                    ),
                    if (lat != null && lng != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          'Lat ${lat!.toStringAsFixed(5)}, Lng ${lng!.toStringAsFixed(5)} · students must be within $rangeM m',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Open'),
              ),
            ],
          );
        },
      ),
    );

    if (ok != true || !mounted) {
      wifiCtrl.dispose();
      return;
    }

    if (mode == 'wifi' && wifiCtrl.text.trim().isEmpty) {
      wifiCtrl.dispose();
      NotificationBridge.showSnackBar(
        const SnackBar(content: Text('Enter the Wi‑Fi network name (SSID).')),
      );
      return;
    }

    if ((mode == 'location' || mode == 'hybrid') &&
        (lat == null || lng == null)) {
      wifiCtrl.dispose();
      NotificationBridge.showSnackBar(
        const SnackBar(
          content: Text('Use GPS (or ensure the course has a default venue).'),
        ),
      );
      return;
    }

    final body = <String, dynamic>{
      'index_number': student.indexNumber.trim().toUpperCase(),
      'password': (pwd ?? '').trim(),
      'course_id': course.courseId,
      'mode': mode,
      'lecturer_status': lecturerStatus,
      'duration_minutes': durationMinutes,
    };
    final trimmedWeek = weekNumberText.trim();
    if (trimmedWeek.isNotEmpty) {
      final parsed = int.tryParse(trimmedWeek);
      if (parsed == null) {
        wifiCtrl.dispose();
        NotificationBridge.showSnackBar(
          const SnackBar(content: Text('Invalid week number.')),
        );
        return;
      }
      body['week_number'] = parsed;
    }
    if (mode == 'wifi') {
      body['allowed_wifi_ssid'] = wifiCtrl.text.trim();
    }
    if (mode == 'location' || mode == 'hybrid') {
      body['location_lat'] = lat;
      body['location_lng'] = lng;
      body['attendance_range_m'] = rangeM;
    }
    wifiCtrl.dispose();

    try {
      final res = await ApiService.classRepOpenSession(body);
      final data = jsonDecode(res.body);
      Map<String, dynamic>? sessionMap;
      String? title;
      var ok = false;
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          data is Map &&
          data['success'] == true) {
        ok = true;
        title = data['message']?.toString();
        final inner = data['data'];
        if (inner is Map) {
          final opened = inner['session'];
          if (opened is Map) {
            sessionMap = Map<String, dynamic>.from(opened);
          }
        }
      }
      if (ok && mounted) {
        await _showSessionQrDialog(
          context,
          title: title ?? 'Session opened',
          sessionMap: sessionMap,
        );
        _refresh();
      } else if (!ok && mounted) {
        final msg = data is Map && data['message'] != null
            ? data['message'].toString()
            : ApiService.messageFromHttpResponse(res);
        NotificationBridge.showSnackBar(
          SnackBar(content: Text(msg.isEmpty ? 'Could not open session' : msg)),
        );
      }
    } catch (e) {
      if (mounted) {
        NotificationBridge.showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _showSessionQrDialog(
    BuildContext context, {
    required String title,
    Map<String, dynamic>? sessionMap,
  }) async {
    final modeRaw =
        (sessionMap?['mode']?.toString() ?? '').toLowerCase().trim();
    final showQr = modeRaw == 'qr' || modeRaw == 'hybrid';
    final String? token = showQr
        ? (sessionMap?['qr_token']?.toString())
        : null;
    final sidRaw = sessionMap?['id'];
    final int? hostSid = sidRaw is int
        ? sidRaw
        : sidRaw is num
            ? sidRaw.toInt()
            : int.tryParse(sidRaw?.toString() ?? '');
    SessionQrHostGuard.setHostingSessionId(hostSid);
    final sessionCode = sessionMap?['session_code']?.toString();
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (token != null && token.isNotEmpty) ...[
                const Text('Students scan this QR (use another device for best results):'),
                const SizedBox(height: 16),
                Center(
                  child: QrImageView(
                    data: token,
                    size: 300,
                    backgroundColor: Colors.white,
                    eyeStyle: const QrEyeStyle(
                      eyeShape: QrEyeShape.square,
                      color: Color(0xFF000000),
                    ),
                    dataModuleStyle: const QrDataModuleStyle(
                      dataModuleShape: QrDataModuleShape.square,
                      color: Color(0xFF000000),
                    ),
                  ),
                ),
                if (sessionCode != null && sessionCode.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  const Text(
                    'Manual entry code (same session):',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 6),
                  SelectableText(
                    sessionCode,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ] else ...[
                if (modeRaw == 'location')
                  const Text(
                    'Location-only session is live. Students mark attendance in the app when they are within the set radius (no QR).',
                  )
                else if (modeRaw == 'wifi')
                  const Text(
                    'Wi‑Fi session is live. Students mark attendance when connected to the allowed network.',
                  )
                else
                  const Text('Session is live. Students can mark attendance in the app.'),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Done'),
          ),
        ],
      ),
    ).whenComplete(() => SessionQrHostGuard.setHostingSessionId(null));
  }

  Future<void> _closeSession(RepCourse course) async {
    final id = course.activeSessionId;
    if (id == null) return;
    final student = await OfflineService.getCurrentStudent();
    if (student == null || !await OfflineService.hasPasswordOrApiToken()) {
      return;
    }
    final pwd = await OfflineService.getApiSessionPassword();
    if (!mounted) return;

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Close session?'),
        content: Text(course.courseName),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Close'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    try {
      final res = await ApiService.classRepCloseSession(
        sessionId: id,
        indexNumber: student.indexNumber,
        password: pwd ?? '',
      );
      final data = jsonDecode(res.body);
      String msg;
      if (data is Map && data['success'] == true) {
        msg = data['message']?.toString() ?? 'Session closed.';
      } else if (data is Map && data['message'] != null) {
        msg = data['message'].toString();
      } else {
        msg = ApiService.isSuccessfulHttp(res.statusCode)
            ? 'Session closed.'
            : 'Could not close (${res.statusCode})';
      }
      if (mounted) {
        NotificationBridge.showSnackBar(SnackBar(content: Text(msg)));
        _refresh();
      }
    } catch (e) {
      if (mounted) {
        NotificationBridge.showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _extendSession(RepCourse course) async {
    final id = course.activeSessionId;
    if (id == null) return;

    final student = await OfflineService.getCurrentStudent();
    if (student == null || !await OfflineService.hasPasswordOrApiToken()) {
      return;
    }
    final pwd = await OfflineService.getApiSessionPassword();
    if (!mounted) return;

    int additionalMinutes = 30;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setLocal) {
          return AlertDialog(
            title: const Text('Extend marking time'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('Choose how many extra minutes to add:'),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: additionalMinutes,
                  items: [15, 30, 45, 60, 90]
                      .map((m) => DropdownMenuItem(value: m, child: Text('$m min')))
                      .toList(),
                  onChanged: (v) {
                    if (v != null) setLocal(() => additionalMinutes = v);
                  },
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Extend'),
              ),
            ],
          );
        },
      ),
    );

    if (ok != true || !mounted) return;

    try {
      final res = await ApiService.classRepExtendSession(
        sessionId: id,
        indexNumber: student.indexNumber,
        password: pwd ?? '',
        additionalMinutes: additionalMinutes,
      );
      final data = jsonDecode(res.body);
      if (res.statusCode >= 200 && res.statusCode < 300 && data is Map) {
        final success = data['success'] == true || data['data']?['success'] == true;
        if (success && mounted) {
          NotificationBridge.showSnackBar(
            const SnackBar(content: Text('Session extended.')),
          );
          _refresh();
        } else if (mounted) {
          final msg = data['message']?.toString() ??
              ApiService.messageFromHttpResponse(res).toString();
          NotificationBridge.showSnackBar(
            SnackBar(content: Text(msg.isEmpty ? 'Could not extend session' : msg)),
          );
        }
      } else if (mounted) {
        NotificationBridge.showSnackBar(
          SnackBar(content: Text(ApiService.messageFromHttpResponse(res))),
        );
      }
    } catch (e) {
      if (mounted) {
        NotificationBridge.showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  void _showQrForCourse(RepCourse course) {
    final t = course.qrToken;
    if (t == null || t.isEmpty) {
      NotificationBridge.showSnackBar(
        const SnackBar(content: Text('No QR token for this session.')),
      );
      return;
    }
    _showSessionQrDialog(
      context,
      title: course.courseName,
      sessionMap: course.activeSession,
    );
  }

  RepCourse? get _selectedCourse {
    if (_selectedIndex == null ||
        _selectedIndex! < 0 ||
        _selectedIndex! >= _courses.length) {
      return null;
    }
    return _courses[_selectedIndex!];
  }

  Widget _buildCoursePanel(RepCourse c) {
    final active = c.activeSession != null;
    final hasActiveQr = c.activeSession?['qr_token']?.toString().isNotEmpty ?? false;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: DashboardSurfaces.cardDecoration(context, radius: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      c.courseName,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    if (c.courseCode != null && c.courseCode!.isNotEmpty)
                      Text(
                        c.courseCode!,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    if (active)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          'Session active',
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: Theme.of(context).colorScheme.primary,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if (active && hasActiveQr)
                    SizedBox(
                      width: 100,
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                          minimumSize: const Size(100, 36),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(11),
                          ),
                        ),
                        onPressed: () => _showQrForCourse(c),
                        child: const Text('Show QR'),
                      ),
                    ),
                  if (active && c.canOpenSession)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: SizedBox(
                        width: 100,
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                            minimumSize: const Size(100, 36),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(11),
                            ),
                          ),
                          onPressed: () => _closeSession(c),
                          child: const Text('Close'),
                        ),
                      ),
                    ),
                  // If a session is already active, "Open" becomes "Extend" so the rep can
                  // prolong marking time (QR button stays visible only when available).
                  if (active && c.canOpenSession)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: SizedBox(
                        width: 100,
                        child: FilledButton.tonal(
                          style: FilledButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                            minimumSize: const Size(100, 36),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(11),
                            ),
                          ),
                          onPressed: () => _extendSession(c),
                          child: const Text('Extend'),
                        ),
                      ),
                    )
                  else if (!active && c.canOpenSession && c.hasSchedule)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: SizedBox(
                        width: 100,
                        child: FilledButton.tonal(
                          style: FilledButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                            minimumSize: const Size(100, 36),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(11),
                            ),
                          ),
                          onPressed: () => _openSession(c),
                          child: const Text('Open'),
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
          if (!c.hasSchedule)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Timetable not set. Admin must set day/time first.',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontSize: 12,
                ),
              ),
            ),
          if (!c.canOpenSession && c.isMainRep == false)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Only main rep can open or close session.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: SoftUi.scaffoldBackground(context),
      appBar: AppBar(
        backgroundColor: SoftUi.scaffoldBackground(context),
        title: const Text('Session management'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loading ? null : _refresh,
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _refresh,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : ModernPullToRefresh(
                  onRefresh: _refresh,
                  child: _courses.isEmpty
                      ? ListView(
                          physics: modernPullToRefreshPhysics,
                          children: const [
                            SizedBox(height: 120),
                            Center(child: Text('No courses assigned to your rep role.')),
                          ],
                        )
                      : ListView(
                          physics: modernPullToRefreshPhysics,
                          padding: const EdgeInsets.all(16),
                          children: [
                            if (_classActiveSession != null)
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(12),
                                decoration: DashboardSurfaces.cardDecoration(
                                  context,
                                  radius: 12,
                                ),
                                child: Text(
                                  'Active session: $_statsPresent/${_statsTotalStudents > 0 ? _statsTotalStudents : '—'} present',
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelLarge
                                      ?.copyWith(fontWeight: FontWeight.w700),
                                ),
                              ),
                            if (_classActiveSession != null)
                              const SizedBox(height: 10),
                            Text(
                              'Pick one course at a time. Open / QR / close apply only to this class.',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: cs.onSurfaceVariant,
                                  ),
                            ),
                            const SizedBox(height: 12),
                            DropdownButtonFormField<int>(
                              initialValue: _selectedIndex,
                              isExpanded: true,
                              decoration: const InputDecoration(
                                labelText: 'Course',
                                border: OutlineInputBorder(),
                                isDense: true,
                              ),
                              hint: const Text('Select a course'),
                              items: [
                                for (var i = 0; i < _courses.length; i++)
                                  DropdownMenuItem<int>(
                                    value: i,
                                    child: Text(
                                      [
                                        _courses[i].courseName,
                                        if (_courses[i].courseCode != null &&
                                            _courses[i].courseCode!.isNotEmpty)
                                          '(${_courses[i].courseCode})',
                                      ].join(' '),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                              ],
                              onChanged: (v) => setState(() => _selectedIndex = v),
                            ),
                            const SizedBox(height: 16),
                            if (_selectedCourse != null) _buildCoursePanel(_selectedCourse!),
                            if (_selectedCourse == null)
                              Padding(
                                padding: const EdgeInsets.only(top: 32),
                                child: Center(
                                  child: Text(
                                    'Choose a course to manage attendance.',
                                    textAlign: TextAlign.center,
                                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                          color: cs.onSurfaceVariant,
                                        ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                ),
    );
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }
}
