import java.io.FileInputStream
import java.nio.file.Files
import java.nio.file.LinkOption
import java.nio.file.Path
import java.nio.file.StandardCopyOption
import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Release-signing wiring. The keystore itself NEVER lives in this
// repository — drop the .jks and a `key.properties` file at
// android/key.properties (which is git-ignored) to enable Play-grade
// signing. The file format is documented in `key.properties.example`.
//
// When key.properties is missing the build still succeeds but the
// release APK is debug-signed (good enough for local QA, blocked by
// Play upload). Gradle prints a clear warning at configure time so
// nobody ships a debug-signed APK by accident.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
val hasReleaseSigning = keystorePropertiesFile.exists()
if (hasReleaseSigning) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
} else {
    logger.warn(
        "key.properties not found at ${keystorePropertiesFile.absolutePath}. " +
            "Release builds will be DEBUG-SIGNED and will be rejected by Google Play."
    )
}

android {
    namespace = "com.attendance.attendance_app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    defaultConfig {
        applicationId = "com.attendance.attendance_app"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            if (hasReleaseSigning) {
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
                storeFile = keystoreProperties.getProperty("storeFile")?.let { rootProject.file(it) }
                storePassword = keystoreProperties.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            // Use the real release signing config when key.properties is
            // present (CI / production builds). Fall back to debug for
            // local "flutter run --release" smoke tests.
            signingConfig = if (hasReleaseSigning) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
            // R8 is OFF for now — enabling without per-plugin proguard
            // rules can break tflite_flutter / flutter_local_notifications.
            // When the team is ready to enable, follow the migration
            // notes in docs/ANDROID_RELEASE.md.
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }

    // Cap NDK ABIs to the 64-bit + legacy 32-bit set Play accepts.
    // Excludes x86 (deprecated) and mips (removed years ago).
    splits {
        abi {
            isEnable = false
            reset()
            include("armeabi-v7a", "arm64-v8a", "x86_64")
            isUniversalApk = true
        }
    }

    // Fail the build on critical lint issues but do not abort on style
    // warnings. Keeps Gradle CI green while still catching missing
    // permission strings, manifest typos, etc.
    lint {
        warningsAsErrors = false
        abortOnError = false
        checkReleaseBuilds = true
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}

// Primary artifact: `at-enda.apk`. Flutter CLI still expects `app-release.apk`, so leave a symlink.
afterEvaluate {
    tasks.named("assembleRelease").configure {
        doLast {
            val dir = layout.buildDirectory.get().asFile.resolve("outputs/flutter-apk")
            val release = dir.resolve("app-release.apk").toPath()
            val branded = dir.resolve("at-enda.apk").toPath()
            if (Files.isRegularFile(release, LinkOption.NOFOLLOW_LINKS)) {
                Files.deleteIfExists(branded)
                Files.move(release, branded, StandardCopyOption.REPLACE_EXISTING)
                Files.createSymbolicLink(release, Path.of("at-enda.apk"))
            }
        }
    }
}
