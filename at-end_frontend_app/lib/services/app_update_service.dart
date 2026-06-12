import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../utils/constants.dart';
import 'api_service.dart';

/// Result of a single update probe. Mirrors the JSON envelope from
/// GET /api/app/latest.
class AppReleaseInfo {
  final bool hasRelease;
  final String? versionName;
  final int? versionCode;
  final String? releaseNotes;
  final String? downloadUrl;
  final String? webLandingUrl;
  final int? apkSizeBytes;
  final String? sha256;
  final bool isUpdateAvailable;
  final bool isUpdateRequired;

  const AppReleaseInfo({
    required this.hasRelease,
    this.versionName,
    this.versionCode,
    this.releaseNotes,
    this.downloadUrl,
    this.webLandingUrl,
    this.apkSizeBytes,
    this.sha256,
    this.isUpdateAvailable = false,
    this.isUpdateRequired = false,
  });

  static const AppReleaseInfo none =
      AppReleaseInfo(hasRelease: false, isUpdateAvailable: false);

  factory AppReleaseInfo.fromJson(Map<String, dynamic> json) {
    return AppReleaseInfo(
      hasRelease: json['has_release'] == true,
      versionName: json['version_name']?.toString(),
      versionCode: _asInt(json['version_code']),
      releaseNotes: json['release_notes']?.toString(),
      downloadUrl: json['download_url']?.toString(),
      webLandingUrl: json['web_landing_url']?.toString(),
      apkSizeBytes: _asInt(json['apk_size_bytes']),
      sha256: json['apk_sha256']?.toString(),
      isUpdateAvailable: json['is_update_available'] == true,
      isUpdateRequired: json['is_update_required'] == true,
    );
  }

  static int? _asInt(dynamic v) {
    if (v == null) return null;
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString());
  }
}

/// One-shot check against /api/app/latest.
///
/// Lives on its own service so the UI layer can be tested in
/// isolation. The Flutter app calls [check] once on app launch
/// (and the user can be re-prompted next launch if they tapped
/// "Later").
class AppUpdateService {
  static const String _platform = 'android';

  // Limit how often we hit the endpoint — once per 6 hours unless
  // the user explicitly triggers a check. Prevents pull-to-refresh
  // hammering the API on every load.
  static const Duration _minRecheckInterval = Duration(hours: 6);
  static const String _prefsLastCheckedAt = 'app_update_last_checked_at_ms';
  static const String _prefsDismissedForVersion =
      'app_update_dismissed_for_version_code';

  static Future<AppReleaseInfo> check({bool force = false}) async {
    // Only Android has a release channel for now. iOS bails early
    // so we don't waste a network request on a no-op.
    if (!kIsWeb && !Platform.isAndroid) {
      return AppReleaseInfo.none;
    }

    if (!force && !await _isDueForCheck()) {
      return AppReleaseInfo.none;
    }

    try {
      final pkg = await PackageInfo.fromPlatform();
      final currentCode = int.tryParse(pkg.buildNumber) ?? 0;

      final uri = Uri.parse('${Constants.baseUrl}/app/latest').replace(
        queryParameters: {
          'platform': _platform,
          'current_version_code': currentCode.toString(),
        },
      );
      final res = await http
          .get(uri, headers: ApiService.requestHeaders())
          .timeout(const Duration(seconds: 6));

      await _markChecked();

      if (res.statusCode < 200 || res.statusCode >= 300) {
        return AppReleaseInfo.none;
      }
      final body = jsonDecode(res.body);
      if (body is! Map ||
          body['success'] != true ||
          body['data'] is! Map) {
        return AppReleaseInfo.none;
      }
      return AppReleaseInfo.fromJson(
        Map<String, dynamic>.from(body['data'] as Map),
      );
    } catch (_) {
      return AppReleaseInfo.none;
    }
  }

  /// Remember that the user said "Later" for this specific version.
  /// We won't pester them about this build again — they'll get a
  /// new prompt only when the next release ships.
  static Future<void> markDismissed(int versionCode) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt(_prefsDismissedForVersion, versionCode);
    } catch (_) {}
  }

  /// Returns true when the user has already declined this exact
  /// version. Forced updates ignore this — they re-show every
  /// launch until the user updates.
  static Future<bool> wasDismissedFor(int versionCode) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getInt(_prefsDismissedForVersion) == versionCode;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> _isDueForCheck() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final last = prefs.getInt(_prefsLastCheckedAt) ?? 0;
      if (last == 0) return true;
      final lastDt = DateTime.fromMillisecondsSinceEpoch(last);
      return DateTime.now().difference(lastDt) >= _minRecheckInterval;
    } catch (_) {
      return true;
    }
  }

  static Future<void> _markChecked() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt(
        _prefsLastCheckedAt,
        DateTime.now().millisecondsSinceEpoch,
      );
    } catch (_) {}
  }
}
