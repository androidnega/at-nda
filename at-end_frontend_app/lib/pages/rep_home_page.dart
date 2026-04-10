import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart' show defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/communication_log_sync.dart';
import '../services/last_attendance_prefs.dart';
import '../services/logout_lock_prefs.dart';
import '../services/notification_bridge.dart';
import '../services/offline_service.dart';
import '../services/student_profile_refresh.dart';
import '../widgets/app_drawer_shell.dart';
import '../widgets/attendance_trend_chart.dart';
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

class _RepHomePageState extends State<RepHomePage> with WidgetsBindingObserver {
  /// Ticks every second while an active session has an end time (countdown UI).
  Timer? _countdownTimer;
  Student? _student;
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
  List<Map<String, dynamic>> _attendanceTrend = [];
  List<Map<String, dynamic>> _flaggedStudents = [];

  /// Pastel “Class diary” pill index (visual filter; list uses live flagged data).
  int _repDiaryPill = 0;
  bool _logoutAllowed = true;
  String? _logoutLockHint;
  bool _appWentToBackground = false;

  /// Opens the drawer; [Scaffold.of] from [State.context] is above the scaffold and fails.
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_syncLogoutLock());
    });
    _loadStudent();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      _appWentToBackground = true;
    } else if (state == AppLifecycleState.resumed) {
      if (_appWentToBackground) {
        _appWentToBackground = false;
        unawaited(_syncLogoutLock());
      }
    }
  }

  Future<void> _syncLogoutLock() async {
    final t = await OfflineService.getApiSessionToken();
    final has = t != null && t.isNotEmpty;
    final current = _student ?? await OfflineService.getCurrentStudent();
    final role = current?.primaryRole;
    await ApiService.loadAppSettings(forceRemote: false);
    final lockEnabled = ApiService.studentLogoutLockEnabled;
    await LogoutLockPrefs.applyGracePeriodAndExtension(hasSession: has);
    final allow = await LogoutLockPrefs.canLogoutNow(
      role: role,
      studentLogoutLockEnabled: lockEnabled,
    );
    final hint =
        allow
            ? null
            : await LogoutLockPrefs.signOutBlockedHint(
              role: role,
              studentLogoutLockEnabled: lockEnabled,
            );
    if (!mounted) return;
    setState(() {
      _logoutAllowed = allow;
      _logoutLockHint = hint;
    });
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
  }

  Future<void> _loadDashboard(Student s) async {
    if (!await OfflineService.hasPasswordOrApiToken()) return;
    if (!await hasInternetConnectivity()) return;

    try {
      await ApiService.loadAppSettings(forceRemote: true);
      unawaited(CommunicationLogSyncService.maybeSync());
    } catch (_) {}

    setState(() {
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
        final tr = <Map<String, dynamic>>[];
        final trendRaw = d['attendance_trend'];
        if (trendRaw is List) {
          for (final e in trendRaw) {
            if (e is Map) tr.add(Map<String, dynamic>.from(e));
          }
        }
        final flaggedRaw = d['flagged_students'];
        final flagged = <Map<String, dynamic>>[];
        if (flaggedRaw is List) {
          for (final e in flaggedRaw) {
            if (e is Map) flagged.add(Map<String, dynamic>.from(e));
          }
        }
        if (!mounted) return;
        setState(() {
          _dashError = null;
          _hasActiveSession = d['has_active_session'] == true;
          _activeSessionsCount = _toInt(d['active_sessions_count']) ?? 0;
          _studentsInClassesCount = _toInt(d['students_in_classes_count']) ?? 0;
          _attendanceTodayCount = todayLogs.length;
          _attendanceTrend = tr;
          _flaggedStudents = flagged;
        });
        await _loadClassActiveSessionStats(s);
      } else {
        if (!mounted) return;
        setState(() {
          _dashError =
              ApiService.messageFromHttpResponse(res).isEmpty
                  ? 'Could not refresh dashboard.'
                  : ApiService.messageFromHttpResponse(res);
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
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
        _activeSessionStudents =
            _toInt(body['total_students']) ?? _studentsInClassesCount;
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
      builder:
          (ctx) => StatefulBuilder(
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
                      items:
                          [15, 30, 45, 60, 90]
                              .map(
                                (m) => DropdownMenuItem(
                                  value: m,
                                  child: Text('$m min'),
                                ),
                              )
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
          NotificationBridge.showSnackBar(
            const SnackBar(content: Text('Session extended.')),
          );
          await _loadDashboard(student);
        }
      } else if (mounted) {
        final msg =
            data is Map
                ? (data['message']?.toString() ?? '')
                : ApiService.messageFromHttpResponse(res);
        NotificationBridge.showSnackBar(
          SnackBar(
            content: Text(msg.isEmpty ? 'Could not extend session' : msg),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        NotificationBridge.showSnackBar(SnackBar(content: Text('Error: $e')));
      }
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
    if (!_logoutAllowed) {
      final msg =
          _logoutLockHint ??
          'This account stays signed in on this device for the current period.';
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder:
            (_) => AlertDialog(
              title: const Text('Sign out not available yet'),
              content: Text(msg),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('OK'),
                ),
              ],
            ),
      );
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
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
    final headerColor = colorScheme.primaryContainer.withValues(alpha: 0.45);

    return Drawer(
      child: AppDrawerShell(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              StudentDrawerHeader(student: s, decorationColor: headerColor),
              ListTile(
                leading: const Icon(Icons.event_note_rounded),
                title: const Text('Session management'),
                subtitle: const Text('Open, QR & close attendance'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  _openSessions();
                },
              ),
              ListTile(
                leading: const Icon(Icons.fact_check_outlined),
                title: const Text('Attendance Records'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  final sid = _activeSessionDetail?['id'];
                  final int? id =
                      sid is int
                          ? sid
                          : sid is num
                          ? sid.toInt()
                          : int.tryParse(sid?.toString() ?? '');
                  Navigator.of(
                    context,
                  ).pushNamed('/attendance-records', arguments: id);
                },
              ),
              ListTile(
                leading: const Icon(Icons.groups_outlined),
                title: const Text('Class List'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(context).pushNamed('/class-rep/students');
                },
              ),
              ListTile(
                leading: Badge(
                  isLabelVisible: _flaggedStudents.isNotEmpty,
                  label: Text(
                    _flaggedStudents.length > 99
                        ? '99+'
                        : '${_flaggedStudents.length}',
                    style: const TextStyle(fontSize: 10),
                  ),
                  child: const Icon(Icons.flag_outlined),
                ),
                title: const Text('Flagged students'),
                subtitle: const Text('3+ consecutive misses'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(context).pushNamed('/class-rep/flagged');
                },
              ),
              ListTile(
                leading: const Icon(Icons.insights_outlined),
                title: const Text('Insights'),
                subtitle: const Text('Attendance trends & summary'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(context).pushNamed('/class-rep/insights');
                },
              ),
              ListTile(
                leading: const Icon(Icons.person_outline),
                title: const Text('Profile'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(
                    context,
                  ).pushNamed('/profile').then((_) => _loadStudent());
                },
              ),
              ListTile(
                leading: const Icon(Icons.calendar_month_rounded),
                title: const Text('Timetable'),
                subtitle: const Text('Weekly class schedule'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(
                    context,
                  ).pushNamed('/timetable').then((_) => _loadStudent());
                },
              ),
              ListTile(
                leading: const Icon(Icons.settings_outlined),
                title: const Text('Settings'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(context).pushNamed('/settings');
                },
              ),
              const Divider(),
              ListTile(
                enabled: _logoutAllowed,
                leading: Icon(
                  Icons.logout,
                  color:
                      _logoutAllowed
                          ? colorScheme.error
                          : colorScheme.onSurfaceVariant,
                ),
                title: Text(
                  'Log out',
                  style: TextStyle(
                    color:
                        _logoutAllowed
                            ? colorScheme.error
                            : colorScheme.onSurfaceVariant,
                  ),
                ),
                subtitle:
                    _logoutLockHint != null && !_logoutAllowed
                        ? Text(
                          _logoutLockHint!,
                          style: TextStyle(
                            fontSize: 11,
                            color: colorScheme.onSurfaceVariant,
                          ),
                        )
                        : null,
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
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
    WidgetsBinding.instance.removeObserver(this);
    _countdownTimer?.cancel();
    super.dispose();
  }

  String _repGreetingName(Student s) {
    final f = s.firstName?.trim();
    if (f != null && f.isNotEmpty) return f;
    final parts = s.name.trim().split(RegExp(r'\s+'));
    return parts.isNotEmpty ? parts.first : s.name;
  }

  ({double? avg, double? latest, int weeks}) _trendStats() {
    if (_attendanceTrend.isEmpty) {
      return (avg: null, latest: null, weeks: 0);
    }
    final rates = <double>[];
    for (final p in _attendanceTrend) {
      final r = p['rate'];
      rates.add(r is num ? r.toDouble() : double.tryParse('$r') ?? 0);
    }
    if (rates.isEmpty) {
      return (avg: null, latest: null, weeks: 0);
    }
    final sum = rates.fold<double>(0, (a, b) => a + b);
    return (avg: sum / rates.length, latest: rates.last, weeks: rates.length);
  }

  void _openSearchTarget() {
    Navigator.of(context).pushNamed('/class-rep/students');
  }

  void _onNotificationTap() {
    if (_flaggedStudents.isNotEmpty) {
      Navigator.of(context).pushNamed('/class-rep/flagged');
    } else {
      Navigator.of(context).pushNamed('/class-rep/insights');
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = _student;
    if (s == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (ApiService.repDashboardTheme ==
        ApiService.repDashboardThemePastelAnalytics) {
      return _buildPastelAnalyticsHome(context, s);
    }
    if (ApiService.repDashboardTheme ==
        ApiService.repDashboardThemeVioletCalendar) {
      return _buildVioletCalendarRepHome(context, s);
    }
    if (ApiService.repDashboardTheme ==
        ApiService.repDashboardThemeMidnightControl) {
      return _buildMidnightControlRepHome(context, s);
    }
    final useNoir =
        ApiService.repDashboardTheme == ApiService.repDashboardThemeNoirTask;
    final useTeamReach =
        ApiService.repDashboardTheme == ApiService.repDashboardThemeTeamReach;

    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final handheld =
        !kIsWeb &&
        (defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS);
    final hPad = handheld ? 16.0 : 20.0;
    final pageBg =
        useNoir
            ? (isDark ? const Color(0xFF0D0F14) : const Color(0xFF101521))
            : useTeamReach
            ? (isDark ? const Color(0xFF101726) : const Color(0xFFF1F6FF))
            : (isDark ? const Color(0xFF121212) : const Color(0xFFF5F5F7));
    final cardBg =
        useNoir
            ? (isDark ? const Color(0xFF171B24) : const Color(0xFF1B2333))
            : useTeamReach
            ? (isDark ? const Color(0xFF1A253A) : Colors.white)
            : (isDark ? const Color(0xFF1E1E1E) : Colors.white);
    final cardBorder =
        useNoir
            ? const Color(0xFF2A3347)
            : (isDark ? const Color(0xFF333333) : const Color(0xFFE4E4E7));
    final textPrimary =
        useNoir
            ? Colors.white
            : (isDark ? Colors.white : const Color(0xFF18181B));
    final textMuted =
        useNoir
            ? const Color(0xFFB5BDCB)
            : (isDark ? const Color(0xFFB0B0B0) : const Color(0xFF52525B));

    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: pageBg,
      drawer: _buildDrawer(context, s),
      body: SafeArea(
        child: ModernPullToRefresh(
          showIndicator: false,
          playChime: false,
          onRefresh: () => _loadDashboard(s),
          child: CustomScrollView(
            physics: modernPullToRefreshPhysics,
            slivers: [
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, handheld ? 4 : 8, hPad, 0),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (_dashError != null) ...[
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: cs.errorContainer.withValues(alpha: 0.4),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: cs.error.withValues(alpha: 0.35),
                            ),
                          ),
                          child: Text(
                            _dashError!,
                            style: tt.bodyMedium?.copyWith(color: cs.onSurface),
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          IconButton(
                            icon: Icon(Icons.menu_rounded, color: textPrimary),
                            onPressed:
                                () => _scaffoldKey.currentState?.openDrawer(),
                            tooltip: 'Menu',
                          ),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Hello, ${_repGreetingName(s)}!',
                                    style: tt.headlineSmall?.copyWith(
                                      fontWeight: FontWeight.w800,
                                      color: textPrimary,
                                      letterSpacing: -0.2,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    "Let's keep attendance on track.",
                                    style: tt.bodyMedium?.copyWith(
                                      color: textMuted,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          Material(
                            color:
                                useNoir
                                    ? const Color(0xFF232C3D)
                                    : (isDark
                                        ? const Color(0xFF2C2C2C)
                                        : const Color(0xFFF4F4F5)),
                            shape: const CircleBorder(),
                            child: InkWell(
                              customBorder: const CircleBorder(),
                              onTap: _onNotificationTap,
                              child: SizedBox(
                                width: 46,
                                height: 46,
                                child: Stack(
                                  clipBehavior: Clip.none,
                                  alignment: Alignment.center,
                                  children: [
                                    Icon(
                                      Icons.notifications_outlined,
                                      color: textPrimary,
                                      size: 22,
                                    ),
                                    if (_flaggedStudents.isNotEmpty)
                                      Positioned(
                                        right: 8,
                                        top: 8,
                                        child: Container(
                                          width: 8,
                                          height: 8,
                                          decoration: const BoxDecoration(
                                            color: Color(0xFFEF4444),
                                            shape: BoxShape.circle,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      _RepSearchPill(isDark: isDark, onTap: _openSearchTarget),
                      const SizedBox(height: 20),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 0, hPad, 12),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      LayoutBuilder(
                        builder: (context, constraints) {
                          const spacing = 12.0;
                          const cols = 2;
                          const rowH = 84.0;
                          final w = constraints.maxWidth;
                          final cellW = (w - spacing) / cols;
                          final aspect = cellW / rowH;
                          return GridView.count(
                            crossAxisCount: cols,
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            mainAxisSpacing: spacing,
                            crossAxisSpacing: spacing,
                            childAspectRatio: aspect,
                            children: [
                              _RepCategoryStatCard(
                                iconBg: const Color(0xFFEF4444),
                                icon: Icons.bolt_rounded,
                                label: 'Active session',
                                value: _hasActiveSession ? 'Live' : 'None',
                                cardBg: cardBg,
                                borderColor: cardBorder,
                                textPrimary: textPrimary,
                                textMuted: textMuted,
                                isDark: isDark,
                              ),
                              _RepCategoryStatCard(
                                iconBg: const Color(0xFF3B82F6),
                                icon: Icons.groups_outlined,
                                label: 'Students',
                                value: '$_studentsInClassesCount',
                                cardBg: cardBg,
                                borderColor: cardBorder,
                                textPrimary: textPrimary,
                                textMuted: textMuted,
                                isDark: isDark,
                              ),
                              _RepCategoryStatCard(
                                iconBg: const Color(0xFF22C55E),
                                icon: Icons.fact_check_outlined,
                                label: 'Marked today',
                                value: '$_attendanceTodayCount',
                                cardBg: cardBg,
                                borderColor: cardBorder,
                                textPrimary: textPrimary,
                                textMuted: textMuted,
                                isDark: isDark,
                              ),
                              _RepCategoryStatCard(
                                iconBg: const Color(0xFFEAB308),
                                icon: Icons.layers_outlined,
                                label: 'Open sessions',
                                value: '$_activeSessionsCount',
                                cardBg: cardBg,
                                borderColor: cardBorder,
                                textPrimary: textPrimary,
                                textMuted: textMuted,
                                isDark: isDark,
                              ),
                            ],
                          );
                        },
                      ),
                      const SizedBox(height: 16),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.tonalIcon(
                          onPressed: _openSessions,
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(52),
                            padding: const EdgeInsets.symmetric(horizontal: 18),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          icon: const Icon(Icons.event_note_outlined),
                          label: Text(
                            handheld
                                ? (_hasActiveSession
                                    ? 'Sessions ($_activeSessionsCount)'
                                    : 'Sessions')
                                : (_hasActiveSession
                                    ? 'Manage sessions ($_activeSessionsCount active)'
                                    : 'Open or close session'),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 8, hPad, 12),
                sliver: SliverToBoxAdapter(
                  child: _RepTrendHeroCard(
                    points: _attendanceTrend,
                    stats: _trendStats(),
                    isDark: isDark,
                    cardBg: cardBg,
                    borderColor: cardBorder,
                    textPrimary: textPrimary,
                    textMuted: textMuted,
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 0, hPad, 24),
                sliver: SliverToBoxAdapter(
                  child: _buildActiveSessionLiveCard(
                    context,
                    cardBg: cardBg,
                    borderColor: cardBorder,
                    isDark: isDark,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
      floatingActionButton:
          useTeamReach
              ? FloatingActionButton(
                onPressed: _openSessions,
                backgroundColor: const Color(0xFF1F6CFF),
                foregroundColor: Colors.white,
                child: const Icon(Icons.add_rounded, size: 30),
              )
              : null,
    );
  }

  Widget _buildActiveSessionLiveCard(
    BuildContext context, {
    required Color cardBg,
    required Color borderColor,
    required bool isDark,
  }) {
    if (!_hasActiveSession || _activeSessionDetail == null) {
      return const SizedBox.shrink();
    }
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final session = _activeSessionDetail!;
    final courseName = session['course_name']?.toString() ?? 'Active session';
    final courseCode = session['course_code']?.toString().trim() ?? '';
    final titleLine =
        courseCode.isNotEmpty ? '$courseName · $courseCode' : courseName;
    final total =
        _activeSessionStudents > 0
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

    final onSurface = cs.onSurface;
    final muted = cs.onSurfaceVariant;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: borderColor),
        boxShadow:
            isDark
                ? null
                : [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                decoration: BoxDecoration(
                  color: cs.error.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.circle, size: 7, color: cs.error),
                    const SizedBox(width: 5),
                    Text(
                      'LIVE',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.85,
                        color: cs.error,
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              Text(
                'Marking window',
                style: tt.labelSmall?.copyWith(
                  fontSize: 11,
                  color: muted,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            titleLine,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: tt.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
              fontSize: 14,
              height: 1.2,
              color: onSurface,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                timeLabel,
                style: tt.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  fontFeatures: const [FontFeature.tabularFigures()],
                  color: cs.primary,
                  letterSpacing: -0.5,
                  fontSize: 26,
                  height: 1.0,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text(
                    _sessionEndsAt != null
                        ? 'remaining until session ends'
                        : 'End time not set — pull to refresh',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: tt.labelSmall?.copyWith(
                      fontSize: 11,
                      color: muted,
                      height: 1.25,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.how_to_reg_rounded, size: 18, color: cs.primary),
              const SizedBox(width: 6),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    style: tt.labelLarge?.copyWith(
                      fontSize: 11.5,
                      height: 1.25,
                      color: cs.onSurfaceVariant,
                    ),
                    children: [
                      TextSpan(
                        text: '$present',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: onSurface,
                        ),
                      ),
                      TextSpan(
                        text: ' live now · ',
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: cs.onSurfaceVariant,
                        ),
                      ),
                      TextSpan(
                        text: '$total in class',
                        style: TextStyle(
                          fontWeight: FontWeight.w500,
                          color: muted,
                        ),
                      ),
                    ],
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 40,
            child: FilledButton.icon(
              onPressed: _extendActiveSession,
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                textStyle: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              icon: const Icon(Icons.more_time_rounded, size: 18),
              label: const Text('Extend'),
            ),
          ),
        ],
      ),
    );
  }

  static const List<String> _repDiaryPillLabels = [
    'Today',
    'Yesterday',
    'Week',
    'Month',
  ];

  Widget _buildPastelAnalyticsHome(BuildContext context, Student s) {
    final tt = Theme.of(context).textTheme;
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final handheld =
        !kIsWeb &&
        (defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS);
    final hPad = handheld ? 16.0 : 20.0;
    final pageBg = isDark ? const Color(0xFF121212) : const Color(0xFFF5F3F8);
    final cardOnPastel = isDark ? const Color(0xFF1E1E1E) : Colors.white;
    final softBorder =
        isDark ? const Color(0xFF333333) : const Color(0xFFE8E0F0);
    final textPrimary = isDark ? Colors.white : const Color(0xFF18181B);
    final textMuted =
        isDark ? const Color(0xFFB0B0B0) : const Color(0xFF52525B);
    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: pageBg,
      drawer: _buildDrawer(context, s),
      body: SafeArea(
        child: ModernPullToRefresh(
          showIndicator: false,
          playChime: false,
          onRefresh: () => _loadDashboard(s),
          child: CustomScrollView(
            physics: modernPullToRefreshPhysics,
            slivers: [
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, handheld ? 8 : 12, hPad, 0),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (_dashError != null) ...[
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: cs.errorContainer.withValues(alpha: 0.4),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: cs.error.withValues(alpha: 0.35),
                            ),
                          ),
                          child: Text(
                            _dashError!,
                            style: tt.bodyMedium?.copyWith(color: cs.onSurface),
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                      Row(
                        children: [
                          IconButton(
                            icon: Icon(Icons.menu_rounded, color: textPrimary),
                            onPressed:
                                () => _scaffoldKey.currentState?.openDrawer(),
                            tooltip: 'Menu',
                          ),
                          Expanded(
                            child: Text(
                              'at-enda',
                              style: tt.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                                color: textPrimary,
                                letterSpacing: -0.2,
                              ),
                            ),
                          ),
                          Material(
                            color:
                                isDark ? const Color(0xFF2C2C2C) : Colors.white,
                            shape: const CircleBorder(),
                            elevation: isDark ? 0 : 1,
                            shadowColor: Colors.black.withValues(alpha: 0.06),
                            child: InkWell(
                              customBorder: const CircleBorder(),
                              onTap: _onNotificationTap,
                              child: SizedBox(
                                width: 46,
                                height: 46,
                                child: Stack(
                                  alignment: Alignment.center,
                                  children: [
                                    Icon(
                                      Icons.more_horiz_rounded,
                                      color: textPrimary,
                                      size: 22,
                                    ),
                                    if (_flaggedStudents.isNotEmpty)
                                      Positioned(
                                        right: 10,
                                        top: 10,
                                        child: Container(
                                          width: 8,
                                          height: 8,
                                          decoration: const BoxDecoration(
                                            color: Color(0xFFEF4444),
                                            shape: BoxShape.circle,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Overview',
                        style: tt.headlineMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                          color: textPrimary,
                          letterSpacing: -0.6,
                        ),
                      ),
                      const SizedBox(height: 18),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 0, hPad, 12),
                sliver: SliverToBoxAdapter(
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      const spacing = 14.0;
                      const cols = 2;
                      final w = constraints.maxWidth;
                      final cellW = (w - spacing) / cols;
                      const tileH = 132.0;
                      final aspect = cellW / tileH;
                      return GridView.count(
                        crossAxisCount: cols,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        mainAxisSpacing: spacing,
                        crossAxisSpacing: spacing,
                        childAspectRatio: aspect,
                        children: [
                          _PastelRepMetricCard(
                            title: 'Sessions',
                            value: _hasActiveSession ? 'Live' : 'None',
                            subValue:
                                _hasActiveSession
                                    ? '$_activeSessionsCount open'
                                    : 'No active window',
                            accent: const Color(0xFFC4D0F5),
                            isDark: isDark,
                            textPrimary: textPrimary,
                            textMuted: textMuted,
                            footer:
                                _attendanceTrend.isEmpty
                                    ? null
                                    : ClipRRect(
                                      borderRadius: BorderRadius.circular(10),
                                      child: SizedBox(
                                        height: 72,
                                        child: AttendanceTrendChart(
                                          points: _attendanceTrend,
                                          title: '',
                                          height: 48,
                                          compact: true,
                                          onGradientBackground: true,
                                        ),
                                      ),
                                    ),
                          ),
                          _PastelRepMetricCard(
                            title: 'Students',
                            value: '$_studentsInClassesCount',
                            subValue: 'In your classes',
                            accent: const Color(0xFFFFD4A8),
                            isDark: isDark,
                            textPrimary: textPrimary,
                            textMuted: textMuted,
                            pattern: _PastelCardPattern.wave,
                          ),
                          _PastelRepMetricCard(
                            title: 'Marked today',
                            value: '$_attendanceTodayCount',
                            subValue: 'Check-ins recorded',
                            accent: const Color(0xFFE8D4EF),
                            isDark: isDark,
                            textPrimary: textPrimary,
                            textMuted: textMuted,
                            pattern: _PastelCardPattern.dots,
                          ),
                          _PastelRepMetricCard(
                            title: 'Open windows',
                            value: '$_activeSessionsCount',
                            subValue: 'Sessions you can run',
                            accent: const Color(0xFFFFC9C0),
                            isDark: isDark,
                            textPrimary: textPrimary,
                            textMuted: textMuted,
                            pattern: _PastelCardPattern.rings,
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 8, hPad, 8),
                sliver: SliverToBoxAdapter(
                  child: SizedBox(
                    width: double.infinity,
                    child: FilledButton.tonalIcon(
                      onPressed: _openSessions,
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(52),
                        padding: const EdgeInsets.symmetric(horizontal: 18),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(22),
                        ),
                      ),
                      icon: const Icon(Icons.event_note_outlined),
                      label: const Text('Session management'),
                    ),
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 8, hPad, 10),
                sliver: SliverToBoxAdapter(
                  child: Text(
                    'Class diary',
                    style: tt.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: textPrimary,
                    ),
                  ),
                ),
              ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 0, hPad, 14),
                sliver: SliverToBoxAdapter(
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: List.generate(_repDiaryPillLabels.length, (i) {
                        final on = i == _repDiaryPill;
                        return Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: Material(
                            color:
                                on
                                    ? (isDark
                                        ? const Color(0xFF2C2C2C)
                                        : Colors.white)
                                    : Colors.transparent,
                            borderRadius: BorderRadius.circular(24),
                            elevation: on && !isDark ? 2 : 0,
                            shadowColor: Colors.black.withValues(alpha: 0.08),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(24),
                              onTap: () => setState(() => _repDiaryPill = i),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 18,
                                  vertical: 10,
                                ),
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(24),
                                  border: Border.all(
                                    color:
                                        on
                                            ? softBorder
                                            : softBorder.withValues(alpha: 0.5),
                                  ),
                                ),
                                child: Text(
                                  _repDiaryPillLabels[i],
                                  style: tt.labelLarge?.copyWith(
                                    fontWeight:
                                        on ? FontWeight.w800 : FontWeight.w600,
                                    color: textPrimary,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                    ),
                  ),
                ),
              ),
              if (_flaggedStudents.isEmpty)
                SliverPadding(
                  padding: EdgeInsets.fromLTRB(hPad, 0, hPad, 12),
                  sliver: SliverToBoxAdapter(
                    child: Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: cardOnPastel,
                        borderRadius: BorderRadius.circular(28),
                        border: Border.all(color: softBorder),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.check_circle_outline,
                            color: textMuted,
                            size: 28,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              'No flagged students right now.',
                              style: tt.bodyMedium?.copyWith(color: textMuted),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: EdgeInsets.fromLTRB(hPad, 0, hPad, 12),
                  sliver: SliverList.separated(
                    itemCount: _flaggedStudents.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final row = _flaggedStudents[i];
                      final name = row['name']?.toString() ?? '—';
                      final course = row['course_name']?.toString() ?? '';
                      final missed = row['consecutive_missed'];
                      final missStr =
                          missed is num
                              ? missed.round().toString()
                              : (missed?.toString() ?? '—');
                      return Material(
                        color: cardOnPastel,
                        borderRadius: BorderRadius.circular(28),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(28),
                          onTap:
                              () => Navigator.of(
                                context,
                              ).pushNamed('/class-rep/flagged'),
                          child: Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(28),
                              border: Border.all(color: softBorder),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: const Color(
                                      0xFFC4D0F5,
                                    ).withValues(alpha: 0.55),
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(
                                    Icons.more_horiz,
                                    color: textPrimary,
                                    size: 20,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        name,
                                        style: tt.titleSmall?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          color: textPrimary,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        course.isNotEmpty ? course : 'Flagged',
                                        style: tt.bodySmall?.copyWith(
                                          color: textMuted,
                                        ),
                                      ),
                                      const SizedBox(height: 6),
                                      Text(
                                        '$missStr consecutive missed',
                                        style: tt.labelMedium?.copyWith(
                                          fontWeight: FontWeight.w700,
                                          color: const Color(0xFF5B7FD9),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              SliverPadding(
                padding: EdgeInsets.fromLTRB(hPad, 4, hPad, 28),
                sliver: SliverToBoxAdapter(
                  child: _buildActiveSessionLiveCard(
                    context,
                    cardBg: cardOnPastel,
                    borderColor: softBorder,
                    isDark: isDark,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildVioletCalendarRepHome(BuildContext context, Student s) {
    final tt = Theme.of(context).textTheme;
    final cs = Theme.of(context).colorScheme;
    const text = Colors.white;
    const muted = Color(0xFFC8C4F0);
    const card = Color(0xFF3D3485);
    const border = Color(0xFF5248A3);
    const cyan = Color(0xFF5DD5F5);
    final rem =
        _sessionEndsAt != null
            ? _formatCountdown(_sessionEndsAt!.difference(DateTime.now()))
            : '--:--';
    final topPad = MediaQuery.paddingOf(context).top > 0 ? 6.0 : 10.0;

    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: const Color(0xFF12101F),
      drawer: _buildDrawer(context, s),
      body: ColoredBox(
        color: const Color(0xFF4334C4),
        child: SafeArea(
          child: ModernPullToRefresh(
            showIndicator: false,
            playChime: false,
            onRefresh: () => _loadDashboard(s),
            child: CustomScrollView(
              physics: modernPullToRefreshPhysics,
              slivers: [
                SliverToBoxAdapter(
                  child: Container(
                    color: const Color(0xFF4334C4),
                    padding: EdgeInsets.fromLTRB(8, topPad, 10, 20),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            IconButton(
                              onPressed:
                                  () => _scaffoldKey.currentState?.openDrawer(),
                              icon: const Icon(
                                Icons.menu_rounded,
                                color: Colors.white,
                              ),
                            ),
                            Expanded(
                              child: Text(
                                'Hi ${s.greetingLastName}',
                                style: tt.titleLarge?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.3,
                                ),
                              ),
                            ),
                            IconButton(
                              onPressed: _onNotificationTap,
                              icon: const Icon(
                                Icons.notifications_outlined,
                                color: Colors.white,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Container(
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(22),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.12),
                                blurRadius: 20,
                                offset: const Offset(0, 8),
                              ),
                            ],
                          ),
                          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Active session',
                                style: tt.titleSmall?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                _hasActiveSession ? 'Session timer' : 'Status',
                                style: tt.labelSmall?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.72),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                rem,
                                style: tt.headlineSmall?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _hasActiveSession
                                    ? 'Session is live now'
                                    : 'No session running right now',
                                style: tt.bodySmall?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.82),
                                ),
                              ),
                              const SizedBox(height: 12),
                              SizedBox(
                                width: double.infinity,
                                child: FilledButton.icon(
                                  onPressed: _openSessions,
                                  icon: const Icon(
                                    Icons.event_note_outlined,
                                    size: 20,
                                    color: Color(0xFF0D1B2A),
                                  ),
                                  label: const Text(
                                    'Session management',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF0D1B2A),
                                    ),
                                  ),
                                  style: FilledButton.styleFrom(
                                    backgroundColor: cyan,
                                    padding: const EdgeInsets.symmetric(
                                      vertical: 14,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                    elevation: 0,
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
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 20),
                  sliver: SliverToBoxAdapter(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Overview',
                          style: tt.titleSmall?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: _violetStatCard(
                                'Students',
                                '$_studentsInClassesCount',
                                card,
                                border,
                                text,
                                muted,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _violetStatCard(
                                'Marked today',
                                '$_attendanceTodayCount',
                                card,
                                border,
                                text,
                                muted,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: _violetStatCard(
                                'Open sessions',
                                '$_activeSessionsCount',
                                card,
                                border,
                                text,
                                muted,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _violetStatCard(
                                'Flagged',
                                '${_flaggedStudents.length}',
                                card,
                                border,
                                text,
                                muted,
                              ),
                            ),
                          ],
                        ),
                        if (_dashError != null) ...[
                          const SizedBox(height: 10),
                          Text(_dashError!, style: TextStyle(color: cs.error)),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildMidnightControlRepHome(BuildContext context, Student s) {
    final tt = Theme.of(context).textTheme;
    final rem =
        _sessionEndsAt != null
            ? _formatCountdown(_sessionEndsAt!.difference(DateTime.now()))
            : '--:--';
    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: const Color(0xFF2D2F34),
      drawer: _buildDrawer(context, s),
      body: SafeArea(
        child: ModernPullToRefresh(
          showIndicator: false,
          playChime: false,
          onRefresh: () => _loadDashboard(s),
          child: CustomScrollView(
            physics: modernPullToRefreshPhysics,
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          IconButton(
                            onPressed:
                                () => _scaffoldKey.currentState?.openDrawer(),
                            icon: const Icon(
                              Icons.menu_rounded,
                              color: Colors.white,
                            ),
                          ),
                          Expanded(
                            child: Text(
                              'Rep home',
                              style: tt.titleLarge?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          IconButton(
                            onPressed: _onNotificationTap,
                            icon: const Icon(
                              Icons.notifications_none_rounded,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      ),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFF24262B),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF454A53)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Session timer',
                              style: TextStyle(
                                color: Color(0xFFBFC5CF),
                                fontSize: 12,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              rem,
                              style: tt.headlineMedium?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 10),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton.icon(
                                onPressed: _openSessions,
                                icon: const Icon(
                                  Icons.event_note_outlined,
                                  size: 18,
                                ),
                                label: const Text('Session management'),
                                style: FilledButton.styleFrom(
                                  backgroundColor: const Color(0xFF06B6D4),
                                  foregroundColor: const Color(0xFF0B1A23),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
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
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 1.55,
                  ),
                  delegate: SliverChildListDelegate.fixed([
                    _midnightRepCard(
                      'Students',
                      '$_studentsInClassesCount',
                      Icons.groups_outlined,
                      const Color(0xFF06B6D4),
                    ),
                    _midnightRepCard(
                      'Marked',
                      '$_attendanceTodayCount',
                      Icons.task_alt_rounded,
                      const Color(0xFF22C55E),
                    ),
                    _midnightRepCard(
                      'Live',
                      _hasActiveSession ? 'Yes' : 'No',
                      Icons.bolt_rounded,
                      const Color(0xFFF97316),
                    ),
                    _midnightRepCard(
                      'Flagged',
                      '${_flaggedStudents.length}',
                      Icons.flag_outlined,
                      const Color(0xFFEAB308),
                    ),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _midnightRepCard(
    String label,
    String value,
    IconData icon,
    Color accent,
  ) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF24262B),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF454A53)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          CircleAvatar(
            radius: 15,
            backgroundColor: accent.withValues(alpha: 0.2),
            child: Icon(icon, color: accent, size: 16),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: Color(0xFFBFC5CF),
                    fontSize: 11,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  value,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 17,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _violetStatCard(
    String label,
    String value,
    Color card,
    Color border,
    Color text,
    Color muted,
  ) {
    return Container(
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border.withValues(alpha: 0.5)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.18),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text(
            value,
            style: TextStyle(
              color: text,
              fontWeight: FontWeight.w800,
              fontSize: 22,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: muted,
              fontSize: 11,
              fontWeight: FontWeight.w600,
              height: 1.2,
            ),
          ),
        ],
      ),
    );
  }
}

enum _PastelCardPattern { none, dots, wave, rings }

/// Rounded pastel tile for rep “analytics” theme.
class _PastelRepMetricCard extends StatelessWidget {
  const _PastelRepMetricCard({
    required this.title,
    required this.value,
    required this.subValue,
    required this.accent,
    required this.isDark,
    required this.textPrimary,
    required this.textMuted,
    this.footer,
    this.pattern = _PastelCardPattern.none,
  });

  final String title;
  final String value;
  final String subValue;
  final Color accent;
  final bool isDark;
  final Color textPrimary;
  final Color textMuted;
  final Widget? footer;
  final _PastelCardPattern pattern;

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    final fill = isDark ? Color.lerp(accent, Colors.black, 0.72)! : accent;
    return ClipRRect(
      borderRadius: BorderRadius.circular(32),
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    fill,
                    Color.lerp(fill, Colors.white, isDark ? 0.08 : 0.35)!,
                  ],
                ),
              ),
            ),
          ),
          if (pattern != _PastelCardPattern.none)
            Positioned.fill(
              child: CustomPaint(
                painter: _PastelTexturePainter(pattern, isDark),
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 14, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: tt.labelMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: textPrimary.withValues(alpha: 0.85),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  value,
                  style: tt.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                    color: textPrimary,
                    fontSize: 22,
                    height: 1.05,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subValue,
                  style: tt.bodySmall?.copyWith(
                    color: textMuted,
                    fontWeight: FontWeight.w500,
                    height: 1.25,
                  ),
                ),
                if (footer != null) ...[const Spacer(), footer!],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PastelTexturePainter extends CustomPainter {
  _PastelTexturePainter(this.pattern, this.isDark);

  final _PastelCardPattern pattern;
  final bool isDark;

  @override
  void paint(Canvas canvas, Size size) {
    final p =
        Paint()..color = Colors.white.withValues(alpha: isDark ? 0.06 : 0.35);
    switch (pattern) {
      case _PastelCardPattern.dots:
        for (var y = 0.0; y < size.height; y += 14) {
          for (var x = 0.0; x < size.width; x += 14) {
            canvas.drawCircle(Offset(x + (y % 28) * 0.5, y), 2, p);
          }
        }
        break;
      case _PastelCardPattern.wave:
        final path = Path();
        for (var i = 0; i < 4; i++) {
          path.moveTo(0, size.height * 0.35 + i * 12);
          path.quadraticBezierTo(
            size.width * 0.5,
            size.height * 0.2 + i * 14,
            size.width,
            size.height * 0.4 + i * 10,
          );
        }
        p.style = PaintingStyle.stroke;
        p.strokeWidth = 2;
        canvas.drawPath(path, p);
        break;
      case _PastelCardPattern.rings:
        for (var i = 0; i < 3; i++) {
          canvas.drawCircle(
            Offset(size.width * 0.85, size.height * 0.15),
            12.0 + i * 16,
            p,
          );
        }
        break;
      case _PastelCardPattern.none:
        break;
    }
  }

  @override
  bool shouldRepaint(covariant _PastelTexturePainter oldDelegate) =>
      oldDelegate.pattern != pattern || oldDelegate.isDark != isDark;
}

/// Pill search row (mock parity); opens class list on tap.
class _RepSearchPill extends StatelessWidget {
  const _RepSearchPill({required this.isDark, required this.onTap});

  final bool isDark;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fill = isDark ? const Color(0xFF2C2C2C) : const Color(0xFFEBEBEB);
    final iconColor =
        isDark ? const Color(0xFF9CA3AF) : const Color(0xFF71717A);
    return Material(
      color: fill,
      borderRadius: BorderRadius.circular(28),
      child: InkWell(
        borderRadius: BorderRadius.circular(28),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          child: Row(
            children: [
              Icon(Icons.search_rounded, color: iconColor, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Search class list',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: iconColor,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
              Icon(Icons.mic_none_rounded, color: iconColor, size: 22),
            ],
          ),
        ),
      ),
    );
  }
}

/// Small bordered card: colored icon tile + label + value (light/dark mock).
class _RepCategoryStatCard extends StatelessWidget {
  const _RepCategoryStatCard({
    required this.iconBg,
    required this.icon,
    required this.label,
    required this.value,
    required this.cardBg,
    required this.borderColor,
    required this.textPrimary,
    required this.textMuted,
    required this.isDark,
  });

  final Color iconBg;
  final IconData icon;
  final String label;
  final String value;
  final Color cardBg;
  final Color borderColor;
  final Color textPrimary;
  final Color textMuted;
  final bool isDark;

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    return Container(
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
        boxShadow:
            isDark
                ? null
                : [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: iconBg,
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: Icon(icon, color: Colors.white, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: tt.labelMedium?.copyWith(
                    color: textMuted,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: tt.titleMedium?.copyWith(
                    color: textPrimary,
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Large dashboard card: trend chart + footer summary rows (no gradient).
class _RepTrendHeroCard extends StatelessWidget {
  const _RepTrendHeroCard({
    required this.points,
    required this.stats,
    required this.isDark,
    required this.cardBg,
    required this.borderColor,
    required this.textPrimary,
    required this.textMuted,
  });

  final List<Map<String, dynamic>> points;
  final ({double? avg, double? latest, int weeks}) stats;
  final bool isDark;
  final Color cardBg;
  final Color borderColor;
  final Color textPrimary;
  final Color textMuted;

  static String _signature(List<Map<String, dynamic>> p) {
    if (p.isEmpty) return 'empty';
    return p.map((e) => '${e['rate']}_${e['label']}').join('|');
  }

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;

    final weeksLine =
        stats.weeks > 0
            ? 'Weeks in chart: ${stats.weeks}'
            : 'Weeks in chart: —';
    final String rateLine;
    if (stats.latest != null && stats.avg != null) {
      rateLine =
          'Latest ${stats.latest!.toStringAsFixed(1)}% · avg ${stats.avg!.toStringAsFixed(1)}%';
    } else if (stats.latest != null) {
      rateLine = 'Latest week: ${stats.latest!.toStringAsFixed(1)}%';
    } else if (stats.avg != null) {
      rateLine = 'Average: ${stats.avg!.toStringAsFixed(1)}%';
    } else {
      rateLine = 'No rate data yet';
    }

    Widget footerRow(IconData i, String text) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          children: [
            Icon(i, size: 18, color: textMuted),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                text,
                style: tt.bodySmall?.copyWith(
                  color: textMuted,
                  fontWeight: FontWeight.w500,
                  fontSize: 13,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Attendance trends',
          style: tt.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
            color: textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        TweenAnimationBuilder<double>(
          key: ValueKey<String>(_signature(points)),
          tween: Tween(begin: 0, end: 1),
          duration: const Duration(milliseconds: 480),
          curve: Curves.easeOutCubic,
          builder: (context, t, child) {
            final e = Curves.easeOutCubic.transform(t);
            return Opacity(
              opacity: e,
              child: Transform.translate(
                offset: Offset(0, 10 * (1 - e)),
                child: child,
              ),
            );
          },
          child: Container(
            decoration: BoxDecoration(
              color: cardBg,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: borderColor),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.45 : 0.08),
                  blurRadius: 22,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(23),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
                    child: AttendanceTrendChart(
                      points: points,
                      title: 'Class attendance % (recent weeks)',
                      height: 128,
                      compact: true,
                      onGradientBackground: false,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Class attendance overview',
                          style: tt.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: textPrimary,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 12),
                        footerRow(Icons.play_circle_outline_rounded, weeksLine),
                        footerRow(Icons.pie_chart_outline_rounded, rateLine),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
