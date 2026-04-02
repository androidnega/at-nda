import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/last_attendance_prefs.dart';
import '../services/offline_service.dart';
import '../utils/connectivity_util.dart';
import '../utils/greeting_util.dart';
import '../utils/app_selectable_scope.dart';
import '../widgets/profile_avatar.dart';
import 'login_page.dart';
import 'rep_session_page.dart';

/// Entry screen for class reps: session management first; student attendance is secondary.
class RepHomePage extends StatefulWidget {
  const RepHomePage({super.key});

  @override
  State<RepHomePage> createState() => _RepHomePageState();
}

class _RepHomePageState extends State<RepHomePage> {
  Student? _student;
  bool _dashLoading = false;
  String? _dashError;
  bool _hasActiveSession = false;
  int _activeSessionsCount = 0;
  int _studentsInClassesCount = 0;

  @override
  void initState() {
    super.initState();
    _loadStudent();
  }

  Future<void> _loadStudent() async {
    final s = await OfflineService.getCurrentStudent();
    if (!mounted) return;
    if (s == null) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => appSelectableScope(const LoginPage()),
        ),
        (_) => false,
      );
      return;
    }
    setState(() => _student = s);
    await _loadDashboard(s);
  }

  Future<void> _loadDashboard(Student s) async {
    if (!await OfflineService.hasPasswordOrApiToken()) return;
    if (!await hasInternetConnectivity()) return;

    setState(() {
      _dashLoading = true;
      _dashError = null;
    });
    try {
      final pwd = await OfflineService.getApiSessionPassword();
      final res = await ApiService.classRepDashboard(
        indexNumber: s.indexNumber,
        password: pwd ?? '',
      );
      final raw = jsonDecode(res.body);
      if (res.statusCode == 200 &&
          raw is Map &&
          raw['success'] == true &&
          raw['data'] is Map) {
        final d = Map<String, dynamic>.from(raw['data'] as Map);
        if (!mounted) return;
        setState(() {
          _dashLoading = false;
          _dashError = null;
          _hasActiveSession = d['has_active_session'] == true;
          _activeSessionsCount = _parseInt(d['active_sessions_count']) ?? 0;
          _studentsInClassesCount =
              _parseInt(d['students_in_classes_count']) ?? 0;
        });
      } else {
        if (!mounted) return;
        setState(() {
          _dashLoading = false;
          _dashError = ApiService.messageFromHttpResponse(res).isEmpty
              ? 'Could not refresh dashboard.'
              : ApiService.messageFromHttpResponse(res);
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _dashLoading = false;
          _dashError = null;
        });
      }
    }
  }

  int? _parseInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.round();
    return int.tryParse(v?.toString() ?? '');
  }

  String _greetingName(Student s) {
    final fl = '${s.firstName ?? ''} ${s.lastName ?? ''}'.trim();
    if (fl.isNotEmpty) return fl;
    final n = s.name.trim();
    if (n.isNotEmpty) return n;
    return s.indexNumber;
  }

  Future<void> _logout() async {
    await LastAttendancePrefs.clear();
    await OfflineService.clearCurrentStudent();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(
        builder: (_) => appSelectableScope(const LoginPage()),
      ),
      (_) => false,
    );
  }

  Future<void> _confirmLogout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Log out'),
        content: const Text('Clear stored account and return to login?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Log out'),
          ),
        ],
      ),
    );
    if (ok == true) await _logout();
  }

  @override
  Widget build(BuildContext context) {
    final s = _student;
    final colorScheme = Theme.of(context).colorScheme;

    if (s == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Class rep'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _dashLoading ? null : () => _loadDashboard(s),
          ),
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).pushNamed('/settings'),
          ),
        ],
      ),
      drawer: Drawer(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              UserAccountsDrawerHeader(
                decoration: BoxDecoration(color: colorScheme.primaryContainer),
                currentAccountPicture: ProfileAvatar(student: s, radius: 36),
                accountName: Text(
                  _greetingName(s),
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                accountEmail: Text(
                  s.indexNumber,
                  style: TextStyle(
                    color: colorScheme.onPrimaryContainer.withValues(alpha: 0.85),
                  ),
                ),
              ),
              ListTile(
                leading: const Icon(Icons.event_seat_outlined),
                title: const Text('Manage sessions'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context)
                      .push<void>(
                        MaterialPageRoute<void>(
                          builder: (_) =>
                              appSelectableScope(const RepSessionPage()),
                        ),
                      )
                      .then((_) {
                    if (mounted && _student != null) {
                      _loadDashboard(_student!);
                    }
                  });
                },
              ),
              ListTile(
                leading: const Icon(Icons.groups_outlined),
                title: const Text('Class roster'),
                subtitle: const Text('Students in your rep classes'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/class-rep/students');
                },
              ),
              ListTile(
                leading: const Icon(Icons.how_to_reg_outlined),
                title: const Text('My attendance'),
                subtitle: const Text('Mark your own attendance'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/home');
                },
              ),
              ListTile(
                leading: const Icon(Icons.person_outline),
                title: const Text('Profile'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/profile');
                },
              ),
              ListTile(
                leading: const Icon(Icons.settings_outlined),
                title: const Text('Settings'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/settings');
                },
              ),
              const Divider(),
              ListTile(
                leading: Icon(Icons.logout, color: colorScheme.error),
                title: Text('Log out', style: TextStyle(color: colorScheme.error)),
                onTap: () {
                  Navigator.pop(context);
                  _confirmLogout();
                },
              ),
            ],
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  ProfileAvatar(student: s, radius: 40),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          getGreeting(),
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: colorScheme.onSurfaceVariant,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _greetingName(s),
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerLeft,
                child: Chip(
                  avatar: Icon(Icons.badge_outlined, size: 18, color: colorScheme.primary),
                  label: const Text('Class representative'),
                  side: BorderSide(color: colorScheme.outlineVariant),
                ),
              ),
              if (_dashLoading) ...[
                const SizedBox(height: 16),
                const LinearProgressIndicator(),
              ],
              if (_dashError != null) ...[
                const SizedBox(height: 12),
                Text(
                  _dashError!,
                  style: TextStyle(
                    color: colorScheme.error,
                    fontSize: 13,
                  ),
                ),
              ],
              if (!_dashLoading && _dashError == null) ...[
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Attendance status',
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Icon(
                              _hasActiveSession
                                  ? Icons.circle
                                  : Icons.circle_outlined,
                              size: 14,
                              color: _hasActiveSession
                                  ? colorScheme.primary
                                  : colorScheme.outline,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _hasActiveSession
                                    ? '$_activeSessionsCount active session(s)'
                                    : 'No active sessions',
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            ),
                          ],
                        ),
                        if (_studentsInClassesCount > 0) ...[
                          const SizedBox(height: 6),
                          Text(
                            '$_studentsInClassesCount student(s) in your classes',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: colorScheme.onSurfaceVariant,
                                ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 28),
              Text(
                'Open or close live attendance for your class, show the session QR, and help classmates check in.',
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: colorScheme.onSurfaceVariant,
                      height: 1.45,
                    ),
              ),
              const SizedBox(height: 28),
              FilledButton.icon(
                onPressed: () {
                  Navigator.of(context)
                      .push<void>(
                        MaterialPageRoute<void>(
                          builder: (_) =>
                              appSelectableScope(const RepSessionPage()),
                        ),
                      )
                      .then((_) {
                    if (mounted && _student != null) {
                      _loadDashboard(_student!);
                    }
                  });
                },
                icon: const Icon(Icons.event_available_rounded),
                label: const Text('Manage class sessions'),
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).pushNamed('/class-rep/students');
                },
                icon: const Icon(Icons.groups_outlined),
                label: const Text('View class roster'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).pushNamed('/home');
                },
                icon: const Icon(Icons.how_to_reg_outlined),
                label: const Text('My attendance (as student)'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
