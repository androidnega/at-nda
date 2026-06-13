import 'dart:math';

/// Client-generated idempotency key for one attendance attempt.
///
/// Format:  `att_<14 char timestamp-base36>_<10 char random-base36>`
/// Total length stays under 64 chars (matches the server validator).
///
/// The timestamp prefix makes the key roughly sortable in logs / DB
/// listings and helps detect "clock-skew" replay issues. The random tail
/// is pulled from [Random.secure] so two parallel submits cannot collide
/// even on the same millisecond.
class AttendanceUuid {
  static final Random _rng = Random.secure();

  static const String _alphabet =
      '0123456789abcdefghijklmnopqrstuvwxyz';

  /// Returns a fresh idempotency key, e.g. `att_l5b1xc9y2k8mn_6q4d2x9bky`.
  static String generate() {
    final ts = DateTime.now().toUtc().millisecondsSinceEpoch;
    final tsPart = _base36(ts).padLeft(9, '0');
    final randomPart = _randomBase36(10);
    return 'att_${tsPart}_$randomPart';
  }

  static String _base36(int value) {
    if (value == 0) return '0';
    var v = value;
    final out = StringBuffer();
    while (v > 0) {
      out.write(_alphabet[v % 36]);
      v ~/= 36;
    }
    return out.toString().split('').reversed.join();
  }

  static String _randomBase36(int length) {
    final out = StringBuffer();
    for (var i = 0; i < length; i++) {
      out.write(_alphabet[_rng.nextInt(36)]);
    }
    return out.toString();
  }
}
