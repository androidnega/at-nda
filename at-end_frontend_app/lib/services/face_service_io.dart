import 'dart:io';
import 'dart:math';

import 'package:camera/camera.dart';
import 'package:image/image.dart' as img;
import 'package:permission_handler/permission_handler.dart';
import 'package:tflite_flutter/tflite_flutter.dart';

/// Mobile/desktop (dart:io): camera capture + TFLite embedding.
/// Uses a bundled MobileNet feature model; first 128 dimensions are L2-normalized
/// to match [FaceService] placeholder size on web.

const int _descriptorDim = 128;

const String _modelAsset = 'assets/models/face_embedding.tflite';

Interpreter? _interpreter;

Future<void> loadModel() async {
  try {
    _interpreter?.close();
    _interpreter = await Interpreter.fromAsset(_modelAsset);
  } catch (_) {
    _interpreter = null;
  }
}

Future<List<double>> getFaceDescriptor() async {
  if (_interpreter == null) return [];

  final perm = await Permission.camera.request();
  if (!perm.isGranted) return [];

  CameraController? controller;
  try {
    final cameras = await availableCameras();
    if (cameras.isEmpty) return [];

    CameraDescription cam = cameras.first;
    for (final c in cameras) {
      if (c.lensDirection == CameraLensDirection.front) {
        cam = c;
        break;
      }
    }

    controller = CameraController(
      cam,
      ResolutionPreset.medium,
      enableAudio: false,
    );
    await controller.initialize();
    final shot = await controller.takePicture();
    final bytes = await File(shot.path).readAsBytes();

    final decoded = img.decodeImage(bytes);
    if (decoded == null) return [];

    final interpreter = _interpreter!;
    final inTensor = interpreter.getInputTensor(0);
    final shape = inTensor.shape;
    if (shape.length < 4) return [];

    final h = shape[1] == -1 ? 224 : shape[1];
    final w = shape[2] == -1 ? 224 : shape[2];
    final c = shape[3];
    if (h <= 0 || w <= 0 || c != 3) return [];

    final resized = img.copyResizeCropSquare(decoded, size: h);

    final input = _buildInput(resized, h, w, inTensor.type);
    final output = _allocOutput(interpreter.getOutputTensor(0));

    interpreter.run(input, output);
    return _outputToDescriptor(interpreter.getOutputTensor(0), output);
  } catch (_) {
    return [];
  } finally {
    await controller?.dispose();
  }
}

Object _buildInput(img.Image resized, int h, int w, TensorType type) {
  List<List<List<int>>> rgbRowsUint8() {
    return List.generate(
      h,
      (y) => List.generate(
        w,
        (x) {
          final p = resized.getPixel(x, y);
          return [p.r.toInt(), p.g.toInt(), p.b.toInt()];
        },
      ),
    );
  }

  List<List<List<double>>> rgbRowsFloat() {
    return List.generate(
      h,
      (y) => List.generate(
        w,
        (x) {
          final p = resized.getPixel(x, y);
          return [
            p.r.toDouble() / 255.0,
            p.g.toDouble() / 255.0,
            p.b.toDouble() / 255.0,
          ];
        },
      ),
    );
  }

  // NHWC batch = 1
  if (type == TensorType.uint8) {
    return [rgbRowsUint8()];
  }
  if (type == TensorType.float32) {
    return [rgbRowsFloat()];
  }
  return [rgbRowsUint8()];
}

Object _allocOutput(Tensor tensor) {
  final shape = tensor.shape;
  final type = tensor.type;
  if (shape.length == 2) {
    final n = shape[1];
    if (type == TensorType.uint8 || type == TensorType.int8) {
      return [List<int>.filled(n, 0)];
    }
    if (type == TensorType.float32) {
      return [List<double>.filled(n, 0.0)];
    }
  }
  final n = shape.isNotEmpty ? shape.last : 1;
  return [List<int>.filled(n, 0)];
}

List<double> _outputToDescriptor(Tensor tensor, Object output) {
  final List<double> raw = [];
  final qp = tensor.params;

  void addVal(num v) {
    if (tensor.type == TensorType.uint8 || tensor.type == TensorType.int8) {
      final vi = v.toInt();
      final scale = qp.scale;
      if (scale > 0) {
        raw.add((vi - qp.zeroPoint) * scale);
      } else {
        raw.add(vi / 255.0);
      }
    } else {
      raw.add(v.toDouble());
    }
  }

  if (output is List && output.isNotEmpty) {
    final first = output[0];
    if (first is List) {
      for (final v in first) {
        if (v is num) addVal(v);
      }
    }
  }

  if (raw.isEmpty) return [];

  final take = min(_descriptorDim, raw.length);
  var sumSq = 0.0;
  for (var i = 0; i < take; i++) {
    final v = raw[i];
    sumSq += v * v;
  }
  final norm = sqrt(sumSq);
  if (norm < 1e-10) return List.filled(_descriptorDim, 0.0);

  return List.generate(
    _descriptorDim,
    (i) => i < take ? raw[i] / norm : 0.0,
  );
}
