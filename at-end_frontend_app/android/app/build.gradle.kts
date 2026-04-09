import java.nio.file.Files
import java.nio.file.LinkOption
import java.nio.file.Path
import java.nio.file.StandardCopyOption

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
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
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.attendance.attendance_app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")
        }
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
