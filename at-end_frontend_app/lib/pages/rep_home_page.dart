import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/last_attendance_prefs.dart';
import '../services/offline_service.dart';
import '../utils/greeting_util.dart';
import '../widgets/profile_avatar.dart';
import 'login_page.dart';
import 'rep_session_page.dart';

/// Entry screen for class reps: session management first; student attendance is secondary.
class RepHomePage extends StatefulWidget {
  const RepHomePage({super.key});

  @override
  State<RepHomePage> createState() => _RepHomePageState();
}

class _RepHomePageState extends State<RepHomePage> {
  Student? _student;

  @override
  void initState() {
    super.initState();
    _loadStudent();
  }

  Future<void> _loadStudent() async {
    final s = await OfflineService.getCurrentStudent();
    if (!mounted) return;
    if (s == null) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => SelectionArea(child: const LoginPage()),
        ),
        (_) => false,
      );
      return;
    }
    setState(() => _student = s);
  }

  String _greetingName(Student s) {
    final fl = '${s.firstName ?? ''} ${s.lastName ?? ''}'.trim();
    if (fl.isNotEmpty) return fl;
    final n = s.name.trim();
    if (n.isNotEmpty) return n;
    return s.indexNumber;
  }

  Future<void> _logout() async {
    await LastAttendancePrefs.clear();
    await OfflineService.clearCurrentStudent();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(
        builder: (_) => SelectionArea(child: const LoginPage()),
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

  @override
  Widget build(BuildContext context) {
    final s = _student;
    final colorScheme = Theme.of(context).colorScheme;

    if (s == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Class rep'),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).pushNamed('/settings'),
          ),
        ],
      ),
      drawer: Drawer(
        child: SafeArea(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              UserAccountsDrawerHeader(
                decoration: BoxDecoration(color: colorScheme.primaryContainer),
                currentAccountPicture: ProfileAvatar(student: s, radius: 36),
                accountName: Text(
                  _greetingName(s),
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                accountEmail: Text(
                  s.indexNumber,
                  style: TextStyle(
                    color: colorScheme.onPrimaryContainer.withValues(alpha: 0.85),
                  ),
                ),
              ),
              ListTile(
                leading: const Icon(Icons.event_seat_outlined),
                title: const Text('Manage sessions'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).push<void>(
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          SelectionArea(child: const RepSessionPage()),
                    ),
                  );
                },
              ),
              ListTile(
                leading: const Icon(Icons.how_to_reg_outlined),
                title: const Text('My attendance'),
                subtitle: const Text('Mark your own attendance'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/home');
                },
              ),
              ListTile(
                leading: const Icon(Icons.person_outline),
                title: const Text('Profile'),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.of(context).pushNamed('/profile');
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
                leading: Icon(Icons.logout, color: colorScheme.error),
                title: Text('Log out', style: TextStyle(color: colorScheme.error)),
                onTap: () {
                  Navigator.pop(context);
                  _confirmLogout();
                },
              ),
            ],
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  ProfileAvatar(student: s, radius: 40),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          getGreeting(),
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: colorScheme.onSurfaceVariant,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _greetingName(s),
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerLeft,
                child: Chip(
                  avatar: Icon(Icons.badge_outlined, size: 18, color: colorScheme.primary),
                  label: const Text('Class representative'),
                  side: BorderSide(color: colorScheme.outlineVariant),
                ),
              ),
              const SizedBox(height: 28),
              Text(
                'Open or close live attendance for your class, show the session QR, and help classmates check in.',
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: colorScheme.onSurfaceVariant,
                      height: 1.45,
                    ),
              ),
              const SizedBox(height: 28),
              FilledButton.icon(
                onPressed: () {
                  Navigator.of(context).push<void>(
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          SelectionArea(child: const RepSessionPage()),
                    ),
                  );
                },
                icon: const Icon(Icons.event_available_rounded),
                label: const Text('Manage class sessions'),
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.of(context).pushNamed('/home');
                },
                icon: const Icon(Icons.how_to_reg_outlined),
                label: const Text('My attendance (as student)'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
