import 'dart:async';

import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;
import 'package:flutter/material.dart';

import 'pages/attendance_records_page.dart';
import 'pages/attendance_page.dart';
import 'pages/home_page.dart';
import 'pages/launch_gate_page.dart';
import 'pages/lecturer_dashboard_page.dart';
import 'pages/login_page.dart';
import 'pages/profile_page.dart';
import 'pages/class_rep_students_page.dart';
import 'pages/rep_home_page.dart';
import 'pages/rep_session_page.dart';
import 'pages/settings_page.dart';
import 'pages/timetable_page.dart';
import 'services/communication_log_sync.dart';
import 'services/notification_bridge.dart';
import 'services/notification_prefs.dart';
import 'services/theme_service.dart';
import 'theme/app_theme.dart';
import 'utils/app_selectable_scope.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ThemeService.load();
  await NotificationPrefs.load();
  await NotificationBridge.initialize();
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
      unawaited(CommunicationLogSyncService.maybeSync());
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class AttendanceApp extends StatelessWidget {
  const AttendanceApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeService.modeNotifier,
      builder: (context, mode, _) {
        final app = MaterialApp(
          title: 'at-enda',
          scaffoldMessengerKey: NotificationBridge.messengerKey,
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: mode,
          initialRoute: '/',
          routes: {
            '/': (_) => appSelectableScope(const LaunchGatePage()),
            '/login': (_) => appSelectableScope(const LoginPage()),
            '/home': (_) => appSelectableScope(const HomePage()),
            '/rep-home': (_) => appSelectableScope(const RepHomePage()),
            '/lecturer-home': (_) => appSelectableScope(const LecturerDashboardPage()),
            '/class-rep/students': (_) =>
                appSelectableScope(const ClassRepStudentsPage()),
            '/attendance': (_) => appSelectableScope(const AttendancePage()),
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
          },
        );
        if (kIsWeb) return app;
        final isMobile = defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS;
        if (!isMobile) return app;
        return SelectionContainer.disabled(child: app);
      },
    );
  }
}
