import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/last_attendance_prefs.dart';
import '../services/offline_service.dart';
import '../theme/dashboard_surfaces.dart';
import '../widgets/app_drawer_shell.dart';
import '../widgets/modern_pull_to_refresh.dart';
import '../utils/app_selectable_scope.dart';
import '../utils/app_state.dart';
import '../utils/connectivity_util.dart';
import 'login_page.dart';
import 'rep_session_page.dart';

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
  int _attendanceTodayCount = 0;

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
        final idx = AppState.studentIndex ?? s.indexNumber;
        final todayLogs = await OfflineService.getTodayAttendanceLogs(idx);
        if (!mounted) return;
        setState(() {
          _dashLoading = false;
          _dashError = null;
          _hasActiveSession = d['has_active_session'] == true;
          _activeSessionsCount = _toInt(d['active_sessions_count']) ?? 0;
          _studentsInClassesCount = _toInt(d['students_in_classes_count']) ?? 0;
          _attendanceTodayCount = todayLogs.length;
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
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _dashLoading = false;
        _dashError = 'Could not refresh dashboard.';
      });
    }
  }

  int? _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.round();
    return int.tryParse(v?.toString() ?? '');
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

  void _openSessions() {
    Navigator.of(context)
        .push<void>(
          MaterialPageRoute<void>(
            builder: (_) => appSelectableScope(const RepSessionPage()),
          ),
        )
        .then((_) {
      if (mounted && _student != null) {
        _loadDashboard(_student!);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final s = _student;
    if (s == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final classLabel = (s.className ?? '').trim().isNotEmpty
        ? s.className!.trim()
        : 'Class';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Class Rep'),
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
        child: AppDrawerShell(
          child: SafeArea(
            child: ListView(
              children: [
                ListTile(
                  leading: const Icon(Icons.event_note_rounded),
                  title: const Text('Session management'),
                  subtitle: const Text('Open, QR & close attendance'),
                  onTap: () {
                    Navigator.pop(context);
                    _openSessions();
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.fact_check_outlined),
                  title: const Text('Attendance Records'),
                  onTap: () {
                    Navigator.pop(context);
                    Navigator.of(context).pushNamed('/attendance-records');
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.groups_outlined),
                  title: const Text('Class List'),
                  onTap: () {
                    Navigator.pop(context);
                    Navigator.of(context).pushNamed('/class-rep/students');
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.how_to_reg_outlined),
                  title: const Text('My Attendance'),
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
                  leading: Icon(
                    Icons.logout,
                    color: Theme.of(context).colorScheme.error,
                  ),
                  title: Text(
                    'Log out',
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                  onTap: () {
                    Navigator.pop(context);
                    _confirmLogout();
                  },
                ),
              ],
            ),
          ),
        ),
      ),
      body: SafeArea(
        child: ModernPullToRefresh(
          onRefresh: () => _loadDashboard(s),
          child: CustomScrollView(
            physics: modernPullToRefreshPhysics,
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              classLabel,
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.w800,
                                  ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 5,
                            ),
                            decoration: DashboardSurfaces.chipDecoration(context),
                            child: Text(
                              'Class Rep',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if (_dashLoading) ...[
                        const SizedBox(height: 10),
                        const LinearProgressIndicator(),
                      ],
                      if (_dashError != null) ...[
                        const SizedBox(height: 8),
                        Text(
                          _dashError!,
                          style: TextStyle(color: Theme.of(context).colorScheme.error),
                        ),
                      ],
                      const SizedBox(height: 12),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 1.35,
                  ),
                  delegate: SliverChildListDelegate(
                    [
                      _metricCard(
                        context,
                        lightPastel: const Color(0xFFE8F5E9),
                        darkAccent: const Color(0xFF4ADE80),
                        icon: Icons.bolt_rounded,
                        title: 'Active Session',
                        value: _hasActiveSession ? 'Live' : 'None',
                      ),
                      _metricCard(
                        context,
                        lightPastel: const Color(0xFFE3F2FD),
                        darkAccent: const Color(0xFF38BDF8),
                        icon: Icons.groups_rounded,
                        title: 'Students Count',
                        value: '$_studentsInClassesCount',
                      ),
                      _metricCard(
                        context,
                        lightPastel: const Color(0xFFF3E5F5),
                        darkAccent: const Color(0xFFC084FC),
                        icon: Icons.today_rounded,
                        title: 'Attendance Today',
                        value: '$_attendanceTodayCount',
                      ),
                      _actionCard(
                        context,
                        lightPastel: const Color(0xFFFFF3E0),
                        darkAccent: const Color(0xFFFBBF24),
                        icon: Icons.event_note_rounded,
                        title: 'Manage Sessions',
                        subtitle: _hasActiveSession
                            ? '$_activeSessionsCount active'
                            : 'Open or close session',
                        onTap: _openSessions,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _metricCard(
    BuildContext context, {
    required Color lightPastel,
    required Color darkAccent,
    required IconData icon,
    required String title,
    required String value,
  }) {
    final cs = Theme.of(context).colorScheme;
    final bg = DashboardSurfaces.metricWash(
      context,
      lightPastel: lightPastel,
      darkAccent: darkAccent,
    );
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(14),
        border: DashboardSurfaces.metricCardBorder(context),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 17, color: cs.primary),
          const Spacer(),
          Text(
            title,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                  color: cs.onSurface.withValues(alpha: 0.88),
                ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: cs.onSurface,
                ),
          ),
        ],
      ),
    );
  }

  Widget _actionCard(
    BuildContext context, {
    required Color lightPastel,
    required Color darkAccent,
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    final cs = Theme.of(context).colorScheme;
    final bg = DashboardSurfaces.metricWash(
      context,
      lightPastel: lightPastel,
      darkAccent: darkAccent,
    );
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(14),
          border: DashboardSurfaces.metricCardBorder(context),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 17, color: cs.primary),
            const Spacer(),
            Text(
              title,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: cs.onSurface.withValues(alpha: 0.9),
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              subtitle,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    fontWeight: FontWeight.w500,
                    color: cs.onSurface.withValues(alpha: 0.72),
                    fontSize: 10,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

