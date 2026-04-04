import 'dart:typed_data';

import 'package:crop_your_image/crop_your_image.dart';
import 'package:flutter/material.dart';

/// Circular crop for profile photos; returns JPEG bytes or null if cancelled.
Future<Uint8List?> showProfileImageCropDialog(
  BuildContext context,
  Uint8List imageBytes,
) {
  final cropController = CropController();

  return showDialog<Uint8List?>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) {
      return AlertDialog(
        title: const Text('Crop photo'),
        content: SizedBox(
          width: double.maxFinite,
          height: 360,
          child: Crop(
            image: imageBytes,
            controller: cropController,
            withCircleUi: true,
            interactive: true,
            initialRectBuilder: InitialRectBuilder.withSizeAndRatio(
              size: 0.85,
              aspectRatio: 1,
            ),
            onCropped: (result) {
              if (result is CropSuccess) {
                Navigator.of(ctx).pop(result.croppedImage);
              } else if (result is CropFailure) {
                Navigator.of(ctx).pop(null);
              }
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(null),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => cropController.cropCircle(),
            child: const Text('Use photo'),
          ),
        ],
      );
    },
  );
}
