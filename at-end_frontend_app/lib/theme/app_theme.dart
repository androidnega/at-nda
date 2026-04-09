import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Modern dark UI: deep slate + electric teal accent.
class AppTheme {
  static const Color _bg = Color(0xFF0B1220);
  static const Color _surface = Color(0xFF151E2E);
  static const Color _surfaceVariant = Color(0xFF1E2A3D);
  static const Color _accent = Color(0xFF2DD4BF);
  static const Color _accentDim = Color(0xFF14B8A6);

  static ThemeData dark() {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: ColorScheme.dark(
        surface: _surface,
        primary: _accent,
        onPrimary: Color(0xFF042F2E),
        secondary: _accentDim,
        tertiary: Color(0xFF67E8F9),
        error: Color(0xFFF87171),
        onSurface: Color(0xFFF1F5F9),
        onSurfaceVariant: Color(0xFF94A3B8),
      ),
      scaffoldBackgroundColor: _bg,
      appBarTheme: AppBarTheme(
        elevation: 0,
        centerTitle: true,
        backgroundColor: Colors.transparent,
        foregroundColor: Color(0xFFF1F5F9),
        titleTextStyle: GoogleFonts.inter(
          fontSize: 17,
          fontWeight: FontWeight.w600,
          color: Color(0xFFF1F5F9),
        ),
      ),
      cardTheme: CardThemeData(
        color: _surfaceVariant.withValues(alpha: 0.85),
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: Colors.white.withValues(alpha: 0.06)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: _bg.withValues(alpha: 0.6),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: _accent, width: 1.5),
        ),
        labelStyle: TextStyle(color: Color(0xFF94A3B8)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: _accent,
          foregroundColor: Color(0xFF042F2E),
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: GoogleFonts.inter(
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: _surfaceVariant,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      drawerTheme: DrawerThemeData(
        backgroundColor: _surface,
        elevation: 20,
        shadowColor: Colors.black.withValues(alpha: 0.55),
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.horizontal(right: Radius.circular(22)),
        ),
        width: 296,
        scrimColor: Colors.black.withValues(alpha: 0.52),
      ),
    );

    return base.copyWith(
      textTheme: GoogleFonts.interTextTheme(base.textTheme).apply(
        bodyColor: Color(0xFFF1F5F9),
        displayColor: Color(0xFFF1F5F9),
      ),
    );
  }

  /// Light theme for dashboard / system light mode.
  static ThemeData light() {
    const bg = Colors.white;
    const surface = Color(0xFFFFFFFF);
    const surfaceVariant = Color(0xFFF1F5F9);
    const accent = Color(0xFF0D9488);
    const accentDim = Color(0xFF14B8A6);

    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: ColorScheme.light(
        surface: surface,
        primary: accent,
        onPrimary: Colors.white,
        secondary: accentDim,
        tertiary: Color(0xFF0E7490),
        error: Color(0xFFDC2626),
        onSurface: Color(0xFF0F172A),
        onSurfaceVariant: Color(0xFF64748B),
      ),
      scaffoldBackgroundColor: bg,
      appBarTheme: AppBarTheme(
        elevation: 0,
        centerTitle: true,
        backgroundColor: Colors.transparent,
        foregroundColor: Color(0xFF0F172A),
        titleTextStyle: GoogleFonts.inter(
          fontSize: 17,
          fontWeight: FontWeight.w600,
          color: Color(0xFF0F172A),
        ),
      ),
      cardTheme: CardThemeData(
        color: surfaceVariant,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: Colors.black.withValues(alpha: 0.06)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: Colors.black.withValues(alpha: 0.1)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: Colors.black.withValues(alpha: 0.1)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: accent, width: 1.5),
        ),
        labelStyle: TextStyle(color: Color(0xFF64748B)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: accent,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: GoogleFonts.inter(
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: Color(0xFF334155),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      drawerTheme: DrawerThemeData(
        backgroundColor: surface,
        elevation: 16,
        shadowColor: Colors.black.withValues(alpha: 0.14),
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.horizontal(right: Radius.circular(22)),
        ),
        width: 296,
        scrimColor: Colors.black.withValues(alpha: 0.32),
      ),
    );

    return base.copyWith(
      textTheme: GoogleFonts.interTextTheme(base.textTheme).apply(
        bodyColor: Color(0xFF0F172A),
        displayColor: Color(0xFF0F172A),
      ),
    );
  }

  static Color seedColorFor(String seed) {
    switch (seed) {
      case 'blue':
        return const Color(0xFF2563EB);
      case 'indigo':
        return const Color(0xFF4F46E5);
      case 'emerald':
        return const Color(0xFF059669);
      case 'rose':
        return const Color(0xFFE11D48);
      case 'amber':
        return const Color(0xFFD97706);
      case 'teal':
      default:
        return const Color(0xFF0D9488);
    }
  }

  static ThemeData lightForSeed(String seed) {
    final c = seedColorFor(seed);
    final scheme = ColorScheme.fromSeed(
      seedColor: c,
      brightness: Brightness.light,
    );
    return light().copyWith(
      colorScheme: scheme,
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
      appBarTheme: light().appBarTheme.copyWith(
            foregroundColor: scheme.onSurface,
          ),
    );
  }

  static ThemeData darkForSeed(String seed) {
    final c = seedColorFor(seed);
    final scheme = ColorScheme.fromSeed(
      seedColor: c,
      brightness: Brightness.dark,
    );
    return dark().copyWith(
      colorScheme: scheme,
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
      appBarTheme: dark().appBarTheme.copyWith(
            foregroundColor: scheme.onSurface,
          ),
    );
  }

  static BoxDecoration heroGradientDecoration() => BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFF0B1220),
            Color(0xFF0F172A),
            Color(0xFF134E4A).withValues(alpha: 0.35),
          ],
        ),
      );

  static LinearGradient accentShimmer() => LinearGradient(
        colors: [
          _accent.withValues(alpha: 0.9),
          Color(0xFF67E8F9).withValues(alpha: 0.85),
        ],
      );
}
