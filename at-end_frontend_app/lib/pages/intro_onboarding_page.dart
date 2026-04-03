import 'package:flutter/material.dart';
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

  static const _flashAsset = 'assets/images/flashimage.png';

  /// This page uses [Scaffold.backgroundColor] white. Theme `onSurface` is light in
  /// dark mode, so headlines must use fixed dark brand tones (teal / emerald).
  static Color _headlineOnLightBg(ColorScheme cs) =>
      Color.lerp(cs.primary, const Color(0xFF042F2E), 0.52)!;

  static Color _bodyOnLightBg(ColorScheme cs) =>
      Color.lerp(cs.primary, const Color(0xFF134E4A), 0.42)!;

  static const _items = <({String title, String text})>[
    (
      title: 'Attendance, Simplified',
      text: 'Track attendance with ease and clarity',
    ),
    (
      title: 'Start Sessions Instantly',
      text: 'Open attendance sessions in seconds',
    ),
    (
      title: 'Fast Student Check-In',
      text: 'QR-based system for quick attendance',
    ),
    (
      title: 'Built for Your Institution',
      text: 'Designed for students, reps, and lecturers',
    ),
  ];

  Future<void> _completeIntro() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_introSeenKey, true);
    if (!mounted) return;
    Navigator.of(context).pushReplacementNamed('/login');
  }

  /// Hero image only on slide 0; slides 1–3 use large icons with distinct motion.
  Widget _slideVisual(BuildContext context, int i, double t) {
    final cs = Theme.of(context).colorScheme;

    if (i == 0) {
      final scale = 0.93 + (0.07 * t);
      return LayoutBuilder(
        builder: (context, constraints) {
          final side = (constraints.maxWidth * 0.94).clamp(320.0, 460.0);
          final img = Image.asset(
            _flashAsset,
            width: side,
            height: side,
            fit: BoxFit.contain,
            filterQuality: FilterQuality.medium,
            gaplessPlayback: true,
          );
          return Opacity(
            opacity: t,
            child: Transform.scale(scale: scale, child: img),
          );
        },
      );
    }

    late final IconData icon;
    if (i == 1) {
      icon = Icons.play_circle_outline_rounded;
    } else if (i == 2) {
      icon = Icons.qr_code_2_rounded;
    } else {
      icon = Icons.groups_2_rounded;
    }

    final iconWidget = Icon(
      icon,
      size: 132,
      color: cs.primary.withValues(alpha: 0.92),
    );

    if (i == 1) {
      final dx = (1 - t) * 42;
      return Transform.translate(
        offset: Offset(dx, 0),
        child: Opacity(opacity: t, child: iconWidget),
      );
    }
    if (i == 2) {
      final bounce = Curves.elasticOut.transform(t);
      return Transform.scale(
        scale: 0.85 + (0.15 * bounce),
        child: Opacity(opacity: t, child: iconWidget),
      );
    }
    final dy = (1 - t) * 24;
    return Transform.translate(
      offset: Offset(0, dy),
      child: Opacity(opacity: t, child: iconWidget),
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
                              const SizedBox(height: 18),
                              Text(
                                'Powered by AuswebLabs',
                                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                                      fontWeight: FontWeight.w600,
                                      letterSpacing: 0.2,
                                      color: _bodyOnLightBg(cs),
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

