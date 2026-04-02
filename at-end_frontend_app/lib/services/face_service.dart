import 'dart:math';

import 'package:flutter/foundation.dart' show kIsWeb;

/// TFLite-based face verification.
/// On web: returns placeholder (camera/TFLite not supported). Use mobile for real verification.
class FaceService {
  /// Placeholder descriptor size (typical face embedding).
  static const int _placeholderSize = 128;

  /// Initialize TFLite model.
  static Future<void> loadModel() async {
    if (kIsWeb) return;
    // Cursor AI: implement TFLite model load for mobile
    // Example: await TfliteFlutter.loadModel(model: 'assets/face_model.tflite');
  }

  /// Capture camera image and generate face descriptor.
  /// On web: returns placeholder. On mobile: implement camera + TFLite; returns [] on failure.
  static Future<List<double>> getFaceDescriptor() async {
    if (kIsWeb) {
      return List.filled(_placeholderSize, 0.0);
    }
    // TODO: implement camera capture + TFLite inference for mobile
    // Return [] when capture fails (no face detected) so UI can show retry
    return [];
  }

  /// Use placeholder for testing when camera/TFLite unavailable.
  static List<double> getPlaceholderDescriptor() {
    return List.filled(_placeholderSize, 0.0);
  }

  /// Compare two face descriptors using Euclidean distance.
  static bool compareFaces(
    List<double> desc1,
    List<double> desc2,
    double threshold,
  ) {
    if (desc1.length != desc2.length) return false;
    double sumSq = 0.0;
    for (int i = 0; i < desc1.length; i++) {
      final d = desc1[i] - desc2[i];
      sumSq += d * d;
    }
    final distance = sqrt(sumSq);
    return distance < threshold;
  }
}
