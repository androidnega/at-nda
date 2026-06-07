package com.attendance.attendance_app

import io.flutter.embedding.android.FlutterFragmentActivity

// `local_auth` requires a FragmentActivity host so its biometric
// prompt has access to the AndroidX fragment manager — without this
// the prompt throws "No FragmentActivity" at runtime.
class MainActivity : FlutterFragmentActivity()
