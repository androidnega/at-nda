import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:permission_handler/permission_handler.dart';

/// Request camera + storage (profile, onboarding). Does **not** request location —
/// GPS permission is requested only when a session has [location_required] (Geolocator).
/// On web: permissions are handled by the browser; skip.
class PermissionService {
  /// Camera + media only (no location — avoids prompting QR-only users for GPS).
  static Future<void> requestCameraStorage() async {
    if (kIsWeb) return;
    await [
      Permission.camera,
      Permission.storage,
      Permission.photos,
    ].request();
  }

  /// Prefer [requestCameraStorage]. Location is not requested globally.
  static Future<void> requestAll() async => requestCameraStorage();

  static Future<bool> hasCamera() async {
    if (kIsWeb) return true;
    return await Permission.camera.isGranted;
  }

  static Future<bool> hasLocation() async {
    if (kIsWeb) return true;
    return await Permission.location.isGranted;
  }
}
