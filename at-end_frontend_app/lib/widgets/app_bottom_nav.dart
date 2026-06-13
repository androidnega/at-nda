import 'package:flutter/material.dart';

/// Primary navigation roles for the bottom bar. Determines tab labels,
/// icons, and the destination of [AppPrimaryFab].
enum AppNavRole { student, rep }

/// Active tab on the bottom bar. We keep this in [HomePage]'s state so
/// the bar correctly highlights "Home" when the user is on the home
/// screen and "—" when the user has pushed into another screen.
enum AppNavTab {
  home,
  history,
  records,
  classView,
  profile,
}

/// Material 3 bottom navigation for the student / class rep shell.
///
/// This is intentionally a thin wrapper around [NavigationBar] — no
/// business logic lives in here. Routes are looked up by name so we
/// keep the route map in `main.dart` as the single source of truth.
class AppBottomNav extends StatelessWidget {
  const AppBottomNav({
    super.key,
    required this.role,
    required this.currentTab,
    this.onHomePressed,
    this.onHistoryPressed,
    this.onRecordsPressed,
    this.onClassPressed,
    this.onProfilePressed,
  });

  final AppNavRole role;
  final AppNavTab currentTab;

  /// Called when "Home" is tapped while not on the home tab. When this
  /// fires the page is expected to pop back to its own root.
  final VoidCallback? onHomePressed;
  final VoidCallback? onHistoryPressed;
  final VoidCallback? onRecordsPressed;
  final VoidCallback? onClassPressed;
  final VoidCallback? onProfilePressed;

  @override
  Widget build(BuildContext context) {
    final destinations = _destinations();
    final currentIndex =
        destinations.indexWhere((d) => d.tab == currentTab).clamp(0, destinations.length - 1);

    return NavigationBar(
      selectedIndex: currentIndex < 0 ? 0 : currentIndex,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      onDestinationSelected: (i) {
        final dest = destinations[i];
        if (dest.tab == currentTab) return;
        dest.onTap?.call();
      },
      destinations: [
        for (final d in destinations)
          NavigationDestination(
            icon: Icon(d.icon),
            selectedIcon: Icon(d.selectedIcon),
            label: d.label,
          ),
      ],
    );
  }

  List<_NavDestination> _destinations() {
    if (role == AppNavRole.rep) {
      return [
        _NavDestination(
          tab: AppNavTab.home,
          icon: Icons.dashboard_outlined,
          selectedIcon: Icons.dashboard,
          label: 'Home',
          onTap: onHomePressed,
        ),
        _NavDestination(
          tab: AppNavTab.records,
          icon: Icons.fact_check_outlined,
          selectedIcon: Icons.fact_check,
          label: 'Records',
          onTap: onRecordsPressed,
        ),
        _NavDestination(
          tab: AppNavTab.classView,
          icon: Icons.groups_outlined,
          selectedIcon: Icons.groups,
          label: 'Class',
          onTap: onClassPressed,
        ),
        _NavDestination(
          tab: AppNavTab.profile,
          icon: Icons.person_outline,
          selectedIcon: Icons.person,
          label: 'Profile',
          onTap: onProfilePressed,
        ),
      ];
    }

    return [
      _NavDestination(
        tab: AppNavTab.home,
        icon: Icons.home_outlined,
        selectedIcon: Icons.home,
        label: 'Home',
        onTap: onHomePressed,
      ),
      // Per-week attendance grid (✓ / ✗ / CANCELLED) — same look as
      // the PDF preview reps see on the web.
      _NavDestination(
        tab: AppNavTab.records,
        icon: Icons.fact_check_outlined,
        selectedIcon: Icons.fact_check,
        label: 'Records',
        onTap: onRecordsPressed,
      ),
      _NavDestination(
        tab: AppNavTab.history,
        icon: Icons.history_outlined,
        selectedIcon: Icons.history,
        label: 'History',
        onTap: onHistoryPressed,
      ),
      _NavDestination(
        tab: AppNavTab.profile,
        icon: Icons.person_outline,
        selectedIcon: Icons.person,
        label: 'Profile',
        onTap: onProfilePressed,
      ),
    ];
  }
}

class _NavDestination {
  _NavDestination({
    required this.tab,
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.onTap,
  });

  final AppNavTab tab;
  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final VoidCallback? onTap;
}
