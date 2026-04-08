import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../theme/student_soft_ui.dart';
import '../widgets/modern_pull_to_refresh.dart';
import '../widgets/profile_avatar.dart';

/// Class rep: student profile + class-scoped attendance (from `POST /api/class-rep/student-detail`).
class ClassRepStudentDetailPage extends StatefulWidget {
  const ClassRepStudentDetailPage({
    super.key,
    required this.studentId,
    required this.previewRow,
  });

  final int studentId;
  final Map<String, dynamic> previewRow;

  @override
  State<ClassRepStudentDetailPage> createState() =>
      _ClassRepStudentDetailPageState();
}

class _ClassRepStudentDetailPageState extends State<ClassRepStudentDetailPage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _student;
  Map<String, dynamic>? _stats;
  List<Map<String, dynamic>> _recent = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final me = await OfflineService.getCurrentStudent();
    if (me == null || !await OfflineService.hasPasswordOrApiToken()) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Sign in online once to load details.';
        });
      }
      return;
    }
    final pwd = await OfflineService.getApiSessionPassword();
    if (pwd == null || pwd.isEmpty) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Sign in again to load details.';
        });
      }
      return;
    }
    try {
      final res = await ApiService.classRepStudentDetail(
        indexNumber: me.indexNumber,
        password: pwd,
        studentId: widget.studentId,
      );
      final raw = jsonDecode(res.body);
      if (res.statusCode == 200 &&
          raw is Map &&
          raw['success'] == true &&
          raw['data'] is Map) {
        final d = Map<String, dynamic>.from(raw['data'] as Map);
        final s = d['student'];
        final st = d['stats'];
        final rec = d['recent_attendance'];
        final list = <Map<String, dynamic>>[];
        if (rec is List) {
          for (final e in rec) {
            if (e is Map) list.add(Map<String, dynamic>.from(e));
          }
        }
        if (mounted) {
          setState(() {
            _loading = false;
            _error = null;
            _student = s is Map ? Map<String, dynamic>.from(s) : null;
            _stats = st is Map ? Map<String, dynamic>.from(st) : null;
            _recent = list;
          });
        }
      } else {
        final msg = raw is Map ? raw['message']?.toString() : '';
        if (mounted) {
          setState(() {
            _loading = false;
            _error = msg != null && msg.isNotEmpty
                ? msg
                : ApiService.messageFromHttpResponse(res);
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Could not load student.';
        });
      }
    }
  }

  Student _mergedStudent() {
    final p = widget.previewRow;
    final s = _student;
    final idx = (s?['index_number'] ?? p['index_number'])?.toString() ?? '';
    final name = (s?['name'] ?? p['name'])?.toString() ?? 'Student';
    final pic = (s?['profile_image'] ?? p['profile_image'] ?? p['profile_picture'])
        ?.toString();
    final id = int.tryParse('${s?['id'] ?? p['id']}');
    return Student(
      serverId: id,
      indexNumber: idx.isNotEmpty ? idx : '—',
      name: name,
      profileImage: (pic != null && pic.isNotEmpty) ? pic : '',
    );
  }

  String _fmtTime(String? iso) {
    if (iso == null || iso.isEmpty) return '—';
    try {
      final d = DateTime.parse(iso).toLocal();
      final mo = d.month.toString().padLeft(2, '0');
      final da = d.day.toString().padLeft(2, '0');
      final h = d.hour.toString().padLeft(2, '0');
      final m = d.minute.toString().padLeft(2, '0');
      return '$da/$mo/${d.year} $h:$m';
    } catch (_) {
      return iso;
    }
  }

  String? _normalizedPhoneDigits(String? raw) {
    if (raw == null) return null;
    final cleaned = raw.replaceAll(RegExp(r'[^0-9+]'), '');
    if (cleaned.isEmpty) return null;
    final digits = cleaned.startsWith('+') ? cleaned.substring(1) : cleaned;
    if (digits.length < 7) return null;
    return digits;
  }

  Future<void> _openWhatsappForPhone(String? rawPhone) async {
    final phone = _normalizedPhoneDigits(rawPhone);
    if (phone == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No valid phone number available for WhatsApp.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final msg = Uri.encodeComponent('Hello, this is your class rep.');
    final appUri = Uri.parse('whatsapp://send?phone=$phone&text=$msg');
    final webUri = Uri.parse('https://wa.me/$phone?text=$msg');

    final openedApp = await launchUrl(appUri);
    if (openedApp) return;
    final openedWeb = await launchUrl(webUri, mode: LaunchMode.externalApplication);
    if (openedWeb || !mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Could not open WhatsApp on this device.'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final light = Theme.of(context).brightness == Brightness.light;
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final bg = light ? StudentSoftUi.cream(cs) : cs.surface;
    final merged = _mergedStudent();
    final s = _student;
    final rate = _stats?['attendance_rate_pct'];
    final rateStr = rate is num ? '${rate.toStringAsFixed(1)}%' : '—';
    final border = light
        ? const Color(0xFFE8DDD4)
        : cs.outline.withValues(alpha: 0.15);

    return Scaffold(
      backgroundColor: bg,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: tt.bodyLarge?.copyWith(color: cs.error),
                        ),
                        const SizedBox(height: 16),
                        FilledButton.tonal(
                          onPressed: _load,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : ModernPullToRefresh(
                  onRefresh: _load,
                  child: CustomScrollView(
                    physics: modernPullToRefreshPhysics,
                    slivers: [
                      SliverPadding(
                        padding: EdgeInsets.fromLTRB(
                          12,
                          MediaQuery.paddingOf(context).top + 8,
                          12,
                          8,
                        ),
                        sliver: SliverToBoxAdapter(
                          child: Row(
                            children: [
                              IconButton(
                                onPressed: () =>
                                    Navigator.of(context).maybePop(),
                                icon: Icon(
                                  Icons.arrow_back_ios_new_rounded,
                                  color: light
                                      ? cs.primary
                                      : cs.onSurface,
                                ),
                              ),
                              Expanded(
                                child: Text(
                                  'Student profile',
                                  style: tt.headlineSmall?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -0.4,
                                    color: light
                                        ? StudentSoftUi.titleBrown(cs)
                                        : cs.onSurface,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                        sliver: SliverToBoxAdapter(
                          child: Material(
                            color: Colors.transparent,
                            child: Ink(
                              decoration: BoxDecoration(
                                color: light
                                    ? StudentSoftUi.cardWhite(cs)
                                    : cs.surfaceContainerHigh,
                                borderRadius: BorderRadius.circular(28),
                                border: Border.all(color: border),
                                boxShadow: light
                                    ? [
                                        BoxShadow(
                                          color: Colors.black
                                              .withValues(alpha: 0.06),
                                          blurRadius: 16,
                                          offset: const Offset(0, 8),
                                        ),
                                      ]
                                    : null,
                              ),
                              child: Padding(
                                padding: const EdgeInsets.fromLTRB(
                                  18,
                                  22,
                                  18,
                                  18,
                                ),
                                child: Column(
                                  children: [
                                    ProfileAvatar(student: merged, radius: 40),
                                    const SizedBox(height: 14),
                                    Text(
                                      merged.name,
                                      textAlign: TextAlign.center,
                                      style: tt.titleLarge?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: light
                                            ? StudentSoftUi.titleBrown(cs)
                                            : cs.onSurface,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      'Index ${merged.indexNumber}',
                                      style: tt.bodyMedium?.copyWith(
                                        color: light
                                            ? StudentSoftUi.mutedBrown(cs)
                                            : cs.onSurfaceVariant,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    Builder(
                                      builder: (context) {
                                        final cn =
                                            s?['class_name']?.toString().trim();
                                        if (cn == null || cn.isEmpty) {
                                          return const SizedBox.shrink();
                                        }
                                        return Padding(
                                          padding:
                                              const EdgeInsets.only(top: 8),
                                          child: Text(
                                            cn,
                                            textAlign: TextAlign.center,
                                            style: tt.bodySmall?.copyWith(
                                              color: light
                                                  ? StudentSoftUi.mutedBrown(cs)
                                                  : cs.onSurfaceVariant,
                                              height: 1.35,
                                            ),
                                          ),
                                        );
                                      },
                                    ),
                                    if ((s?['department_name']?.toString() ??
                                                '')
                                            .isNotEmpty ||
                                        (s?['faculty_name']?.toString() ?? '')
                                            .isNotEmpty) ...[
                                      const SizedBox(height: 8),
                                      Text(
                                        [
                                          s?['department_name']?.toString(),
                                          s?['faculty_name']?.toString(),
                                        ]
                                            .where((e) =>
                                                e != null &&
                                                e.toString().isNotEmpty)
                                            .join(' · '),
                                        textAlign: TextAlign.center,
                                        style: tt.labelMedium?.copyWith(
                                          color: cs.onSurfaceVariant,
                                          height: 1.4,
                                        ),
                                      ),
                                    ],
                                    Builder(
                                      builder: (context) {
                                        final phone = s?['phone_number']
                                            ?.toString()
                                            .trim();
                                        if (phone == null || phone.isEmpty) {
                                          return const SizedBox.shrink();
                                        }
                                        return Padding(
                                          padding:
                                              const EdgeInsets.only(top: 12),
                                          child: Wrap(
                                            alignment: WrapAlignment.center,
                                            crossAxisAlignment:
                                                WrapCrossAlignment.center,
                                            spacing: 8,
                                            runSpacing: 8,
                                            children: [
                                              InkWell(
                                                onTap: () =>
                                                    _openWhatsappForPhone(phone),
                                                borderRadius:
                                                    BorderRadius.circular(999),
                                                child: Padding(
                                                  padding:
                                                      const EdgeInsets.symmetric(
                                                    horizontal: 6,
                                                    vertical: 4,
                                                  ),
                                                  child: Row(
                                                    mainAxisSize:
                                                        MainAxisSize.min,
                                                    children: [
                                                      const Icon(
                                                        Icons.phone_rounded,
                                                        size: 16,
                                                        color: Color(0xFF1D9F50),
                                                      ),
                                                      const SizedBox(width: 6),
                                                      Text(
                                                        phone,
                                                        style: tt.bodyMedium
                                                            ?.copyWith(
                                                          fontWeight:
                                                              FontWeight.w700,
                                                          color: cs.primary,
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                              ),
                                              FilledButton.tonalIcon(
                                                onPressed: () =>
                                                    _openWhatsappForPhone(phone),
                                                icon: const Icon(
                                                  Icons.message_rounded,
                                                  size: 16,
                                                ),
                                                label: const Text(
                                                    'WhatsApp student'),
                                                style:
                                                    FilledButton.styleFrom(
                                                  visualDensity:
                                                      VisualDensity.compact,
                                                ),
                                              ),
                                            ],
                                          ),
                                        );
                                      },
                                    ),
                                    const SizedBox(height: 16),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 12,
                                        vertical: 6,
                                      ),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFE8F5E9),
                                        borderRadius:
                                            BorderRadius.circular(999),
                                      ),
                                      child: Text(
                                        'Attendance rate $rateStr',
                                        style: tt.labelSmall?.copyWith(
                                          fontWeight: FontWeight.w700,
                                          color: const Color(0xFF2E7D32),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                        sliver: SliverToBoxAdapter(
                          child: Row(
                            children: [
                              Expanded(
                                child: _metricCell(
                                  context,
                                  light,
                                  value: rateStr,
                                  label: 'Rate',
                                  cs: cs,
                                ),
                              ),
                              Expanded(
                                child: _metricCell(
                                  context,
                                  light,
                                  value: '${_stats?['present_count'] ?? 0}',
                                  label: 'Present',
                                  cs: cs,
                                ),
                              ),
                              Expanded(
                                child: _metricCell(
                                  context,
                                  light,
                                  value: '${_stats?['total_marked'] ?? 0}',
                                  label: 'Marked',
                                  cs: cs,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
                        sliver: SliverToBoxAdapter(
                          child: Text(
                            'Recent attendance',
                            style: tt.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              color: light
                                  ? StudentSoftUi.titleBrown(cs)
                                  : cs.onSurface,
                            ),
                          ),
                        ),
                      ),
                      if (_recent.isEmpty)
                        SliverToBoxAdapter(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 24,
                              vertical: 16,
                            ),
                            child: Text(
                              'No attendance records in this class yet.',
                              style: tt.bodyMedium?.copyWith(
                                color: cs.onSurfaceVariant,
                              ),
                            ),
                          ),
                        )
                      else
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
                          sliver: SliverList.separated(
                            itemCount: _recent.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 10),
                            itemBuilder: (context, i) {
                              final r = _recent[i];
                              final course =
                                  r['course_name']?.toString() ?? 'Course';
                              final code =
                                  r['course_code']?.toString() ?? '';
                              final week = r['week_number'];
                              final weekStr =
                                  week != null ? 'Week $week' : '';
                              final status = r['status']?.toString() ?? '';
                              final t =
                                  _fmtTime(r['attendance_time']?.toString());
                              final present =
                                  status.toLowerCase() == 'present';
                              return Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: light
                                      ? StudentSoftUi.cardWhite(cs)
                                      : cs.surfaceContainerHigh,
                                  borderRadius: BorderRadius.circular(22),
                                  border: Border.all(color: border),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            code.isNotEmpty
                                                ? '$course ($code)'
                                                : course,
                                            style: tt.titleSmall?.copyWith(
                                              fontWeight: FontWeight.w700,
                                              color: light
                                                  ? StudentSoftUi.titleBrown(cs)
                                                  : cs.onSurface,
                                            ),
                                          ),
                                        ),
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 10,
                                            vertical: 5,
                                          ),
                                          decoration: BoxDecoration(
                                            color: present
                                                ? const Color(0xFFE8F5E9)
                                                : cs.errorContainer
                                                    .withValues(alpha: 0.55),
                                            borderRadius:
                                                BorderRadius.circular(999),
                                          ),
                                          child: Text(
                                            status.isEmpty ? '—' : status,
                                            style: tt.labelSmall?.copyWith(
                                              fontWeight: FontWeight.w700,
                                              color: present
                                                  ? const Color(0xFF2E7D32)
                                                  : cs.onErrorContainer,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    if (weekStr.isNotEmpty) ...[
                                      const SizedBox(height: 4),
                                      Text(
                                        weekStr,
                                        style: tt.bodySmall?.copyWith(
                                          color: cs.onSurfaceVariant,
                                        ),
                                      ),
                                    ],
                                    const SizedBox(height: 6),
                                    Text(
                                      t,
                                      style: tt.labelMedium?.copyWith(
                                        color: light
                                            ? StudentSoftUi.mutedBrown(cs)
                                            : cs.onSurfaceVariant,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }

  Widget _metricCell(
    BuildContext context,
    bool light, {
    required String value,
    required String label,
    required ColorScheme cs,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        decoration: BoxDecoration(
          color: light
              ? StudentSoftUi.cardWhite(cs)
              : cs.surfaceContainerHighest.withValues(alpha: 0.45),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: light
                ? const Color(0xFFE8DDD4)
                : cs.outline.withValues(alpha: 0.12),
          ),
        ),
        child: Column(
          children: [
            Text(
              value,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: light
                        ? StudentSoftUi.titleBrown(cs)
                        : cs.onSurface,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: light
                        ? StudentSoftUi.mutedBrown(cs)
                        : cs.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}
