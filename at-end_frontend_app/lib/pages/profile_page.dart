import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:image/image.dart' as img;
import 'package:image_picker/image_picker.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../services/profile_identity_cooldown.dart';
import '../services/profile_image_cache.dart';
import '../services/permission_service.dart';
import '../services/student_profile_refresh.dart';
import '../utils/constants.dart';
import '../widgets/profile_avatar.dart';
import '../widgets/profile_image_crop_dialog.dart';

/// Profile: view / edit name & email; photo via crop; phone read-only (admin-only changes on server).
class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  Student? _student;
  final _emailController = TextEditingController();
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  String _baselineFirst = '';
  String _baselineLast = '';
  bool _isLoading = true;
  bool _isSaving = false;
  bool _editing = false;

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
        _emailController.text = student.email ?? '';
        _firstNameController.text = f;
        _lastNameController.text = l;
        _isLoading = false;
        _editing = false;
      });
    }
  }

  void _syncControllersFromStudent() {
    final student = _student;
    if (student == null) return;
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
    _firstNameController.text = f;
    _lastNameController.text = l;
    _emailController.text = student.email ?? '';
  }

  void _cancelEdit() {
    _syncControllersFromStudent();
    setState(() => _editing = false);
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
        imageQuality: 92,
      );
      if (image == null || !mounted) return;
      final rawBytes = await image.readAsBytes();
      if (!mounted) return;
      final cropped = await showProfileImageCropDialog(context, rawBytes);
      if (cropped == null || !mounted) return;

      Uint8List jpegBytes;
      try {
        final decoded = img.decodeImage(cropped);
        if (decoded != null) {
          jpegBytes = Uint8List.fromList(img.encodeJpg(decoded, quality: 88));
        } else {
          jpegBytes = cropped;
        }
      } catch (_) {
        jpegBytes = cropped;
      }

      final rawB64 = base64Encode(jpegBytes);
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
      Student? merged = updated;
      try {
        final refreshed = await refreshStudentProfileFromApi(updated);
        if (refreshed != null) merged = refreshed;
      } catch (_) {}
      if (mounted) {
        setState(() => _student = merged);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile photo updated.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not update photo: $e')),
        );
      }
    }
  }

  Future<void> _save() async {
    if (_student == null) return;

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

    setState(() => _isSaving = true);

    final combined = '$newFirst $newLast'.trim();
    final updated = _student!.copyWith(
      phoneNumber: _student!.phoneNumber,
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
        if (!mounted) return;
        _baselineFirst = newFirst;
        _baselineLast = newLast;
      }
      setState(() {
        _student = updated;
        _isSaving = false;
        _editing = false;
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(feedback)),
      );
    }
  }

  @override
  void dispose() {
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

    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final s = _student!;
    final phone = (s.phoneNumber ?? '').trim();
    final email = (s.email ?? '').trim();
    final classGroup = s.classGroupWithLevelLabel;
    final semester = (s.semester ?? '').trim();
    final faculty = (s.faculty ?? '').trim();
    final department = (s.department ?? '').trim();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          if (_editing)
            TextButton(
              onPressed: _isSaving ? null : _cancelEdit,
              child: const Text('Cancel'),
            )
          else
            TextButton(
              onPressed: () => setState(() => _editing = true),
              child: const Text('Edit'),
            ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Semantics(
                label: 'Change profile photo',
                button: true,
                child: SizedBox(
                  width: 108,
                  height: 108,
                  child: Stack(
                    clipBehavior: Clip.none,
                    alignment: Alignment.center,
                    children: [
                      ProfileAvatar(
                        key: ValueKey<String>(
                          '${s.indexNumber}_${s.profileImage.hashCode}_${s.serverId}',
                        ),
                        student: s,
                        radius: 48,
                      ),
                      Positioned(
                        right: -2,
                        bottom: -2,
                        child: Material(
                          elevation: 3,
                          shadowColor: Colors.black26,
                          shape: const CircleBorder(),
                          color: cs.surface,
                          child: InkWell(
                            customBorder: const CircleBorder(),
                            onTap: _retakePhoto,
                            child: Padding(
                              padding: const EdgeInsets.all(10),
                              child: FaIcon(
                                FontAwesomeIcons.penToSquare,
                                size: 15,
                                color: cs.primary,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              s.displayFirstLastName,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w600,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            _ProfileInfoCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _kvRow(context, 'Index', s.indexNumber, mono: true),
                  if (classGroup != null && classGroup.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _kvRow(context, 'Class', classGroup),
                  ],
                  if (semester.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _kvRow(context, 'Semester', semester),
                  ],
                  if (faculty.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _kvRow(context, 'Faculty', faculty),
                  ],
                  if (department.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _kvRow(context, 'Department', department),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            _ProfileInfoCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    _editing ? 'Edit details' : 'Your details',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Name and photo: one edit per 90 days. Phone is set by an administrator.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: cs.onSurfaceVariant,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (!_editing) ...[
                    _kvRow(context, 'Name', s.displayFirstLastName),
                    const SizedBox(height: 12),
                    _kvRow(
                      context,
                      'Phone',
                      phone.isNotEmpty ? phone : '—',
                      mono: phone.isNotEmpty,
                    ),
                    const SizedBox(height: 12),
                    _kvRow(
                      context,
                      'Email',
                      email.isNotEmpty ? email : '—',
                    ),
                  ] else ...[
                    TextFormField(
                      controller: _firstNameController,
                      decoration: const InputDecoration(
                        labelText: 'First name',
                        border: OutlineInputBorder(),
                        isDense: true,
                      ),
                      textCapitalization: TextCapitalization.words,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _lastNameController,
                      decoration: const InputDecoration(
                        labelText: 'Last name',
                        border: OutlineInputBorder(),
                        isDense: true,
                      ),
                      textCapitalization: TextCapitalization.words,
                    ),
                    const SizedBox(height: 14),
                    _kvRow(
                      context,
                      'Phone',
                      phone.isNotEmpty ? phone : '—',
                      mono: phone.isNotEmpty,
                    ),
                    Padding(
                      padding: const EdgeInsets.only(left: 88, top: 4),
                      child: Text(
                        'Contact an administrator to change your phone number.',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: cs.onSurfaceVariant,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _emailController,
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        border: OutlineInputBorder(),
                        isDense: true,
                      ),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 22),
                    FilledButton(
                      onPressed: _isSaving ? null : _save,
                      child: _isSaving
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Save changes'),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Light card shell matching app surfaces.
class _ProfileInfoCard extends StatelessWidget {
  const _ProfileInfoCard({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: cs.surfaceContainerHighest.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
        child: child,
      ),
    );
  }
}

Widget _kvRow(
  BuildContext context,
  String label,
  String value, {
  bool mono = false,
}) {
  final theme = Theme.of(context);
  final cs = theme.colorScheme;
  return Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(
        width: 88,
        child: Text(
          label,
          style: theme.textTheme.labelLarge?.copyWith(
            color: cs.onSurfaceVariant,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
      Expanded(
        child: SelectableText(
          value,
          style: theme.textTheme.bodyLarge?.copyWith(
            fontWeight: FontWeight.w600,
            fontFeatures: mono
                ? const [FontFeature.tabularFigures()]
                : null,
          ),
        ),
      ),
    ],
  );
}
