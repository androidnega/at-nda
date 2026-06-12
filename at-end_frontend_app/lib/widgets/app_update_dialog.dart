import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/app_update_service.dart';

/// Modal that nudges the student to install a newer build.
///
/// Two variants:
///   * **Optional** (`isRequired == false`): Update button + Later
///     button. Tapping Later remembers the dismissal so we don't
///     re-prompt for this exact version.
///   * **Forced**   (`isRequired == true`): blocks the app — the
///     user can't dismiss the dialog except by going to the
///     download URL.
class AppUpdateDialog extends StatelessWidget {
  const AppUpdateDialog({super.key, required this.release});

  final AppReleaseInfo release;

  /// Convenience: show the dialog only when there's an update to
  /// install AND the user hasn't already dismissed this version.
  /// Returns true when the prompt was shown.
  static Future<bool> maybeShow(
    BuildContext context, {
    required AppReleaseInfo release,
  }) async {
    if (!release.hasRelease || !release.isUpdateAvailable) return false;
    if (release.versionCode == null) return false;

    if (!release.isUpdateRequired) {
      final dismissed =
          await AppUpdateService.wasDismissedFor(release.versionCode!);
      if (dismissed) return false;
    }
    if (!context.mounted) return false;
    await showDialog<void>(
      context: context,
      // Forced updates can't be cancelled with a tap-outside.
      barrierDismissible: !release.isUpdateRequired,
      builder: (_) => AppUpdateDialog(release: release),
    );
    return true;
  }

  @override
  Widget build(BuildContext context) {
    final required = release.isUpdateRequired;

    return PopScope(
      canPop: !required,
      child: Dialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
        ),
        backgroundColor: Colors.white,
        clipBehavior: Clip.antiAlias,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: required
                        ? const [Color(0xFF7F1D1D), Color(0xFFB91C1C)]
                        : const [Color(0xFF065F46), Color(0xFF047857)],
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.android,
                          color: Colors.white, size: 26),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            required
                                ? 'Update required'
                                : 'New version available',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                          if (release.versionName != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              'v${release.versionName}'
                              '${release.versionCode != null ? ' · code ${release.versionCode}' : ''}',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.85),
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      required
                          ? 'You need to install the latest build before you can continue using the app.'
                          : 'A newer build of the attendance app is available. Install it for the latest fixes and features.',
                      style: const TextStyle(
                        fontSize: 13,
                        color: Color(0xFF334155),
                        height: 1.4,
                      ),
                    ),
                    if ((release.releaseNotes ?? '').trim().isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(10),
                          border:
                              Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'WHAT\'S NEW',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF64748B),
                                letterSpacing: 0.5,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              release.releaseNotes!.trim(),
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF334155),
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    if (release.apkSizeBytes != null &&
                        release.apkSizeBytes! > 0) ...[
                      const SizedBox(height: 10),
                      Text(
                        'Download size: ${_humanSize(release.apkSizeBytes!)}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const Divider(height: 1, color: Color(0xFFE5E7EB)),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    if (!required)
                      TextButton(
                        onPressed: () async {
                          if (release.versionCode != null) {
                            await AppUpdateService.markDismissed(
                                release.versionCode!);
                          }
                          if (context.mounted) Navigator.of(context).pop();
                        },
                        child: const Text('Later'),
                      ),
                    FilledButton.icon(
                      style: FilledButton.styleFrom(
                        backgroundColor: required
                            ? const Color(0xFFB91C1C)
                            : const Color(0xFF047857),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 18, vertical: 12),
                      ),
                      onPressed: () => _openDownload(context),
                      icon: const Icon(Icons.download_rounded, size: 18),
                      label: Text(required ? 'Update now' : 'Download'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openDownload(BuildContext context) async {
    final url = release.downloadUrl ?? release.webLandingUrl;
    if (url == null || url.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('No download URL configured. Contact an admin.'),
      ));
      return;
    }
    final uri = Uri.tryParse(url);
    if (uri == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Invalid download URL.'),
      ));
      return;
    }
    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (ok && !release.isUpdateRequired && context.mounted) {
        // Once the browser is open the user is on their way; close
        // the optional prompt so they aren't staring at it when
        // they switch back to the app.
        Navigator.of(context).pop();
      }
      if (!ok && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Could not open the download page.'),
        ));
      }
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Could not open the download page.'),
        ));
      }
    }
  }

  static String _humanSize(int bytes) {
    if (bytes <= 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    var value = bytes.toDouble();
    var i = 0;
    while (value >= 1024 && i < units.length - 1) {
      value /= 1024;
      i++;
    }
    return '${value.toStringAsFixed(1)} ${units[i]}';
  }
}
