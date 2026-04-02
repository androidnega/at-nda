import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/location_service.dart';
import '../utils/constants.dart';
import '../utils/session_attendance_payload.dart';

/// Debug page to verify Flutter ↔ Laravel connection over the network.
/// Access from login screen to test GET students and POST attendance.
class ApiTestPage extends StatefulWidget {
  const ApiTestPage({super.key});

  @override
  State<ApiTestPage> createState() => _ApiTestPageState();
}

class _ApiTestPageState extends State<ApiTestPage> {
  String _log = 'Tap a button to test the API connection.';
  bool _isLoading = false;

  void _logMsg(String msg) {
    setState(() => _log = '$_log\n\n$msg');
    debugPrint(msg);
  }

  Future<void> _fetchStudents() async {
    setState(() {
      _isLoading = true;
      _log = 'Fetching students from ${Constants.baseUrl}/students...';
    });

    try {
      final res = await ApiService.getStudents();
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final list = data is List ? data : <dynamic>[];
        _logMsg('Success! Status ${res.statusCode}\nStudents: ${list.length}');
        if (list.isNotEmpty) {
          _logMsg(list.map((s) => '${s['index_number']}: ${s['name']}').join('\n'));
        } else {
          _logMsg('(No students in database)');
        }
      } else {
        _logMsg('Failed! Status: ${res.statusCode}\n${res.body}');
      }
    } catch (e) {
      _logMsg('Error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _testAttendance() async {
    setState(() {
      _isLoading = true;
      _log = 'Posting test attendance...';
    });

    try {
      final sessions = await ApiService.getActiveSessions();
      if (sessions.isEmpty) {
        final err = ApiService.lastActiveSessionErrorMessage;
        _logMsg(
          err.isEmpty
              ? 'No active sessions — load sessions first (GET /sessions/active).'
              : err,
        );
        return;
      }
      final s = Map<String, dynamic>.from(sessions.first);
      final sessionId = parseSessionId(s);
      if (sessionId == null) {
        _logMsg('First session has no valid id.');
        return;
      }
      final courseId = parseOptionalCourseId(s);
      final weekId = parseOptionalWeekId(s);

      final body = buildAttendancePostBody(
        indexNumber: 'BC/ITD/24/001',
        sessionId: sessionId,
        courseId: courseId,
        weekId: weekId,
        lat: 5.6321,
        lng: -0.1871,
        includeLocation: true,
        faceDescriptor: <double>[],
        deviceIp: '192.168.1.51',
        timestamp: DateTime.now().toIso8601String(),
      );

      final res = await ApiService.post('attendance', body);
      final data = jsonDecode(res.body);
      _logMsg('Attendance API response (${res.statusCode}):\n$data');
      if (res.statusCode == 422 && data is Map && data['password_required'] == true) {
        _logMsg('\n⚠️ Set password first: Log in with this index, create password, complete onboarding.');
      }
    } catch (e) {
      _logMsg('Error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _postSessionStartWithGps() async {
    setState(() {
      _isLoading = true;
      _log = 'Getting GPS (best for navigation)…';
    });

    try {
      final pos = await LocationService.getCurrentPositionForSessionStart();
      _logMsg(
        'Fix: ${pos.latitude}, ${pos.longitude} · ±${pos.accuracy.toStringAsFixed(1)} m',
      );
      final res = await ApiService.startSession(
        lat: pos.latitude,
        lng: pos.longitude,
        accuracy: pos.accuracy,
      );
      _logMsg('POST sessions/start (${res.statusCode}):\n${res.body}');
    } catch (e) {
      _logMsg('Error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _fetchSession() async {
    setState(() {
      _isLoading = true;
      _log = 'Fetching active session...';
    });

    try {
      final data = await ApiService.getActiveSessions();
      if (data.isEmpty) {
        final err = ApiService.lastActiveSessionErrorMessage;
        _logMsg(
          err.isEmpty
              ? 'No active sessions (empty / none).'
              : 'Error: $err',
        );
      } else {
        _logMsg('Sessions (${data.length}):\n$data');
      }
    } catch (e) {
      _logMsg('Error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('API Test'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Base URL: ${Constants.baseUrl}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ElevatedButton(
                    onPressed: _isLoading ? null : _fetchStudents,
                    child: const Text('Fetch Students'),
                  ),
                  ElevatedButton(
                    onPressed: _isLoading ? null : _fetchSession,
                    child: const Text('Fetch Session'),
                  ),
                  ElevatedButton(
                    onPressed: _isLoading ? null : _postSessionStartWithGps,
                    child: const Text('Start session (GPS)'),
                  ),
                  ElevatedButton(
                    onPressed: _isLoading ? null : _testAttendance,
                    child: const Text('Test Attendance'),
                  ),
                ],
              ),
              if (_isLoading)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: LinearProgressIndicator(),
                ),
              const SizedBox(height: 16),
              Expanded(
                child: SingleChildScrollView(
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade200,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: SelectableText(
                      _log,
                      style: const TextStyle(
                        fontFamily: 'monospace',
                        fontSize: 12,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
