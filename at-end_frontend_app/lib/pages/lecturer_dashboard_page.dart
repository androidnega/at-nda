import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../services/theme_service.dart';
import '../utils/app_selectable_scope.dart';
import '../widgets/attendance_trend_chart.dart';
import '../widgets/modern_pull_to_refresh.dart';
import 'lecturer_class_detail_page.dart';
import 'login_page.dart';

/// Lecturer analytics home (Bearer token from lecturer API login).
class LecturerDashboardPage extends StatefulWidget {
  const LecturerDashboardPage({super.key});

  @override
  State<LecturerDashboardPage> createState() => _LecturerDashboardPageState();
}

class _LecturerDashboardPageState extends State<LecturerDashboardPage> {
  bool _loading = true;
  String? _error;
  bool _sendingMessage = false;
  String _name = '';
  int _totalClasses = 0;
  double _avgAttendance = 0;
  int _atRisk = 0;
  int _activeSessions = 0;
  List<Map<String, dynamic>> _classes = [];
  List<Map<String, dynamic>> _trend = [];
  Map<String, dynamic> _insights = {};
  int? _selectedCourseId;
  final TextEditingController _messageTitleController = TextEditingController(
    text: 'Notice from lecturer',
  );
  final TextEditingController _messageBodyController = TextEditingController();
  final TextEditingController _studentIndexController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _messageTitleController.dispose();
    _messageBodyController.dispose();
    _studentIndexController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiService.lecturerDashboard();
      final body = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          body is Map &&
          body['success'] == true &&
          body['data'] is Map) {
        final d = Map<String, dynamic>.from(body['data'] as Map);
        final classes = d['classes'];
        final trend = d['attendance_trend'];
        final ins = d['insights'];
        final parsedClasses =
            classes is List
                ? classes
                    .map((e) => Map<String, dynamic>.from(e as Map))
                    .toList()
                : <Map<String, dynamic>>[];
        int? selectedCourse = _selectedCourseId;
        if (parsedClasses.isNotEmpty) {
          final ids =
              parsedClasses.map(_courseIdFromClass).whereType<int>().toSet();
          if (selectedCourse == null || !ids.contains(selectedCourse)) {
            selectedCourse = _courseIdFromClass(parsedClasses.first);
          }
        } else {
          selectedCourse = null;
        }
        if (!mounted) return;
        setState(() {
          _name = d['lecturer_name']?.toString() ?? 'Lecturer';
          _totalClasses = _toInt(d['total_classes']) ?? 0;
          _avgAttendance = _toDouble(d['avg_attendance_pct']) ?? 0;
          _atRisk = _toInt(d['at_risk_count']) ?? 0;
          _activeSessions = _toInt(d['active_sessions']) ?? 0;
          _classes = parsedClasses;
          _trend =
              trend is List
                  ? trend
                      .map((e) => Map<String, dynamic>.from(e as Map))
                      .toList()
                  : [];
          _insights = ins is Map ? Map<String, dynamic>.from(ins) : {};
          _selectedCourseId = selectedCourse;
          _loading = false;
        });
      } else {
        final msg = body is Map ? body['message']?.toString() : '';
        if (!mounted) return;
        setState(() {
          _error = msg?.isNotEmpty == true ? msg! : 'Could not load dashboard';
          _loading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
        _loading = false;
      });
    }
  }

  int? _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.round();
    return int.tryParse(v?.toString() ?? '');
  }

  double? _toDouble(dynamic v) {
    if (v is double) return v;
    if (v is num) return v.toDouble();
    return double.tryParse(v?.toString() ?? '');
  }

  int? _courseIdFromClass(Map<String, dynamic> c) {
    final id = c['course_id'];
    if (id is int) return id;
    if (id is num) return id.toInt();
    return int.tryParse(id?.toString() ?? '');
  }

  String _courseLabel(Map<String, dynamic> c) {
    final name = c['course_name']?.toString().trim() ?? '';
    final code = c['course_code']?.toString().trim() ?? '';
    if (name.isNotEmpty && code.isNotEmpty) return '$code · $name';
    if (name.isNotEmpty) return name;
    if (code.isNotEmpty) return code;
    return 'Course';
  }

  String _lecturerLastName() {
    final raw = _name.trim();
    if (raw.isEmpty) return 'Lecturer';
    if (raw.contains(',')) {
      final beforeComma = raw.split(',').first.trim();
      if (beforeComma.isNotEmpty) return beforeComma;
    }
    final parts = raw.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return 'Lecturer';
    return parts.last;
  }

  Future<void> _toggleThemeMode() async {
    final current = ThemeService.modeNotifier.value;
    final platformDark =
        MediaQuery.of(context).platformBrightness == Brightness.dark;
    final isDarkNow =
        current == ThemeMode.dark ||
        (current == ThemeMode.system && platformDark);
    await ThemeService.setTheme(isDarkNow ? ThemeMode.light : ThemeMode.dark);
  }

  Future<void> _logout() async {
    await OfflineService.clearCurrentStudent();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => appSelectableScope(const LoginPage())),
      (_) => false,
    );
  }

  Future<void> _confirmLogout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder:
          (_) => AlertDialog(
            title: const Text('Log out'),
            content: const Text('Sign out from this lecturer account now?'),
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
    if (ok == true) {
      await _logout();
    }
  }

  void _showSnack(String text, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(text),
        behavior: SnackBarBehavior.floating,
        backgroundColor: error ? Theme.of(context).colorScheme.error : null,
      ),
    );
  }

  Future<bool> _sendDirectMessage({
    void Function(bool value)? onLoadingChanged,
  }) async {
    final courseId = _selectedCourseId;
    final title = _messageTitleController.text.trim();
    final body = _messageBodyController.text.trim();
    final target = _studentIndexController.text.trim();

    if (courseId == null || courseId <= 0) {
      _showSnack('Select a course first.', error: true);
      return false;
    }
    if (title.isEmpty || body.isEmpty) {
      _showSnack('Add a title and message body.', error: true);
      return false;
    }

    if (onLoadingChanged != null) {
      onLoadingChanged(true);
    } else if (mounted) {
      setState(() => _sendingMessage = true);
    }
    try {
      final res = await ApiService.lecturerSendDirectMessage(
        courseId: courseId,
        title: title,
        body: body,
        studentIndexNumber: target.isEmpty ? null : target,
      );
      dynamic decoded;
      try {
        decoded = jsonDecode(res.body);
      } catch (_) {}

      final ok =
          res.statusCode >= 200 &&
          res.statusCode < 300 &&
          decoded is Map &&
          decoded['success'] == true;
      if (!ok) {
        final backend = decoded is Map ? decoded['message']?.toString() : null;
        final msg =
            (backend != null && backend.trim().isNotEmpty)
                ? backend.trim()
                : ApiService.messageFromHttpResponse(res);
        _showSnack(
          msg.isEmpty ? 'Could not send lecturer message.' : msg,
          error: true,
        );
        return false;
      }

      final decodedMap = Map<String, dynamic>.from(decoded);
      int recipients = 0;
      if (decodedMap['data'] is Map) {
        final data = Map<String, dynamic>.from(decodedMap['data'] as Map);
        recipients =
            _toInt(data['recipient_count']) ?? _toInt(data['sent_count']) ?? 0;
      }
      _messageBodyController.clear();
      _studentIndexController.clear();
      _showSnack(
        recipients > 0
            ? 'Message sent to $recipients student(s).'
            : 'Message sent successfully.',
      );
      return true;
    } catch (e) {
      _showSnack('Failed to send message: $e', error: true);
      return false;
    } finally {
      if (onLoadingChanged != null) {
        onLoadingChanged(false);
      } else if (mounted) {
        setState(() => _sendingMessage = false);
      }
    }
  }

  Widget _summaryTile(
    BuildContext context, {
    required String label,
    required String value,
    required IconData icon,
    required Color iconBg,
  }) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cs.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.7)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: iconBg,
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: Icon(icon, size: 20, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: tt.labelMedium?.copyWith(
                    color: cs.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: tt.titleMedium?.copyWith(
                    color: cs.onSurface,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGrowthHero(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final delta = _toDouble(_insights['delta_pct']) ?? 0;
    final direction = (_insights['direction']?.toString() ?? '').toLowerCase();
    final positive = direction == 'up' || (direction != 'down' && delta >= 0);
    final deltaAbs = delta.abs();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
      decoration: BoxDecoration(
        color: cs.surfaceContainerLow,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                'Growth index',
                style: tt.labelLarge?.copyWith(
                  color: cs.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: cs.surfaceContainerHigh,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'This week',
                      style: tt.labelMedium?.copyWith(
                        color: cs.onSurface,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Icon(
                      Icons.keyboard_arrow_down_rounded,
                      size: 16,
                      color: cs.onSurfaceVariant,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            '${positive ? '+' : '-'}${deltaAbs.toStringAsFixed(1)}%',
            style: tt.headlineMedium?.copyWith(
              color: cs.onSurface,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.4,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '${positive ? 'up' : 'down'} ${deltaAbs.toStringAsFixed(1)}% from last week',
            style: tt.bodySmall?.copyWith(
              color: cs.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTrendCard(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: cs.surfaceContainerLow,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.8)),
      ),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Attendance trend',
            style: tt.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
              color: cs.onSurface,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Class participation over recent weeks',
            style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
          ),
          const SizedBox(height: 10),
          AttendanceTrendChart(
            points: _trend,
            title: '',
            compact: true,
            height: 132,
          ),
        ],
      ),
    );
  }

  Future<void> _openMessageComposerModal() async {
    if (_classes.isEmpty) {
      _showSnack('No course available yet for messaging.', error: true);
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final cs = Theme.of(context).colorScheme;
            final tt = Theme.of(context).textTheme;
            final canSend = _selectedCourseId != null && !_sendingMessage;
            final insets = MediaQuery.viewInsetsOf(context);

            return Padding(
              padding: EdgeInsets.fromLTRB(16, 8, 16, insets.bottom + 16),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.campaign_outlined, color: cs.primary),
                        const SizedBox(width: 8),
                        Text(
                          'Direct message to students',
                          style: tt.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: cs.onSurface,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Send to a whole class or one student index.',
                      style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedCourseId,
                      items:
                          _classes
                              .map((c) {
                                final id = _courseIdFromClass(c);
                                if (id == null) return null;
                                return DropdownMenuItem<int>(
                                  value: id,
                                  child: Text(
                                    _courseLabel(c),
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                );
                              })
                              .whereType<DropdownMenuItem<int>>()
                              .toList(),
                      onChanged:
                          (v) => setModalState(() {
                            _selectedCourseId = v;
                          }),
                      decoration: const InputDecoration(
                        labelText: 'Course',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _studentIndexController,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(
                        labelText: 'Student index (optional)',
                        hintText: 'Leave empty to notify all students in class',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _messageTitleController,
                      decoration: const InputDecoration(
                        labelText: 'Title',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _messageBodyController,
                      maxLines: 3,
                      decoration: const InputDecoration(
                        labelText: 'Message',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed:
                            canSend
                                ? () async {
                                  final sent = await _sendDirectMessage(
                                    onLoadingChanged:
                                        (value) => setModalState(
                                          () => _sendingMessage = value,
                                        ),
                                  );
                                  if (sent && context.mounted) {
                                    Navigator.of(sheetContext).pop();
                                  }
                                }
                                : null,
                        icon:
                            _sendingMessage
                                ? SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: cs.onPrimary,
                                  ),
                                )
                                : const Icon(Icons.send_rounded),
                        label: Text(
                          _sendingMessage ? 'Sending...' : 'Send notification',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildClassCard(BuildContext context, Map<String, dynamic> c) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final id = _courseIdFromClass(c) ?? 0;
    final pct = (_toDouble(c['attendance_pct']) ?? 0).clamp(0, 100).toDouble();
    final students = _toInt(c['student_count']) ?? 0;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap:
            id > 0
                ? () {
                  Navigator.of(context).push<void>(
                    MaterialPageRoute<void>(
                      builder: (_) => LecturerClassDetailPage(courseId: id),
                    ),
                  );
                }
                : null,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
          decoration: BoxDecoration(
            color: cs.surfaceContainerLow,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: cs.outlineVariant.withValues(alpha: 0.75),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      _courseLabel(c),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: tt.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: cs.onSurface,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '${pct.toStringAsFixed(0)}%',
                    style: tt.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: cs.primary,
                    ),
                  ),
                  const SizedBox(width: 2),
                  Icon(Icons.chevron_right_rounded, color: cs.onSurfaceVariant),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                '$students students',
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              ),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(999),
                child: LinearProgressIndicator(
                  minHeight: 7,
                  value: pct / 100,
                  backgroundColor: cs.surfaceContainerHighest,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final lastName = _lecturerLastName();
    return Scaffold(
      backgroundColor: cs.surface,
      appBar: AppBar(
        title: const Text('Lecturer dashboard'),
        actions: [
          IconButton(
            tooltip: isDark ? 'Switch to light mode' : 'Switch to dark mode',
            icon: FaIcon(
              isDark ? FontAwesomeIcons.sun : FontAwesomeIcons.moon,
              size: 18,
            ),
            onPressed: _toggleThemeMode,
          ),
          IconButton(
            tooltip: 'Settings',
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).pushNamed('/settings'),
          ),
          IconButton(
            tooltip: 'Log out',
            icon: const Icon(Icons.logout_rounded),
            onPressed: _confirmLogout,
          ),
        ],
      ),
      body:
          _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
              ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(_error!, textAlign: TextAlign.center),
                ),
              )
              : ModernPullToRefresh(
                onRefresh: _load,
                child: ListView(
                  physics: modernPullToRefreshPhysics,
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                  children: [
                    Text(
                      'Hello, $lastName',
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Yo, $lastName',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: cs.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 16),
                    _buildGrowthHero(context),
                    const SizedBox(height: 14),
                    GridView.count(
                      crossAxisCount: 2,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 12,
                      childAspectRatio: 1.62,
                      children: [
                        _summaryTile(
                          context,
                          label: 'Classes',
                          value: '$_totalClasses',
                          icon: Icons.class_outlined,
                          iconBg: const Color(0xFF3B82F6),
                        ),
                        _summaryTile(
                          context,
                          label: 'Active sessions',
                          value: '$_activeSessions',
                          icon: Icons.bolt_rounded,
                          iconBg: const Color(0xFFEF4444),
                        ),
                        _summaryTile(
                          context,
                          label: 'Avg attendance',
                          value: '${_avgAttendance.toStringAsFixed(1)}%',
                          icon: Icons.percent_rounded,
                          iconBg: const Color(0xFF10B981),
                        ),
                        _summaryTile(
                          context,
                          label: 'At-risk',
                          value: '$_atRisk',
                          icon: Icons.warning_amber_rounded,
                          iconBg: const Color(0xFFF59E0B),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _buildTrendCard(context),
                    const SizedBox(height: 24),
                    Text(
                      'Your classes',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    ..._classes.map(
                      (c) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _buildClassCard(context, c),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Open a class for session history, exports, and flagged students.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: cs.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
      floatingActionButton:
          kIsWeb
              ? null
              : FloatingActionButton(
                onPressed: _openMessageComposerModal,
                tooltip: 'Message students',
                child: Stack(
                  clipBehavior: Clip.none,
                  alignment: Alignment.center,
                  children: [
                    const Icon(Icons.message_rounded),
                    Positioned(
                      right: -2,
                      bottom: -2,
                      child: Container(
                        width: 16,
                        height: 16,
                        decoration: BoxDecoration(
                          color: cs.onPrimary,
                          shape: BoxShape.circle,
                        ),
                        alignment: Alignment.center,
                        child: Icon(Icons.add, size: 11, color: cs.primary),
                      ),
                    ),
                  ],
                ),
              ),
    );
  }
}
