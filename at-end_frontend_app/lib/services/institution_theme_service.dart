/// Compatibility service for backend-driven theme seed.
///
/// The app currently reads `mobile_app_theme_seed` from `/api/settings`, but
/// the runtime theme implementation is static. Keep this as a no-op so API
/// parsing remains stable and future theme wiring can plug in here.
class InstitutionThemeService {
  InstitutionThemeService._();

  static Future<void> applyFromApi(String? seed) async {
    // Intentionally no-op for now.
    if (seed == null) return;
    final _ = seed.trim();
  }
}
