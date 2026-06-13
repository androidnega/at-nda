# Google Play / Play Protect Readiness

This document describes the hardening applied to the Android build to
keep the app aligned with Google Play Console policies and Google Play
Protect signals. It is intended for the release engineer who uploads
the AAB to Play.

## TL;DR

The APK / AAB produced by `flutter build appbundle --release` ships with:

- 8 user-facing permissions, all justified by app function
- HTTPS-only network access in release (`networkSecurityConfig`)
- `allowBackup=false` + Android-12+ `dataExtractionRules` (no cloud
  backup, no device-to-device transfer of credentials)
- Predictive-back gesture support (`enableOnBackInvokedCallback=true`)
- 64-bit native libraries (`arm64-v8a`) + 32-bit ARM + x86_64
- `targetSdkVersion=36` (Android 16), `compileSdkVersion=36`
- Every component (`<activity>`, `<service>`, `<receiver>`, `<provider>`)
  declares `android:exported` explicitly
- No advertising id (`AD_ID` removed via `tools:node="remove"`)
- No exact-alarm permissions (`SCHEDULE_EXACT_ALARM`, `USE_EXACT_ALARM`
  removed)
- No legacy storage permissions (`READ/WRITE_EXTERNAL_STORAGE`,
  `READ_MEDIA_*`, `MANAGE_EXTERNAL_STORAGE` all removed)
- No microphone / SMS / call-log / contacts permissions
- Release signing wired through `android/key.properties` (a missing
  file falls back to debug-signing locally, which Play rejects on
  upload)

## Final permission set (merged manifest)

```
android.permission.INTERNET                  Laravel API + sync
android.permission.ACCESS_NETWORK_STATE      connectivity_plus
android.permission.POST_NOTIFICATIONS        in-foreground notifications only
android.permission.VIBRATE                   haptic on successful mark
android.permission.CAMERA                    QR + face capture
android.permission.ACCESS_FINE_LOCATION      attendance geofence
android.permission.ACCESS_COARSE_LOCATION    attendance geofence fallback
android.permission.USE_BIOMETRIC             biometric gate before marking
```

Signature-level permissions auto-added by AndroidX (invisible to users):

```
DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION
DUMP                                         protects ProfileInstallReceiver
BIND_JOB_SERVICE                             protects JobInfoSchedulerService
```

## Files that drive Play hygiene

```
android/app/src/main/AndroidManifest.xml
  Lean root manifest. Declares only the 8 permissions above and
  uses tools:node="remove" to strip any transitive permission a
  plugin tries to merge in.

android/app/src/main/res/xml/network_security_config.xml
  Release-build network policy. Cleartext disabled. System trust
  anchors only.

android/app/src/debug/res/xml/network_security_config.xml
  Debug-build overlay. Re-enables cleartext for 10.0.2.2 / localhost
  so the emulator can talk to a developer-machine Laravel. This file
  is NEVER bundled into release builds.

android/app/src/main/res/xml/data_extraction_rules.xml
  Android 12+ deny-all backup + device-to-device transfer rules.
  Server is source of truth; a fresh install must log in again.

android/app/build.gradle.kts
  - Release signing through key.properties (with safe fallback to
    debug signing for local "flutter run --release" smoke tests).
  - 64-bit ABIs enforced (arm64-v8a included).
  - Lint set to fail on critical issues at release time.

android/key.properties.example
  Template the release engineer copies to android/key.properties
  (git-ignored) and fills in with the upload-keystore credentials.
```

## Pre-upload checklist (release engineer)

1. **Keystore.** Generate the upload keystore once:
   ```
   keytool -genkey -v -keystore upload-keystore.jks \
     -keyalg RSA -keysize 2048 -validity 10000 -alias upload
   ```
   Copy the .jks to `android/upload-keystore.jks` (git-ignored) and
   create `android/key.properties` from the .example template.

2. **Build the AAB.** From `at-end_frontend_app/`:
   ```
   flutter build appbundle --release \
     --dart-define=API_BASE_URL=https://at-enda.manuelcode.info/api \
     --dart-define=QR_SECRET=<production_qr_secret>
   ```

