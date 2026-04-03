import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../services/profile_identity_cooldown.dart';
import '../services/profile_image_cache.dart';
import '../utils/constants.dart';
import '../services/permission_service.dart';
import '../widgets/profile_avatar.dart';

/// Profile: update phone, email, upload/retake photo.
class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  Student? _student;
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  /// Baseline for cooldown: values shown after last successful load/save.
  String _baselineFirst = '';
  String _baselineLast = '';
  bool _isLoading = true;
  bool _isSaving = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final student = await OfflineService.getCurrentStudent();
    if (student == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }
    var f = student.firstName?.trim() ?? '';
    var l = student.lastName?.trim() ?? '';
    if (f.isEmpty && l.isEmpty) {
      final parts = student.name.trim().split(RegExp(r'\s+'));
      if (parts.length >= 2) {
        f = parts.first;
        l = parts.sublist(1).join(' ');
      } else if (parts.isNotEmpty) {
        f = parts.first;
      }
    }
    _baselineFirst = f;
    _baselineLast = l;
    if (mounted) {
      setState(() {
        _student = student;
        _phoneController.text = student.phoneNumber ?? '';
        _emailController.text = student.email ?? '';
        _firstNameController.text = f;
        _lastNameController.text = l;
        _isLoading = false;
      });
    }
  }

  Future<void> _retakePhoto() async {
    if (_student == null) return;
    if (!await ProfileIdentityCooldown.canEditIdentity()) {
      if (!mounted) return;
      final hint = await ProfileIdentityCooldown.nextAllowedHint();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(hint ?? 'Try again later.')),
      );
      return;
    }
    await PermissionService.requestAll();
    final picker = ImagePicker();
    try {
      final XFile? image = await picker.pickImage(
        source: kIsWeb ? ImageSource.gallery : ImageSource.camera,
        imageQuality: 80,
      );
      if (image == null || !mounted) return;
      final bytes = await image.readAsBytes();
      final rawB64 = base64Encode(bytes);
      final dataUri = Constants.jpegDataUriFromRawBase64(rawB64);
      final updated = _student!.copyWith(profileImage: dataUri);
      await OfflineService.setCurrentStudent(updated);
      try {
        final pwd = await OfflineService.getApiSessionPassword();
        if (pwd != null && pwd.isNotEmpty) {
          await ApiService.post('student/profile', {
            'index_number': updated.indexNumber,
            'password': pwd,
            'profile_image': dataUri,
          });
        }
      } catch (_) {}
      await ProfileImageCache.instance.invalidate(updated.indexNumber);
      await ProfileIdentityCooldown.recordIdentityEdit();
      if (mounted) {
        setState(() {
          _student = updated;
          _message = 'Profile photo updated.';
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not capture: $e')),
        );
      }
    }
  }

  Future<void> _save() async {
    if (_student == null) return;

    final phone = _phoneController.text.trim();
    final email = _emailController.text.trim();
    final newFirst = _firstNameController.text.trim();
    final newLast = _lastNameController.text.trim();
    final nameIdentityChanged =
        newFirst != _baselineFirst || newLast != _baselineLast;

    if (nameIdentityChanged) {
      if (!await ProfileIdentityCooldown.canEditIdentity()) {
        if (!mounted) return;
        final hint = await ProfileIdentityCooldown.nextAllowedHint();
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(hint ?? 'Try again later.')),
        );
        return;
      }
    }

    setState(() {
      _isSaving = true;
      _message = null;
    });

    final combined = '$newFirst $newLast'.trim();
    final updated = _student!.copyWith(
      phoneNumber: phone.isEmpty ? null : phone,
      email: email.isEmpty ? null : email,
      firstName: newFirst.isEmpty ? null : newFirst,
      lastName: newLast.isEmpty ? null : newLast,
      name: combined.isEmpty ? _student!.name : combined,
    );

    await OfflineService.setCurrentStudent(updated);

    var feedback = 'Profile updated.';
    try {
      final pwd = await OfflineService.getApiSessionPassword();
      if (pwd != null && pwd.isNotEmpty) {
        final res = await ApiService.updateProfile({
          'index_number': updated.indexNumber,
          'password': pwd,
          'phone_number': updated.phoneNumber,
          'first_name': updated.firstName,
          'last_name': updated.lastName,
          'email': updated.email,
        });
        if (res.statusCode < 200 || res.statusCode >= 300) {
          final hint = ApiService.messageFromHttpResponse(res);
          feedback = hint.isEmpty
              ? 'Saved on device; server returned ${res.statusCode}.'
              : 'Saved on device. $hint';
        }
      } else {
        feedback =
            'Saved on this device only. Sign in online once to sync profile to the server.';
      }
    } catch (_) {
      feedback =
          'Saved on this device. Could not reach server — try again when online.';
    }

    if (mounted) {
      if (nameIdentityChanged) {
        await ProfileIdentityCooldown.recordIdentityEdit();
        _baselineFirst = newFirst;
        _baselineLast = newLast;
      }
      setState(() {
        _student = updated;
        _isSaving = false;
        _message = feedback;
      });
    }
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _emailController.dispose();
    _firstNameController.dispose();
    _lastNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_student == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: const Center(child: Text('No profile.')),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Card(
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: ProfileAvatar(student: _student!, radius: 48),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      _student!.displayFirstLastName,
                      style: Theme.of(context).textTheme.titleLarge,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Student ID',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                    ),
                    const SizedBox(height: 2),
                    SelectableText(
                      _student!.indexNumber,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.5,
                          ),
                    ),
                    if (_student!.className != null) ...[
                      const SizedBox(height: 8),
                      Text('Class: ${_student!.className}'),
                    ],
                    if (_student!.faculty != null)
                      Text('Faculty: ${_student!.faculty}'),
                    if (_student!.department != null)
                      Text('Department: ${_student!.department}'),
                    if (_student!.level != null)
                      Text('Level: ${_student!.level}'),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Card(
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Update info',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'First name, last name, and profile photo share one limit: at most one change every 90 days. Phone and email are not limited.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _firstNameController,
                      decoration: const InputDecoration(
                        labelText: 'First name',
                        border: OutlineInputBorder(),
                      ),
                      textCapitalization: TextCapitalization.words,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _lastNameController,
                      decoration: const InputDecoration(
                        labelText: 'Last name',
                        border: OutlineInputBorder(),
                      ),
                      textCapitalization: TextCapitalization.words,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _phoneController,
                      decoration: const InputDecoration(
                        labelText: 'Phone number',
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: TextInputType.phone,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _emailController,
                      decoration: const InputDecoration(
                        labelText: 'Email (optional)',
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    if (_message != null) ...[
                      const SizedBox(height: 16),
                      Text(_message!),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _isSaving ? null : _save,
                      child: _isSaving
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Save'),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Card(
              elevation: 0,
              child: ListTile(
                title: const Text('Retake profile photo'),
                subtitle: const Text(
                  'Camera or gallery. Counts toward the 90-day name/photo limit.',
                ),
                leading: const Icon(Icons.camera_alt),
                onTap: _retakePhoto,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
