import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../widgets/app_drawer_shell.dart';
import '../widgets/modern_pull_to_refresh.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/attendance_local_notify.dart';
import '../services/logout_lock_prefs.dart';
import '../services/last_attendance_prefs.dart';
import '../services/offline_service.dart';
import '../services/session_cache_prefs.dart';
import '../services/student_profile_refresh.dart';
import '../services/sync_service.dart';
import '../services/location_service.dart';
import '../utils/absence_warning_format.dart';
import '../utils/connectivity_util.dart';
import '../utils/app_selectable_scope.dart';
import '../utils/app_state.dart';
import '../utils/constants.dart';
import '../utils/session_attendance_payload.dart';
import '../theme/flat_dashboard.dart';
import '../theme/student_soft_ui.dart';
import '../widgets/student_noir_task_dashboard.dart';
import '../widgets/student_pastel_profile_dashboard.dart';
import '../widgets/student_team_reach_dashboard.dart';
import '../widgets/student_today_dashboard.dart';
import '../widgets/student_violet_calendar_dashboard.dart';
import '../widgets/student_midnight_control_dashboard.dart';
import '../widgets/student_drawer_header.dart';
import '../widgets/dynamic_widget_renderer.dart';
import 'attendance_history_page.dart';
import 'attendance_page.dart';
import 'login_page.dart';
import 'rep_home_page.dart';
import 'sync_status_page.dart';
import 'timetable_page.dart';

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
  bool _logoutAllowed = true;
  String? _logoutLockHint;
  bool _appWentToBackground = false;

  /// From `GET /api/student/attendance-insights` (non–class-rep students only).
  bool _studentAtRisk = false;
  int _studentConsecutiveMissed = 0;

  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();

  /// Today's timetable slots from `GET /api/timetable` (`by_day` for current weekday).
  List<Map<String, dynamic>> _todayTimetable = [];
  bool _liteUiMode = false;

  String _lastCheckInLine =
      'Your last check-in: mark when your class session is live.';

  static const List<String> _weekdayApi = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_syncLogoutLock());
    });
    _load();
    _syncPendingOnStart();
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen((
      results,
    ) {
      final hasConnection = results.any(
        (r) =>
            r == ConnectivityResult.wifi ||
            r == ConnectivityResult.mobile ||
            r == ConnectivityResult.ethernet ||
            r == ConnectivityResult.vpn,
      );
      if (hasConnection && mounted) {
        _syncPendingOnStart();
      }
    });
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
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      _appWentToBackground = true;
    } else if (state == AppLifecycleState.resumed) {
      if (_appWentToBackground) {
        _appWentToBackground = false;
        unawaited(_syncLogoutLock());
      }
      _load(silent: true);
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

  /// Prefer `end_time` / `ends_at` (ISO8601). Fallback: now + `remaining_minutes`.
  DateTime? _parseSessionEndTime(Map<String, dynamic> session) {
    final isCheckInCheckout =
        (session['attendance_mode']?.toString() ?? '') == 'checkin_checkout' ||
        ApiService.attendanceMode == ApiService.attendanceModeCheckInCheckout;
    final raw =
        isCheckInCheckout
            ? (session['end_time'] ??
                session['ends_at'] ??
                session['closed_at'] ??
                session['closed_time'] ??
                session['expected_end_time'])
            : (session['end_time'] ?? session['ends_at']);
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
    final isCheckInCheckout =
        (session['attendance_mode']?.toString() ?? '') == 'checkin_checkout' ||
        ApiService.attendanceMode == ApiService.attendanceModeCheckInCheckout;
    if (isCheckInCheckout) {
      final out = session['check_out_time']?.toString() ?? '';
      if (out.trim().isNotEmpty) return true;
      return false;
    }
    if (session['already_marked'] == true) return true;
    final id = _parseSessionId(session);
    if (id == null) return false;
    return _markedSessionIdsToday.contains(id);
  }

bool _isCheckInCheckoutSession(Map<String, dynamic> session) {
  return (session['attendance_mode']?.toString() ?? '') == 'checkin_checkout';
}

  bool _hasCheckedIn(Map<String, dynamic> session) {
    final t = session['check_in_time']?.toString() ?? '';
    return t.trim().isNotEmpty;
  }

  bool _hasCheckedOut(Map<String, dynamic> session) {
    final t = session['check_out_time']?.toString() ?? '';
    return t.trim().isNotEmpty;
  }

  bool _isCheckoutEnabled(Map<String, dynamic> session) {
    return session['checkout_enabled'] == true ||
        session['can_check_out'] == true ||
        _sessionEndedFor(session);
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
    if (mounted) _load(silent: true);
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
      final explicit =
          icr == true ||
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

  Future<void> _load({bool silent = false}) async {
    final online = await hasInternetConnectivity();
    var nextLiteUiMode = !online;
    _dynamicUi = const [];
    if (online) {
      final settingsSw = Stopwatch()..start();
      await ApiService.loadAppSettings(forceRemote: true);
      settingsSw.stop();
      if (settingsSw.elapsedMilliseconds >= 2500) {
        nextLiteUiMode = true;
      }
      _dynamicUi = ApiService.dynamicUi;
    }
    _sessionUiTicker?.cancel();
    _sessionUiTicker = null;
    if (!silent) {
      setState(() {
        _isLoading = true;
        _error = null;
        _activeSessions = [];
        _markedSessionIdsToday = {};
        _studentAtRisk = false;
        _studentConsecutiveMissed = 0;
        _todayTimetable = [];
      });
    }

    try {
      _student = await OfflineService.getCurrentStudent();
    } catch (_) {
      _student = null;
    }

    if (online && _student != null) {
      final refreshed = await refreshStudentProfileFromApi(_student!);
      if (refreshed != null && mounted) {
        _student = refreshed;
      }
    }

    if (online && _student != null) {
      await _syncClassRepFlagFromApi();
    }

    if (online && _student != null) {
      await OfflineService.hasPasswordOrApiToken();
    }

    var pendingSync = 0;
    try {
      pendingSync = await OfflineService.getPendingAttendanceCount();
    } catch (_) {}
    if (pendingSync > 0) {
      nextLiteUiMode = true;
    }
    if (mounted) {
      setState(() => _pendingSyncCount = pendingSync);
    }

    Future<List<Map<String, dynamic>>> cachedSessionsForCurrentStudent() async {
      final st = _student;
      if (st == null) return const [];
      return SessionCachePrefs.getActiveSessions(st.indexNumber);
    }

    if (!online) {
      final cached = await cachedSessionsForCurrentStudent();
      _absenceWarningAutoDismissTimer?.cancel();
      if (mounted) {
        setState(() {
          _activeSessions = cached;
          _error =
              cached.isEmpty
                  ? 'Offline — connect to the internet to load active sessions.'
                  : 'Offline — showing last synced sessions.';
          _showAbsenceWarning = false;
          _absenceWarningsSnapshot = [];
          _studentAtRisk = false;
          _studentConsecutiveMissed = 0;
        });
      }
    } else {
      try {
        final sessionsSw = Stopwatch()..start();
        final sessions = await ApiService.getActiveSessions(
          indexNumber: _student?.indexNumber,
        );
        sessionsSw.stop();
        if (sessionsSw.elapsedMilliseconds >= 2500) {
          nextLiteUiMode = true;
        }
        debugPrint('FULL RESPONSE: sessions count=${sessions.length}');
        for (var i = 0; i < sessions.length; i++) {
          debugPrint(
            'CURRENT SESSION [$i]: id=${sessions[i]['id']} '
            'course=${sessions[i]['course_code'] ?? sessions[i]['course_name']}',
          );
        }
        final hasValidationError =
            sessions.isEmpty &&
            ApiService.lastActiveSessionErrorMessage.isNotEmpty;
        if (_student != null && !hasValidationError) {
          await SessionCachePrefs.saveActiveSessions(
            _student!.indexNumber,
            sessions,
          );
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
          if (apiErr.isNotEmpty) {
            final cached = await cachedSessionsForCurrentStudent();
            if (!mounted) return;
            if (cached.isNotEmpty) {
              setState(() {
                _activeSessions = cached;
                _error = 'Showing last synced sessions (server issue).';
              });
            } else {
              setState(() {
                _activeSessions = [];
                _error = apiErr;
              });
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
          } else {
            setState(() {
              _activeSessions = [];
              _error = null;
            });
          }
        }
      } catch (e) {
        // ignore: avoid_print
        print('SESSION ERROR: $e');
        nextLiteUiMode = true;
        if (!mounted) return;
        final cached = await cachedSessionsForCurrentStudent();
        if (cached.isNotEmpty) {
          setState(() {
            _activeSessions = cached;
            _error = 'Offline — showing last synced sessions.';
          });
        } else if (Constants.useDemoActiveSessionWhenEmpty) {
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

    if (online && _student != null) {
      unawaited(
        AttendanceLocalNotify.afterSessionsRefresh(
          List<Map<String, dynamic>>.from(
            _activeSessions.map((m) => Map<String, dynamic>.from(m)),
          ),
          silentRefresh: silent,
          isClassRep: _student!.isClassRep == true,
          studentIndex: _student!.indexNumber.trim(),
        ),
      );
    }

    if (online && _student != null) {
      unawaited(_loadStudentAttendanceInsights());
      unawaited(_loadTodayTimetable());
    }

    var lastLine = _lastCheckInLine;
    try {
      lastLine = await _computeLastCheckInLine();
    } catch (_) {}

    if (mounted) {
      setState(() {
        _liteUiMode = nextLiteUiMode;
        if (_liteUiMode) {
          _dynamicUi = const [];
        }
        _isLoading = false;
        _lastCheckInLine = lastLine;
      });
    }
  }

  Future<void> _loadTodayTimetable() async {
    final s = _student;
    if (s == null) return;

    Future<void> loadCachedTimetable() async {
      final cachedRoot = await SessionCachePrefs.getTimetable(s.indexNumber);
      if (cachedRoot == null) return;
      final by = cachedRoot['by_day'];
      if (by is! Map) return;
      final dayKey = _weekdayApi[DateTime.now().weekday - 1];
      final rawList = by[dayKey];
      final out = <Map<String, dynamic>>[];
      if (rawList is List) {
        for (final e in rawList) {
          if (e is Map) out.add(Map<String, dynamic>.from(e));
        }
      }
      out.sort(
        (a, b) => _slotMinutes(
          a['start_time']?.toString(),
        ).compareTo(_slotMinutes(b['start_time']?.toString())),
      );
      if (!mounted) return;
      setState(() => _todayTimetable = out);
    }

    final tok = await OfflineService.getApiSessionToken();
    if (tok == null || tok.isEmpty) {
      await loadCachedTimetable();
      return;
    }
    ApiService.setSessionBearerToken(tok);
    try {
      final res = await ApiService.getTimetable();
      if (!ApiService.isSuccessfulHttp(res.statusCode)) {
        await loadCachedTimetable();
        return;
      }
      final decoded = jsonDecode(res.body);
      if (decoded is! Map) {
        await loadCachedTimetable();
        return;
      }
      var root = Map<String, dynamic>.from(decoded);
      if (!root.containsKey('by_day') &&
          !root.containsKey('ordered_days') &&
          root['data'] is Map) {
        root = Map<String, dynamic>.from(root['data'] as Map);
      }
      await SessionCachePrefs.saveTimetable(s.indexNumber, root);
      final by = root['by_day'];
      if (by is! Map) {
        await loadCachedTimetable();
        return;
      }
      final dayKey = _weekdayApi[DateTime.now().weekday - 1];
      final rawList = by[dayKey];
      final out = <Map<String, dynamic>>[];
      if (rawList is List) {
        for (final e in rawList) {
          if (e is Map) out.add(Map<String, dynamic>.from(e));
        }
      }
      out.sort(
        (a, b) => _slotMinutes(
          a['start_time']?.toString(),
        ).compareTo(_slotMinutes(b['start_time']?.toString())),
      );
      if (!mounted) return;
      setState(() => _todayTimetable = out);
    } catch (_) {
      await loadCachedTimetable();
    }
  }

  int _slotMinutes(String? hhmm) {
    if (hhmm == null || !hhmm.contains(':')) return 0;
    final p = hhmm.split(':');
    final h = int.tryParse(p[0].trim()) ?? 0;
    final m = int.tryParse(p[1].trim()) ?? 0;
    return h * 60 + m;
  }

  DateTime? _todayAtTime(String? hhmm) {
    if (hhmm == null || !hhmm.contains(':')) return null;
    final p = hhmm.split(':');
    final h = int.tryParse(p[0].trim());
    final m = int.tryParse(p[1].trim());
    if (h == null || m == null) return null;
    final n = DateTime.now();
    return DateTime(n.year, n.month, n.day, h, m);
  }

  List<String> _dashboardClockSegmentsFrom(String tl) {
    if (tl == '—' || tl == '--:--') {
      return ['—', '—', '—'];
    }
    if (tl == 'Session ended') {
      return ['—', '—', '—'];
    }
    final parts = tl.split(':');
    if (parts.length == 3) {
      return [
        parts[0].trim().padLeft(2, '0'),
        parts[1].trim().padLeft(2, '0'),
        parts[2].trim().padLeft(2, '0'),
      ];
    }
    if (parts.length == 2) {
      return [
        '00',
        parts[0].trim().padLeft(2, '0'),
        parts[1].trim().padLeft(2, '0'),
      ];
    }
    return ['—', '—', '—'];
  }

  /// Label + three segments for the summary clock: live session or next class.
  ({String label, List<String> parts}) _dashboardFocusClockRow() {
    final um = _unmarkedSessions;
    final first = um.isNotEmpty ? um.first : null;
    if (first != null && !_sessionEndedFor(first)) {
      final tl = _timeLeftLabel(first);
      if (tl != '—' && tl != '--:--' && tl != 'Session ended') {
        return (
          label: 'Session ends in',
          parts: _dashboardClockSegmentsFrom(tl),
        );
      }
    }

    final now = DateTime.now();
    for (final slot in _todayTimetable) {
      final st = _todayAtTime(slot['start_time']?.toString());
      if (st != null && now.isBefore(st)) {
        final diff = st.difference(now);
        if (!diff.isNegative) {
          final tl = _formatDurationRemaining(diff);
          return (
            label: 'Next class starts in',
            parts: _dashboardClockSegmentsFrom(tl),
          );
        }
      }
    }
    for (final slot in _todayTimetable) {
      final st = _todayAtTime(slot['start_time']?.toString());
      final en = _todayAtTime(slot['end_time']?.toString());
      if (st != null && en != null && !now.isBefore(st) && !now.isAfter(en)) {
        final diff = en.difference(now);
        if (!diff.isNegative) {
          final tl = _formatDurationRemaining(diff);
          return (
            label: 'Class ends in',
            parts: _dashboardClockSegmentsFrom(tl),
          );
        }
      }
    }

    return (label: '', parts: const []);
  }

  String? _firstTodayVenueHint() {
    for (final slot in _todayTimetable) {
      final v = (slot['venue']?.toString() ?? '').trim();
      if (v.isNotEmpty) return v;
    }
    return null;
  }

  /// 0–1 between 9:00 and 18:00 local time.
  double _workingDayProgressFraction() {
    final now = DateTime.now();
    final start = DateTime(now.year, now.month, now.day, 9);
    final end = DateTime(now.year, now.month, now.day, 18);
    if (now.isBefore(start)) return 0;
    if (!now.isBefore(end)) return 1;
    final t = now.difference(start).inMinutes / end.difference(start).inMinutes;
    return t.clamp(0.0, 1.0);
  }

  Map<String, String> _heroDashboardCopy() {
    if (_student?.isClassRep == true) {
      return {
        'title': 'Class rep',
        'subtitle': 'Use Class rep tools for sessions & attendance.',
      };
    }
    final first = _unmarkedSessions.isNotEmpty ? _unmarkedSessions.first : null;
    if (first != null && !_sessionEndedFor(first)) {
      final tl = _timeLeftLabel(first);
      if (tl != '—' && tl != '--:--' && tl != 'Session ended') {
        return {'title': tl, 'subtitle': 'Time left to mark this session'};
      }
    }
    final now = DateTime.now();
    for (final slot in _todayTimetable) {
      final st = _todayAtTime(slot['start_time']?.toString());
      final en = _todayAtTime(slot['end_time']?.toString());
      if (st != null && en != null && now.isBefore(st)) {
        final diff = st.difference(now);
        if (diff.inMinutes < 60) {
          return {
            'title': 'In ${diff.inMinutes} min',
            'subtitle':
                '${slot['course_code'] ?? slot['course_name']} · starts ${slot['start_time']}',
          };
        }
        return {
          'title': _formatTimeAmPm(slot['start_time']?.toString()),
          'subtitle': 'Next class today',
        };
      }
    }
    if (_todayTimetable.isNotEmpty) {
      return {'title': 'On track', 'subtitle': 'No session to mark right now'};
    }
    return {
      'title': 'Welcome',
      'subtitle': 'Pull to refresh for sessions & timetable',
    };
  }

  String _formatTimeAmPm(String? hhmm) {
    if (hhmm == null || !hhmm.contains(':')) return '—';
    final p = hhmm.split(':');
    var h = int.tryParse(p[0].trim()) ?? 0;
    final m = int.tryParse(p[1].trim()) ?? 0;
    final pm = h >= 12;
    if (h > 12) h -= 12;
    if (h == 0) h = 12;
    return '$h:${m.toString().padLeft(2, '0')} ${pm ? 'PM' : 'AM'}';
  }

  Future<String> _computeLastCheckInLine() async {
    final ids = <int>{};
    for (final s in _activeSessions) {
      final id = _parseSessionId(s);
      if (id != null) ids.add(id);
    }
    final last = await LastAttendancePrefs.load(
      currentActiveSessionIds: ids.isNotEmpty ? ids : null,
    );
    if (last == null) {
      return 'Your last check-in: mark when your class session is live.';
    }
    final tStr = last['time']?.toString();
    if (tStr == null) {
      return 'Your last check-in: mark when your class session is live.';
    }
    try {
      final t = DateTime.parse(tStr);
      final diff = DateTime.now().difference(t);
      String rel;
      if (diff.inMinutes < 1) {
        rel = 'just now';
      } else if (diff.inMinutes < 60) {
        rel = '${diff.inMinutes} min ago';
      } else if (diff.inHours < 24) {
        rel = '${diff.inHours} hour${diff.inHours == 1 ? '' : 's'} ago';
      } else {
        rel = '${diff.inDays} day${diff.inDays == 1 ? '' : 's'} ago';
      }
      final course = last['course']?.toString() ?? 'class';
      return 'Your last check-in was: $rel · $course';
    } catch (_) {
      return 'Your last check-in: mark when your class session is live.';
    }
  }

  void _onTimetableSlotTap(Map<String, dynamic> slot) {
    if (_student?.isClassRep == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Class reps are marked automatically when sessions run.',
          ),
        ),
      );
      return;
    }
    final code = slot['course_code']?.toString().trim() ?? '';
    for (final s in _unmarkedSessions) {
      final sc = s['course_code']?.toString().trim() ?? '';
      if (code.isNotEmpty && sc == code) {
        _openAttendancePage(s);
        return;
      }
    }
    final label =
        code.isNotEmpty ? code : (slot['course_name'] ?? 'This class');
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Mark attendance when your lecturer opens a live session for $label.',
        ),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _onDashboardBell() {
    if (_showAbsenceWarning && _absenceWarningsSnapshot.isNotEmpty) {
      setState(() => _showAbsenceWarning = true);
    }
    if (_pendingSyncCount > 0) {
      _openOfflineQueue();
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'You will see alerts here for absences and pending sync.',
        ),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _loadStudentAttendanceInsights() async {
    final s = _student;
    if (s == null || s.isClassRep) {
      if (mounted) {
        setState(() {
          _studentAtRisk = false;
          _studentConsecutiveMissed = 0;
        });
      }
      return;
    }
    final tok = await OfflineService.getApiSessionToken();
    if (tok == null || tok.isEmpty) return;
    ApiService.setSessionBearerToken(tok);
    try {
      final res = await ApiService.studentAttendanceInsights();
      if (!mounted) return;
      if (res.statusCode < 200 || res.statusCode >= 300) return;
      final raw = jsonDecode(res.body);
      if (raw is! Map || raw['success'] != true || raw['data'] is! Map) {
        return;
      }
      final d = Map<String, dynamic>.from(raw['data'] as Map);
      final ins = d['insights'];
      final i =
          ins is Map ? Map<String, dynamic>.from(ins) : <String, dynamic>{};
      final atRisk = i['at_risk'] == true;
      final cm = i['consecutive_missed_sessions'];
      int streak = 0;
      if (cm is int) {
        streak = cm;
      } else if (cm is num) {
        streak = cm.round();
      } else {
        streak = int.tryParse(cm?.toString() ?? '') ?? 0;
      }
      if (!mounted) return;
      setState(() {
        _studentAtRisk = atRisk;
        _studentConsecutiveMissed = streak;
      });
    } catch (_) {}
  }

  Future<void> _logout() async {
    await LastAttendancePrefs.clear();
    await OfflineService.clearCurrentStudent();
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => appSelectableScope(const LoginPage()),
        ),
        (_) => false,
      );
    }
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

  void _syncAbsenceWarningsUi() {
    _absenceWarningAutoDismissTimer?.cancel();
    final list = List<Map<String, dynamic>>.from(
      ApiService.lastSessionWarnings,
    );
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
            builder:
                (_) => appSelectableScope(AttendancePage(session: session)),
          ),
        )
        .then((value) async {
          if (value == true) _dismissAbsenceWarning();
          await _load();
        });
  }

  Future<void> _handleDashboardAttendanceAction(
    Map<String, dynamic> session,
  ) async {
    if (_isCheckInCheckoutSession(session)) {
      _openAttendancePage(session);
      return;
    }
    if (!_isCheckInCheckoutSession(session)) {
      _openAttendancePage(session);
      return;
    }
    final s = _student;
    if (s == null) return;
    final inDone = _hasCheckedIn(session);
    final outDone = _hasCheckedOut(session);
    if (outDone) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('You already checked out for this session.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }
    try {
      final pos = await LocationService.getCurrentLocation();
      if (!mounted) return;
      final payload = buildAttendancePostBody(
        indexNumber: AppState.studentIndex ?? s.indexNumber,
        sessionId: parseSessionId(Map<String, dynamic>.from(session)),
        courseId: parseOptionalCourseId(Map<String, dynamic>.from(session)),
        weekId: parseOptionalWeekId(Map<String, dynamic>.from(session)),
        lat: pos.latitude,
        lng: pos.longitude,
        includeLocation: true,
        timestamp: DateTime.now().toIso8601String(),
      );
      final endpoint = inDone ? 'attendance/checkout' : 'attendance';
      if (inDone && !_isCheckoutEnabled(session)) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Checkout is not enabled yet.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
      final res = await ApiService.post(endpoint, payload);
      if (!mounted) return;
      if (ApiService.isSuccessfulHttp(res.statusCode)) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(inDone ? 'Checkout recorded.' : 'Check-in recorded.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
        await _load(silent: true);
      } else {
        final msg = ApiService.messageFromHttpResponse(res);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg.isEmpty ? 'Could not submit attendance.' : msg),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Location/attendance error: $e'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
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
                  builder: (_) => appSelectableScope(const RepHomePage()),
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
      _activeSessions.where((s) {
        if (_isSessionMarked(s)) return false;
        if (!_sessionEndedFor(s)) return true;
        // Closed/ended check-in-checkout sessions remain actionable only for
        // students who checked in but have not checked out yet.
        return _isCheckInCheckoutSession(s) &&
            _hasCheckedIn(s) &&
            !_hasCheckedOut(s);
      }).toList();

  @override
  Widget build(BuildContext context) {
    final s = _student;
    if (_isLoading || s == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final hero = _heroDashboardCopy();
    final um = _unmarkedSessions;
    final liveUnmarked = um.where((s) => !_sessionEndedFor(s)).toList();
    final liveSessionCount = liveUnmarked.length;
    final primaryActionLabel =
        (!s.isClassRep && um.isNotEmpty && _isCheckInCheckoutSession(um.first))
            ? 'Open check-in'
            : 'Mark attendance';
    final showMark =
        !s.isClassRep &&
        um.isNotEmpty &&
        (!_sessionEndedFor(um.first) || _isCheckInCheckoutSession(um.first));
    final focusClock = _dashboardFocusClockRow();

    final extraSessions =
        !s.isClassRep && liveUnmarked.length > 1
            ? Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                '${liveUnmarked.length - 1} other live session(s) — pull to refresh.',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: Theme.of(context).colorScheme.primary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            )
            : null;

    final light = Theme.of(context).brightness == Brightness.light;
    final cs = Theme.of(context).colorScheme;
    // Keep dashboard theme consistent even when offline/slow.
    final studentTheme = ApiService.studentDashboardTheme;

    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: light ? StudentSoftUi.cream(cs) : cs.surface,
      drawer: _buildDrawer(context),
      body: SafeArea(
        top: true,
        bottom: false,
        child: ModernPullToRefresh(
          onRefresh: () => _load(silent: true),
          child:
              !s.isClassRep &&
                      studentTheme ==
                          ApiService.studentDashboardThemePastelProfile
                  ? StudentPastelProfileDashboard(
                    student: s,
                    todaySlots: _todayTimetable,
                    unmarkedSessions: um,
                    heroTitle: hero['title']!,
                    heroSubtitle: hero['subtitle']!,
                    showMarkButton: showMark,
                    onMarkAttendance:
                        () => _handleDashboardAttendanceAction(um.first),
                    primaryActionLabel: primaryActionLabel,
                    lastCheckInLine: _lastCheckInLine,
                    dayProgress: _workingDayProgressFraction(),
                    onOpenDrawer: () => _scaffoldKey.currentState?.openDrawer(),
                    onBell: _onDashboardBell,
                    onOpenFullTimetable: _openTimetable,
                    onSeeAllClasses: _openTimetable,
                    onSlotTap: _onTimetableSlotTap,
                    statsClassesToday: _todayTimetable.length,
                    statsLiveSessions: liveSessionCount,
                    statsMarkedToday: _markedSessionIdsToday.length,
                    dashboardClockLabel: focusClock.label,
                    dashboardClockSegments: focusClock.parts,
                    todayVenueHint: _firstTodayVenueHint(),
                    classRepCard: null,
                    dynamicBlocks: [
                      if (Constants.debugShowSessionApiResponseOnHome) ...[
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _buildSessionApiDebugPanel(context),
                        ),
                      ],
                      if (_dynamicUi.isNotEmpty) ...[
                        ...DynamicWidgetRenderer.render(context, _dynamicUi),
                      ],
                      if (extraSessions != null) extraSessions,
                    ],
                    warningBanner:
                        _showAbsenceWarning &&
                                _absenceWarningsSnapshot.isNotEmpty
                            ? _buildAbsenceWarningBanner(context)
                            : null,
                    pendingSyncChip:
                        _pendingSyncCount > 0
                            ? _buildPendingSyncChip(context)
                            : null,
                    errorText: _error,
                    riskSection:
                        _studentAtRisk
                            ? _buildConsecutiveMissWarning(context)
                            : null,
                    demoBanner:
                        Constants.useDemoActiveSessionWhenEmpty &&
                                _activeSessions.isNotEmpty &&
                                _activeSessions.first['course_code'] ==
                                    'DEMO-101'
                            ? Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                'Demo session · API unavailable or empty',
                                style: Theme.of(
                                  context,
                                ).textTheme.labelSmall?.copyWith(
                                  color: Theme.of(context).colorScheme.tertiary,
                                ),
                              ),
                            )
                            : null,
                  )
                  : !s.isClassRep &&
                      studentTheme == ApiService.studentDashboardThemeNoirTask
                  ? StudentNoirTaskDashboard(
                    student: s,
                    todaySlots: _todayTimetable,
                    unmarkedSessions: um,
                    heroTitle: hero['title']!,
                    heroSubtitle: hero['subtitle']!,
                    showMarkButton: showMark,
                    onMarkAttendance:
                        () => _handleDashboardAttendanceAction(um.first),
                    primaryActionLabel: primaryActionLabel,
                    lastCheckInLine: _lastCheckInLine,
                    dayProgress: _workingDayProgressFraction(),
                    onOpenDrawer: () => _scaffoldKey.currentState?.openDrawer(),
                    onBell: _onDashboardBell,
                    onOpenFullTimetable: _openTimetable,
                    onSeeAllClasses: _openTimetable,
                    onSlotTap: _onTimetableSlotTap,
                    statsClassesToday: _todayTimetable.length,
                    statsLiveSessions: liveSessionCount,
                    statsMarkedToday: _markedSessionIdsToday.length,
                    dashboardClockLabel: focusClock.label,
                    dashboardClockSegments: focusClock.parts,
                    todayVenueHint: _firstTodayVenueHint(),
                    classRepCard: null,
                    dynamicBlocks: [
                      if (Constants.debugShowSessionApiResponseOnHome) ...[
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _buildSessionApiDebugPanel(context),
                        ),
                      ],
                      if (_dynamicUi.isNotEmpty) ...[
                        ...DynamicWidgetRenderer.render(context, _dynamicUi),
                      ],
                      if (extraSessions != null) extraSessions,
                    ],
                    warningBanner:
                        _showAbsenceWarning &&
                                _absenceWarningsSnapshot.isNotEmpty
                            ? _buildAbsenceWarningBanner(context)
                            : null,
                    pendingSyncChip:
                        _pendingSyncCount > 0
                            ? _buildPendingSyncChip(context)
                            : null,
                    errorText: _error,
                    riskSection:
                        _studentAtRisk
                            ? _buildConsecutiveMissWarning(context)
                            : null,
                    demoBanner:
                        Constants.useDemoActiveSessionWhenEmpty &&
                                _activeSessions.isNotEmpty &&
                                _activeSessions.first['course_code'] ==
                                    'DEMO-101'
                            ? Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                'Demo session · API unavailable or empty',
                                style: Theme.of(
                                  context,
                                ).textTheme.labelSmall?.copyWith(
                                  color: Theme.of(context).colorScheme.tertiary,
                                ),
                              ),
                            )
                            : null,
                  )
                  : !s.isClassRep &&
                      studentTheme == ApiService.studentDashboardThemeTeamReach
                  ? StudentTeamReachDashboard(
                    student: s,
                    todaySlots: _todayTimetable,
                    unmarkedSessions: um,
                    heroTitle: hero['title']!,
                    heroSubtitle: hero['subtitle']!,
                    showMarkButton: showMark,
                    onMarkAttendance:
                        () => _handleDashboardAttendanceAction(um.first),
                    primaryActionLabel: primaryActionLabel,
                    lastCheckInLine: _lastCheckInLine,
                    dayProgress: _workingDayProgressFraction(),
                    onOpenDrawer: () => _scaffoldKey.currentState?.openDrawer(),
                    onBell: _onDashboardBell,
                    onOpenFullTimetable: _openTimetable,
                    onSeeAllClasses: _openTimetable,
                    onSlotTap: _onTimetableSlotTap,
                    statsClassesToday: _todayTimetable.length,
                    statsLiveSessions: liveSessionCount,
                    statsMarkedToday: _markedSessionIdsToday.length,
                    dashboardClockLabel: focusClock.label,
                    dashboardClockSegments: focusClock.parts,
                    todayVenueHint: _firstTodayVenueHint(),
                    classRepCard: null,
                    dynamicBlocks: [
                      if (Constants.debugShowSessionApiResponseOnHome) ...[
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _buildSessionApiDebugPanel(context),
                        ),
                      ],
                      if (_dynamicUi.isNotEmpty) ...[
                        ...DynamicWidgetRenderer.render(context, _dynamicUi),
                      ],
                      if (extraSessions != null) extraSessions,
                    ],
                    warningBanner:
                        _showAbsenceWarning &&
                                _absenceWarningsSnapshot.isNotEmpty
                            ? _buildAbsenceWarningBanner(context)
                            : null,
                    pendingSyncChip:
                        _pendingSyncCount > 0
                            ? _buildPendingSyncChip(context)
                            : null,
                    errorText: _error,
                    riskSection:
                        _studentAtRisk
                            ? _buildConsecutiveMissWarning(context)
                            : null,
                    demoBanner:
                        Constants.useDemoActiveSessionWhenEmpty &&
                                _activeSessions.isNotEmpty &&
                                _activeSessions.first['course_code'] ==
                                    'DEMO-101'
                            ? Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                'Demo session · API unavailable or empty',
                                style: Theme.of(
                                  context,
                                ).textTheme.labelSmall?.copyWith(
                                  color: Theme.of(context).colorScheme.tertiary,
                                ),
                              ),
                            )
                            : null,
                  )
                  : !s.isClassRep &&
                      studentTheme ==
                          ApiService.studentDashboardThemeVioletCalendar
                  ? StudentVioletCalendarDashboard(
                    student: s,
                    todaySlots: _todayTimetable,
                    unmarkedSessions: um,
                    heroTitle: hero['title']!,
                    heroSubtitle: hero['subtitle']!,
                    showMarkButton: showMark,
                    onMarkAttendance:
                        () => _handleDashboardAttendanceAction(um.first),
                    primaryActionLabel: primaryActionLabel,
                    lastCheckInLine: _lastCheckInLine,
                    dayProgress: _workingDayProgressFraction(),
                    onOpenDrawer: () => _scaffoldKey.currentState?.openDrawer(),
                    onBell: _onDashboardBell,
                    onOpenFullTimetable: _openTimetable,
                    onSeeAllClasses: _openTimetable,
                    onSlotTap: _onTimetableSlotTap,
                    statsClassesToday: _todayTimetable.length,
                    statsLiveSessions: liveSessionCount,
                    statsMarkedToday: _markedSessionIdsToday.length,
                    dashboardClockLabel: focusClock.label,
                    dashboardClockSegments: focusClock.parts,
                    todayVenueHint: _firstTodayVenueHint(),
                    classRepCard: null,
                    dynamicBlocks: [
                      if (Constants.debugShowSessionApiResponseOnHome) ...[
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _buildSessionApiDebugPanel(context),
                        ),
                      ],
                      if (_dynamicUi.isNotEmpty) ...[
                        ...DynamicWidgetRenderer.render(context, _dynamicUi),
                      ],
                      if (extraSessions != null) extraSessions,
                    ],
                    warningBanner:
                        _showAbsenceWarning &&
                                _absenceWarningsSnapshot.isNotEmpty
                            ? _buildAbsenceWarningBanner(context)
                            : null,
                    pendingSyncChip:
                        _pendingSyncCount > 0
                            ? _buildPendingSyncChip(context)
                            : null,
                    errorText: _error,
                    riskSection:
                        _studentAtRisk
                            ? _buildConsecutiveMissWarning(context)
                            : null,
                    demoBanner:
                        Constants.useDemoActiveSessionWhenEmpty &&
                                _activeSessions.isNotEmpty &&
                                _activeSessions.first['course_code'] ==
                                    'DEMO-101'
                            ? Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                'Demo session · API unavailable or empty',
                                style: Theme.of(
                                  context,
                                ).textTheme.labelSmall?.copyWith(
                                  color: Theme.of(context).colorScheme.tertiary,
                                ),
                              ),
                            )
                            : null,
                  )
                  : !s.isClassRep &&
                      studentTheme ==
                          ApiService.studentDashboardThemeMidnightControl
                  ? StudentMidnightControlDashboard(
                    student: s,
                    showMarkButton: showMark,
                    onMarkAttendance:
                        () => _handleDashboardAttendanceAction(um.first),
                    primaryActionLabel: primaryActionLabel,
                    onOpenDrawer: () => _scaffoldKey.currentState?.openDrawer(),
                    onBell: _onDashboardBell,
                    statsClassesToday: _todayTimetable.length,
                    statsLiveSessions: liveSessionCount,
                    statsMarkedToday: _markedSessionIdsToday.length,
                    unmarkedCount: um.length,
                    dynamicBlocks: [
                      if (Constants.debugShowSessionApiResponseOnHome) ...[
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _buildSessionApiDebugPanel(context),
                        ),
                      ],
                      if (_dynamicUi.isNotEmpty) ...[
                        ...DynamicWidgetRenderer.render(context, _dynamicUi),
                      ],
                      if (extraSessions != null) extraSessions,
                    ],
                    warningBanner:
                        _showAbsenceWarning &&
                                _absenceWarningsSnapshot.isNotEmpty
                            ? _buildAbsenceWarningBanner(context)
                            : null,
                    pendingSyncChip:
                        _pendingSyncCount > 0
                            ? _buildPendingSyncChip(context)
                            : null,
                    errorText: _error,
                    riskSection:
                        _studentAtRisk
                            ? _buildConsecutiveMissWarning(context)
                            : null,
                  )
                  : StudentTodayDashboard(
                    student: s,
                    todaySlots: _todayTimetable,
                    unmarkedSessions: um,
                    heroTitle: hero['title']!,
                    heroSubtitle: hero['subtitle']!,
                    showMarkButton: showMark,
                    onMarkAttendance:
                        () => _handleDashboardAttendanceAction(um.first),
                    primaryActionLabel: primaryActionLabel,
                    lastCheckInLine: _lastCheckInLine,
                    dayProgress: _workingDayProgressFraction(),
                    onOpenDrawer: () => _scaffoldKey.currentState?.openDrawer(),
                    onBell: _onDashboardBell,
                    onOpenFullTimetable: _openTimetable,
                    onSeeAllClasses: _openTimetable,
                    onSlotTap: _onTimetableSlotTap,
                    statsClassesToday: _todayTimetable.length,
                    statsLiveSessions: liveSessionCount,
                    statsMarkedToday: _markedSessionIdsToday.length,
                    dashboardClockLabel: focusClock.label,
                    dashboardClockSegments: focusClock.parts,
                    todayVenueHint: _firstTodayVenueHint(),
                    classRepCard:
                        s.isClassRep ? _buildClassRepEntryCard(context) : null,
                    dynamicBlocks: [
                      if (Constants.debugShowSessionApiResponseOnHome) ...[
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _buildSessionApiDebugPanel(context),
                        ),
                      ],
                      if (_dynamicUi.isNotEmpty) ...[
                        ...DynamicWidgetRenderer.render(context, _dynamicUi),
                      ],
                      if (extraSessions != null) extraSessions,
                    ],
                    warningBanner:
                        _showAbsenceWarning &&
                                _absenceWarningsSnapshot.isNotEmpty
                            ? _buildAbsenceWarningBanner(context)
                            : null,
                    pendingSyncChip:
                        _pendingSyncCount > 0
                            ? _buildPendingSyncChip(context)
                            : null,
                    errorText: _error,
                    riskSection:
                        !s.isClassRep && _studentAtRisk
                            ? _buildConsecutiveMissWarning(context)
                            : null,
                    demoBanner:
                        Constants.useDemoActiveSessionWhenEmpty &&
                                _activeSessions.isNotEmpty &&
                                _activeSessions.first['course_code'] ==
                                    'DEMO-101'
                            ? Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                'Demo session · API unavailable or empty',
                                style: Theme.of(
                                  context,
                                ).textTheme.labelSmall?.copyWith(
                                  color: Theme.of(context).colorScheme.tertiary,
                                ),
                              ),
                            )
                            : null,
                  ),
        ),
      ),
    );
  }

  Widget _buildConsecutiveMissWarning(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7ED),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFDBA74)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.warning_amber_rounded, color: Color(0xFFC2410C)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Attendance alert',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: FlatDashboard.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'You have missed $_studentConsecutiveMissed consecutive '
                  'session${_studentConsecutiveMissed == 1 ? '' : 's'}. '
                  'Attend the next sessions to avoid falling further behind.',
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.35,
                    color: FlatDashboard.textPrimary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
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
            Icon(
              Icons.cloud_upload_outlined,
              size: 20,
              color: cs.onSecondaryContainer,
            ),
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
                  Icon(
                    Icons.bug_report,
                    size: 18,
                    color: Colors.amber.shade900,
                  ),
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
                  color: Theme.of(
                    context,
                  ).colorScheme.onSurface.withValues(alpha: 0.88),
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
    final headerColor = colorScheme.primaryContainer.withValues(alpha: 0.45);

    return Drawer(
      child: AppDrawerShell(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              if (s != null)
                StudentDrawerHeader(student: s, decorationColor: headerColor)
              else
                Material(
                  color: headerColor,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
                    child: Row(
                      children: [
                        CircleAvatar(
                          backgroundColor: colorScheme.primary.withValues(
                            alpha: 0.3,
                          ),
                          radius: 28,
                          child: Icon(
                            Icons.person,
                            color: colorScheme.primary,
                            size: 28,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          '—',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                      ],
                    ),
                  ),
                ),
              ListTile(
                leading: const Icon(Icons.person_outline_rounded),
                title: const Text('Profile'),
                subtitle: const Text('Account details'),
                onTap: () {
                  _scaffoldKey.currentState?.closeDrawer();
                  Navigator.of(
                    context,
                  ).pushNamed('/profile').then((_) => _load());
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
                  ).pushNamed('/timetable').then((_) => _load());
                },
              ),
              if (s?.isClassRep == true)
                ListTile(
                  leading: Icon(
                    Icons.dashboard_customize_outlined,
                    color: colorScheme.primary,
                  ),
                  title: const Text('Class rep dashboard'),
                  subtitle: const Text('Sessions, QR & tools'),
                  onTap: () {
                    _scaffoldKey.currentState?.closeDrawer();
                    Navigator.of(context)
                        .push(
                          MaterialPageRoute<void>(
                            builder:
                                (_) => appSelectableScope(const RepHomePage()),
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
                  _scaffoldKey.currentState?.closeDrawer();
                  _openAttendanceHistory();
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
                  style: GoogleFonts.dmSans(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
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

  Future<void> _openOfflineQueue() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => appSelectableScope(const SyncStatusPage()),
      ),
    );
    if (mounted) _load();
  }

  Future<void> _openTimetable() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => appSelectableScope(const TimetablePage()),
      ),
    );
    if (mounted) _load(silent: true);
  }

  void _openAttendanceHistory() {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) => appSelectableScope(const AttendanceHistoryPage()),
          ),
        )
        .then((_) => _load());
  }
}
