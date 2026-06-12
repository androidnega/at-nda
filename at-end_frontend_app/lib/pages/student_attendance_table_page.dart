import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/api_user_message.dart';
import '../utils/constants.dart';

/// Per-student week-by-week attendance table. One card per course
/// with a compact grid (✅ present / ❌ absent / CANCELLED) plus an
/// "Export PDF" button that downloads the server-rendered PDF and
/// hands it to the system share sheet.
class StudentAttendanceTablePage extends StatefulWidget {
  const StudentAttendanceTablePage({super.key});

  static const String routeName = '/student/attendance-table';

  @override
  State<StudentAttendanceTablePage> createState() =>
      _StudentAttendanceTablePageState();
}

class _StudentAttendanceTablePageState
    extends State<StudentAttendanceTablePage> {
  bool _loading = true;
  bool _exporting = false;
  String? _error;
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final student = await OfflineService.getCurrentStudent();
    final password = await OfflineService.getApiSessionPassword();
    if (student == null || password == null || password.isEmpty) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Please sign in again.';
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final uri = Uri.parse(
        '${Constants.baseUrl}/student/attendance-grid',
      ).replace(queryParameters: {
        'index_number': student.indexNumber,
        'password': password,
      });
      final res = await http.get(uri, headers: ApiService.requestHeaders());
      final body = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          body is Map &&
          body['success'] == true &&
          body['data'] is Map) {
        if (!mounted) return;
        setState(() {
          _data = Map<String, dynamic>.from(body['data'] as Map);
          _loading = false;
        });
      } else {
        if (!mounted) return;
        setState(() {
          _loading = false;
          _error = sanitizeApiUserMessage(
            body is Map ? body['message']?.toString() : null,
          ).ifEmptyFallback('Could not load attendance.');
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Network error. Pull down to retry.';
      });
    }
  }

  Future<void> _exportPdf() async {
    if (_exporting) return;
    final student = await OfflineService.getCurrentStudent();
    final password = await OfflineService.getApiSessionPassword();
    if (student == null || password == null || password.isEmpty) {
      _toast('Please sign in again.');
      return;
    }

    setState(() => _exporting = true);
    try {
      final uri = Uri.parse(
        '${Constants.baseUrl}/student/attendance-grid/pdf',
      ).replace(queryParameters: {
        'index_number': student.indexNumber,
        'password': password,
      });
      final res = await http.get(uri, headers: ApiService.requestHeaders());
      if (res.statusCode < 200 || res.statusCode >= 300) {
        _toast('Could not generate PDF (${res.statusCode}).');
        return;
      }
      // Heuristic check — PDF starts with the magic bytes "%PDF".
      final isPdf = res.bodyBytes.length >= 4 &&
          res.bodyBytes[0] == 0x25 &&
          res.bodyBytes[1] == 0x50 &&
          res.bodyBytes[2] == 0x44 &&
          res.bodyBytes[3] == 0x46;
      if (!isPdf) {
        _toast('Unexpected response from the server.');
        return;
      }

      final dir = await getTemporaryDirectory();
      final safeIndex = student.indexNumber
          .replaceAll(RegExp(r'[^A-Za-z0-9]+'), '-')
          .toLowerCase();
      final ts = DateTime.now().millisecondsSinceEpoch;
      final file = File('${dir.path}/attendance-$safeIndex-$ts.pdf');
      await file.writeAsBytes(res.bodyBytes, flush: true);

      await Share.shareXFiles(
        [XFile(file.path, mimeType: 'application/pdf')],
        subject: 'My attendance — ${student.indexNumber}',
        text: 'Generated from the attendance app.',
      );
    } catch (_) {
      _toast('Could not export PDF. Please try again.');
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  void _toast(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: const Text('My attendance'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _loading ? null : _load,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _buildBody(theme),
      ),
      floatingActionButton: (_data == null || _loading)
          ? null
          : FloatingActionButton.extended(
              onPressed: _exporting ? null : _exportPdf,
              icon: _exporting
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.picture_as_pdf_rounded),
              label: Text(_exporting ? 'Preparing…' : 'Export PDF'),
              backgroundColor: const Color(0xFF0B3C98),
              foregroundColor: Colors.white,
            ),
    );
  }

  Widget _buildBody(ThemeData theme) {
    if (_loading && _data == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _data == null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 80),
          const Icon(Icons.cloud_off_rounded,
              size: 48, color: Color(0xFF94A3B8)),
          const SizedBox(height: 12),
          Text(_error!,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium),
          const SizedBox(height: 16),
          Center(
            child: FilledButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Try again'),
            ),
          ),
        ],
      );
    }

    final data = _data;
    if (data == null) {
      return const SizedBox.shrink();
    }
    final student = Map<String, dynamic>.from(data['student'] as Map? ?? {});
    final summary = Map<String, dynamic>.from(data['summary'] as Map? ?? {});
    final courses = (data['courses'] as List? ?? const [])
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .toList();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
      children: [
        _SummaryCard(student: student, summary: summary),
        const SizedBox(height: 16),
        if (courses.isEmpty)
          _EmptyState(className: student['class_name']?.toString())
        else
          ...courses.map((c) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _CourseCard(course: c),
              )),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.student, required this.summary});

  final Map<String, dynamic> student;
  final Map<String, dynamic> summary;

  @override
  Widget build(BuildContext context) {
    final percent = (summary['percent'] as int?) ?? 0;
    final attended = (summary['classes_attended'] as int?) ?? 0;
    final held = (summary['classes_held'] as int?) ?? 0;
    final cancelled = (summary['classes_cancelled'] as int?) ?? 0;
    final courseCount = (summary['course_count'] as int?) ?? 0;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student['name']?.toString() ?? '',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      [
                        student['index_number']?.toString(),
                        student['class_name']?.toString(),
                      ].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFF475569),
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFFE0F2FE),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFF38BDF8)),
                ),
                child: Text(
                  '$percent%',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF075985),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              _Pill(label: 'Courses', value: '$courseCount'),
              _Pill(label: 'Held', value: '$held'),
              _Pill(label: 'Attended', value: '$attended'),
              if (cancelled > 0)
                _Pill(
                  label: 'Cancelled',
                  value: '$cancelled',
                  bg: const Color(0xFFFFF7ED),
                  fg: const Color(0xFFB45309),
                  border: const Color(0xFFFED7AA),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.value,
    this.bg,
    this.fg,
    this.border,
  });

  final String label;
  final String value;
  final Color? bg;
  final Color? fg;
  final Color? border;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bg ?? const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border ?? const Color(0xFFE2E8F0)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: fg ?? const Color(0xFF475569),
            ),
          ),
          const SizedBox(width: 6),
          Text(
            value,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: fg ?? const Color(0xFF0F172A),
            ),
          ),
        ],
      ),
    );
  }
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course});

  final Map<String, dynamic> course;

  @override
  Widget build(BuildContext context) {
    final weeks = (course['weeks'] as List? ?? const [])
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .toList();
    final percent = (course['percent'] as int?) ?? 0;
    final held = (course['held_count'] as int?) ?? 0;
    final present = (course['present_count'] as int?) ?? 0;
    final cancelled = (course['cancelled_count'] as int?) ?? 0;
    final name = course['course_name']?.toString() ?? 'Course';
    final code = course['course_code']?.toString();
    final lecturer = course['lecturer_name']?.toString();
    final venue = course['venue']?.toString();

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    if ((code ?? '').isNotEmpty)
                      Text(
                        code!,
                        style: const TextStyle(
                          fontSize: 11,
                          color: Color(0xFF64748B),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: percent >= 75
                      ? const Color(0xFFDCFCE7)
                      : (percent >= 50
                          ? const Color(0xFFFEF3C7)
                          : const Color(0xFFFEE2E2)),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '$percent%',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: percent >= 75
                        ? const Color(0xFF14532D)
                        : (percent >= 50
                            ? const Color(0xFF92400E)
                            : const Color(0xFF991B1B)),
                  ),
                ),
              ),
            ],
          ),
          if (lecturer != null && lecturer.isNotEmpty ||
              venue != null && venue.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              [
                if (lecturer != null && lecturer.isNotEmpty) lecturer,
                if (venue != null && venue.isNotEmpty) venue,
              ].join(' · '),
              style: const TextStyle(
                fontSize: 11,
                color: Color(0xFF64748B),
              ),
            ),
          ],
          const SizedBox(height: 6),
          Text(
            cancelled > 0
                ? '$present / $held attended · $cancelled cancelled'
                : '$present / $held classes attended',
            style: const TextStyle(
              fontSize: 11,
              color: Color(0xFF475569),
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 10),
          if (weeks.isEmpty)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 14),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Center(
                child: Text(
                  'No classes held yet for this course.',
                  style: TextStyle(
                    fontSize: 11,
                    color: Color(0xFF64748B),
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ),
            )
          else
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: weeks
                    .map((w) => Padding(
                          padding: const EdgeInsets.only(right: 6),
                          child: _WeekCell(week: w),
                        ))
                    .toList(),
              ),
            ),
        ],
      ),
    );
  }
}

