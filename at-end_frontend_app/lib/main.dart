import 'package:flutter/material.dart';

import 'pages/api_test_page.dart';
import 'pages/attendance_page.dart';
import 'pages/home_page.dart';
import 'pages/login_page.dart';
import 'pages/profile_page.dart';
import 'pages/class_rep_students_page.dart';
import 'pages/rep_home_page.dart';
import 'pages/rep_session_page.dart';
import 'pages/settings_page.dart';
import 'services/theme_service.dart';
import 'theme/app_theme.dart';
import 'utils/app_selectable_scope.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ThemeService.load();
  runApp(const AttendanceApp());
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
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: mode,
          initialRoute: '/',
          routes: {
            '/': (_) => appSelectableScope(const LoginPage()),
            '/home': (_) => appSelectableScope(const HomePage()),
            '/rep-home': (_) => appSelectableScope(const RepHomePage()),
            '/class-rep/students': (_) =>
                appSelectableScope(const ClassRepStudentsPage()),
            '/attendance': (_) => appSelectableScope(const AttendancePage()),
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
