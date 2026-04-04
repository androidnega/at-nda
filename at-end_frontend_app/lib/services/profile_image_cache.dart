import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../models/student.dart';
import 'student_avatar_storage_io.dart' if (dart.library.html) 'student_avatar_storage_web.dart' as storage;
import '../utils/constants.dart';
import 'api_service.dart';

/// Sent with avatar HTTP fetches (some CDNs/WAFs block empty or “Dart” user agents on mobile).
const Map<String, String> kProfileImageHttpHeaders = {
  'Accept': 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
  'User-Agent': 'Mozilla/5.0 (compatible; AttendanceApp/1.0; +https://flutter.dev)',
};

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

    final candidates = student.profileImageNetworkUrlCandidates(apiUri);
    if (candidates.isEmpty) return null;

    // Flutter web: browser image pipeline (avoids CORS on byte fetches for first candidate).
    if (kIsWeb) {
      return NetworkImage(candidates.first);
    }

    for (final url in candidates) {
      final cached = await storage.StudentAvatarStorage.loadIfUrlMatches(
        student.indexNumber,
        url,
      );
      if (cached != null) return cached;
    }

    for (final url in candidates) {
      try {
        final res = await http
            .get(Uri.parse(url), headers: ApiService.profileImageGetHeaders())
            .timeout(const Duration(seconds: 25));
        if (res.statusCode < 200 || res.statusCode >= 300) continue;
        final bytes = res.bodyBytes;
        if (bytes.isEmpty) continue;
        return storage.StudentAvatarStorage.saveBytes(
          student.indexNumber,
          url,
          bytes,
        );
      } catch (_) {}
    }
    return null;
  }
}
