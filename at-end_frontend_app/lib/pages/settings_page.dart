import 'dart:async';

import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../services/logout_lock_prefs.dart';
import '../services/notification_bridge.dart';
import '../services/notification_prefs.dart';
import '../services/offline_service.dart';
import '../services/sync_service.dart';
import '../services/theme_service.dart';
import '../utils/app_selectable_scope.dart';
import 'login_page.dart';

/// Appearance, sync, notifications — minimal mobile layout.
class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  bool _syncing = false;
  bool _logoutAllowed = true;
  String? _logoutLockHint;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_refreshLogoutLock());
    });
  }

  Future<void> _refreshLogoutLock() async {
    final allow = await LogoutLockPrefs.canLogoutNow();
    final hint = allow ? null : await LogoutLockPrefs.signOutBlockedHint();
    if (!mounted) return;
    setState(() {
      _logoutAllowed = allow;
      _logoutLockHint = hint;
    });
  }

  Future<void> _syncData() async {
    setState(() => _syncing = true);
    try {
      await SyncService.syncAttendance();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Synced')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Sync failed: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _syncing = false);
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
    if (!_logoutAllowed) {
      final msg = _logoutLockHint ??
          'This account stays signed in on this device for the current period.';
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
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
      builder: (_) => AlertDialog(
        title: const Text('Log out'),
        content: const Text('Clear this account on this device?'),
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
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
      ),
      body: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        children: [
          Text(
            'Appearance',
            style: tt.labelLarge?.copyWith(
              color: cs.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          ValueListenableBuilder<ThemeMode>(
            valueListenable: ThemeService.modeNotifier,
            builder: (context, mode, _) {
              return SegmentedButton<ThemeMode>(
                segments: const [
                  ButtonSegment(
                    value: ThemeMode.light,
                    label: Text('Light'),
                    icon: Icon(Icons.light_mode_outlined, size: 18),
                  ),
                  ButtonSegment(
                    value: ThemeMode.system,
                    label: Text('Auto'),
                    icon: Icon(Icons.brightness_auto_outlined, size: 18),
                  ),
                  ButtonSegment(
                    value: ThemeMode.dark,
                    label: Text('Dark'),
                    icon: Icon(Icons.dark_mode_outlined, size: 18),
                  ),
                ],
                selected: {mode},
                onSelectionChanged: (s) {
                  if (s.isEmpty) return;
                  ThemeService.setTheme(s.first);
                },
              );
            },
          ),
          const SizedBox(height: 28),
          Text(
            'Account',
            style: tt.labelLarge?.copyWith(
              color: cs.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.person_outline_rounded, color: cs.primary),
            title: const Text('Profile'),
            trailing: const Icon(Icons.chevron_right, size: 20),
            onTap: () => Navigator.of(context).pushNamed('/profile'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: _syncing
                ? SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: cs.primary,
                    ),
                  )
                : Icon(Icons.sync_rounded, color: cs.primary),
            title: const Text('Sync attendance'),
            subtitle: Text(
              'Upload pending marks',
              style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
            ),
            onTap: _syncing ? null : _syncData,
          ),
          ListTile(
            enabled: _logoutAllowed,
            contentPadding: EdgeInsets.zero,
            leading: Icon(
              Icons.logout_rounded,
              color: _logoutAllowed ? cs.error : cs.onSurfaceVariant,
            ),
            title: Text(
              'Log out',
              style: TextStyle(
                color: _logoutAllowed ? cs.error : cs.onSurfaceVariant,
              ),
            ),
            subtitle: _logoutLockHint != null && !_logoutAllowed
                ? Text(
                    _logoutLockHint!,
                    style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                  )
                : null,
            onTap: _confirmLogout,
          ),
          const SizedBox(height: 20),
          Text(
            'Notifications',
            style: tt.labelLarge?.copyWith(
              color: cs.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          ValueListenableBuilder<bool>(
            valueListenable: NotificationPrefs.enabledNotifier,
            builder: (context, on, _) {
              return SwitchListTile(
                contentPadding: EdgeInsets.zero,
                secondary: Icon(
                  Icons.notifications_outlined,
                  color: cs.primary,
                ),
                title: const Text('In-app reminders'),
                subtitle: Text(
                  on ? 'On' : 'Off',
                  style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                ),
                value: on,
                onChanged: (v) async {
                  await NotificationPrefs.setEnabled(v);
                  if (v) await NotificationBridge.pollPending();
                },
              );
            },
          ),
          const SizedBox(height: 24),
          FutureBuilder<PackageInfo>(
            future: PackageInfo.fromPlatform(),
            builder: (context, snap) {
              final v = snap.data?.version ?? '—';
              final b = snap.data?.buildNumber ?? '—';
              return Text(
                'at-enda · v$v ($b)',
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              );
            },
          ),
        ],
      ),
    );
  }
}
