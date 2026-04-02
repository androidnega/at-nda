import 'dart:convert';

import 'package:crypto/crypto.dart';

/// Hash password for local storage. Use same hash to verify.
class PasswordUtil {
  static String hash(String password) {
    final bytes = utf8.encode(password);
    final digest = sha256.convert(bytes);
    return digest.toString();
  }

  static bool verify(String password, String storedHash) {
    return hash(password) == storedHash;
  }
}
