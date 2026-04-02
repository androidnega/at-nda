import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

/// GPS location and range checking for attendance validation.
/// Uses multiple high-accuracy fixes and picks the best (lowest horizontal uncertainty),
/// then applies a geofence tolerance based on reported GPS accuracy (reduces false
/// "out of range" when indoors / weak signal).
class LocationService {
  static const Duration _fixTimeLimit = Duration(seconds: 45);

  /// High-accuracy fix for lecturer/session start (source of truth for venue GPS).
  static Future<Position> _fixForSessionStart() => Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.bestForNavigation,
        timeLimit: _fixTimeLimit,
      );

  /// Fix for student attendance checks — [LocationAccuracy.best] per geofence flow.
  static Future<Position> _fixForAttendance() => Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.best,
        timeLimit: _fixTimeLimit,
      );

  /// Parse API [lat] / [lng] whether JSON sends numbers or strings.
  static double? parseCoordinate(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    if (v is String) {
      final t = v.trim();
      if (t.isEmpty) return null;
      return double.tryParse(t);
    }
    return double.tryParse(v.toString());
  }

  /// Server [range_meters] + device-reported horizontal accuracy (real-world tolerance).
  /// [allowedRange] = baseRange + accuracy (invalid accuracy → base only).
  static double adjustedRangeMeters(
    double baseRangeMeters,
    double positionAccuracyMeters,
  ) {
    if (!positionAccuracyMeters.isFinite || positionAccuracyMeters < 0) {
      return baseRangeMeters;
    }
    return baseRangeMeters + positionAccuracyMeters;
  }

  static Future<void> _ensureServiceAndPermission() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw Exception('Location services disabled');
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw Exception('Location permission denied');
      }
    }
    if (permission == LocationPermission.deniedForever) {
      throw Exception('Location permission permanently denied');
    }
  }

  /// Single fix — best for navigation (session start / venue capture).
  static Future<Position> getCurrentPositionBestNavigation() async {
    await _ensureServiceAndPermission();
    return _fixForSessionStart();
  }

  /// One-shot GPS for POST /sessions/start — phone as source of truth for venue location.
  static Future<Position> getCurrentPositionForSessionStart() async {
    await _ensureServiceAndPermission();
    return _fixForSessionStart();
  }

  /// Best-effort precise fix: warm-up + several samples, keep lowest [accuracy].
  static Future<Position> getRefinedPositionForAttendance() async {
    await _ensureServiceAndPermission();

    // Discard a noisy first fix (common on cold start).
    try {
      await _fixForAttendance();
    } catch (_) {}

    await Future<void>.delayed(const Duration(milliseconds: 600));

    Position? best;
    var bestAcc = double.infinity;

    for (var attempt = 0; attempt < 4; attempt++) {
      try {
        final p = await _fixForAttendance();
        final acc = p.accuracy;
        if (acc.isFinite && acc >= 0 && acc < bestAcc) {
          bestAcc = acc;
          best = p;
          // Stop early if the device reports a tight fix.
          if (acc <= 20) break;
        } else {
          best ??= p;
        }
      } catch (e) {
        if (attempt == 3 && best == null) rethrow;
      }
      if (attempt < 3) {
        await Future<void>.delayed(const Duration(milliseconds: 900));
      }
    }

    if (best != null) {
      if (kDebugMode) {
        // ignore: avoid_print
        print(
          'GPS fix chosen: accuracy=${best.accuracy.toStringAsFixed(1)} m '
          '(${best.latitude.toStringAsFixed(6)}, ${best.longitude.toStringAsFixed(6)})',
        );
      }
      return best;
    }

    return _fixForAttendance();
  }

  /// Backwards-compatible alias — uses refined pipeline.
  static Future<Position> getCurrentLocation() async {
    return getRefinedPositionForAttendance();
  }

  /// Straight-line distance in meters.
  static double calculateDistance(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) {
    return Geolocator.distanceBetween(lat1, lng1, lat2, lng2);
  }

  /// Uses [adjustedRangeMeters] with [position.accuracy].
  static bool isWithinRange(
    Position current,
    double targetLat,
    double targetLng,
    double baseRangeMeters,
  ) {
    final allowed = adjustedRangeMeters(baseRangeMeters, current.accuracy);
    final distance = calculateDistance(
      current.latitude,
      current.longitude,
      targetLat,
      targetLng,
    );
    if (kDebugMode) {
      // ignore: avoid_print
      print(
        'Range check: distance=${distance.toStringAsFixed(1)} m, '
        'allowed=${allowed.toStringAsFixed(1)} m (base=$baseRangeMeters, '
        'accuracy=${current.accuracy})',
      );
    }
    return distance <= allowed;
  }
}
