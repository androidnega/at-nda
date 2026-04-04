import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/services.dart';

/// Short pleasant chime when QR is valid and attendance is recorded.
class SuccessChime {
  SuccessChime._();

  static final AudioPlayer _player = AudioPlayer();

  static Future<void> play() async {
    try {
      await _player.setReleaseMode(ReleaseMode.stop);
      await _player.setVolume(0.88);
      await _player.stop();
      await _player.play(AssetSource('assets/sounds/success.wav'));
    } catch (_) {
      // Playback can fail on some emulators or without audio output.
    }
  }

  /// Softer, shorter-feeling feedback when pull-to-refresh finishes (lower volume).
  static Future<void> playRefreshComplete() async {
    try {
      await _player.setReleaseMode(ReleaseMode.stop);
      await _player.setVolume(0.34);
      await _player.stop();
      await _player.play(AssetSource('assets/sounds/success.wav'));
    } catch (_) {
      // Playback can fail on some emulators or without audio output.
    }
  }

  /// In-app reminder (polling) — short tone so users notice without full celebration volume.
  static Future<void> playNotificationTone() async {
    try {
      await _player.setReleaseMode(ReleaseMode.stop);
      await _player.setVolume(0.62);
      await _player.stop();
      await _player.play(AssetSource('assets/sounds/success.wav'));
    } catch (_) {
      try {
        await SystemSound.play(SystemSoundType.alert);
      } catch (_) {}
    }
  }

  /// Sound + haptics after attendance is recorded (not used when QR scanner already played [play]).
  static Future<void> celebrateAttendanceMarked({bool playChime = true}) async {
    if (playChime) await play();
    if (kIsWeb) return;
    try {
      await HapticFeedback.heavyImpact();
      await Future<void>.delayed(const Duration(milliseconds: 48));
      await HapticFeedback.mediumImpact();
    } catch (_) {}
  }
}
