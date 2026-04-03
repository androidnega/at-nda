import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../widgets/app_drawer_shell.dart';
import '../widgets/course_book_icon.dart';
import '../widgets/modern_pull_to_refresh.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/last_attendance_prefs.dart';
import '../services/offline_service.dart';
import '../services/session_cache_prefs.dart';
import '../services/sync_service.dart';
import '../utils/absence_warning_format.dart';
import '../utils/connectivity_util.dart';
import '../utils/app_selectable_scope.dart';
import '../utils/constants.dart';
import '../utils/greeting_util.dart';
import '../widgets/profile_avatar.dart';
import '../widgets/dynamic_widget_renderer.dart';
import 'attendance_history_page.dart';
import 'attendance_page.dart';
import 'attendance_stats_page.dart';
import 'login_page.dart';
import 'rep_home_page.dart';
import 'sync_status_page.dart';

/// Attendance-focused home: primary session + actions; everything else in the drawer.
class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> with WidgetsBindingObserver {
  /// Active sessions from API `sessions` array (see [ApiService.getActiveSessions]).
  List<Map<String, dynamic>> _activeSessions = [];
  Student? _student;
  bool _isLoading = true;
  String? _error;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  /// Session ids marked today (SQLite + API `already_marked`).
  Set<int> _markedSessionIdsToday = {};
  Timer? _sessionUiTicker;
  /// Offline [attendance] queue rows not yet POSTed to the API.
  int _pendingSyncCount = 0;

  /// Backend-driven small UI blocks (optional).
  List<dynamic> _dynamicUi = const [];

