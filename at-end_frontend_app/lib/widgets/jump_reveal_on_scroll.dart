import 'package:flutter/material.dart';

/// Reveals [child] as it enters the viewport: slight scale-up + slide with an
/// ease-out-back curve for a subtle “jump” / bounce common in product lists.
class JumpRevealOnScroll extends StatefulWidget {
  const JumpRevealOnScroll({
    super.key,
    required this.scrollController,
    required this.child,
  });

  final ScrollController scrollController;
  final Widget child;

  @override
  State<JumpRevealOnScroll> createState() => _JumpRevealOnScrollState();
}

class _JumpRevealOnScrollState extends State<JumpRevealOnScroll> {
  final GlobalKey _boxKey = GlobalKey();
  double _t = 0;

  @override
  void initState() {
    super.initState();
    widget.scrollController.addListener(_tick);
    WidgetsBinding.instance.addPostFrameCallback((_) => _compute());
  }

  @override
  void didUpdateWidget(covariant JumpRevealOnScroll oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.scrollController != widget.scrollController) {
      oldWidget.scrollController.removeListener(_tick);
      widget.scrollController.addListener(_tick);
    }
  }

  @override
  void dispose() {
    widget.scrollController.removeListener(_tick);
    super.dispose();
  }

  void _tick() => _compute();

  void _compute() {
    final ctx = _boxKey.currentContext;
    if (!mounted || ctx == null) return;
    final box = ctx.findRenderObject() as RenderBox?;
    if (box == null || !box.hasSize) return;

    final media = MediaQuery.of(ctx);
    final screenH = media.size.height;
    final top = box.localToGlobal(Offset.zero).dy;

    const enterFromBottom = 0.91;
    const settled = 0.54;
    final hi = screenH * enterFromBottom;
    final lo = screenH * settled;

    final double rawT;
    if (top <= lo) {
      rawT = 1;
    } else if (top >= hi) {
      rawT = 0;
    } else {
      rawT = 1 - (top - lo) / (hi - lo);
    }
    final eased = Curves.easeOutBack.transform(rawT.clamp(0.0, 1.0));
    if ((eased - _t).abs() > 0.01) {
      setState(() => _t = eased);
    }
  }

  @override
  Widget build(BuildContext context) {
    return KeyedSubtree(
      key: _boxKey,
      child: Opacity(
        opacity: _t.clamp(0.0, 1.0),
        child: Transform.translate(
          offset: Offset(0, 22 * (1 - _t)),
          child: Transform.scale(
            scale: (0.9 + 0.1 * _t).clamp(0.9, 1.02),
            alignment: Alignment.centerLeft,
            filterQuality: FilterQuality.medium,
            child: widget.child,
          ),
        ),
      ),
    );
  }
}