3. **Confirm the merged manifest** in the AAB has only the 8
   user-facing permissions listed above. From the project root:
   ```
   $ANDROID_HOME/build-tools/36.0.0/aapt2 dump permissions \
     build/app/outputs/bundle/release/app-release.aab
   ```

4. **Play Console items that are NOT in source code** but Play will
   still gate on:

   - **Privacy policy URL.** Add a URL to a public privacy policy.
     The policy MUST cover camera, location (foreground), and
     biometric data handling. The app does on-device face
     embedding (TFLite) and sends only the embedding vector to the
     server, never the raw image — the privacy policy should say so.
   - **Data Safety form.** Declare:
       Location → "Approximate" + "Precise" → "App functionality"
                  (attendance verification). Not shared with third
                  parties. Collected.
       Camera   → "Photos and videos" → "App functionality"
                  (face verification + QR). On-device processing only.
       Account info → "User IDs" + "Name" → "App functionality"
                      (student index, profile photo). Server-stored.
       NO: financial info, health, fitness, messages, location
       history, advertising data.
   - **Permissions and APIs that require declarations.** The current
     build does not include any restricted permission that requires
     a declaration form (no SMS, no Accessibility, no Call Log, no
     All Files Access, no exact alarms, no foreground location
     background-execution).
   - **Sensitive permissions justification.** Camera and Location
     prompts already have rationale strings handled by
     `permission_handler` in the Flutter app.

5. **Internal testing track first.** Roll the AAB through the
   "Internal testing" track on Play before promoting to closed /
   open testing. Play Protect scans every upload — an internal track
   release will fail fast if a scanner flag was missed here.

## What changed (this checkpoint)

| Area                   | Before                                | After                                       |
|------------------------|---------------------------------------|---------------------------------------------|
| Cleartext traffic      | `usesCleartextTraffic="true"`         | Disabled in release; emulator-only in debug |
| Backup                 | Default (auto-backup of prefs / DB)   | `allowBackup=false` + `dataExtractionRules` |
| AD_ID permission       | Auto-merged                           | Removed (`tools:node="remove"`)             |
| Exact-alarm perms      | Auto-merged from flutter_local_notif. | Removed                                     |
| Storage perms          | Auto-merged from image_picker         | Removed; image_picker plugin removed        |
| URL launcher           | Listed in pubspec, unused             | Removed                                     |
| Release signing        | Debug-key (Play would reject)         | `key.properties` driven release signing     |
| 32-bit ARM             | Auto                                  | Explicit `arm64-v8a + armeabi-v7a + x86_64` |
| `<uses-feature>`       | Not declared                          | Camera + location declared as `not-required`|
| Predictive back        | Off                                   | `enableOnBackInvokedCallback=true`          |
| Components `exported`  | MainActivity only                     | Every component explicitly declared         |

## Things to be aware of going forward

- **Adding a plugin?** Run a debug build, then dump permissions:
  ```
  aapt2 dump permissions build/app/outputs/flutter-apk/app-debug.apk
  ```
  If a new permission appears that the app does not actually need at
  runtime, add a `tools:node="remove"` line for it in the manifest.

- **Adding URL launching?** When you add `url_launcher` (or any
  intent-launching feature) back, also add the matching `<intent>`
  entries inside `<queries>` so Android 11+ package visibility
  works.

- **Adding scheduled notifications?** If notifications need to fire
  while the app is closed, do NOT add `SCHEDULE_EXACT_ALARM` — use
  inexact alarms (`schedule(allowWhileIdle: false)`). Exact alarms
  trigger a separate Play declaration form.

- **Adding analytics / ads?** If a future release uses Firebase
  Analytics, Google Ads, or any SDK that needs the advertising id,
  the `<uses-permission android:name="com.google.android.gms.permission.AD_ID" tools:node="remove" />`
  entry must be removed AND the Data Safety form updated to declare
  advertising-id collection.
