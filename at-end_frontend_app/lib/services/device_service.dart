import 'dart:io';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

/// Device IP and ID for attendance submission.
/// Laravel uses these for duplicate detection and validation.
class DeviceService {
  static const String _deviceIdKey = 'attendance_device_id';

  /// Cached local interface IP (recomputed at most every 10 min so the
  /// attendance flow does not hit NetworkInterface.list on every submit).
  static String? _cachedIp;
  static DateTime? _cachedIpAt;
  static const Duration _ipTtl = Duration(minutes: 10);

  /// Alias for [getDeviceIp] (matches common naming).
  static Future<String?> getIp() => getDeviceIp();

  /// Prefer the device's own LAN address (Wi-Fi or mobile interface).
  /// The server overrides device_ip with $request->ip() anyway, so leaking
  /// the device's public IP to a 3rd party (ipify.org) on every submit was
  /// pure cost. ipify is only used as a silent 1.5 s fallback when no
  /// usable local interface exists.
  static Future<String?> getDeviceIp({bool refresh = false}) async {
    if (!refresh && _cachedIp != null && _cachedIpAt != null) {
      if (DateTime.now().difference(_cachedIpAt!) < _ipTtl) {
        return _cachedIp;
      }
    }

    final local = await _firstLocalNonLoopbackIp();
    if (local != null && local.isNotEmpty) {
      _cachedIp = local;
      _cachedIpAt = DateTime.now();
      return local;
    }

    // Last-resort public IP lookup. Short timeout so the attendance UI
    // never stalls waiting for it.
    try {
      final res = await http.get(Uri.parse('https://api.ipify.org')).timeout(
        const Duration(milliseconds: 1500),
      );
      if (res.statusCode == 200 && res.body.isNotEmpty) {
        final ip = res.body.trim();
        _cachedIp = ip;
        _cachedIpAt = DateTime.now();
        return ip;
      }
    } catch (_) {}

    return null;
  }

  /// Local non-loopback IPv4 first; falls back to IPv6 if no IPv4 was found.
  static Future<String?> _firstLocalNonLoopbackIp() async {
    if (kIsWeb) return null;
    try {
      final v4 = await NetworkInterface.list(
        includeLinkLocal: false,
        includeLoopback: false,
        type: InternetAddressType.IPv4,
      );
      for (final iface in v4) {
        for (final addr in iface.addresses) {
          if (!addr.isLoopback && addr.address.isNotEmpty) {
            return addr.address;
          }
        }
      }
      final v6 = await NetworkInterface.list(
        includeLinkLocal: false,
        includeLoopback: false,
        type: InternetAddressType.IPv6,
      );
      for (final iface in v6) {
        for (final addr in iface.addresses) {
          if (!addr.isLoopback && addr.address.isNotEmpty) {
            return addr.address;
          }
        }
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
