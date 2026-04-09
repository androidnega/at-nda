import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/communication_log_sync.dart';
import '../services/logout_lock_prefs.dart';
import '../services/offline_service.dart';
import '../services/permission_service.dart';
import '../utils/app_state.dart';
import '../utils/connectivity_util.dart';
import '../utils/api_user_message.dart';
import '../utils/constants.dart';
import '../utils/login_response_parser.dart';
import '../utils/password_util.dart';
import '../services/sync_service.dart';
import '../services/push_service.dart';
import '../services/notification_bridge.dart';

/// Sign-in: Laravel API when localAuthOnly=false, local-only when true.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _indexController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  bool _checkingStored = true;
  String? _error;
  bool _obscure = true;

  static const Color _ink = Color(0xFF1E293B);
  static const Color _muted = Color(0xFF64748B);
  /// Matches [AppTheme.light] primary (system teal).
  static const Color _primaryTeal = Color(0xFF0D9488);
  static const Color _tealButton = Color(0xFF0F766E);
  static const Color _fieldBorder = Color(0xFFE2E8F0);

  static const double _pillRadius = 999;

  OutlineInputBorder _pillBorder(Color color, {double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(_pillRadius),
      borderSide: BorderSide(color: color, width: width),
    );
  }

  Future<void> _persistSavedLoginId(String? savedLoginId) async {
    if (savedLoginId == null || savedLoginId.isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('login_saved_index', savedLoginId);
  }

  void _showInfoDialog(String title, String body) {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  void initState() {
    super.initState();
    PermissionService.requestAll();
    _checkStoredStudent();
  }

  Future<void> _checkStoredStudent() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedIndex = prefs.getString('login_saved_index');

      final student = await OfflineService.getCurrentStudent()
          .timeout(const Duration(seconds: 3));
      if (student != null && mounted) {
        _indexController.text = student.indexNumber;
        final t = await OfflineService.getApiSessionToken();
        if (t != null && t.isNotEmpty) {
          ApiService.setSessionBearerToken(t);
        }
      } else if (savedIndex != null && savedIndex.isNotEmpty && mounted) {
        _indexController.text = savedIndex;
      }
      if (mounted) setState(() => _checkingStored = false);
    } catch (_) {
      if (mounted) setState(() => _checkingStored = false);
    }
  }

  @override
  void dispose() {
    _indexController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final index = _indexController.text.trim();
    // Password: do not uppercase or strip here — ApiService trims only for the HTTP body.
    final password = _passwordController.text;

    if (index.isEmpty) {
      setState(() {
        _error = 'Enter your index number.';
        _isLoading = false;
      });
      return;
    }

    try {
      if (Constants.localAuthOnly) {
        await _loginLocalOnly(index, password);
      } else {
        await _loginViaApi(index, password);
      }
    } catch (e) {
      setState(() => _error = sanitizeApiUserMessage(e.toString()));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  /// If login JSON omitted rep flags, `POST /api/rep/courses` confirms access and persists `is_class_rep`.
  Future<Student> _enrichRepFromApi(Student s) async {
    if (!await hasInternetConnectivity()) return s;
    if (!await OfflineService.hasPasswordOrApiToken()) return s;
    try {
      final pwd = await OfflineService.getApiSessionPassword();
      final res = await ApiService.repCourses(
        indexNumber: s.indexNumber,
        password: pwd ?? '',
      );
      if (res.statusCode != 200) return s;
      final decoded = jsonDecode(res.body);
      if (decoded is! Map) return s;
      final data = Map<String, dynamic>.from(decoded);
      final icr = data['is_class_rep'];
      final explicit = icr == true ||
          icr == 1 ||
          (icr != null && icr.toString().toLowerCase() == 'true');
      final courses = data['courses'];
      final hasCourses = courses is List && courses.isNotEmpty;
      if (!explicit && !hasCourses) return s;
      if (s.isClassRep) return s;
      final updated = s.copyWith(isClassRep: true);
      await OfflineService.setCurrentStudent(updated);
      return updated;
    } catch (_) {
      return s;
    }
  }

  Future<void> _goToPostLoginHome(Student student) async {
    final forNav = await _enrichRepFromApi(student);
    if (!mounted) return;
    final route = forNav.primaryRole == 'class_rep'
        ? '/rep-home'
        : forNav.primaryRole == 'lecturer'
            ? '/lecturer-home'
            : '/home';
    Navigator.of(context).pushReplacementNamed(route);
  }

  /// Laravel API login. Save student locally on success.
  /// Accepts 200 JSON either as `{ student: {...} }` or flat `{ index_number, name, ... }`
  /// (no `success` field required — ApiService throws on non-200).
  Future<void> _loginViaApi(String index, String password) async {
    final cleanIndex = ApiService.normalizeLoginId(index);
    final online = await hasInternetConnectivity();
    if (!online) {
      await _loginFromLocalCache(cleanIndex, password);
      return;
    }

    final body = await ApiService.login(cleanIndex, password);

    final studentData = studentMapFromLoginBody(body);

    if (studentData == null) {
      setState(() => _error = 'Invalid response from server.');
      return;
    }

    // Match what Laravel received (trimmed password only).
    final passwordForStorage = password.trim();

    final student = Student.fromJson(studentData);
    await OfflineService.setCurrentStudent(student);
    await OfflineService.setPasswordHash(PasswordUtil.hash(passwordForStorage));
    await OfflineService.setApiSessionPassword(passwordForStorage);
    var tokenStr = parseLoginResponseToken(body['token']);
    tokenStr ??= await ApiService.loginV1SanctumToken(cleanIndex, passwordForStorage);
    if (tokenStr != null && tokenStr.isNotEmpty) {
      await OfflineService.setApiSessionToken(tokenStr);
      ApiService.setSessionBearerToken(tokenStr);
    } else {
      await OfflineService.setApiSessionToken(null);
      ApiService.clearSessionBearerToken();
    }
    AppState.studentIndex = student.indexNumber;
    await _persistSavedLoginId(cleanIndex);

    try {
      await SyncService.syncAttendance();
    } catch (_) {}
    await PushService.registerAfterLogin(student.indexNumber);
    // Firebase-free reminders: poll immediately after successful login.
    await NotificationBridge.pollPending();
    await LogoutLockPrefs.recordFreshLoginBinding();

    try {
      await ApiService.loadAppSettings();
    } catch (_) {}
    unawaited(CommunicationLogSyncService.maybeSync());

    if (!mounted) return;

    setState(() => _error = null);

    await _goToPostLoginHome(student);
  }

  /// Offline: load student from SQLite (or [users] fallback) and verify password hash.
  Future<void> _loginFromLocalCache(String cleanIndex, String password) async {
    final stored = await OfflineService.getCurrentStudent();
    if (stored == null || stored.indexNumber != cleanIndex) {
      setState(() => _error =
          'No connection. Sign in online once, then you can use offline login.');
      return;
    }
    final hash = await OfflineService.getPasswordHash();
    final passwordForStorage = password.trim();
    if (hash == null || !PasswordUtil.verify(passwordForStorage, hash)) {
      setState(() => _error = 'No connection or incorrect password.');
      return;
    }
    // Needed for Firebase-free reminder polling (`/api/notifications/pending`).
    await OfflineService.setApiSessionPassword(passwordForStorage);
    AppState.studentIndex = stored.indexNumber;
    await _persistSavedLoginId(cleanIndex);
    final t = await OfflineService.getApiSessionToken();
    if (t != null && t.isNotEmpty) {
      ApiService.setSessionBearerToken(t);
    }
    try {
      await SyncService.syncAttendance();
    } catch (_) {}
    await PushService.registerAfterLogin(stored.indexNumber);
    await NotificationBridge.pollPending();
    try {
      await ApiService.loadAppSettings();
    } catch (_) {}
    unawaited(CommunicationLogSyncService.maybeSync());
    if (!mounted) return;
    setState(() => _error = null);
    await _goToPostLoginHome(stored);
  }

  /// Local-only: any index + password (when localAuthOnly=true).
  Future<void> _loginLocalOnly(String index, String password) async {
    final stored = await OfflineService.getCurrentStudent();
    final hash = await OfflineService.getPasswordHash();

    final sameUser = stored?.indexNumber == index;

    if (sameUser && hash != null && hash.isNotEmpty) {
      if (!PasswordUtil.verify(password, hash)) {
        setState(() => _error = 'Incorrect password.');
        return;
      }
    } else {
      if (password.length < 4) {
        setState(() => _error = 'Password must be at least 4 characters.');
        return;
      }
    }

    final student = Student(
      indexNumber: index,
      name: sameUser ? stored!.name : 'Student',
      profileImage: sameUser ? stored!.profileImage : '',
      faceDescriptor: sameUser ? stored!.faceDescriptor : null,
      phoneNumber: sameUser ? stored!.phoneNumber : null,
      email: sameUser ? stored!.email : null,
      className: sameUser ? stored!.className : 'Demo Class',
      faculty: sameUser ? stored!.faculty : 'Faculty',
      department: sameUser ? stored!.department : 'Department',
      level: sameUser ? stored!.level : 'Level 100',
      role: sameUser ? stored!.primaryRole : 'student',
    );

    await OfflineService.setCurrentStudent(student);
    await OfflineService.setApiSessionPassword(null);
    await OfflineService.setApiSessionToken(null);
    ApiService.clearSessionBearerToken();
    if (!sameUser || hash == null || hash.isEmpty) {
      await OfflineService.setPasswordHash(PasswordUtil.hash(password));
    }
    AppState.studentIndex = student.indexNumber;
    await _persistSavedLoginId(ApiService.normalizeLoginId(index));

    if (!mounted) return;

    try {
      await SyncService.syncAttendance();
    } catch (_) {}
    await PushService.registerAfterLogin(student.indexNumber);
    await LogoutLockPrefs.recordFreshLoginBinding();

    if (!mounted) return;
    final route = student.primaryRole == 'class_rep'
        ? '/rep-home'
        : student.primaryRole == 'lecturer'
            ? '/lecturer-home'
            : '/home';
    Navigator.of(context).pushReplacementNamed(route);
  }

  Widget _loginHeader() {
    return Container(
      width: double.infinity,
      color: _primaryTeal,
      alignment: Alignment.center,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(24),
        child: Image.asset(
          'branding/app_icon.png',
          width: 96,
          height: 96,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.22),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.event_available_rounded,
              color: Colors.white,
              size: 44,
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingStored) {
      return Scaffold(
        backgroundColor: _primaryTeal,
        body: ColoredBox(
          color: _primaryTeal,
          child: Column(
            children: [
              Expanded(
                  flex: 28,
                  child: SafeArea(bottom: false, child: _loginHeader())),
              Expanded(
                flex: 72,
                child: Container(
                  width: double.infinity,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.vertical(top: Radius.circular(40)),
                  ),
                  child: const Center(
                    child: CircularProgressIndicator(
                      color: _primaryTeal,
                      strokeWidth: 2.5,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final labelStyle = GoogleFonts.plusJakartaSans(
      fontSize: 12,
      fontWeight: FontWeight.w700,
      color: _ink,
      letterSpacing: 0.2,
    );
    final fieldStyle = GoogleFonts.plusJakartaSans(
      fontWeight: FontWeight.w500,
      fontSize: 14,
      color: _ink,
    );
    final hintStyle = GoogleFonts.plusJakartaSans(
      fontWeight: FontWeight.w400,
      fontSize: 14,
      color: _muted.withValues(alpha: 0.85),
    );

    final fieldDeco = InputDecoration(
      isDense: true,
      filled: true,
      fillColor: Colors.white,
      hintStyle: hintStyle,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
      border: _pillBorder(_fieldBorder),
      enabledBorder: _pillBorder(_fieldBorder),
      focusedBorder: _pillBorder(_primaryTeal, width: 1.5),
      errorBorder: _pillBorder(const Color(0xFFDC2626)),
      focusedErrorBorder: _pillBorder(const Color(0xFFDC2626), width: 1.5),
    );

    return Scaffold(
      backgroundColor: _primaryTeal,
      body: ColoredBox(
        color: _primaryTeal,
        child: Column(
          children: [
            Expanded(
              flex: 28,
              child: SafeArea(bottom: false, child: _loginHeader()),
            ),
            Expanded(
              flex: 72,
              child: ClipRRect(
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(40)),
                clipBehavior: Clip.antiAlias,
                child: ColoredBox(
                  color: Colors.white,
                  child: SafeArea(
                    top: false,
                    child: LayoutBuilder(
                    builder: (context, _) {
                      return SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                                  Text(
                                    'Login Account',
                                    textAlign: TextAlign.center,
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 20,
                                      fontWeight: FontWeight.w800,
                                      color: _ink,
                                    ),
                                  ),
                                  if (Constants.localAuthOnly) ...[
                                    const SizedBox(height: 6),
                                    Text(
                                      'Demo: any index and password (4+ chars for new).',
                                      textAlign: TextAlign.center,
                                      style: GoogleFonts.plusJakartaSans(
                                        fontSize: 11,
                                        color: _muted,
                                        height: 1.35,
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 22),
                                  Text('Index number', style: labelStyle),
                                  const SizedBox(height: 6),
                                  Text(
                                    'Staff: use your issued username or email.',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 11,
                                      color: _muted,
                                      height: 1.25,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  TextFormField(
                                    controller: _indexController,
                                    onChanged: (_) => setState(() {}),
                                    decoration: fieldDeco.copyWith(
                                      hintText: 'e.g. BC/ITS/24/047',
                                    ),
                                    style: fieldStyle,
                                    keyboardType: _indexController.text.contains('@')
                                        ? TextInputType.emailAddress
                                        : TextInputType.text,
                                    autocorrect: _indexController.text.contains('@'),
                                    textCapitalization:
                                        _indexController.text.contains('@')
                                            ? TextCapitalization.none
                                            : TextCapitalization.characters,
                                    inputFormatters:
                                        _indexController.text.contains('@')
                                            ? null
                                            : [
                                                TextInputFormatter.withFunction(
                                                    (old, copy) => copy.copyWith(
                                                        text: copy.text
                                                            .toUpperCase())),
                                              ],
                                    validator: (v) =>
                                        (v == null || v.trim().isEmpty)
                                            ? 'Required'
                                            : null,
                                  ),
                                  const SizedBox(height: 14),
                                  Text('Password', style: labelStyle),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _passwordController,
                                    decoration: fieldDeco.copyWith(
                                      hintText: 'Password',
                                      suffixIcon: IconButton(
                                        icon: Icon(
                                          _obscure
                                              ? Icons.visibility_outlined
                                              : Icons.visibility_off_outlined,
                                          color: _muted,
                                          size: 20,
                                        ),
                                        onPressed: () =>
                                            setState(() => _obscure = !_obscure),
                                      ),
                                    ),
                                    style: fieldStyle,
                                    obscureText: _obscure,
                                    autocorrect: false,
                                    enableSuggestions: false,
                                    textCapitalization: TextCapitalization.none,
                                    onFieldSubmitted: (_) => _login(),
                                    validator: (v) =>
                                        (v == null || v.isEmpty) ? 'Required' : null,
                                  ),
                                  const SizedBox(height: 6),
                                  Align(
                                    alignment: Alignment.centerRight,
                                    child: TextButton(
                                      onPressed: () {
                                        if (Constants.localAuthOnly) {
                                          _showInfoDialog(
                                            'Forgot password',
                                            'This demo build has no password reset. Use any password (4+ characters) for a new index.',
                                          );
                                        } else {
                                          _showInfoDialog(
                                            'Forgot password',
                                            'Password reset is handled by your institution. Please contact your administrator or IT help desk.',
                                          );
                                        }
                                      },
                                      style: TextButton.styleFrom(
                                        foregroundColor: _primaryTeal,
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 4,
                                          vertical: 0,
                                        ),
                                        minimumSize: Size.zero,
                                        tapTargetSize:
                                            MaterialTapTargetSize.shrinkWrap,
                                      ),
                                      child: Text(
                                        'Forgot password?',
                                        style: GoogleFonts.plusJakartaSans(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ),
                                  if (_error != null) ...[
                                    const SizedBox(height: 10),
                                    Text(
                                      _error!,
                                      style: GoogleFonts.plusJakartaSans(
                                        color: const Color(0xFFDC2626),
                                        fontSize: 12,
                                        height: 1.35,
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 20),
                                  SizedBox(
                                    height: 48,
                                    width: double.infinity,
                                    child: FilledButton(
                                      onPressed: _isLoading ? null : _login,
                                      style: FilledButton.styleFrom(
                                        backgroundColor: _tealButton,
                                        foregroundColor: Colors.white,
                                        elevation: 0,
                                        shape: const StadiumBorder(),
                                        textStyle: GoogleFonts.plusJakartaSans(
                                          fontSize: 16,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                      child: _isLoading
                                          ? const SizedBox(
                                              width: 22,
                                              height: 22,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Text('Login'),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                      );
                    },
                  ),
                ),
              ),
            ),
            ),
          ],
        ),
      ),
    );
  }
}
