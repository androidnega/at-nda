import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/dashboard_surfaces.dart';
import '../widgets/modern_pull_to_refresh.dart';

class LecturerDashboardPage extends StatefulWidget {
  const LecturerDashboardPage({super.key});

  @override
  State<LecturerDashboardPage> createState() => _LecturerDashboardPageState();
}

class _LecturerDashboardPageState extends State<LecturerDashboardPage> {
  Future<void> _onRefresh() async {
    await Future<void>.delayed(const Duration(milliseconds: 320));
    if (!mounted) return;
    await HapticFeedback.lightImpact();
  }

  Widget _tile(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String subtitle,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: DashboardSurfaces.cardDecoration(context),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 22, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 10),
            Text(
              label,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 6),
            Text(
              subtitle,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Lecturer Panel')),
      body: SafeArea(
        child: ModernPullToRefresh(
          onRefresh: _onRefresh,
          child: CustomScrollView(
            physics: modernPullToRefreshPhysics,
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                sliver: SliverToBoxAdapter(
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: DashboardSurfaces.cardDecoration(context),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Lecturer Dashboard',
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Monitor sessions and attendance activity.',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: Theme.of(context).colorScheme.onSurfaceVariant,
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 1.18,
                  ),
                  delegate: SliverChildListDelegate(
                    [
                      _tile(
                        context,
                        icon: Icons.event_note_outlined,
                        label: 'View Sessions',
                        subtitle: 'Check session activity and status.',
                      ),
                      _tile(
                        context,
                        icon: Icons.analytics_outlined,
                        label: 'Attendance Monitor',
                        subtitle: 'Review student attendance patterns.',
                      ),
                      _tile(
                        context,
                        icon: Icons.task_alt_outlined,
                        label: 'Approvals',
                        subtitle: 'Approve sessions when backend supports it.',
                      ),
                      _tile(
                        context,
                        icon: Icons.settings_outlined,
                        label: 'Settings',
                        subtitle: 'Open profile and application settings.',
                        onTap: () => Navigator.of(context).pushNamed('/settings'),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
