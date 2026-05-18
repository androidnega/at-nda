import 'package:flutter/material.dart';

import '../models/student.dart';

class StudentMidnightControlDashboard extends StatelessWidget {
  const StudentMidnightControlDashboard({
    super.key,
    required this.student,
    required this.showMarkButton,
    required this.onMarkAttendance,
    required this.primaryActionLabel,
    required this.onOpenDrawer,
    required this.onBell,
    required this.statsClassesToday,
    required this.statsLiveSessions,
    required this.statsMarkedToday,
    required this.unmarkedCount,
    required this.dynamicBlocks,
    required this.warningBanner,
    required this.pendingSyncChip,
    required this.errorText,
    required this.riskSection,
  });

  final Student student;
  final bool showMarkButton;
  final VoidCallback onMarkAttendance;
  final String primaryActionLabel;
  final VoidCallback onOpenDrawer;
  final VoidCallback onBell;
  final int statsClassesToday;
  final int statsLiveSessions;
  final int statsMarkedToday;
  final int unmarkedCount;
  final List<Widget> dynamicBlocks;
  final Widget? warningBanner;
  final Widget? pendingSyncChip;
  final String? errorText;
  final Widget? riskSection;

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    final cards = [
      _tile('Live', '$statsLiveSessions', Icons.bolt_rounded, const Color(0xFFF97316)),
      _tile('Classes', '$statsClassesToday', Icons.calendar_today_rounded, const Color(0xFF06B6D4)),
      _tile('Marked', '$statsMarkedToday', Icons.task_alt_rounded, const Color(0xFF22C55E)),
      _tile('Pending', '$unmarkedCount', Icons.pending_actions_rounded, const Color(0xFFEAB308)),
    ];

    return ColoredBox(
      color: const Color(0xFF2D2F34),
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.fromLTRB(14, MediaQuery.paddingOf(context).top > 0 ? 6 : 12, 14, 12),
              child: Column(
                children: [
                  Row(
                    children: [
                      IconButton(
                        onPressed: onOpenDrawer,
                        icon: const Icon(Icons.menu_rounded, color: Colors.white),
                      ),
                      Expanded(
                        child: Text(
                          'Hey ${student.greetingLastName}!',
                          style: tt.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
                        ),
                      ),
                      IconButton(
                        onPressed: onBell,
                        icon: const Icon(Icons.notifications_none_rounded, color: Colors.white),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (showMarkButton)
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: onMarkAttendance,
                        icon: const Icon(Icons.touch_app_rounded, size: 18),
                        label: Text(primaryActionLabel),
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF06B6D4),
                          foregroundColor: const Color(0xFF0B1A23),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          padding: const EdgeInsets.symmetric(vertical: 14),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 10),
            sliver: SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 1.45,
              ),
              delegate: SliverChildBuilderDelegate((context, i) => cards[i], childCount: cards.length),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
            sliver: SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (dynamicBlocks.isNotEmpty) ...dynamicBlocks,
                  if (warningBanner != null) ...[const SizedBox(height: 10), warningBanner!],
                  if (pendingSyncChip != null) ...[const SizedBox(height: 10), pendingSyncChip!],
                  if (riskSection != null) ...[const SizedBox(height: 10), riskSection!],
                  if (errorText != null && errorText!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Text(errorText!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _tile(String label, String value, IconData icon, Color accent) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF24262B),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF44474D)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(color: accent.withValues(alpha: 0.15), shape: BoxShape.circle),
            alignment: Alignment.center,
            child: Icon(icon, color: accent, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFFBCC1CB), fontSize: 12)),
                const SizedBox(height: 3),
                Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
