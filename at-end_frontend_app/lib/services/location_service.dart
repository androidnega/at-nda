import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

/// Thrown when GPS data is intentionally manipulated (mock-location apps).
class MockLocationDetectedException implements Exception {
  const MockLocationDetectedException();

  @override
  String toString() =>
      'Mock-location apps are not allowed for attendance. Disable any GPS '
      'spoofer (e.g. FakeGPS) and try again.';
}

/// Thrown when no fix could be obtained with usable accuracy.
class LocationAccuracyTooLowException implements Exception {
  LocationAccuracyTooLowException(this.reportedMeters);
  final double reportedMeters;

  @override
  String toString() =>
      'GPS accuracy is too weak (${reportedMeters.toStringAsFixed(0)} m). '
      'Move to an open area and try again.';
}

/// GPS location and range checking for attendance validation.
/// Uses multiple high-accuracy fixes and picks the best (lowest horizontal uncertainty),
/// then applies a geofence tolerance based on reported GPS accuracy (reduces false
/// "out of range" when indoors / weak signal).
class LocationService {
  static const Duration _fixTimeLimit = Duration(seconds: 45);

  /// Hard ceiling for an attendance fix; above this the position is rejected
  /// regardless of how many samples we collected. Wider than the typical
  /// geofence but tight enough that a hostile "huge accuracy" payload still
  /// fails the server-side cap.
  static const double maxAcceptableAccuracyMeters = 200.0;

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
  ///
  /// Throws [MockLocationDetectedException] when ANY of the samples reports
  /// [Position.isMocked]; throws [LocationAccuracyTooLowException] when no
  /// sample comes back tighter than [maxAcceptableAccuracyMeters].
  static Future<Position> getRefinedPositionForAttendance() async {
    await _ensureServiceAndPermission();

    // Discard a noisy first fix (common on cold start).
    try {
      final warmup = await _fixForAttendance();
      _rejectIfMocked(warmup);
    } catch (e) {
      if (e is MockLocationDetectedException) rethrow;
      // warmup-only errors are tolerated; the real loop below will retry.
    }

    await Future<void>.delayed(const Duration(milliseconds: 600));

    Position? best;
    var bestAcc = double.infinity;

    for (var attempt = 0; attempt < 4; attempt++) {
      try {
        final p = await _fixForAttendance();
        _rejectIfMocked(p);
        final acc = p.accuracy;
        if (acc.isFinite && acc >= 0 && acc < bestAcc) {
          bestAcc = acc;
          best = p;
          if (acc <= 20) break;
        } else {
          best ??= p;
        }
      } catch (e) {
        if (e is MockLocationDetectedException) rethrow;
        if (attempt == 3 && best == null) rethrow;
      }
      if (attempt < 3) {
        await Future<void>.delayed(const Duration(milliseconds: 900));
      }
    }

    if (best == null) {
      // No fix at all — let the caller fall back to a single attempt so the
      // existing error UI is consistent.
      best = await _fixForAttendance();
      _rejectIfMocked(best);
    }

    if (best.accuracy.isFinite && best.accuracy > maxAcceptableAccuracyMeters) {
      throw LocationAccuracyTooLowException(best.accuracy);
    }

    if (kDebugMode) {
      // ignore: avoid_print
      print(
        'GPS fix chosen: accuracy=${best.accuracy.toStringAsFixed(1)} m '
        '(${best.latitude.toStringAsFixed(6)}, ${best.longitude.toStringAsFixed(6)})',
      );
    }
    return best;
  }

  /// Throws when the OS flagged the position as coming from a mock provider.
  /// Only meaningful on Android; iOS always reports isMocked = false.
  static void _rejectIfMocked(Position p) {
    if (p.isMocked) {
      throw const MockLocationDetectedException();
    }
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
