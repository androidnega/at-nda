import 'package:connectivity_plus/connectivity_plus.dart';

/// True when the device reports a usable network interface (not [ConnectivityResult.none] only).
Future<bool> hasInternetConnectivity() async {
  final results = await Connectivity().checkConnectivity();
  return results.any(
    (r) =>
        r == ConnectivityResult.wifi ||
        r == ConnectivityResult.mobile ||
        r == ConnectivityResult.ethernet ||
        r == ConnectivityResult.vpn,
  );
}
