import 'package:flutter/material.dart';

import 'pages/api_test_page.dart';
import 'pages/attendance_page.dart';
import 'pages/home_page.dart';
import 'pages/login_page.dart';
import 'pages/profile_page.dart';
import 'pages/rep_session_page.dart';
import 'pages/settings_page.dart';
import 'services/theme_service.dart';
import 'theme/app_theme.dart';

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
            '/': (_) => SelectionArea(child: const LoginPage()),
            '/home': (_) => SelectionArea(child: const HomePage()),
            '/attendance': (_) => SelectionArea(child: const AttendancePage()),
            '/profile': (_) => SelectionArea(child: const ProfilePage()),
            '/settings': (_) => SelectionArea(child: const SettingsPage()),
            '/rep-sessions': (_) =>
                SelectionArea(child: const RepSessionPage()),
            '/api-test': (_) => SelectionArea(child: const ApiTestPage()),
          },
        );
      },
    );
  }
}