class _WeekCell extends StatelessWidget {
  const _WeekCell({required this.week});

  final Map<String, dynamic> week;

  @override
  Widget build(BuildContext context) {
    final n = (week['week_number'] as int?) ?? 0;
    final status = (week['status']?.toString() ?? 'absent').toLowerCase();

    Color bg, border, fg;
    Widget glyph;
    if (status == 'present') {
      bg = const Color(0xFFDCFCE7);
      border = const Color(0xFF16A34A);
      fg = const Color(0xFF14532D);
      glyph = const Text('✓',
          style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: Color(0xFF14532D)));
    } else if (status == 'cancelled') {
      bg = const Color(0xFFFFF7ED);
      border = const Color(0xFFFED7AA);
      fg = const Color(0xFFB91C1C);
      glyph = const Text('OFF',
          style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w800,
              color: Color(0xFFB91C1C),
              letterSpacing: 0.5));
    } else {
      bg = const Color(0xFFFEE2E2);
      border = const Color(0xFFDC2626);
      fg = const Color(0xFF7F1D1D);
      glyph = const Text('✗',
          style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: Color(0xFF7F1D1D)));
    }

    return Container(
      width: 44,
      decoration: BoxDecoration(
        color: bg,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(10),
      ),
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'W$n',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: fg,
            ),
          ),
          const SizedBox(height: 2),
          SizedBox(height: 18, child: Center(child: glyph)),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({this.className});

  final String? className;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          const Icon(Icons.school_outlined,
              size: 40, color: Color(0xFF94A3B8)),
          const SizedBox(height: 8),
          Text(
            (className != null && className!.isNotEmpty)
                ? 'No courses linked to $className yet'
                : 'No courses linked to your class yet',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: Color(0xFF334155),
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'An admin needs to add courses for your class.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
          ),
        ],
      ),
    );
  }
}

extension on String {
  String ifEmptyFallback(String fallback) => trim().isEmpty ? fallback : this;
}
