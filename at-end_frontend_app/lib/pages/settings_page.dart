import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../services/notification_bridge.dart';
import '../services/notification_prefs.dart';
import '../services/offline_service.dart';
import '../services/sync_service.dart';
import '../services/theme_service.dart';
import '../theme/dashboard_surfaces.dart';
import '../utils/app_selectable_scope.dart';
import 'login_page.dart';

/// Appearance, profile, refresh, logout — compact layout.
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
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          ValueListenableBuilder<ThemeMode>(
            valueListenable: ThemeService.modeNotifier,
            builder: (context, mode, _) {
              return Container(
                padding: const EdgeInsets.all(16),
                decoration: DashboardSurfaces.cardDecoration(context),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.palette_outlined, size: 22, color: cs.primary),
                        const SizedBox(width: 10),
                        Text(
                          'Appearance',
                          style: tt.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Choose how the app looks. System follows your phone setting.',
                      style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                    ),
                    const SizedBox(height: 14),
                    _themeOption(
                      context,
                      selected: mode,
                      value: ThemeMode.light,
                      icon: Icons.light_mode_outlined,
                      label: 'Light',
                    ),
                    const SizedBox(height: 8),
                    _themeOption(
                      context,
                      selected: mode,
                      value: ThemeMode.system,
                      icon: Icons.brightness_auto_outlined,
                      label: 'System',
                    ),
                    const SizedBox(height: 8),
                    _themeOption(
                      context,
                      selected: mode,
                      value: ThemeMode.dark,
                      icon: Icons.dark_mode_outlined,
                      label: 'Dark',
                    ),
                  ],
                ),
              );
            },
          ),
          const SizedBox(height: 12),
          Container(
            decoration: DashboardSurfaces.cardDecoration(context),
            child: Column(
              children: [
                ListTile(
                  leading: Icon(Icons.person_outline_rounded, color: cs.primary),
                  title: const Text('Profile'),
                  subtitle: const Text('Update your details and photo'),
                  trailing: const Icon(Icons.chevron_right, size: 20),
                  onTap: () =>
                      Navigator.of(context).pushNamed('/profile').then((_) {
                        if (mounted) setState(() {});
                      }),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: _refreshing
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
                  subtitle: const Text('Send any pending marks from this device'),
                  onTap: _refreshing ? null : _refreshData,
                ),
                const Divider(height: 1),
                ListTile(
                  leading: Icon(Icons.logout_rounded, color: cs.error),
                  title: Text('Log out', style: TextStyle(color: cs.error)),
                  onTap: _confirmLogout,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            decoration: DashboardSurfaces.cardDecoration(context),
            child: ValueListenableBuilder<bool>(
              valueListenable: NotificationPrefs.enabledNotifier,
              builder: (context, on, _) {
                return SwitchListTile(
                  secondary: Icon(
                    Icons.notifications_outlined,
                    color: cs.primary,
                  ),
                  title: const Text('In-app reminders'),
                  subtitle: Text(
                    on
                        ? 'You’ll see class reminders here when your school sends them.'
                        : 'Turn on to start receiving reminders and notices in the app.',
                    style: tt.bodySmall?.copyWith(
                      color: cs.onSurfaceVariant,
                      height: 1.35,
                    ),
                  ),
                  value: on,
                  onChanged: (v) async {
                    await NotificationPrefs.setEnabled(v);
                    if (v) {
                      await NotificationBridge.pollPending();
                    }
                  },
                );
              },
            ),
          ),
          const SizedBox(height: 20),
          Text(
            'About',
            style: tt.titleSmall?.copyWith(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 8),
          FutureBuilder<PackageInfo>(
            future: PackageInfo.fromPlatform(),
            builder: (context, snap) {
              final v = snap.data?.version ?? '—';
              final b = snap.data?.buildNumber ?? '—';
              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: DashboardSurfaces.cardDecoration(context),
                child: Row(
                  children: [
                    Icon(Icons.info_outline_rounded, color: cs.primary, size: 22),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'at-enda · Manuel (TTU CS) · Managed by AuswebLabs',
                          ),
                          Text(
                            'Version $v ($b)',
                            style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _themeOption(
    BuildContext context, {
    required ThemeMode selected,
    required ThemeMode value,
    required IconData icon,
    required String label,
  }) {
    final cs = Theme.of(context).colorScheme;
    final on = selected == value;
    return Material(
      color: on ? cs.primaryContainer.withValues(alpha: 0.35) : cs.surfaceContainerHighest.withValues(alpha: 0.4),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () => ThemeService.setTheme(value),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Icon(icon, size: 22, color: on ? cs.primary : cs.onSurfaceVariant),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: on ? FontWeight.w700 : FontWeight.w500,
                      ),
                ),
              ),
              if (on)
                Icon(Icons.check_circle_rounded, color: cs.primary, size: 22),
            ],
          ),
        ),
      ),
    );
  }
}
