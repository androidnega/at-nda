import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../models/rep_course.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/constants.dart';

/// Class rep tools: list class courses, open live attendance, show QR, close session.
class RepSessionPage extends StatefulWidget {
  const RepSessionPage({super.key});

  @override
  State<RepSessionPage> createState() => _RepSessionPageState();
}

class _RepSessionPageState extends State<RepSessionPage> {
  bool _loading = true;
  String? _error;
  List<RepCourse> _courses = [];

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final student = await OfflineService.getCurrentStudent();
    final pwd = await OfflineService.getApiSessionPassword();
    if (student == null || pwd == null || pwd.isEmpty) {
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
      final res = await ApiService.repCourses(
        indexNumber: student.indexNumber,
        password: pwd,
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
        });
      }
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

  Future<void> _openSession(RepCourse course) async {
    final student = await OfflineService.getCurrentStudent();
    final pwd = await OfflineService.getApiSessionPassword();
    if (student == null || pwd == null || pwd.isEmpty) return;

    String mode = 'qr';
    String lecturerStatus = 'present';
    int durationMinutes = 60;
    final wifiCtrl = TextEditingController();
    double? lat;
    double? lng;
    int? rangeM;

    final ok = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setLocal) {
          Future<void> captureGps() async {
            if (kIsWeb) {
              ScaffoldMessenger.of(context).showSnackBar(
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
                  ScaffoldMessenger.of(context).showSnackBar(
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
                  ScaffoldMessenger.of(context).showSnackBar(
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
                rangeM = Constants.defaultRangeMeters.round();
              });
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      'GPS: ${lat!.toStringAsFixed(5)}, ${lng!.toStringAsFixed(5)}',
                    ),
                  ),
                );
              }
            } catch (e) {
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
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
                    value: mode,
                    items: const [
                      DropdownMenuItem(value: 'qr', child: Text('QR only')),
                      DropdownMenuItem(
                        value: 'location',
                        child: Text('Location only'),
                      ),
                      DropdownMenuItem(
                        value: 'hybrid',
                        child: Text('Hybrid (GPS + QR)'),
                      ),
                      DropdownMenuItem(
                        value: 'wifi',
                        child: Text('Wi‑Fi SSID'),
                      ),
                    ],
                    onChanged: (v) {
                      if (v != null) setLocal(() => mode = v);
                    },
                  ),
                  const SizedBox(height: 12),
                  const Text('Lecturer'),
                  DropdownButtonFormField<String>(
                    value: lecturerStatus,
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
                    value: durationMinutes,
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
                    if (lat != null && lng != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          'Lat ${lat!.toStringAsFixed(5)}, Lng ${lng!.toStringAsFixed(5)}, range ${rangeM ?? 50} m',
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter the Wi‑Fi network name (SSID).')),
      );
      return;
    }

    if ((mode == 'location' || mode == 'hybrid') &&
        (lat == null || lng == null || rangeM == null)) {
      wifiCtrl.dispose();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Use GPS (or ensure the course has a default venue).'),
        ),
      );
      return;
    }

    final body = <String, dynamic>{
      'index_number': student.indexNumber.trim().toUpperCase(),
      'password': pwd.trim(),
      'course_id': course.courseId,
      'mode': mode,
      'lecturer_status': lecturerStatus,
      'duration_minutes': durationMinutes,
    };
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
      final res = await ApiService.repOpenSession(body);
      final data = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          data is Map &&
          data['success'] == true) {
        if (mounted) {
          final opened = data['session'];
          await _showSessionQrDialog(
            context,
            title: data['message']?.toString() ?? 'Session opened',
            sessionMap: opened is Map
                ? Map<String, dynamic>.from(opened)
                : null,
          );
          _refresh();
        }
      } else {
        final msg = data is Map && data['message'] != null
            ? data['message'].toString()
            : ApiService.messageFromHttpResponse(res);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(msg.isEmpty ? 'Could not open session' : msg)),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
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
    final token = sessionMap?['qr_token']?.toString();
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (token != null && token.isNotEmpty) ...[
                const Text('Students scan this QR code:'),
                const SizedBox(height: 12),
                QrImageView(
                  data: token,
                  size: 220,
                  backgroundColor: Colors.white,
                ),
              ] else
                const Text('Session is live. Students can mark attendance in the app.'),
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
    );
  }

  Future<void> _closeSession(RepCourse course) async {
    final id = course.activeSessionId;
    if (id == null) return;
    final student = await OfflineService.getCurrentStudent();
    final pwd = await OfflineService.getApiSessionPassword();
    if (student == null || pwd == null || pwd.isEmpty) return;

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
      final res = await ApiService.repCloseSession(
        sessionId: id,
        indexNumber: student.indexNumber,
        password: pwd,
      );
      final data = jsonDecode(res.body);
      final msg = data is Map && data['message'] != null
          ? data['message'].toString()
          : (ApiService.isSuccessfulHttp(res.statusCode)
              ? 'Session closed.'
              : 'Could not close (${res.statusCode})');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
        _refresh();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  void _showQrForCourse(RepCourse course) {
    final t = course.qrToken;
    if (t == null || t.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Class rep · sessions'),
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
              : RefreshIndicator(
                  onRefresh: _refresh,
                  child: _courses.isEmpty
                      ? ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: const [
                            SizedBox(height: 120),
                            Center(child: Text('No courses assigned to your rep role.')),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _courses.length,
                          itemBuilder: (context, i) {
                            final c = _courses[i];
                            final active = c.activeSession != null;
                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            c.courseName,
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleMedium
                                                ?.copyWith(
                                                  fontWeight: FontWeight.w700,
                                                ),
                                          ),
                                        ),
                                        Chip(
                                          label: Text(c.roleLabel),
                                          visualDensity: VisualDensity.compact,
                                        ),
                                      ],
                                    ),
                                    if (c.courseCode != null &&
                                        c.courseCode!.isNotEmpty)
                                      Text(
                                        c.courseCode!,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall,
                                      ),
                                    if (!c.hasSchedule)
                                      Padding(
                                        padding: const EdgeInsets.only(top: 8),
                                        child: Text(
                                          'Timetable not set — admin must set day/time before opening.',
                                          style: TextStyle(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .error,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ),
                                    if (active) ...[
                                      const SizedBox(height: 8),
                                      Row(
                                        children: [
                                          Icon(
                                            Icons.circle,
                                            size: 10,
                                            color: Theme.of(context)
                                                .colorScheme
                                                .primary,
                                          ),
                                          const SizedBox(width: 6),
                                          const Text('Session active'),
                                        ],
                                      ),
                                    ],
                                    const SizedBox(height: 12),
                                    Wrap(
                                      spacing: 8,
                                      runSpacing: 8,
                                      children: [
                                        if (active &&
                                            (c.activeSession?['qr_token']
                                                    ?.toString()
                                                    .isNotEmpty ??
                                                false))
                                          OutlinedButton.icon(
                                            onPressed: () => _showQrForCourse(c),
                                            icon: const Icon(Icons.qr_code_2),
                                            label: const Text('Show QR'),
                                          ),
                                        if (active && c.canOpenSession)
                                          FilledButton.tonal(
                                            onPressed: () => _closeSession(c),
                                            child: const Text('Close session'),
                                          ),
                                        if (c.canOpenSession && c.hasSchedule)
                                          FilledButton.icon(
                                            onPressed: () => _openSession(c),
                                            icon: const Icon(Icons.play_arrow),
                                            label: const Text('Open attendance'),
                                          ),
                                      ],
                                    ),
                                    if (!c.canOpenSession && c.isMainRep == false)
                                      Padding(
                                        padding: const EdgeInsets.only(top: 8),
                                        child: Text(
                                          'Only the main rep can open or close sessions. You can view the QR when a session is active.',
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodySmall,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
