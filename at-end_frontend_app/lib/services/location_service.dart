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

/// Thrown when readings jump impossibly fast (spoof / teleport).
class LocationSpoofDetectedException implements Exception {
  const LocationSpoofDetectedException();

  @override
  String toString() =>
      'Location readings were inconsistent. Disable mock-location apps and try again.';
}

/// Refined multi-sample fix with confidence scoring for geofence checks.
class RefinedLocationFix {
  const RefinedLocationFix({
    required this.position,
    required this.confidence,
    required this.sampleCount,
    required this.distanceMeters,
    this.passesGeofence = false,
  });

  final Position position;
  final double confidence;
  final int sampleCount;
  final double? distanceMeters;
  final bool passesGeofence;

  Map<String, dynamic> clientMetaExtras() => {
        'gps_accuracy_m': position.accuracy,
        'location_confidence': confidence,
        'gps_samples': sampleCount,
        if (position.altitude.isFinite && position.altitude.abs() > 0.01)
          'altitude_m': position.altitude,
        if (distanceMeters != null) 'distance_m': distanceMeters!.round(),
      };
}

/// GPS location and range checking for attendance validation.
class LocationService {
  static const Duration _fixTimeLimit = Duration(seconds: 12);

  static const double maxAcceptableAccuracyMeters = 200.0;
  static const double proximityPassMeters = 4.0;
  static const double poorAccuracyThresholdM = 50.0;
  static const double confidenceApproveThreshold = 0.55;
  static const double maxJumpSpeedMps = 45.0;