  /// From last GET /sessions/active `warnings` (snapshot for UI).
  List<Map<String, dynamic>> _absenceWarningsSnapshot = [];
  bool _showAbsenceWarning = false;
  Timer? _absenceWarningAutoDismissTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _syncPendingOnStart();
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen(
      (results) {
        final hasConnection = results.any((r) =>
            r == ConnectivityResult.wifi ||
            r == ConnectivityResult.mobile ||
            r == ConnectivityResult.ethernet ||
            r == ConnectivityResult.vpn);
        if (hasConnection && mounted) {
          _syncPendingOnStart();
        }
      },
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sessionUiTicker?.cancel();
    _absenceWarningAutoDismissTimer?.cancel();
    _connectivitySubscription?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _load();
    }
  }

  /// Prefer `end_time` / `ends_at` (ISO8601). Fallback: now + `remaining_minutes`.
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

  void _restartSessionUiTicker() {
    _sessionUiTicker?.cancel();
    _sessionUiTicker = null;
    if (_activeSessions.isEmpty) return;
    _sessionUiTicker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      if (_activeSessions.isEmpty) {
        _sessionUiTicker?.cancel();
        _sessionUiTicker = null;
        return;
      }
      setState(() {});
    });
  }

  bool _sessionEndedFor(Map<String, dynamic> session) {
    final end = _parseSessionEndTime(session);
    if (end == null) return false;
    return DateTime.now().isAfter(end);
  }

  bool _isSessionMarked(Map<String, dynamic> session) {
    if (session['already_marked'] == true) return true;
    final id = _parseSessionId(session);
    if (id == null) return false;
    return _markedSessionIdsToday.contains(id);
  }

  String _timeLeftLabel(Map<String, dynamic> session) {
    if (_isSessionMarked(session)) return '—';
    final end = _parseSessionEndTime(session);
    if (end == null) return '--:--';
    final diff = end.difference(DateTime.now());
    if (diff.isNegative) return 'Session ended';
    return _formatDurationRemaining(diff);
  }

  int? _parseSessionId(Map<String, dynamic> session) {
    final id = session['id'];
    if (id == null) return null;
    if (id is int) return id;
    if (id is num) return id.toInt();
    return int.tryParse(id.toString());
  }

  Future<void> _syncPendingOnStart() async {
    await SyncService.syncAttendance();
    if (mounted) _load();
  }

  /// If login payload missed rep flags, `POST /api/rep/courses` confirms class rep access.
  Future<void> _syncClassRepFlagFromApi() async {
    final s = _student;
    if (s == null) return;
    if (!await OfflineService.hasPasswordOrApiToken()) return;
    try {
      final pwd = await OfflineService.getApiSessionPassword();
      final res = await ApiService.repCourses(
        indexNumber: s.indexNumber,
        password: pwd ?? '',
      );
      if (res.statusCode != 200) return;
      final raw = jsonDecode(res.body);
      if (raw is! Map) return;
      final data = Map<String, dynamic>.from(raw);
      final courses = data['courses'];
      final icr = data['is_class_rep'];
      final explicit = icr == true ||
          icr == 1 ||
          (icr != null && icr.toString().toLowerCase() == 'true');
      final hasCourses = courses is List && courses.isNotEmpty;
      if (!explicit && !hasCourses) return;
      if (!s.isClassRep && mounted) {
        final updated = s.copyWith(isClassRep: true);
        await OfflineService.setCurrentStudent(updated);
        setState(() => _student = updated);
      }
    } catch (_) {}
  }

  Future<void> _load() async {
    final online = await hasInternetConnectivity();
    _dynamicUi = const [];
    if (online) {
      await ApiService.loadAppSettings();
      _dynamicUi = ApiService.dynamicUi;
    }
    await SessionCachePrefs.clear();
    _sessionUiTicker?.cancel();
    _sessionUiTicker = null;
    setState(() {
      _isLoading = true;
      _error = null;
      _activeSessions = [];
      _markedSessionIdsToday = {};
    });

    try {
      _student = await OfflineService.getCurrentStudent();
    } catch (_) {
      _student = null;
    }

    if (online && _student != null) {
      await _syncClassRepFlagFromApi();
    }

    var pendingSync = 0;
    try {
      pendingSync = await OfflineService.getPendingAttendanceCount();
    } catch (_) {}
    if (mounted) {
      setState(() => _pendingSyncCount = pendingSync);
    }

    if (!online) {
      _absenceWarningAutoDismissTimer?.cancel();
      if (mounted) {
        setState(() {
          _activeSessions = [];
          _error = 'Offline — connect to the internet to load active sessions.';
          _showAbsenceWarning = false;
          _absenceWarningsSnapshot = [];
        });
      }
    } else {
      try {
        final sessions = await ApiService.getActiveSessions(
          indexNumber: _student?.indexNumber,
        );
        debugPrint('FULL RESPONSE: sessions count=${sessions.length}');
        for (var i = 0; i < sessions.length; i++) {
          debugPrint('CURRENT SESSION [$i]: id=${sessions[i]['id']} '
              'course=${sessions[i]['course_code'] ?? sessions[i]['course_name']}');
        }
        if (!mounted) return;
        if (sessions.isNotEmpty) {
          setState(() {
            _activeSessions = sessions;
            _error = null;
          });
        } else if (Constants.useDemoActiveSessionWhenEmpty) {
          final demo = Map<String, dynamic>.from(Constants.demoActiveSession);
          debugPrint('activeSession: demo list');
          setState(() {
            _activeSessions = [demo];
            _error = null;
          });
        } else {
          final apiErr = ApiService.lastActiveSessionErrorMessage;
          setState(() {
            _activeSessions = [];
            _error = apiErr.isNotEmpty ? apiErr : null;
          });
          if (apiErr.isNotEmpty) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (!mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(apiErr),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            });
          }
        }
      } catch (e) {
        // ignore: avoid_print
        print('SESSION ERROR: $e');
        if (!mounted) return;
        if (Constants.useDemoActiveSessionWhenEmpty) {
          final demo = Map<String, dynamic>.from(Constants.demoActiveSession);
          debugPrint('activeSession: $demo');
          setState(() {
            _activeSessions = [demo];
            _error = null;
          });
        } else {
          setState(() {
            _error = 'Cannot load session.';
            _activeSessions = [];
          });
        }
      }
    }

    if (online) {
      _syncAbsenceWarningsUi();
    }

    final marked = <int>{};
    if (_student != null) {
      for (final s in _activeSessions) {
        final sid = _parseSessionId(s);
        if (sid != null) {
          final done = await OfflineService.hasMarkedSessionToday(
            indexNumber: _student!.indexNumber,
            sessionId: sid,
          );
          if (done || s['already_marked'] == true) {
            marked.add(sid);
          }
        }
      }
    }
    if (!mounted) return;
    setState(() {
      _markedSessionIdsToday = marked;
    });
    _restartSessionUiTicker();

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _logout() async {
    await LastAttendancePrefs.clear();
    await OfflineService.clearCurrentStudent();
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => appSelectableScope(const LoginPage())),
        (_) => false,
      );
    }
  }

  Future<void> _confirmLogout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Log out'),
        content: const Text('Clear stored student and return to login?'),
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
    if (ok == true) _logout();
  }

  String _greetingDisplayName() {
    final s = _student;
    if (s == null) return 'there';
    final fl = '${s.firstName ?? ''} ${s.lastName ?? ''}'.trim();
    if (fl.isNotEmpty) return fl;
    final n = s.name.trim();
    if (n.isNotEmpty) return n;
    return s.indexNumber;
  }

  void _syncAbsenceWarningsUi() {
    _absenceWarningAutoDismissTimer?.cancel();
    final list = List<Map<String, dynamic>>.from(ApiService.lastSessionWarnings);
    if (list.isEmpty) {
      if (mounted) {
        setState(() {
          _showAbsenceWarning = false;
          _absenceWarningsSnapshot = [];
        });
      }
      return;
    }
    if (!mounted) return;
    setState(() {
      _absenceWarningsSnapshot = list;
      _showAbsenceWarning = true;
    });
    _absenceWarningAutoDismissTimer = Timer(const Duration(seconds: 10), () {
      if (mounted) setState(() => _showAbsenceWarning = false);
    });
  }

  void _dismissAbsenceWarning() {
    _absenceWarningAutoDismissTimer?.cancel();
    if (mounted) setState(() => _showAbsenceWarning = false);
  }

  void _openAttendancePage(Map<String, dynamic> session) {
    if (_student?.isClassRep == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Class reps are auto-marked when a session is active.'),
        ),
      );
      return;
    }
    Navigator.of(context)
        .push<bool>(
          MaterialPageRoute(
            builder: (_) => appSelectableScope(
              AttendancePage(session: session),
            ),
          ),
        )
        .then((value) async {
      if (value == true) _dismissAbsenceWarning();
      await _load();
    });
  }

  Widget _buildClassRepEntryCard(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Material(
      color: colorScheme.primaryContainer.withValues(alpha: 0.35),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () {
          Navigator.of(context)
              .push<void>(
                MaterialPageRoute<void>(
                  builder: (_) =>
                      appSelectableScope(const RepHomePage()),
                ),
              )
              .then((_) => _load());
        },
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              Icon(Icons.groups_rounded, color: colorScheme.primary),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Class rep',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    Text(
                      'Rep dashboard — sessions, QR & class tools',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: colorScheme.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }

  /// Sessions not yet marked — marked ones are hidden from the home list.
  List<Map<String, dynamic>> get _unmarkedSessions =>
      _activeSessions.where((s) => !_isSessionMarked(s)).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: _buildDrawer(context),
      appBar: AppBar(
        title: const Text('Attendance'),
      ),
      body: ModernPullToRefresh(
        onRefresh: _load,
        child: _isLoading
            ? LayoutBuilder(
                builder: (context, constraints) {
                  return SingleChildScrollView(
                    physics: modernPullToRefreshPhysics,
                    child: SizedBox(
                      height: constraints.maxHeight,
                      child: const Center(child: CircularProgressIndicator()),
                    ),
                  );
                },
              )
            : SingleChildScrollView(
                physics: modernPullToRefreshPhysics,
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (Constants.debugShowSessionApiResponseOnHome)
                      _buildSessionApiDebugPanel(context),
                    _buildGreetingRow(context),
                    if (_dynamicUi.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      ...DynamicWidgetRenderer.render(context, _dynamicUi),
                    ],
                    if (_student?.isClassRep == true) ...[
                      const SizedBox(height: 12),
                      _buildClassRepEntryCard(context),
                    ],
                    if (_showAbsenceWarning && _absenceWarningsSnapshot.isNotEmpty)
                      _buildAbsenceWarningBanner(context),
                    if (_pendingSyncCount > 0) ...[
                      const SizedBox(height: 10),
                      _buildPendingSyncChip(context),
                    ],
                    const SizedBox(height: 20),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: Text(
                          _error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),
                    if (Constants.useDemoActiveSessionWhenEmpty &&
                        _activeSessions.isNotEmpty &&
                        _activeSessions.first['course_code'] == 'DEMO-101')
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          'Demo session · API unavailable or empty',
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: Theme.of(context).colorScheme.tertiary,
                              ),
                        ),
                      ),
                    if (_student?.isClassRep == true)
                      _buildNoActiveSessionCard()
                    else if (_unmarkedSessions.isNotEmpty) ...[
                      _buildPrimarySessionCard(context, _unmarkedSessions.first),
                      if (_unmarkedSessions.length > 1) ...[
                        const SizedBox(height: 20),
                        Text(
                          'Other sessions (${_unmarkedSessions.length - 1})',
                          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                        ),
                        const SizedBox(height: 10),
                        ..._unmarkedSessions.skip(1).map(
                              (s) => Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: _buildSecondarySessionCard(context, s),
                              ),
                            ),
                      ],
                    ] else if (_activeSessions.isNotEmpty)
                      _buildAllMarkedOrEmptyState(context)
                    else
                      _buildNoActiveSessionCard(),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildGreetingRow(BuildContext context) {
    final s = _student;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        if (s != null)
          ProfileAvatar(student: s, radius: 28)
        else
          CircleAvatar(
            radius: 28,
            child: Icon(Icons.person, color: Theme.of(context).colorScheme.primary),
          ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${getGreeting()}, ${_greetingDisplayName()}',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              if (s != null)
                Text(
                  s.indexNumber,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildAbsenceWarningBanner(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12, bottom: 4),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.red.shade100,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.warning, color: Colors.red.shade700),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final w in _absenceWarningsSnapshot)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Text(
                        formatAbsenceWarningLine(w),
                        style: TextStyle(
                          fontSize: 13,
                          height: 1.35,
                          color: Colors.red.shade900,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.close),
              color: Colors.red.shade800,
              onPressed: _dismissAbsenceWarning,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPendingSyncChip(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: cs.secondaryContainer,
      borderRadius: BorderRadius.circular(20),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        child: Row(
          children: [
            Icon(Icons.cloud_upload_outlined, size: 20, color: cs.onSecondaryContainer),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                '$_pendingSyncCount waiting to sync · pull to refresh when online',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: cs.onSecondaryContainer,
                      fontWeight: FontWeight.w600,
                    ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAllMarkedOrEmptyState(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cs.surfaceContainerHighest.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outline.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Icon(Icons.check_circle_outline_rounded, color: cs.outline, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'No sessions to mark — you are up to date.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: cs.onSurfaceVariant,
                    fontSize: 13,
                  ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPrimarySessionCard(BuildContext context, Map<String, dynamic> s) {
    final courseName =
        (s['course_name'] ?? s['course_title'] ?? 'Active session').toString();
    final courseCode = (s['course_code'] ?? '').toString().trim();
    final lecturer = (s['lecturer_name'] ?? '').toString().trim();
    final ended = _sessionEndedFor(s);
    final canMark = !ended;
    final timeLabel = _timeLeftLabel(s);
    final showCountdown = canMark &&
        timeLabel != '--:--' &&
        timeLabel != '—';

    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final accent = canMark
        ? (isDark ? cs.primary : const Color(0xFF1B5E20))
        : cs.outline;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: canMark
            ? (isDark
                ? cs.primary.withValues(alpha: 0.18)
                : const Color(0xFF1B5E20).withValues(alpha: 0.08))
            : cs.surfaceContainerHighest.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: canMark
              ? accent.withValues(alpha: isDark ? 0.55 : 0.5)
              : cs.outline.withValues(alpha: 0.35),
          width: 1.5,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.circle,
                size: 10,
                color: canMark
                    ? (isDark ? cs.primary : Colors.green.shade700)
                    : cs.outline,
              ),
              const SizedBox(width: 8),
              Text(
                'ACTIVE SESSION',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.4,
                      fontSize: 11,
                      color: accent,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CourseBookIcon(size: 20, color: accent),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  courseName,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        height: 1.25,
                        fontSize: 17,
                      ),
                ),
              ),
            ],
          ),
          if (courseCode.isNotEmpty || lecturer.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              [
                if (courseCode.isNotEmpty) courseCode,
                if (lecturer.isNotEmpty) '· $lecturer',
              ].join(' '),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    fontSize: 12,
                  ),
            ),
          ],
          if (showCountdown) ...[
            const SizedBox(height: 8),
            Text(
              'Time left: $timeLabel',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
            ),
          ] else if (ended)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Session ended',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: Theme.of(context).colorScheme.outline,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                    ),
              ),
            ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: canMark ? () => _openAttendancePage(s) : null,
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 12),
                textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
              child: const Text('Mark attendance'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSecondarySessionCard(BuildContext context, Map<String, dynamic> s) {
    final courseName =
        (s['course_name'] ?? s['course_title'] ?? 'Session').toString();
    final courseCode = (s['course_code'] ?? '').toString().trim();
    final ended = _sessionEndedFor(s);
    final canOpen = !ended;

    return Opacity(
      opacity: canOpen ? 1.0 : 0.55,
      child: Material(
        color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: canOpen ? () => _openAttendancePage(s) : null,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            child: Row(
              children: [
                Icon(
                  Icons.circle,
                  size: 8,
                  color: canOpen
                      ? Colors.green.shade600
                      : Theme.of(context).colorScheme.outline,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        courseName,
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                            ),
                      ),
                      if (courseCode.isNotEmpty)
                        Text(
                          courseCode,
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: Theme.of(context).colorScheme.onSurfaceVariant,
                                fontSize: 11,
                              ),
                        ),
                    ],
                  ),
                ),
                Text(
                  ended ? 'Ended' : 'Open',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        fontWeight: FontWeight.w600,
                        fontSize: 11,
                        color: canOpen
                            ? Theme.of(context).colorScheme.primary
                            : Theme.of(context).colorScheme.outline,
                      ),
                ),
                const SizedBox(width: 2),
                Icon(
                  Icons.chevron_right_rounded,
                  size: 20,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// Temporary: shows exactly what the device received from GET /sessions/active.
  Widget _buildSessionApiDebugPanel(BuildContext context) {
    final raw = ApiService.lastActiveSessionRawBody;
    final note = ApiService.lastActiveSessionDebugNote;
    final status = ApiService.lastActiveSessionHttpStatus;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Card(
        color: Colors.amber.shade50,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: Colors.amber.shade200),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.bug_report, size: 18, color: Colors.amber.shade900),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'DEBUG: sessions/active · HTTP $status',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: Colors.amber.shade900,
                          ),
                    ),
                  ),
                ],
              ),
              if (note.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  note,
                  style: TextStyle(
                    color: Colors.red.shade800,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
              ],
              const SizedBox(height: 8),
              SelectableText(
                raw.isEmpty ? '(no body yet — pull to refresh)' : raw,
                style: TextStyle(
                  fontSize: 11,
                  height: 1.35,
                  fontFamily: 'monospace',
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.88),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDrawer(BuildContext context) {
    final s = _student;
    final colorScheme = Theme.of(context).colorScheme;
    final firstLast = s == null
        ? ''
        : '${s.firstName ?? ''} ${s.lastName ?? ''}'.trim().isEmpty
            ? s.displayFirstLastName
            : '${s.firstName ?? ''} ${s.lastName ?? ''}'.trim();

    return Drawer(
      child: AppDrawerShell(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              UserAccountsDrawerHeader(
                margin: EdgeInsets.zero,
                decoration: BoxDecoration(
                  color: colorScheme.primaryContainer.withValues(alpha: 0.45),
                ),
                currentAccountPicture: s != null
                    ? ProfileAvatar(student: s, radius: 26)
                    : CircleAvatar(
                        backgroundColor: colorScheme.primary.withValues(alpha: 0.3),
                        child: Icon(Icons.person, color: colorScheme.primary, size: 26),
                      ),
                accountName: Text(
                  s?.indexNumber ?? '—',
                  style: GoogleFonts.dmSans(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: colorScheme.onSurface,
                  ),
                ),
                accountEmail: Text(
                  firstLast.isEmpty ? (s?.email ?? '') : firstLast,
                  style: GoogleFonts.dmSans(
                    fontSize: 11.5,
                    height: 1.25,
                    color: colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
              ListTile(
                leading: const Icon(Icons.person_outline_rounded),
                title: const Text('Profile'),
                subtitle: const Text('Details & photo'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/profile').then((_) => _load());
                },
              ),
              if (s?.isClassRep == true)
                ListTile(
                  leading: Icon(Icons.dashboard_customize_outlined,
                      color: colorScheme.primary),
                  title: const Text('Class rep dashboard'),
                  subtitle: const Text('Sessions, QR & tools'),
                  onTap: () {
                    Navigator.pop(context);
                    Navigator.of(context)
                        .push(
                          MaterialPageRoute<void>(
                            builder: (_) =>
                                appSelectableScope(const RepHomePage()),
                          ),
                        )
                        .then((_) => _load());
                  },
                ),
              ListTile(
                leading: const Icon(Icons.history_rounded),
                title: const Text('Attendance history'),
                subtitle: const Text('Past sessions & status'),
                onTap: () {
                  Navigator.pop(context);
                  _openAttendanceHistory();
                },
              ),
              ListTile(
                leading: const Icon(Icons.bar_chart_rounded),
                title: const Text('Statistics'),
                subtitle: const Text('Marks per course'),
                onTap: () {
                  Navigator.pop(context);
                  _openStats();
                },
              ),
              ListTile(
                leading: const Icon(Icons.cloud_queue_rounded),
                title: const Text('Offline queue'),
                subtitle: const Text('Pending sync'),
                onTap: () {
                  Navigator.pop(context);
                  _openOfflineQueue();
                },
              ),
              ListTile(
                leading: const Icon(Icons.settings_outlined),
                title: const Text('Settings'),
                subtitle: const Text('Theme & refresh'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/settings').then((_) => _load());
                },
              ),
              const Divider(),
              ListTile(
                leading: Icon(Icons.logout, color: colorScheme.error),
                title: Text(
                  'Log out',
                  style: GoogleFonts.dmSans(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: colorScheme.error,
                  ),
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

  Future<void> _openStats() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => appSelectableScope(const AttendanceStatsPage()),
      ),
    );
    if (mounted) _load();
  }

  Future<void> _openOfflineQueue() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => appSelectableScope(const SyncStatusPage()),
      ),
    );
    if (mounted) _load();
  }

  Widget _buildNoActiveSessionCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context)
            .colorScheme
            .surfaceContainerHighest
            .withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.event_busy_outlined,
            color: Theme.of(context).colorScheme.outline,
            size: 26,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'No active session',
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ),
        ],
      ),
    );
  }

  void _openAttendanceHistory() {
    Navigator.of(context)
        .push(MaterialPageRoute(
            builder: (_) => appSelectableScope(const AttendanceHistoryPage())))
        .then((_) => _load());
  }
}
