import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../services/permission_service.dart';
import '../utils/app_state.dart';
import '../utils/connectivity_util.dart';
import '../utils/constants.dart';
import '../utils/password_util.dart';
import '../services/sync_service.dart';
import '../services/push_service.dart';

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

  static const Color _ink = Color(0xFF0F172A);
  static const Color _accent = Color(0xFF0D9488);
  static const Color _muted = Color(0xFF64748B);

  ThemeData _loginLightTheme() {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: ColorScheme.light(
        primary: _accent,
        onPrimary: Colors.white,
        surface: Colors.white,
        onSurface: _ink,
        error: const Color(0xFFDC2626),
      ),
      scaffoldBackgroundColor: Colors.white,
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: Colors.black.withValues(alpha: 0.06)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFFF8FAFC),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: Colors.black.withValues(alpha: 0.08)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: Colors.black.withValues(alpha: 0.08)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: _accent, width: 1.5),
        ),
        labelStyle: const TextStyle(color: _muted, fontWeight: FontWeight.w500),
        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: _accent,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: GoogleFonts.plusJakartaSans(
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
    return base.copyWith(
      textTheme: GoogleFonts.plusJakartaSansTextTheme(base.textTheme).apply(
        bodyColor: _ink,
        displayColor: _ink,
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
      final student = await OfflineService.getCurrentStudent()
          .timeout(const Duration(seconds: 3));
      if (student != null && mounted) {
        _indexController.text = student.indexNumber;
        final t = await OfflineService.getApiSessionToken();
        if (t != null && t.isNotEmpty) {
          ApiService.setSessionBearerToken(t);
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _checkingStored = false);
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
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  /// Non-empty Sanctum token from legacy login, or null if absent / JSON null.
  String? _parseLoginToken(dynamic raw) {
    if (raw == null) return null;
    if (raw is String) {
      final s = raw.trim();
      if (s.isEmpty || s == 'null') return null;
      return s;
    }
    final s = raw.toString().trim();
    if (s.isEmpty || s == 'null') return null;
    return s;
  }

  /// Merges `student`, `user`, top-level so `is_class_rep` / `rep_roles` are never dropped.
  Map<String, dynamic>? _studentMapFromLoginBody(Map<String, dynamic> body) {
    Map<String, dynamic>? primary;
    if (body['student'] is Map) {
      primary = Map<String, dynamic>.from(body['student'] as Map);
    } else if (body['user'] is Map) {
      primary = Map<String, dynamic>.from(body['user'] as Map);
    } else {
      final idx = body['index_number'];
      if (idx != null && idx.toString().trim().isNotEmpty) {
        primary = Map<String, dynamic>.from(body);
      }
    }
    if (primary == null) return null;

    void copyRepKeys(Map<String, dynamic> src) {
      if (src.containsKey('is_class_rep')) {
        primary!['is_class_rep'] = src['is_class_rep'];
      }
      if (src.containsKey('rep_roles')) {
        primary!['rep_roles'] = src['rep_roles'];
      }
      if (src.containsKey('primary_role')) {
        primary!['primary_role'] = src['primary_role'];
      }
    }

    copyRepKeys(body);
    if (body['user'] is Map) {
      copyRepKeys(Map<String, dynamic>.from(body['user'] as Map));
    }
    return primary;
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
    final route = forNav.isClassRep ? '/rep-home' : '/home';
    Navigator.of(context).pushReplacementNamed(route);
  }

  /// Laravel API login. Save student locally on success.
  /// Accepts 200 JSON either as `{ student: {...} }` or flat `{ index_number, name, ... }`
  /// (no `success` field required — ApiService throws on non-200).
  Future<void> _loginViaApi(String index, String password) async {
    final cleanIndex = index.trim().toUpperCase();
    final online = await hasInternetConnectivity();
    if (!online) {
      await _loginFromLocalCache(cleanIndex, password);
      return;
    }

    final body = await ApiService.login(cleanIndex, password);

    final studentData = _studentMapFromLoginBody(body);

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
    var tokenStr = _parseLoginToken(body['token']);
    tokenStr ??= await ApiService.loginV1SanctumToken(cleanIndex, passwordForStorage);
    if (tokenStr != null && tokenStr.isNotEmpty) {
      await OfflineService.setApiSessionToken(tokenStr);
      ApiService.setSessionBearerToken(tokenStr);
    } else {
      await OfflineService.setApiSessionToken(null);
      ApiService.clearSessionBearerToken();
    }
    AppState.studentIndex = student.indexNumber;

    try {
      await SyncService.syncAttendance();
    } catch (_) {}
    await PushService.registerAfterLogin(student.indexNumber);

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
    AppState.studentIndex = stored.indexNumber;
    final t = await OfflineService.getApiSessionToken();
    if (t != null && t.isNotEmpty) {
      ApiService.setSessionBearerToken(t);
    }
    try {
      await SyncService.syncAttendance();
    } catch (_) {}
    await PushService.registerAfterLogin(stored.indexNumber);
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
    );

    await OfflineService.setCurrentStudent(student);
    await OfflineService.setApiSessionPassword(null);
    await OfflineService.setApiSessionToken(null);
    ApiService.clearSessionBearerToken();
    if (!sameUser || hash == null || hash.isEmpty) {
      await OfflineService.setPasswordHash(PasswordUtil.hash(password));
    }
    AppState.studentIndex = student.indexNumber;

    if (!mounted) return;

    try {
      await SyncService.syncAttendance();
    } catch (_) {}
    await PushService.registerAfterLogin(student.indexNumber);

    if (!mounted) return;
    final route = student.isClassRep ? '/rep-home' : '/home';
    Navigator.of(context).pushReplacementNamed(route);
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingStored) {
      return Scaffold(
        backgroundColor: Colors.white,
        body: Center(
          child: CircularProgressIndicator(
            color: _accent,
            strokeWidth: 2.5,
          ),
        ),
      );
    }

    return Theme(
      data: _loginLightTheme(),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 20),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SizedBox(height: 28),
                  Center(
                    child: Container(
                      width: 96,
                      height: 96,
                      decoration: BoxDecoration(
                        color: _accent.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: _accent.withValues(alpha: 0.15),
                            blurRadius: 24,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.event_available_rounded,
                        size: 48,
                        color: _accent,
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),
                  Text(
                    'at-enda',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.outfit(
                      fontSize: 40,
                      fontWeight: FontWeight.w700,
                      letterSpacing: -1,
                      color: _ink,
                      height: 1.05,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Attendance, simplified.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 15,
                      fontWeight: FontWeight.w500,
                      color: _muted,
                      height: 1.4,
                    ),
                  ),
                  if (Constants.localAuthOnly) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Use your index and password to sign in.',
                      textAlign: TextAlign.center,
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 13,
                        color: _muted.withValues(alpha: 0.9),
                      ),
                    ),
                  ],
                  const SizedBox(height: 36),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          TextFormField(
                            controller: _indexController,
                            decoration: const InputDecoration(
                              labelText: 'Index number',
                              hintText: 'e.g. BC/ITD/24/001',
                              prefixIcon: Icon(Icons.badge_outlined, color: _muted),
                            ),
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w500,
                              color: _ink,
                            ),
                            textCapitalization: TextCapitalization.characters,
                            inputFormatters: [
                              TextInputFormatter.withFunction((old, copy) =>
                                  copy.copyWith(text: copy.text.toUpperCase())),
                            ],
                            validator: (v) =>
                                (v == null || v.trim().isEmpty) ? 'Required' : null,
                          ),
                          const SizedBox(height: 16),
                          TextFormField(
                            controller: _passwordController,
                            decoration: InputDecoration(
                              labelText: 'Password',
                              hintText: 'Enter your password',
                              prefixIcon: const Icon(Icons.lock_outline_rounded, color: _muted),
                              suffixIcon: IconButton(
                                icon: Icon(
                                  _obscure
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                  color: _muted,
                                ),
                                onPressed: () =>
                                    setState(() => _obscure = !_obscure),
                              ),
                            ),
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w500,
                              color: _ink,
                            ),
                            obscureText: _obscure,
                            autocorrect: false,
                            enableSuggestions: false,
                            textCapitalization: TextCapitalization.none,
                            onFieldSubmitted: (_) => _login(),
                            validator: (v) =>
                                (v == null || v.isEmpty) ? 'Required' : null,
                          ),
                          if (_error != null) ...[
                            const SizedBox(height: 16),
                            Text(
                              _error!,
                              style: GoogleFonts.plusJakartaSans(
                                color: Theme.of(context).colorScheme.error,
                                fontSize: 13,
                              ),
                            ),
                          ],
                          const SizedBox(height: 28),
                          SizedBox(
                            height: 54,
                            child: FilledButton(
                              onPressed: _isLoading ? null : _login,
                              child: _isLoading
                                  ? const SizedBox(
                                      width: 22,
                                      height: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : Text(
                                      'Login',
                                      style: GoogleFonts.plusJakartaSans(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 16,
                                      ),
                                    ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  TextButton(
                    onPressed: () => Navigator.of(context).pushNamed('/api-test'),
                    child: Text(
                      'Developer · API test',
                      style: GoogleFonts.plusJakartaSans(
                        color: _muted.withValues(alpha: 0.85),
                        fontSize: 13,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
