import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/last_attendance_prefs.dart';
import '../services/offline_service.dart';
import '../services/student_profile_refresh.dart';
import '../theme/dashboard_surfaces.dart';
import '../widgets/app_drawer_shell.dart';
import '../widgets/modern_pull_to_refresh.dart';
import '../widgets/student_drawer_header.dart';
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
  Timer? _pollTimer;
  /// Ticks every second while an active session has an end time (countdown UI).
  Timer? _countdownTimer;
  Student? _student;
  bool _dashLoading = false;
  String? _dashError;
  bool _hasActiveSession = false;
  int _activeSessionsCount = 0;
  int _studentsInClassesCount = 0;
  int _attendanceTodayCount = 0;
  int _activeSessionStudents = 0;
  int _activeSessionPresent = 0;
  /// Session object from `GET /api/class/active-session` (id, end_time, course_name, …).
  Map<String, dynamic>? _activeSessionDetail;
  DateTime? _sessionEndsAt;

  @override
  void initState() {
    super.initState();
    _loadStudent();
  }

  Future<void> _loadStudent() async {
    var s = await OfflineService.getCurrentStudent();
    if (s != null && await hasInternetConnectivity()) {
      final r = await refreshStudentProfileFromApi(s);
      if (r != null) s = r;
    }
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
    _startPolling();
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
        await _loadClassActiveSessionStats(s);
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

  Future<void> _loadClassActiveSessionStats(Student s) async {
    final pwd = await OfflineService.getApiSessionPassword();
    if (pwd == null || pwd.isEmpty) return;
    try {
      final res = await ApiService.classActiveSession(
        indexNumber: s.indexNumber,
        password: pwd,
      );
      if (res.statusCode != 200) return;
      final body = jsonDecode(res.body);
      if (body is! Map) return;
      Map<String, dynamic>? sessionDetail;
      DateTime? endsAt;
      if (body['session'] is Map) {
        sessionDetail = Map<String, dynamic>.from(body['session'] as Map);
        final endRaw = sessionDetail['end_time'];
        if (endRaw != null) {
          try {
            endsAt = DateTime.parse(endRaw.toString());
          } catch (_) {}
        }
      }
      if (!mounted) return;
      final active = body['has_active_session'] == true;
      setState(() {
        _hasActiveSession = active;
        _activeSessionStudents = _toInt(body['total_students']) ?? _studentsInClassesCount;
        _activeSessionPresent = _toInt(body['total_present']) ?? 0;
        if (active) {
          _activeSessionDetail = sessionDetail;
          _sessionEndsAt = endsAt;
        } else {
          _activeSessionDetail = null;
          _sessionEndsAt = null;
        }
      });
      _syncCountdownTimer();
    } catch (_) {}
  }

  void _syncCountdownTimer() {
    _countdownTimer?.cancel();
    if (!_hasActiveSession || _sessionEndsAt == null) return;
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      final end = _sessionEndsAt;
      if (end != null && DateTime.now().isAfter(end)) {
        _countdownTimer?.cancel();
        final st = _student;
        if (st != null) _loadDashboard(st);
        return;
      }
      setState(() {});
    });
  }

  String _formatCountdown(Duration remaining) {
    if (remaining.isNegative) return '00:00';
    final h = remaining.inHours;
    final m = remaining.inMinutes.remainder(60);
    final s = remaining.inSeconds.remainder(60);
    if (h > 0) {
      return '${h.toString().padLeft(2, '0')}:'
          '${m.toString().padLeft(2, '0')}:'
          '${s.toString().padLeft(2, '0')}';
    }
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  Future<void> _extendActiveSession() async {
    final session = _activeSessionDetail;
    final student = _student;
    if (session == null || student == null) return;
    final sessionId = _toInt(session['id']);
    if (sessionId == null || sessionId <= 0) return;
    final pwd = await OfflineService.getApiSessionPassword();
    if (pwd == null || pwd.isEmpty || !mounted) return;

    var additionalMinutes = 30;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setLocal) {
          return AlertDialog(
            title: const Text('Extend marking time'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('Add extra minutes to this session:'),
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
        sessionId: sessionId,
        indexNumber: student.indexNumber,
        password: pwd,
        additionalMinutes: additionalMinutes,
      );
      final data = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          data is Map &&
          data['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Session extended.')),
          );
          await _loadDashboard(student);
        }
      } else if (mounted) {
        final msg = data is Map
            ? (data['message']?.toString() ?? '')
            : ApiService.messageFromHttpResponse(res);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg.isEmpty ? 'Could not extend session' : msg)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      final s = _student;
      if (s == null || !mounted) return;
      _loadDashboard(s);
    });
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

  Widget _buildDrawer(BuildContext context, Student s) {
    final colorScheme = Theme.of(context).colorScheme;
    final headerColor =
        colorScheme.primaryContainer.withValues(alpha: 0.45);

    return Drawer(
      child: AppDrawerShell(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              StudentDrawerHeader(
                student: s,
                decorationColor: headerColor,
              ),
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
                leading: const Icon(Icons.person_outline),
                title: const Text('Profile'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context)
                      .pushNamed('/profile')
                      .then((_) => _loadStudent());
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
                  color: colorScheme.error,
                ),
                title: Text(
                  'Log out',
                  style: TextStyle(color: colorScheme.error),
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
    );
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _countdownTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = _student;
    if (s == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

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
      drawer: _buildDrawer(context, s),
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
                              'Dashboard',
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
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
                sliver: SliverToBoxAdapter(
                  child: _buildActiveSessionLiveCard(context),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildActiveSessionLiveCard(BuildContext context) {
    if (!_hasActiveSession || _activeSessionDetail == null) {
      return const SizedBox.shrink();
    }
    final cs = Theme.of(context).colorScheme;
    final isLight = Theme.of(context).brightness == Brightness.light;
    final session = _activeSessionDetail!;
    final courseName = session['course_name']?.toString() ?? 'Active session';
    final courseCode = session['course_code']?.toString().trim() ?? '';
    final titleLine =
        courseCode.isNotEmpty ? '$courseName · $courseCode' : courseName;
    final total = _activeSessionStudents > 0
        ? _activeSessionStudents
        : _studentsInClassesCount;
    final present = _activeSessionPresent;

    String timeLabel;
    if (_sessionEndsAt != null) {
      final rem = _sessionEndsAt!.difference(DateTime.now());
      timeLabel = _formatCountdown(rem);
    } else {
      timeLabel = '—';
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            cs.primaryContainer.withValues(alpha: isLight ? 0.65 : 0.28),
            cs.surfaceContainerHighest.withValues(alpha: isLight ? 0.9 : 0.45),
          ],
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: cs.primary.withValues(alpha: 0.4)),
        boxShadow: isLight
            ? [
                BoxShadow(
                  color: cs.primary.withValues(alpha: 0.08),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ]
            : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: cs.error.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.circle, size: 9, color: cs.error),
                    const SizedBox(width: 6),
                    Text(
                      'LIVE',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.9,
                        color: cs.error,
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              Text(
                'Marking window',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: isLight
                          ? const Color(0xFF475569)
                          : cs.onSurfaceVariant,
                      fontWeight: FontWeight.w600,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            titleLine,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: isLight ? const Color(0xFF0F172A) : cs.onSurface,
                  height: 1.25,
                ),
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                timeLabel,
                style: Theme.of(context).textTheme.displaySmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      fontFeatures: const [FontFeature.tabularFigures()],
                      color: cs.primary,
                      letterSpacing: -1,
                      fontSize: 36,
                    ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _sessionEndsAt != null
                      ? 'remaining until session ends'
                      : 'End time not set — refresh if needed',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: isLight ? const Color(0xFF64748B) : cs.onSurfaceVariant,
                        height: 1.3,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Icon(Icons.how_to_reg_rounded, size: 22, color: cs.primary),
              const SizedBox(width: 8),
              Text(
                '$present',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: isLight ? const Color(0xFF0F172A) : cs.onSurface,
                    ),
              ),
              Text(
                ' live now',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      color: isLight ? const Color(0xFF334155) : cs.onSurfaceVariant,
                    ),
              ),
              Text(
                ' · $total in class',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w500,
                      color: isLight ? const Color(0xFF64748B) : cs.onSurfaceVariant,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _dashLoading ? null : _extendActiveSession,
              icon: const Icon(Icons.more_time_rounded, size: 20),
              label: const Text('Extend'),
            ),
          ),
        ],
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

