import 'package:audioplayers/audioplayers.dart';

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
}
