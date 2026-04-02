import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

/// Device IP and ID for attendance submission.
/// Laravel uses these for duplicate detection and validation.
class DeviceService {
  static const String _deviceIdKey = 'attendance_device_id';

  /// Alias for [getDeviceIp] (matches common naming).
  static Future<String?> getIp() => getDeviceIp();

  /// Public IP (for device_ip field). Laravel can use $request->ip() as fallback.
  static Future<String?> getDeviceIp() async {
    try {
      final res = await http.get(Uri.parse('https://api.ipify.org')).timeout(
        const Duration(seconds: 3),
      );
      if (res.statusCode == 200 && res.body.isNotEmpty) {
        return res.body.trim();
      }
    } catch (_) {}
    return null;
  }

  /// Unique device ID (persisted). Used for duplicate detection on late sync.
  static Future<String> getDeviceId() async {
    final prefs = await SharedPreferences.getInstance();
    var id = prefs.getString(_deviceIdKey);
    if (id == null || id.isEmpty) {
      id = const Uuid().v4();
      await prefs.setString(_deviceIdKey, id);
    }
    return id;
  }
}
