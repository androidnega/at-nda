import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../models/student.dart';
import 'api_service.dart';
import 'student_avatar_storage_io.dart' if (dart.library.html) 'student_avatar_storage_web.dart' as storage;
import '../utils/constants.dart';

/// Fetches profile photos with API headers, caches to disk (mobile/desktop) or memory (web).
class ProfileImageCache {
  ProfileImageCache._();
  static final ProfileImageCache instance = ProfileImageCache._();

  final Map<String, Uint8List> _memoryWeb = {};

  Future<void> invalidate(String indexNumber) async {
    await storage.StudentAvatarStorage.deleteFor(indexNumber);
    _memoryWeb.removeWhere((k, _) => k.startsWith('$indexNumber|'));
  }

  Future<ImageProvider?> resolve(Student student) async {
    final apiUri = Uri.parse(Constants.baseUrl);

    final raw = student.profileImage.trim();
    if (raw.isNotEmpty &&
        !raw.startsWith('http://') &&
        !raw.startsWith('https://') &&
        !raw.startsWith('/')) {
      if (raw.startsWith('data:image')) {
        try {
          final comma = raw.indexOf(',');
          if (comma > 0) {
            final b64 = raw.substring(comma + 1);
            final bytes = base64Decode(b64);
            return MemoryImage(bytes);
          }
        } catch (_) {}
      }
      try {
        final bytes = base64Decode(raw);
        return MemoryImage(bytes);
      } catch (_) {}
    }

    final url = student.resolvedNetworkProfileUrl(apiUri) ?? student.profilePictureUrl;
    if (url == null || url.isEmpty) return null;

    final memKey = '${student.indexNumber}|${url.hashCode}';
    if (kIsWeb) {
      final b = _memoryWeb[memKey];
      if (b != null) return MemoryImage(b);
    } else {
      final cached = await storage.StudentAvatarStorage.loadIfUrlMatches(
        student.indexNumber,
        url,
      );
      if (cached != null) return cached;
    }

    try {
      final res = await http
          .get(Uri.parse(url), headers: ApiService.requestHeaders())
          .timeout(const Duration(seconds: 25));
      if (res.statusCode < 200 || res.statusCode >= 300) return null;
      final bytes = res.bodyBytes;
      if (bytes.isEmpty) return null;

      if (kIsWeb) {
        _memoryWeb[memKey] = bytes;
        return MemoryImage(bytes);
      }
      return storage.StudentAvatarStorage.saveBytes(
        student.indexNumber,
        url,
        bytes,
      );
    } catch (_) {
      return null;
    }
  }
}
