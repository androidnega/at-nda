import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';

class IntroOnboardingPage extends StatefulWidget {
  const IntroOnboardingPage({super.key});

  @override
  State<IntroOnboardingPage> createState() => _IntroOnboardingPageState();
}

class _IntroOnboardingPageState extends State<IntroOnboardingPage> {
  static const _introSeenKey = 'intro_onboarding_seen_v1';
  final PageController _controller = PageController();
  int _index = 0;

  /// This page uses [Scaffold.backgroundColor] white. Theme `onSurface` is light in
  /// dark mode, so headlines must use fixed dark brand tones (teal / emerald).
  static Color _headlineOnLightBg(ColorScheme cs) =>
      Color.lerp(cs.primary, const Color(0xFF042F2E), 0.52)!;

  static Color _bodyOnLightBg(ColorScheme cs) =>
      Color.lerp(cs.primary, const Color(0xFF134E4A), 0.42)!;

  /// Assets used as the visual on a slide. When set, [icon] is ignored.
  static const _studentAccessAsset =
      'assets/images/onboarding/student-access-hero.png';
  static const _attendanceMarkedAsset =
      'assets/images/onboarding/attendance-marked-hero.png';

  static const _items = <({String title, String text, IconData? icon, String? imageAsset})>[
    (
      title: 'Sign In With Your Student ID',
      text: 'Use your official ID to open your a-tenda account',
      icon: null,
      imageAsset: _studentAccessAsset,
    ),
    (
      title: 'Attendance, Simplified',
      text: 'Track attendance with ease and clarity',
      icon: Icons.fact_check_rounded,
      imageAsset: null,
    ),
    (
      title: 'Start Sessions Instantly',
      text: 'Open attendance sessions in seconds',
      icon: Icons.play_circle_outline_rounded,
      imageAsset: null,
    ),
    (
      title: 'Fast Student Check-In',
      text: 'QR-based system for quick attendance',
      icon: Icons.qr_code_2_rounded,
      imageAsset: null,
    ),
    (
      title: 'You Are All Set',
      text: 'Mark attendance in seconds, even offline',
      icon: null,
      imageAsset: _attendanceMarkedAsset,
    ),
  ];

  Future<void> _completeIntro() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_introSeenKey, true);
    if (!mounted) return;
    Navigator.of(context).pushReplacementNamed('/login');
  }

  /// Slide visual: bitmap when [imageAsset] is set, otherwise a tinted icon.
  Widget _slideVisual(BuildContext context, int i, double t) {
    final cs = Theme.of(context).colorScheme;
    final item = _items[i];
    final imageAsset = item.imageAsset;

    Widget visual;
    if (imageAsset != null) {
      // Constrained box keeps the hero from dominating; fits ~260 dp tall on a
      // typical phone, smaller on compact devices.
      visual = ConstrainedBox(
        constraints: const BoxConstraints(
          maxHeight: 280,
          maxWidth: 320,
        ),
        child: Image.asset(
          imageAsset,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.medium,
        ),
      );
    } else {
      visual = Icon(
        item.icon ?? Icons.fact_check_rounded,
        size: 132,
        color: cs.primary.withValues(alpha: 0.92),
      );
    }

    if (i == 0) {
      final scale = 0.88 + (0.12 * t);
      return Opacity(
        opacity: t,
        child: Transform.scale(scale: scale, child: visual),
      );
    }
    if (i == 1) {
      final dx = (1 - t) * 42;
      return Transform.translate(
        offset: Offset(dx, 0),
        child: Opacity(opacity: t, child: visual),
      );
    }
    if (i == 2) {
      final bounce = Curves.elasticOut.transform(t);
      return Transform.scale(
        scale: 0.85 + (0.15 * bounce),
        child: Opacity(opacity: t, child: visual),
      );
    }
    final dy = (1 - t) * 24;
    return Transform.translate(
      offset: Offset(0, dy),
      child: Opacity(opacity: t, child: visual),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final last = _index == _items.length - 1;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: _items.length,
                physics: const BouncingScrollPhysics(),
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) {
                  final item = _items[i];
                  return TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0, end: 1),
                    duration: const Duration(milliseconds: 520),
                    curve: Curves.easeOut,
                    builder: (context, t, _) {
                      return Padding(
                        padding: const EdgeInsets.fromLTRB(26, 20, 26, 8),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            _slideVisual(context, i, t),
                            const SizedBox(height: 24),
                            Text(
                              item.title,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: _headlineOnLightBg(cs),
                                    height: 1.22,
                                  ),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              item.text,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w500,
                                    color: _bodyOnLightBg(cs),
                                    height: 1.35,
                                  ),
                            ),
                            if (last) ...[
                              const SizedBox(height: 22),
                              Text(
                                'Developed by Manuel',
                                style: GoogleFonts.inter(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w500,
                                  letterSpacing: 0.35,
                                  color: _bodyOnLightBg(cs)
                                      .withValues(alpha: 0.85),
                                ),
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 18),
              child: Row(
                children: [
                  Row(
                    children: List.generate(_items.length, (i) {
                      final selected = i == _index;
                      return AnimatedContainer(
                        duration: const Duration(milliseconds: 220),
                        margin: const EdgeInsets.only(right: 6),
                        width: selected ? 18 : 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: selected
                              ? cs.primary
                              : cs.primary.withValues(alpha: 0.22),
                          borderRadius: BorderRadius.circular(99),
                        ),
                      );
                    }),
                  ),
                  const Spacer(),
                  if (!last)
                    TextButton(
                      onPressed: () {
                        _controller.nextPage(
                          duration: const Duration(milliseconds: 320),
                          curve: Curves.easeOutBack,
                        );
                      },
                      child: const Text('Next'),
                    ),
                  if (last)
                    FilledButton(
                      onPressed: _completeIntro,
                      child: const Text('Get Started'),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

