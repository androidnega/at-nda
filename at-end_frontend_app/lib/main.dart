import 'package:flutter/material.dart';

import 'pages/api_test_page.dart';
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
        return MaterialApp(
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
            '/attendance-records': (_) =>
                appSelectableScope(const AttendanceRecordsPage()),
            '/profile': (_) => appSelectableScope(const ProfilePage()),
            '/settings': (_) => appSelectableScope(const SettingsPage()),
            '/rep-sessions': (_) => appSelectableScope(const RepSessionPage()),
            '/api-test': (_) => appSelectableScope(const ApiTestPage()),
          },
        );
      },
    );
  }
}
