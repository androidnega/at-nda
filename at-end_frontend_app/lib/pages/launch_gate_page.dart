import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../services/offline_service.dart';
import '../utils/app_selectable_scope.dart';
import 'intro_onboarding_page.dart';
import 'login_page.dart';

class LaunchGatePage extends StatefulWidget {
  const LaunchGatePage({super.key});

  @override
  State<LaunchGatePage> createState() => _LaunchGatePageState();
}

class _LaunchGatePageState extends State<LaunchGatePage> {
  static const _introSeenKey = 'intro_onboarding_seen_v1';

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final prefs = await SharedPreferences.getInstance();
    final seen = prefs.getBool(_introSeenKey) ?? false;
    if (!seen) {
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => appSelectableScope(const IntroOnboardingPage()),
        ),
      );
      return;
    }

    final student = await OfflineService.getCurrentStudent();
    if (!mounted) return;
    if (student == null) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => appSelectableScope(const LoginPage())),
      );
      return;
    }

    final role = student.primaryRole;
    final route = role == 'class_rep'
        ? '/rep-home'
        : role == 'lecturer'
            ? '/lecturer-home'
            : '/home';

    Navigator.of(context).pushReplacementNamed(route);
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}

