import 'package:flutter/material.dart';

import '../services/offline_service.dart';
import '../services/sync_service.dart';
import '../services/theme_service.dart';
import '../utils/app_selectable_scope.dart';
import 'login_page.dart';

/// Appearance, profile shortcut, refresh, logout.
class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  bool _refreshing = false;

  Future<void> _refreshData() async {
    setState(() => _refreshing = true);
    try {
      await SyncService.syncAttendance();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Data refreshed')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Refresh failed: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _logout() async {
    await OfflineService.clearCurrentStudent();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => appSelectableScope(const LoginPage())),
      (_) => false,
    );
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
    if (ok == true) await _logout();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ValueListenableBuilder<ThemeMode>(
            valueListenable: ThemeService.modeNotifier,
            builder: (context, mode, _) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Appearance',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  const SizedBox(height: 8),
                  SegmentedButton<ThemeMode>(
                    segments: const [
                      ButtonSegment<ThemeMode>(
                        value: ThemeMode.light,
                        label: Text('Light'),
                        icon: Icon(Icons.light_mode_outlined),
                      ),
                      ButtonSegment<ThemeMode>(
                        value: ThemeMode.system,
                        label: Text('System'),
                        icon: Icon(Icons.brightness_auto_outlined),
                      ),
                      ButtonSegment<ThemeMode>(
                        value: ThemeMode.dark,
                        label: Text('Dark'),
                        icon: Icon(Icons.dark_mode_outlined),
                      ),
                    ],
                    selected: {mode},
                    onSelectionChanged: (s) {
                      if (s.isEmpty) return;
                      ThemeService.setTheme(s.first);
                    },
                  ),
                ],
              );
            },
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.person_outline_rounded),
            title: const Text('Profile update'),
            subtitle: const Text('Name, email, phone, photo'),
            onTap: () =>
                Navigator.of(context).pushNamed('/profile').then((_) {
                  if (mounted) setState(() {});
                }),
          ),
          ListTile(
            leading: _refreshing
                ? const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded),
            title: const Text('Refresh data'),
            subtitle: const Text('Sync pending attendance & reload'),
            onTap: _refreshing ? null : _refreshData,
          ),
          ListTile(
            leading: Icon(Icons.logout, color: Theme.of(context).colorScheme.error),
            title: Text('Log out', style: TextStyle(color: Theme.of(context).colorScheme.error)),
            onTap: _confirmLogout,
          ),
        ],
      ),
    );
  }
}
