import 'dart:convert';

import 'package:crypto/crypto.dart';

import '../utils/constants.dart';

/// Thrown when a signed QR fails HMAC or expiry checks.
class SignedQrException implements Exception {
  SignedQrException(this.message);
  final String message;

  @override
  String toString() => message;
}

/// Result of validating a server-signed attendance QR offline.
class SignedQrPayload {
  SignedQrPayload({
    required this.sessionId,
    required this.data,
    required this.tokenForApi,
    required this.rawScan,
  });

  /// Session id from signed [data] (validated against active session in UI).
  final int sessionId;

  /// Decoded `data` object from the QR (may include course_id, expires_at, etc.).
  final Map<String, dynamic> data;

  /// Value to send as `qr_code` / token to Laravel (prefers `data['token']`).
  final String tokenForApi;

  /// Original scanned string (for debugging or server echo).
  final String rawScan;
}

/// Offline-capable QR validation: JSON `{ data, sig }` (optionally Base64-wrapped) + HMAC-SHA256 + `expires_at`.
///
/// **Secret:** set at build time — do not commit real secrets:
/// `flutter run --dart-define=QR_SECRET=your_shared_secret`
///
/// Laravel must sign the **same** UTF-8 payload string (see [signingPayload] or raw [jsonEncode] of `data`).
class SignedQrService {
  SignedQrService._();

  /// Recursively sort map keys so signing matches typical `ksort` + `json_encode` flows.
  static dynamic _canonicalize(dynamic v) {
    if (v is Map) {
      final keys = v.keys.map((k) => k.toString()).toList()..sort();
      return <String, dynamic>{
        for (final k in keys) k: _canonicalize(v[k]),
      };
    }
    if (v is List) {
      return v.map(_canonicalize).toList();
    }
    return v;
  }

  /// JSON string used for HMAC (must match server).
  static String signingPayload(Map<String, dynamic> data) =>
      jsonEncode(_canonicalize(data));

  static String _hmacSha256Hex(String secret, String message) {
    final key = utf8.encode(secret);
    final bytes = utf8.encode(message);
    final digest = Hmac(sha256, key).convert(bytes);
    return digest.toString();
  }

  /// Returns true if Base64 decodes to JSON with `data` + `sig`.
  static bool looksLikeSignedEnvelope(String scanned) {
    final t = scanned.trim();
    if (t.isEmpty || t.startsWith('{')) return false;
    try {
      final decoded = utf8.decode(base64Decode(t));
      final j = jsonDecode(decoded);
      return j is Map &&
          j.containsKey('data') &&
          j.containsKey('sig');
    } catch (_) {
      return false;
    }
  }

  /// Parse legacy plain JSON: `{ "session_id", "token" }`.
  static SignedQrPayload? tryParseLegacyPlainJson(String scanned) {
    final t = scanned.trim();
    if (!t.startsWith('{')) return null;
    try {
      final raw = jsonDecode(t);
      if (raw is! Map) return null;
      final m = Map<String, dynamic>.from(raw);
      final sid = _parsePositiveInt(m['session_id']);
      final token = m['token']?.toString();
      if (sid == null || token == null || token.isEmpty) return null;
      return SignedQrPayload(
        sessionId: sid,
        data: m,
        tokenForApi: token,
        rawScan: scanned,
      );
    } catch (_) {
      return null;
    }
  }

  static int? _parsePositiveInt(dynamic v) {
    if (v == null) return null;
    if (v is int) return v > 0 ? v : null;
    if (v is num) {
      final i = v.toInt();
      return i > 0 ? i : null;
    }
    return int.tryParse(v.toString());
  }

