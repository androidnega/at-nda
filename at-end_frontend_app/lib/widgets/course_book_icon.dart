import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

/// Course row icon: Font Awesome `bookOpen` with safe [Icons.menu_book] fallback.
class CourseBookIcon extends StatelessWidget {
  const CourseBookIcon({
    super.key,
    this.size = 18,
    this.color = const Color(0xFF15803D),
  });

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    try {
      return FaIcon(
        FontAwesomeIcons.bookOpen,
        size: size,
        color: color,
      );
    } catch (e, st) {
      assert(() {
        debugPrint('CourseBookIcon: FaIcon failed, using Icons.menu_book ($e)');
        debugPrint('$st');
        return true;
      }());
      return Icon(Icons.menu_book, size: size, color: color);
    }
  }
}
