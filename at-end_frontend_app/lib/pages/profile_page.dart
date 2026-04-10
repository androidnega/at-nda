import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/logout_lock_prefs.dart';
import '../services/offline_service.dart';
import '../services/profile_identity_cooldown.dart';
import 'login_page.dart';
import 'sync_status_page.dart';
import '../utils/app_selectable_scope.dart';

/// Profile (students & reps): name/email only on app (no profile images).
class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  Student? _student;
  final _emailController = TextEditingController();
  final _fullNameController = TextEditingController();
  String _baselineFull = '';
  bool _isLoading = true;
  bool _isSaving = false;
  bool _editing = false;
  bool _passwordHidden = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Widget _nameBadge(Student s, TextTheme tt, ColorScheme cs) {
    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        color: cs.primaryContainer.withValues(alpha: 0.5),
        shape: BoxShape.circle,
      ),
      alignment: Alignment.center,
      child: Text(
        s.greetingLastName.isNotEmpty
            ? s.greetingLastName[0].toUpperCase()
            : 'S',
        style: tt.headlineLarge?.copyWith(
          fontWeight: FontWeight.w900,
          color: cs.onPrimaryContainer,
        ),
      ),
    );
  }

  Future<void> _load() async {
    final student = await OfflineService.getCurrentStudent();
    if (student == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }
    if (mounted) {
      setState(() {
        _student = student;
        _emailController.text = student.email ?? '';
        _fullNameController.text = student.displayFirstLastName;
        _baselineFull = _fullNameController.text.trim();
        _isLoading = false;
        _editing = false;
      });
    }
  }

  void _syncControllersFromStudent() {
    final student = _student;
    if (student == null) return;
    _fullNameController.text = student.displayFirstLastName;
    _emailController.text = student.email ?? '';
  }

  void _cancelEdit() {
    _syncControllersFromStudent();
    setState(() => _editing = false);
  }

  ({String first, String last}) _splitFullName(String raw) {
    final parts = raw.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty) return (first: '', last: '');
    if (parts.length == 1) return (first: parts[0], last: '');
    return (first: parts[0], last: parts.sublist(1).join(' '));
  }

  Future<void> _save() async {
    if (_student == null) return;

    final email = _emailController.text.trim();
    final full = _fullNameController.text.trim();
    final split = _splitFullName(full);
    final newFirst = split.first;
    final newLast = split.last;
    final nameIdentityChanged = full != _baselineFull;

    if (nameIdentityChanged) {
      if (!await ProfileIdentityCooldown.canEditIdentity()) {
        if (!mounted) return;
        final hint = await ProfileIdentityCooldown.nextAllowedHint();
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(hint ?? 'Try again later.')));
        return;
      }
    }

    setState(() => _isSaving = true);

    final combined = full.isEmpty ? _student!.name : full;
    final updated = _student!.copyWith(
      phoneNumber: _student!.phoneNumber,
      email: email.isEmpty ? null : email,
      firstName: newFirst.isEmpty ? null : newFirst,
      lastName: newLast.isEmpty ? null : newLast,
      name: combined,
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
          feedback =
              hint.isEmpty
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
        _baselineFull = full;
      }
      setState(() {
        _student = updated;
        _isSaving = false;
        _editing = false;
      });
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(feedback)));
    }
  }

  Future<void> _confirmLogout() async {
    await ApiService.loadAppSettings(forceRemote: false);
    final role = _student?.primaryRole;
    final allow = await LogoutLockPrefs.canLogoutNow(
      role: role,
      studentLogoutLockEnabled: ApiService.studentLogoutLockEnabled,
    );
    if (!allow) {
      final hint = await LogoutLockPrefs.signOutBlockedHint(
        role: role,
        studentLogoutLockEnabled: ApiService.studentLogoutLockEnabled,
      );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder:
            (_) => AlertDialog(
              title: const Text('Sign out not available yet'),
              content: Text(
                hint ??
                    'This account stays signed in on this device for the current period.',
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('OK'),
                ),
              ],
            ),
      );
      return;
    }
    if (!mounted) return;
    final ok = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Log out'),
            content: const Text('Clear this account on this device?'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Cancel'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Log out'),
              ),
            ],
          ),
    );
    if (ok == true && mounted) {
      await OfflineService.clearCurrentStudent();
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => appSelectableScope(const LoginPage()),
        ),
        (_) => false,
      );
    }
  }

  Future<void> _showAbout() async {
    final info = await PackageInfo.fromPlatform();
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder:
          (ctx) => AlertDialog(
            title: const Text('Information'),
            content: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('${info.appName} ${info.version} (${info.buildNumber})'),
                  const SizedBox(height: 12),
                  Text(
                    'Name changes are limited to once every 90 days. '
                    'Phone numbers are updated by an administrator.',
                    style: Theme.of(ctx).textTheme.bodyMedium,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('OK'),
              ),
            ],
          ),
    );
  }

  Future<void> _onDeleteAccount() async {
    await showDialog<void>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Delete account'),
            content: const Text(
              'Account removal must be done by your institution. Contact an administrator.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('OK'),
              ),
            ],
          ),
    );
  }

  Widget _yellowButton(
    ColorScheme cs, {
    required String label,
    required VoidCallback? onPressed,
    bool loading = false,
  }) {
    return SizedBox(
      width: double.infinity,
      child: FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: cs.primary,
          foregroundColor: cs.onPrimary,
          disabledBackgroundColor: cs.primary.withValues(alpha: 0.5),
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        child:
            loading
                ? SizedBox(
                  height: 22,
                  width: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: cs.onPrimary,
                  ),
                )
                : Text(
                  label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
      ),
    );
  }

  Widget _menuTile({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
    bool danger = false,
  }) {
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final iconColor = danger ? Colors.red : cs.primary;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color:
                      danger
                          ? Colors.red.withValues(alpha: 0.12)
                          : (isDark ? Colors.transparent : cs.primaryContainer),
                  shape: BoxShape.circle,
                  border:
                      isDark && !danger
                          ? Border.all(color: cs.primary.withValues(alpha: 0.7))
                          : null,
                ),
                child: Icon(icon, size: 20, color: iconColor),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: danger ? Colors.red : null,
                  ),
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _fieldDecoration({
    required String label,
    required IconData prefixIcon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(prefixIcon),
      suffixIcon: suffixIcon,
      filled: true,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(
          color: Theme.of(context).colorScheme.outline.withValues(alpha: 0.35),
        ),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(
          color: Theme.of(context).colorScheme.primary,
          width: 1.5,
        ),
      ),
    );
  }

  @override
  void dispose() {
    _emailController.dispose();
    _fullNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_student == null) {
      return Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        appBar: AppBar(title: const Text('Profile')),
        body: const Center(child: Text('No profile.')),
      );
    }

    final s = _student!;
    final phone = (s.phoneNumber ?? '').trim();
    final email = (s.email ?? '').trim();
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cs = Theme.of(context).colorScheme;
    final bg = Theme.of(context).scaffoldBackgroundColor;

    if (_editing) {
      return Scaffold(
        backgroundColor: bg,
        appBar: AppBar(
          backgroundColor: bg,
          surfaceTintColor: Colors.transparent,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
            onPressed: _isSaving ? null : _cancelEdit,
          ),
          title: const Text('Edit Profile'),
          centerTitle: true,
        ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(child: _nameBadge(s, Theme.of(context).textTheme, cs)),
              const SizedBox(height: 20),
              TextFormField(
                controller: _fullNameController,
                textCapitalization: TextCapitalization.words,
                decoration: _fieldDecoration(
                  label: 'Full name',
                  prefixIcon: Icons.person_outline_rounded,
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                decoration: _fieldDecoration(
                  label: 'E-mail',
                  prefixIcon: Icons.mail_outline_rounded,
                ),
              ),
              const SizedBox(height: 16),
              InputDecorator(
                decoration: _fieldDecoration(
                  label: 'Phone no.',
                  prefixIcon: Icons.phone_outlined,
                ).copyWith(
                  helperText:
                      'Contact an administrator to change your phone number.',
                ),
                child: Text(
                  phone.isNotEmpty ? phone : '—',
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
              ),
              const SizedBox(height: 16),
              InputDecorator(
                decoration: _fieldDecoration(
                  label: 'Password',
                  prefixIcon: Icons.fingerprint_rounded,
                  suffixIcon: IconButton(
                    icon: Icon(
                      _passwordHidden
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                    onPressed:
                        () =>
                            setState(() => _passwordHidden = !_passwordHidden),
                  ),
                ).copyWith(
                  helperText: 'Password is managed by your institution.',
                ),
                child: Text(
                  '••••••••',
                  style: Theme.of(
                    context,
                  ).textTheme.bodyLarge?.copyWith(letterSpacing: 2),
                ),
              ),
              const SizedBox(height: 28),
              _yellowButton(
                cs,
                label: 'Save changes',
                onPressed: _isSaving ? null : _save,
                loading: _isSaving,
              ),
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Index: ${s.indexNumber}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                  TextButton(
                    onPressed: _onDeleteAccount,
                    style: TextButton.styleFrom(
                      foregroundColor: Colors.red.shade800,
                    ),
                    child: const Text('Delete'),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: bg,
        surfaceTintColor: Colors.transparent,
        leading:
            Navigator.canPop(context)
                ? IconButton(
                  icon: const Icon(Icons.arrow_back_ios_new_rounded),
                  onPressed: () => Navigator.of(context).maybePop(),
                )
                : null,
        title: const Text('Profile'),
        centerTitle: true,
        actions: [
          IconButton(
            tooltip: 'Settings',
            icon: Icon(
              Icons.settings_outlined,
              color: isDark ? cs.primary : null,
            ),
            onPressed: () => Navigator.of(context).pushNamed('/settings'),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(child: _nameBadge(s, Theme.of(context).textTheme, cs)),
            const SizedBox(height: 20),
            Text(
              s.displayFirstLastName,
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            Text(
              email.isNotEmpty ? email : 'No email on file',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 24),
            _yellowButton(
              cs,
              label: 'Edit Profile',
              onPressed: () => setState(() => _editing = true),
            ),
            const SizedBox(height: 28),
            _menuTile(
              icon: Icons.settings_outlined,
              title: 'Settings',
              onTap: () => Navigator.of(context).pushNamed('/settings'),
            ),
            _menuTile(
              icon: Icons.calendar_month_outlined,
              title: 'Timetable',
              onTap: () => Navigator.of(context).pushNamed('/timetable'),
            ),
            _menuTile(
              icon: Icons.fact_check_outlined,
              title: 'Attendance history',
              onTap:
                  () => Navigator.of(context).pushNamed('/attendance-records'),
            ),
            _menuTile(
              icon: Icons.sync_rounded,
              title: 'Data sync',
              onTap: () {
                Navigator.of(context).push<void>(
                  MaterialPageRoute(
                    builder: (_) => appSelectableScope(const SyncStatusPage()),
                  ),
                );
              },
            ),
            if (s.isClassRep || s.repRoles.isNotEmpty)
              _menuTile(
                icon: Icons.groups_outlined,
                title: 'Class roster',
                onTap:
                    () =>
                        Navigator.of(context).pushNamed('/class-rep/students'),
              ),
            _menuTile(
              icon: Icons.info_outline_rounded,
              title: 'Information',
              onTap: _showAbout,
            ),
            const Divider(height: 32),
            _menuTile(
              icon: Icons.power_settings_new_rounded,
              title: 'Logout',
              danger: true,
              onTap: _confirmLogout,
            ),
            const SizedBox(height: 16),
            _ProfileInfoCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Academic',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (s.classGroupWithLevelLabel != null &&
                      s.classGroupWithLevelLabel!.isNotEmpty)
                    _kvRow(context, 'Class', s.classGroupWithLevelLabel!),
                  if ((s.semester ?? '').trim().isNotEmpty) ...[
                    const SizedBox(height: 8),
                    _kvRow(context, 'Semester', s.semester!.trim()),
                  ],
                  if ((s.faculty ?? '').trim().isNotEmpty) ...[
                    const SizedBox(height: 8),
                    _kvRow(context, 'Faculty', s.faculty!.trim()),
                  ],
                  if ((s.department ?? '').trim().isNotEmpty) ...[
                    const SizedBox(height: 8),
                    _kvRow(context, 'Department', s.department!.trim()),
                  ],
                  const SizedBox(height: 8),
                  _kvRow(context, 'Index', s.indexNumber, mono: true),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Light card shell for read-only academic block.
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
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
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
          style: theme.textTheme.labelMedium?.copyWith(
            fontSize: 12,
            height: 1.3,
            color: cs.onSurfaceVariant,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      Expanded(
        child: SelectableText(
          value,
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 14,
            height: 1.35,
            fontWeight: FontWeight.w500,
            color: cs.onSurface,
            fontFeatures: mono ? const [FontFeature.tabularFigures()] : null,
          ),
        ),
      ),
    ],
  );
}
