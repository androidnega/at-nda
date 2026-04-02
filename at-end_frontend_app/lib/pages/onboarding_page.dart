import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/face_service.dart';
import '../services/offline_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_state.dart';
import '../utils/constants.dart';
import '../widgets/custom_button.dart';

/// First-time onboarding: face descriptor + optional API sync.
class OnboardingPage extends StatefulWidget {
  final String indexNumber;
  final Student student;
  final String password;

  const OnboardingPage({
    super.key,
    required this.indexNumber,
    required this.student,
    required this.password,
  });

  @override
  State<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends State<OnboardingPage> {
  bool _isLoading = false;
  String? _error;
  bool _captureFailed = false;

  /// Laravel: `POST /api/onboarding/complete`.
  Future<void> _syncOnboardingToServer(Student updatedStudent) async {
    try {
      final body = <String, dynamic>{
        'index_number': widget.indexNumber,
      };
      final phone = (widget.student.phoneNumber ?? '').trim();
      if (phone.length >= 10) {
        body['phone_number'] = phone;
      }
      final img = updatedStudent.profileImage.trim();
      if (img.startsWith('data:image/')) {
        body['profile_image'] = img;
      } else if (img.isNotEmpty &&
          img != 'local_captured' &&
          !img.startsWith('http://') &&
          !img.startsWith('https://')) {
        body['profile_image'] = Constants.jpegDataUriFromRawBase64(img);
      }
      await ApiService.post('onboarding/complete', body);
    } catch (_) {}
  }

  Future<void> _completeOnboarding() async {
    setState(() {
      _isLoading = true;
      _error = null;
      _captureFailed = false;
    });

    try {
      await FaceService.loadModel();
      if (!kIsWeb) {
        setState(() => _error = 'Position your face in the frame');
      }
      List<double> descriptor = await FaceService.getFaceDescriptor();
      if (descriptor.isEmpty && !kIsWeb) {
        setState(() {
          _captureFailed = true;
          _error = 'Could not capture face, try again';
          _isLoading = false;
        });
        return;
      }
      if (descriptor.isEmpty) {
        descriptor = FaceService.getPlaceholderDescriptor();
      }
      setState(() {
        _error = null;
        _captureFailed = false;
      });

      final updatedStudent = widget.student.copyWith(
        profileImage: widget.student.profileImage.isEmpty
            ? 'local_captured'
            : widget.student.profileImage,
        faceDescriptor: descriptor,
      );

      await OfflineService.setCurrentStudent(updatedStudent);
      AppState.studentIndex = widget.indexNumber;
      await OfflineService.setApiSessionPassword(widget.password.trim());
      await _syncOnboardingToServer(updatedStudent);

      if (mounted) {
        Navigator.of(context).pushReplacementNamed('/home');
      }
    } catch (e) {
      setState(() => _error = 'Error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _completeWithPlaceholder() async {
    setState(() {
      _isLoading = true;
      _error = null;
      _captureFailed = false;
    });
    try {
      final descriptor = FaceService.getPlaceholderDescriptor();
      final updatedStudent = widget.student.copyWith(
        profileImage: widget.student.profileImage.isEmpty
            ? 'local_captured'
            : widget.student.profileImage,
        faceDescriptor: descriptor,
      );
      await OfflineService.setCurrentStudent(updatedStudent);
      AppState.studentIndex = widget.indexNumber;
      await OfflineService.setApiSessionPassword(widget.password.trim());
      await _syncOnboardingToServer(updatedStudent);
      if (mounted) {
        Navigator.of(context).pushReplacementNamed('/home');
      }
    } catch (e) {
      setState(() => _error = 'Error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: AppTheme.heroGradientDecoration(),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 16),
                Text(
                  'Almost there',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 8),
                Text(
                  'We need a quick face profile for attendance verification.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
                const SizedBox(height: 8),
                Text(
                  kIsWeb
                      ? 'On web we use a placeholder. Use a phone for a real capture.'
                      : 'Position your face in the frame when you tap the button.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 28),
                Expanded(
                  child: Card(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        children: [
                          Icon(
                            Icons.face_retouching_natural_rounded,
                            size: 72,
                            color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.9),
                          ),
                          const SizedBox(height: 20),
                          if (_error != null)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: Column(
                                children: [
                                  Text(
                                    _error!,
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      color: _captureFailed
                                          ? Theme.of(context).colorScheme.error
                                          : Theme.of(context).colorScheme.primary,
                                    ),
                                  ),
                                  if (_captureFailed) ...[
                                    const SizedBox(height: 12),
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        TextButton(
                                          onPressed: _completeOnboarding,
                                          child: const Text('Retry'),
                                        ),
                                        TextButton(
                                          onPressed: _completeWithPlaceholder,
                                          child: const Text('Skip for now'),
                                        ),
                                      ],
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          const Spacer(),
                          CustomButton(
                            label: 'Capture & continue',
                            onPressed: _completeOnboarding,
                            isLoading: _isLoading,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