  static Future<Position> _fixForSessionStart() => Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.bestForNavigation,
        timeLimit: _fixTimeLimit,
      );

  static Future<Position> _fixForAttendance() => Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.best,
        timeLimit: const Duration(seconds: 8),
      );

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

  static Future<Position> getCurrentPositionBestNavigation() async {
    await _ensureServiceAndPermission();
    return _fixForSessionStart();
  }

  static Future<Position> getCurrentPositionForSessionStart() async {
    await _ensureServiceAndPermission();
    return _fixForSessionStart();
  }

  static void _rejectIfMocked(Position p) {
    if (p.isMocked) {
      throw const MockLocationDetectedException();
    }
  }

  static void _rejectImpossibleJump(Position a, Position b) {
    final dt = (b.timestamp.millisecondsSinceEpoch -
            a.timestamp.millisecondsSinceEpoch) /
        1000.0;
    if (dt <= 0.2 || dt > 60) return;
    final dist = Geolocator.distanceBetween(
      a.latitude,
      a.longitude,
      b.latitude,
      b.longitude,
    );
    if (dist / dt > maxJumpSpeedMps) {
      throw const LocationSpoofDetectedException();
    }
  }

  static List<Position> _rejectOutliers(List<Position> samples) {
    if (samples.length < 3) return samples;
    final lats = samples.map((p) => p.latitude).toList()..sort();
    final lngs = samples.map((p) => p.longitude).toList()..sort();
    final medLat = lats[lats.length ~/ 2];
    final medLng = lngs[lngs.length ~/ 2];
    final kept = samples.where((p) {
      final d = Geolocator.distanceBetween(
        medLat,
        medLng,
        p.latitude,
        p.longitude,
      );
      final tol = (p.accuracy.isFinite ? p.accuracy : 30) * 2.5;
      return d <= tol.clamp(25, 120);
    }).toList();
    return kept.isEmpty ? samples : kept;
  }

  static Position _weightedCentroid(List<Position> samples) {
    var sumLat = 0.0;
    var sumLng = 0.0;
    var totalW = 0.0;
    var bestAcc = double.infinity;
    var best = samples.first;

    for (final p in samples) {
      final acc = p.accuracy.isFinite && p.accuracy > 0 ? p.accuracy : 50.0;
      final w = 1 / acc;
      sumLat += p.latitude * w;
      sumLng += p.longitude * w;
      totalW += w;
      if (acc < bestAcc) {
        bestAcc = acc;
        best = p;
      }
    }

    return Position(
      latitude: sumLat / totalW,
      longitude: sumLng / totalW,
      timestamp: best.timestamp,
      accuracy: bestAcc,
      altitude: best.altitude,
      altitudeAccuracy: best.altitudeAccuracy,
      heading: best.heading,
      headingAccuracy: best.headingAccuracy,
      speed: best.speed,
      speedAccuracy: best.speedAccuracy,
      isMocked: best.isMocked,
    );
  }

  static double _scoreConfidence({
    required double distanceM,
    required double allowedM,
    required double accuracyM,
    required int sampleCount,
    required bool passes,
  }) {
    final distScore = (1 - (distanceM / (allowedM + accuracyM).clamp(1, 9999)))
        .clamp(0.0, 1.0);
    final accScore = (1 - (accuracyM / 120)).clamp(0.0, 1.0);
    final sampleScore = (sampleCount / 6).clamp(0.0, 1.0);
    final raw = distScore * 0.45 + accScore * 0.35 + sampleScore * 0.2;
    if (!passes) return raw * 0.4;
    final stability = sampleCount >= 3 ? 1.0 : 0.65;
    return (raw * stability + 0.12).clamp(0.0, 1.0);
  }

  /// Multi-sample pipeline (~3–5 s) with outlier rejection and confidence.
  static Future<RefinedLocationFix> getRefinedFixForAttendance({
    double? targetLat,
    double? targetLng,
    double baseRangeMeters = 200,
  }) async {
    await _ensureServiceAndPermission();

    try {
      final warmup = await _fixForAttendance();
      _rejectIfMocked(warmup);
    } catch (e) {
      if (e is MockLocationDetectedException ||
          e is LocationSpoofDetectedException) {
        rethrow;
      }
    }

    await Future<void>.delayed(const Duration(milliseconds: 300));

    final samples = <Position>[];
    final started = DateTime.now();
    var deadline = started.add(const Duration(milliseconds: 3000));

    for (var attempt = 0;
        attempt < 8 && DateTime.now().isBefore(deadline);
        attempt++) {
      try {
        final p = await _fixForAttendance();
        _rejectIfMocked(p);
        if (samples.isNotEmpty) {
          _rejectImpossibleJump(samples.last, p);
        }
        samples.add(p);
        if (p.accuracy.isFinite &&
            p.accuracy <= 8 &&
            samples.length >= 3) {
          break;
        }
        if (p.accuracy > poorAccuracyThresholdM &&
            DateTime.now().isBefore(started.add(const Duration(seconds: 5)))) {
          deadline = started.add(const Duration(seconds: 5));
        }
      } catch (e) {
        if (e is MockLocationDetectedException ||
            e is LocationSpoofDetectedException) {
          rethrow;
        }
        if (attempt >= 7 && samples.isEmpty) rethrow;
      }
      if (attempt < 7) {
        await Future<void>.delayed(const Duration(milliseconds: 380));
      }
    }

    if (samples.isEmpty) {
      final fallback = await _fixForAttendance();
      _rejectIfMocked(fallback);
      samples.add(fallback);
    }

    final filtered = _rejectOutliers(samples);
    final centroid = _weightedCentroid(filtered);

    if (centroid.accuracy.isFinite &&
        centroid.accuracy > maxAcceptableAccuracyMeters) {
      throw LocationAccuracyTooLowException(centroid.accuracy);
    }

    double? distanceM;
    var passes = true;
    if (targetLat != null && targetLng != null) {
      distanceM = calculateDistance(
        centroid.latitude,
        centroid.longitude,
        targetLat,
        targetLng,
      );
      passes = isWithinRange(centroid, targetLat, targetLng, baseRangeMeters);
    }

    final confidence = _scoreConfidence(
      distanceM: distanceM ?? 0,
      allowedM: baseRangeMeters,
      accuracyM: centroid.accuracy,
      sampleCount: filtered.length,
      passes: passes,
    );

    if (kDebugMode) {
      // ignore: avoid_print
      print(
        'GPS refined: samples=${filtered.length} accuracy='
        '${centroid.accuracy.toStringAsFixed(1)}m confidence='
        '${confidence.toStringAsFixed(2)} distance='
        '${distanceM?.toStringAsFixed(1) ?? "?"}m passes=$passes',
      );
    }

    return RefinedLocationFix(
      position: centroid,
      confidence: confidence,
      sampleCount: filtered.length,
      distanceMeters: distanceM,
      passesGeofence: passes,
    );
  }

  static Future<Position> getRefinedPositionForAttendance() async {
    final fix = await getRefinedFixForAttendance();
    return fix.position;
  }

  static Future<Position> getCurrentLocation() async {
    return getRefinedPositionForAttendance();
  }

  static double calculateDistance(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) {
    return Geolocator.distanceBetween(lat1, lng1, lat2, lng2);
  }

  static bool isWithinRange(
    Position current,
    double targetLat,
    double targetLng,
    double baseRangeMeters,
  ) {
    final distance = calculateDistance(
      current.latitude,
      current.longitude,
      targetLat,
      targetLng,
    );
    if (distance <= proximityPassMeters) {
      return true;
    }
    final allowed = adjustedRangeMeters(baseRangeMeters, current.accuracy);
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