  static SignedQrPayload _verifySignedEnvelopeMap(
    Map<String, dynamic> envelope,
    String rawScan,
  ) {
    final secret = Constants.qrSecret.trim();
    if (secret.isEmpty) {
      throw SignedQrException(
        'Signed QR requires QR_SECRET (use --dart-define=QR_SECRET=... at build time).',
      );
    }

    final sig = envelope['sig']?.toString();
    final dataRaw = envelope['data'];
    if (sig == null || sig.isEmpty || dataRaw is! Map) {
      throw SignedQrException('Invalid QR: missing data or sig.');
    }

    final data = Map<String, dynamic>.from(dataRaw);
    final sigNorm = sig.trim().toLowerCase();
    final candCanonical = _hmacSha256Hex(secret, signingPayload(data)).toLowerCase();
    final candRaw = _hmacSha256Hex(secret, jsonEncode(data)).toLowerCase();
    if (sigNorm != candCanonical && sigNorm != candRaw) {
      throw SignedQrException('Invalid QR (tampered or wrong secret).');
    }

    final exp = data['expires_at'];
    int? expiresUnix;
    if (exp is int) {
      expiresUnix = exp;
    } else if (exp is num) {
      expiresUnix = exp.toInt();
    } else {
      expiresUnix = int.tryParse(exp?.toString() ?? '');
    }
    if (expiresUnix == null) {
      throw SignedQrException('Invalid QR: missing expires_at.');
    }

    final now = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    if (now > expiresUnix) {
      throw SignedQrException('QR expired.');
    }

    final sessionId = _parsePositiveInt(data['session_id']);
    if (sessionId == null) {
      throw SignedQrException('Invalid QR: missing session_id.');
    }

    final token = data['token']?.toString().trim();
    final tokenForApi = (token != null && token.isNotEmpty) ? token : rawScan;

    return SignedQrPayload(
      sessionId: sessionId,
      data: data,
      tokenForApi: tokenForApi,
      rawScan: rawScan,
    );
  }

  /// Decode Base64 → JSON → verify `sig` and `expires_at`, return [SignedQrPayload].
  static SignedQrPayload verifySignedEnvelope(String scanned) {
    Map<String, dynamic> envelope;
    try {
      final bytes = base64Decode(scanned.trim());
      final decoded = jsonDecode(utf8.decode(bytes));
      if (decoded is! Map) {
        throw SignedQrException('Invalid QR: envelope is not an object.');
      }
      envelope = Map<String, dynamic>.from(decoded);
    } catch (e) {
      if (e is SignedQrException) rethrow;
      throw SignedQrException('Invalid QR: could not decode Base64 JSON.');
    }

    return _verifySignedEnvelopeMap(envelope, scanned.trim());
  }

  /// Try signed envelope first (if it looks like one), else legacy JSON.
  static SignedQrPayload parseScan(String scanned) {
    final trimmed = scanned.trim();

    if (trimmed.startsWith('{')) {
      try {
        final m = Map<String, dynamic>.from(jsonDecode(trimmed));
        if (m.containsKey('data') && m.containsKey('sig')) {
          return _verifySignedEnvelopeMap(m, trimmed);
        }
      } on SignedQrException {
        rethrow;
      } catch (_) {}
    }

    if (looksLikeSignedEnvelope(trimmed)) {
      return verifySignedEnvelope(trimmed);
    }

    var legacy = tryParseLegacyPlainJson(trimmed);
    if (legacy != null) {
      return legacy;
    }

    if (!trimmed.startsWith('{')) {
      try {
        final inner = utf8.decode(base64Decode(trimmed));
        legacy = tryParseLegacyPlainJson(inner);
        if (legacy != null) {
          return legacy;
        }
        if (inner.trim().startsWith('{')) {
          final m = Map<String, dynamic>.from(jsonDecode(inner));
          if (m.containsKey('data') && m.containsKey('sig')) {
            return _verifySignedEnvelopeMap(m, trimmed);
          }
        }
      } on SignedQrException {
        rethrow;
      } catch (_) {}
    }

    throw SignedQrException(
      'Unrecognized QR format. Expected signed envelope {data,sig} or JSON with session_id and token.',
    );
  }
}
