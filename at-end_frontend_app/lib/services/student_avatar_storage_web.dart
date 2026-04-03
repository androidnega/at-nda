import 'dart:typed_data';

import 'package:flutter/material.dart';

/// Web: no [dart:io]; profile bytes are held in memory by [ProfileImageCache].
class StudentAvatarStorage {
  static Future<void> deleteFor(String indexNumber) async {}

  static Future<ImageProvider?> loadIfUrlMatches(String indexNumber, String url) async {
    return null;
  }

  static Future<ImageProvider> saveBytes(String indexNumber, String url, Uint8List bytes) async {
    return MemoryImage(bytes);
  }
}
