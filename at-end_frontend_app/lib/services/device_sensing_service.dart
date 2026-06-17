import 'dart:async';

import 'package:geolocator/geolocator.dart';
import 'package:sensors_plus/sensors_plus.dart';

/// Barometer + altitude hints for indoor / multi-floor attendance.
class DeviceSensingService {
  static Future<Map<String, dynamic>> collectForAttendance() async {
    final out = <String, dynamic>{};

    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.best,
        timeLimit: const Duration(seconds: 8),
      );
      if (position.altitude.isFinite && position.altitude.abs() > 0.01) {
        out['altitude_m'] = position.altitude;
      }
      if (position.altitudeAccuracy.isFinite &&
          position.altitudeAccuracy > 0) {
        out['altitude_accuracy_m'] = position.altitudeAccuracy;
      }
    } catch (_) {}

    try {
      final sample = await barometerEventStream()
          .first
          .timeout(const Duration(seconds: 2));
      out['pressure_hpa'] = sample.pressure;
    } catch (_) {}

    final pressure = out['pressure_hpa'];
    if (pressure is num) {
      // Rough floor estimate from sea-level reference (~1013 hPa).
      final delta = 1013.25 - pressure.toDouble();
      if (delta.isFinite) {
        out['floor_estimate'] = (delta / 3.5).round().clamp(-5, 40);
      }
    }

    return out;
  }
}
