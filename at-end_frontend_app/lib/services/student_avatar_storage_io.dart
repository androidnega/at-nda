import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Persists downloaded profile images under app support dir (iOS/Android/desktop).
class StudentAvatarStorage {
  static String _metaKey(String index) => 'profile_img_cache_v1_$index';

  static Future<void> deleteFor(String indexNumber) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_metaKey(indexNumber));
    if (raw != null) {
      try {
        final m = jsonDecode(raw) as Map<String, dynamic>;
        final path = m['path'] as String?;
        if (path != null) {
          final f = File(path);
          if (await f.exists()) await f.delete();
        }
      } catch (_) {}
      await prefs.remove(_metaKey(indexNumber));
    }
  }

  static Future<ImageProvider?> loadIfUrlMatches(String indexNumber, String url) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_metaKey(indexNumber));
    if (raw == null) return null;
    try {
      final m = jsonDecode(raw) as Map<String, dynamic>;
      if (m['url'] != url) return null;
      final path = m['path'] as String?;
      if (path == null) return null;
      final f = File(path);
      if (!await f.exists()) return null;
      return FileImage(f);
    } catch (_) {
      return null;
    }
  }

  static Future<ImageProvider> saveBytes(String indexNumber, String url, Uint8List bytes) async {
    final dir = await getApplicationSupportDirectory();
    final safe = url.hashCode.abs();
    final path = p.join(dir.path, 'profile_${indexNumber}_$safe.jpg');
    final f = File(path);
    await f.writeAsBytes(bytes, flush: true);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _metaKey(indexNumber),
      jsonEncode({'url': url, 'path': f.path}),
    );
    return FileImage(f);
  }
}
