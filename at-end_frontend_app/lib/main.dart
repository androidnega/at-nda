import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'pages/attendance_history_page.dart';
import 'pages/attendance_records_page.dart';
import 'pages/attendance_page.dart';
import 'pages/home_page.dart';
import 'pages/launch_gate_page.dart';
import 'pages/lecturer_dashboard_page.dart';
import 'pages/login_page.dart';
import 'pages/profile_page.dart';
import 'pages/class_rep_students_page.dart';
import 'pages/rep_flagged_students_page.dart';
import 'pages/rep_home_page.dart';
import 'pages/rep_insights_page.dart';
import 'pages/rep_late_attendance_page.dart';
import 'pages/rep_session_page.dart';
import 'pages/settings_page.dart';
import 'pages/student_attendance_table_page.dart';
import 'pages/timetable_page.dart';
import 'services/app_update_service.dart';
import 'services/attendance_local_notify.dart';
import 'services/institution_theme_service.dart';
import 'services/notification_bridge.dart';
import 'services/notification_prefs.dart';
import 'services/offline_sync_coordinator.dart';
import 'services/theme_service.dart';
import 'theme/app_theme.dart';
import 'utils/app_selectable_scope.dart';
import 'widgets/app_update_dialog.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setEnabledSystemUIMode(
    SystemUiMode.manual,
    overlays: SystemUiOverlay.values,
  );
  await ThemeService.load();
  await InstitutionThemeService.loadCached();
  await NotificationPrefs.load();
  await NotificationBridge.initialize();
  await AttendanceLocalNotify.init();
  // Phase 2 — app-wide attendance sync coordinator. Runs the outbox
  // drain on connectivity restore, app resume, and a 60 s heartbeat,
  // independent of which page is in front.
  OfflineSyncCoordinator.instance.start();
  runApp(
    const _AppLifecycleShell(
      child: AttendanceApp(),
    ),
  );
}

/// Fires [NotificationBridge.onAppForegrounded] when the app returns to foreground
/// (wire FCM topic sync / token refresh here).
class _AppLifecycleShell extends StatefulWidget {
  const _AppLifecycleShell({required this.child});

  final Widget child;

  @override
  State<_AppLifecycleShell> createState() => _AppLifecycleShellState();
}

class _AppLifecycleShellState extends State<_AppLifecycleShell>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Run the first update check after the first frame so the
    // scaffold messenger is mounted and we can display a dialog.
    // AppUpdateService rate-limits internally (6 h) so back-to-back
    // resumes won't spam the API.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _maybePromptForUpdate();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      NotificationBridge.onAppForegrounded();
      // Re-check on resume — rate-limited by AppUpdateService so
      // toggling the app every few seconds won't hit the API.
      _maybePromptForUpdate();
    }
  }

  Future<void> _maybePromptForUpdate() async {
    try {
      final info = await AppUpdateService.check();
      if (!info.hasRelease || !info.isUpdateAvailable) return;
      final ctx = NotificationBridge.messengerKey.currentContext;
      if (ctx == null || !ctx.mounted) return;
      await AppUpdateDialog.maybeShow(ctx, release: info);
    } catch (_) {
      // Update checks must never crash the app — swallow.
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class AttendanceApp extends StatelessWidget {
  const AttendanceApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<String>(
      valueListenable: InstitutionThemeService.seedNotifier,
      builder: (context, seed, __) {
        return ValueListenableBuilder<ThemeMode>(
          valueListenable: ThemeService.modeNotifier,
          builder: (context, mode, _) {
            final app = MaterialApp(
              title: 'at-enda',
              scaffoldMessengerKey: NotificationBridge.messengerKey,
              debugShowCheckedModeBanner: false,
              theme: AppTheme.lightForSeed(seed),
              darkTheme: AppTheme.darkForSeed(seed),
              themeMode: mode,
              builder: (context, child) {
                final mq = MediaQuery.of(context);
                return MediaQuery(
                  data: mq.copyWith(
                    textScaler: TextScaler.noScaling,
                    boldText: false,
                  ),
                  child: child ?? const SizedBox.shrink(),
                );
              },
              initialRoute: '/',
              routes: {
                '/': (_) => appSelectableScope(const LaunchGatePage()),
                '/login': (_) => appSelectableScope(const LoginPage()),
                '/home': (_) => appSelectableScope(const HomePage()),
                '/rep-home': (_) => appSelectableScope(const RepHomePage()),
                '/lecturer-home': (_) => appSelectableScope(const LecturerDashboardPage()),
                '/class-rep/students': (_) =>
                    appSelectableScope(const ClassRepStudentsPage()),
                '/class-rep/flagged': (_) =>
                    appSelectableScope(const RepFlaggedStudentsPage()),
                '/class-rep/insights': (_) =>
                    appSelectableScope(const RepInsightsPage()),
                '/attendance': (_) => appSelectableScope(const AttendancePage()),
                '/attendance-history': (_) =>
                    appSelectableScope(const AttendanceHistoryPage()),
                '/attendance-records': (context) {
                  final args = ModalRoute.of(context)?.settings.arguments;
                  int? sessionId;
                  if (args is int) {
                    sessionId = args;
                  } else if (args is num) {
                    sessionId = args.toInt();
                  }
                  return appSelectableScope(
                    AttendanceRecordsPage(initialSessionId: sessionId),
                  );
                },
                '/profile': (_) => appSelectableScope(const ProfilePage()),
                '/settings': (_) => appSelectableScope(const SettingsPage()),
                '/rep-sessions': (_) => appSelectableScope(const RepSessionPage()),
                '/timetable': (_) => appSelectableScope(const TimetablePage()),
                StudentAttendanceTablePage.routeName: (_) =>
                    appSelectableScope(const StudentAttendanceTablePage()),
                RepLateAttendancePage.routeName: (_) =>
                    appSelectableScope(const RepLateAttendancePage()),
              },
            );
            return SelectionContainer.disabled(child: app);
          },
        );
      },
    );
  }
}
