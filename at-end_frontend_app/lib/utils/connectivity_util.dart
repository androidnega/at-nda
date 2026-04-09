import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart' show kIsWeb;

/// True when the device reports a usable network interface (not [ConnectivityResult.none] only).
///
/// On web, [Connectivity] is unreliable across browsers; treat as online and let HTTP fail visibly.
Future<bool> hasInternetConnectivity() async {
  if (kIsWeb) return true;
  try {
    final results = await Connectivity().checkConnectivity();
    if (results.isEmpty) return true;
    return results.any(
      (r) =>
          r == ConnectivityResult.wifi ||
          r == ConnectivityResult.mobile ||
          r == ConnectivityResult.ethernet ||
          r == ConnectivityResult.vpn ||
          r == ConnectivityResult.other,
    );
  } catch (_) {
    return true;
  }
}
