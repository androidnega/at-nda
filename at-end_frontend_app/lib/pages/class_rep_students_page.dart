import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../theme/student_soft_ui.dart';
import '../utils/app_selectable_scope.dart';
import '../widgets/jump_reveal_on_scroll.dart';
import '../widgets/modern_pull_to_refresh.dart';
import '../widgets/profile_avatar.dart';
import 'class_rep_student_detail_page.dart';
import 'login_page.dart';

/// Class list from `POST /api/class-rep/students` (server-enforced class rep only).
class ClassRepStudentsPage extends StatefulWidget {
  const ClassRepStudentsPage({super.key});

  @override
  State<ClassRepStudentsPage> createState() => _ClassRepStudentsPageState();
}

class _ClassRepStudentsPageState extends State<ClassRepStudentsPage> {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _rows = [];

  String _searchQuery = '';
  /// `null` = all classes; otherwise exact class name from API.
  String? _classFilter;

  static const Color _fabLavender = Color(0xFF8B7EC8);

  static String _norm(String? s) => (s ?? '').trim();

  bool _hasDisplayName(Map<String, dynamic> r) {
    final n = _norm(r['name']?.toString());
    if (n.isEmpty || n == '—') return false;
    final idx = _norm(r['index_number']?.toString());
    if (idx.isNotEmpty && n == idx) return false;
    return true;
  }

  String? _sharedClassLabel() {
    if (_rows.isEmpty) return null;
    final first = _norm(_rows.first['class_name']?.toString());
    if (first.isEmpty) return null;
    final same = _rows.every(
      (r) => _norm(r['class_name']?.toString()) == first,
    );
    return same ? first : null;
  }

  List<String> _distinctClassNames() {
    final set = <String>{};
    for (final r in _rows) {
      final c = _norm(r['class_name']?.toString());
      if (c.isNotEmpty) set.add(c);
    }
    final list = set.toList()..sort();
    return list;
  }

  List<Map<String, dynamic>> get _filteredRows {
    var list = _rows;
    if (_classFilter != null && _classFilter!.isNotEmpty) {
      list = list
          .where((r) => _norm(r['class_name']?.toString()) == _classFilter)
          .toList();
    }
    final q = _searchQuery.trim().toLowerCase();
    if (q.isEmpty) return list;
    return list.where((r) {
      final name = _norm(r['name']?.toString()).toLowerCase();
      final idx = _norm(r['index_number']?.toString()).toLowerCase();
      return name.contains(q) || idx.contains(q);
    }).toList();
  }

  Student _rowToStudent(Map<String, dynamic> r) {
    final idx = _norm(r['index_number']?.toString());
    final display = _norm(r['name']?.toString());
    final id = int.tryParse('${r['id']}');
    final picPreferred = _norm(r['profile_picture']?.toString());
    final picAlt = _norm(r['profile_image']?.toString());
    final pic = picPreferred.isNotEmpty ? picPreferred : picAlt;
    return Student(
      serverId: id,
      indexNumber: idx.isNotEmpty ? idx : '—',
      name: display.isNotEmpty ? display : (idx.isNotEmpty ? idx : 'Student'),
      profileImage: pic,
    );
  }

  void _openStudentDetail(Map<String, dynamic> r) {
    final id = int.tryParse('${r['id']}');
    if (id == null || id <= 0) return;
    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ClassRepStudentDetailPage(
          studentId: id,
          previewRow: r,
        ),
      ),
    );
  }

  /// Short “level” hint from class name (e.g. 300L) when present.
  static String _levelHint(Map<String, dynamic> r) {
    final cn = _norm(r['class_name']?.toString());
    if (cn.isEmpty) return '—';
    final levelLike = RegExp(
      r'\b(\d{3,4}\s*[Ll]|\d{1,2}\s*[Ll]|[Ll]evel\s*\d+)\b',
    ).firstMatch(cn);
    if (levelLike != null) return levelLike.group(1)!.trim();
    final year = RegExp(r'\b(20\d{2})\b').firstMatch(cn);
    if (year != null) return year.group(1)!;
    return cn.length > 14 ? '${cn.substring(0, 12)}…' : cn;
  }

  /// Uses API fields when the backend adds them; otherwise "—".
  static String _attendancePercentLine(Map<String, dynamic> r) {
    final v = r['attendance_rate'] ??
        r['attendance_percent'] ??
        r['attendance_percentage'];
    if (v == null) return '—';
    if (v is num) return '${v.round()}%';
    final s = v.toString().trim();
    if (s.isEmpty) return '—';
    return s.contains('%') ? s : '$s%';
  }

  static String _statusKey(Map<String, dynamic> r) {
    final s = _norm(r['attendance_status']?.toString()).toLowerCase();
    if (s.contains('present')) return 'present';
    if (s.contains('absent')) return 'absent';
    if (s.contains('late')) return 'late';
    if (s.contains('excus')) return 'excused';
    return 'member';
  }

  static ({String label, Color bg, Color fg}) _statusStyle(
    BuildContext context,
    String key,
    bool light,
  ) {
    switch (key) {
      case 'present':
        return (
          label: 'Present',
          bg: const Color(0xFFE8F5E9),
          fg: const Color(0xFF2E7D32),
        );
      case 'absent':
        return (
          label: 'Absent',
          bg: const Color(0xFFFFEBEE),
          fg: const Color(0xFFC62828),
        );
      case 'late':
        return (
          label: 'Late',
          bg: const Color(0xFFFFF3E0),
          fg: const Color(0xFFE65100),
        );
      case 'excused':
        return (
          label: 'Excused',
          bg: const Color(0xFFE3F2FD),
          fg: const Color(0xFF1565C0),
        );
      default:
        return (
          label: 'Member',
          bg: light
              ? const Color(0xFFF3E5F5)
              : Theme.of(context).colorScheme.surfaceContainerHighest,
          fg: light
              ? const Color(0xFF6A1B9A)
              : Theme.of(context).colorScheme.onSurfaceVariant,
        );
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
      });
    } else {
      setState(() => _error = null);
    }
    final student = await OfflineService.getCurrentStudent();
    if (student == null || !await OfflineService.hasPasswordOrApiToken()) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Sign in online once to load this list.';
          _rows = [];
        });
      }
      return;
    }
    try {
      final pwd = await OfflineService.getApiSessionPassword();
      final res = await ApiService.classRepStudents(
        indexNumber: student.indexNumber,
        password: pwd ?? '',
      );
      final raw = jsonDecode(res.body);
      if (res.statusCode == 401 || res.statusCode == 403) {
        final msg = raw is Map && raw['message'] != null
            ? raw['message'].toString()
            : 'Not allowed.';
        if (mounted) {
          setState(() {
            _loading = false;
            _error = msg;
            _rows = [];
          });
        }
        return;
      }
      if (raw is! Map ||
          raw['success'] != true ||
          raw['data'] is! Map) {
        if (mounted) {
          setState(() {
            _loading = false;
            _error = ApiService.messageFromHttpResponse(res).isEmpty
                ? 'Could not load students (${res.statusCode}).'
                : ApiService.messageFromHttpResponse(res);
            _rows = [];
          });
        }
        return;
      }
      final data = Map<String, dynamic>.from(raw['data'] as Map);
      final list = data['students'];
      final out = <Map<String, dynamic>>[];
      if (list is List) {
        for (final item in list) {
          if (item is Map) {
            out.add(Map<String, dynamic>.from(item));
          }
        }
      }
      if (mounted) {
        setState(() {
          _loading = false;
          _error = null;
          _rows = out;
          _classFilter = null;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Error: $e';
          _rows = [];
        });
      }
    }
  }

  void _showFilterSheet(BuildContext context) {
    final classes = _distinctClassNames();
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Filter by class',
                  style: Theme.of(ctx).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 12),
                ListTile(
                  title: const Text('All classes'),
                  leading: const Icon(Icons.groups_rounded),
                  onTap: () {
                    setState(() => _classFilter = null);
                    Navigator.pop(ctx);
                  },
                ),
                for (final c in classes)
                  ListTile(
                    title: Text(c),
                    onTap: () {
                      setState(() => _classFilter = c);
                      Navigator.pop(ctx);
                    },
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _pill(
    BuildContext context, {
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(999),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeOutCubic,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
            decoration: BoxDecoration(
              color: selected ? cs.primary : Colors.white,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                color: selected
                    ? cs.primary
                    : const Color(0xFFE0D5CC),
              ),
              boxShadow: selected
                  ? [
                      BoxShadow(
                        color: cs.primary.withValues(alpha: 0.25),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ]
                  : null,
            ),
            child: Text(
              label,
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: selected ? cs.onPrimary : StudentSoftUi.titleBrown(cs),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _studentCard(BuildContext context, Map<String, dynamic> r) {
    final light = Theme.of(context).brightness == Brightness.light;
    final cs = Theme.of(context).colorScheme;
    final idx = _norm(r['index_number']?.toString());
    final hasName = _hasDisplayName(r);
    final displayName = _norm(r['name']?.toString());
    final primaryLine =
        hasName ? displayName : (idx.isNotEmpty ? idx : '—');
    final className = _norm(r['class_name']?.toString());
    final classHint = _sharedClassLabel();
    final subtitle = hasName
        ? (idx.isNotEmpty ? 'Index $idx' : 'Student')
        : (className.isNotEmpty ? className : 'Class member');

    final statusKey = _statusKey(r);
    final st = _statusStyle(context, statusKey, light);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => _openStudentDetail(r),
        borderRadius: BorderRadius.circular(28),
        child: Ink(
          decoration: BoxDecoration(
            color: light ? StudentSoftUi.cardWhite(cs) : Theme.of(context).colorScheme.surfaceContainerHigh,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(
              color: light
                  ? const Color(0xFFE8DDD4)
                  : Theme.of(context).colorScheme.outline.withValues(alpha: 0.15),
            ),
            boxShadow: light
                ? [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 16,
                      offset: const Offset(0, 8),
                    ),
                  ]
                : null,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ProfileAvatar(
                      key: ValueKey(
                        '${idx}_${r['id']}_${r['profile_image']}_${r['profile_picture']}',
                      ),
                      student: _rowToStudent(r),
                      radius: 28,
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            primaryLine,
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: light
                                      ? StudentSoftUi.titleBrown(cs)
                                      : Theme.of(context).colorScheme.onSurface,
                                  height: 1.2,
                                ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            subtitle,
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: light
                                      ? StudentSoftUi.mutedBrown(cs)
                                      : Theme.of(context).colorScheme.onSurfaceVariant,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                          if (classHint == null &&
                              className.isNotEmpty &&
                              hasName) ...[
                            const SizedBox(height: 4),
                            Text(
                              className,
                              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                    color: light
                                        ? StudentSoftUi.mutedBrown(cs)
                                        : Theme.of(context).colorScheme.onSurfaceVariant,
                                  ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: st.bg,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        st.label,
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 11,
                          color: st.fg,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Divider(
                height: 1,
                thickness: 1,
                color: light
                    ? const Color(0xFFF0E6DE)
                    : Theme.of(context).colorScheme.outline.withValues(alpha: 0.12),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                child: Row(
                  children: [
                    Expanded(
                      child: _metricCell(
                        context,
                        light,
                        value: _levelHint(r),
                        label: 'Level / group',
                      ),
                    ),
                    Expanded(
                      child: _metricCell(
                        context,
                        light,
                        value: _attendancePercentLine(r),
                        label: 'Attendance',
                      ),
                    ),
                    Expanded(
                      child: _metricCell(
                        context,
                        light,
                        value: className.isNotEmpty
                            ? (className.length > 10
                                ? '${className.substring(0, 9)}…'
                                : className)
                            : '—',
                        label: 'Class',
                      ),
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

  Widget _metricCell(
    BuildContext context,
    bool light, {
    required String value,
    required String label,
  }) {
    final cs = Theme.of(context).colorScheme;
    return Column(
      children: [
        Text(
          value,
          textAlign: TextAlign.center,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: light
                    ? StudentSoftUi.titleBrown(cs)
                    : Theme.of(context).colorScheme.onSurface,
              ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: light
                    ? StudentSoftUi.mutedBrown(cs)
                    : Theme.of(context).colorScheme.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final light = Theme.of(context).brightness == Brightness.light;
    final cs = Theme.of(context).colorScheme;
    final classes = _distinctClassNames();
    final filtered = _filteredRows;
    final bg = Colors.white;

    return Scaffold(
      backgroundColor: bg,
      body: Stack(
        children: [
          _loading
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
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                            const SizedBox(height: 16),
                            if (_error!.contains('Sign in'))
                              FilledButton(
                                onPressed: () => Navigator.of(context)
                                    .pushReplacement(
                                  MaterialPageRoute<void>(
                                    builder: (_) => appSelectableScope(
                                      const LoginPage(),
                                    ),
                                  ),
                                ),
                                child: const Text('Go to login'),
                              )
                            else
                              FilledButton.tonal(
                                onPressed: () => _load(),
                                child: const Text('Retry'),
                              ),
                          ],
                        ),
                      ),
                    )
                  : ModernPullToRefresh(
                      onRefresh: () => _load(silent: true),
                      child: CustomScrollView(
                        controller: _scrollController,
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
                                          : Theme.of(context)
                                              .colorScheme
                                              .onSurface,
                                    ),
                                  ),
                                  Expanded(
                                    child: Text(
                                      'All students',
                                      style: Theme.of(context)
                                          .textTheme
                                          .headlineSmall
                                          ?.copyWith(
                                            fontWeight: FontWeight.w800,
                                            letterSpacing: -0.4,
                                            color: light
                                                ? StudentSoftUi.titleBrown(cs)
                                                : Theme.of(context)
                                                    .colorScheme
                                                    .onSurface,
                                          ),
                                    ),
                                  ),
                                  const SizedBox(width: 52),
                                ],
                              ),
                            ),
                          ),
                          SliverToBoxAdapter(
                            child: SizedBox(
                              height: 46,
                              child: ListView(
                                scrollDirection: Axis.horizontal,
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 16),
                                children: [
                                  _pill(
                                    context,
                                    label: 'All',
                                    selected: _classFilter == null,
                                    onTap: () =>
                                        setState(() => _classFilter = null),
                                  ),
                                  for (final c in classes)
                                    _pill(
                                      context,
                                      label: c.length > 18
                                          ? '${c.substring(0, 16)}…'
                                          : c,
                                      selected: _classFilter == c,
                                      onTap: () =>
                                          setState(() => _classFilter = c),
                                    ),
                                ],
                              ),
                            ),
                          ),
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                            sliver: SliverToBoxAdapter(
                              child: TextField(
                                controller: _searchController,
                                onChanged: (v) =>
                                    setState(() => _searchQuery = v),
                                decoration: InputDecoration(
                                  hintText: 'Search name or index…',
                                  prefixIcon: Icon(
                                    Icons.search_rounded,
                                    color: light
                                        ? StudentSoftUi.mutedBrown(cs)
                                        : Theme.of(context)
                                            .colorScheme
                                            .onSurfaceVariant,
                                  ),
                                  suffixIcon: IconButton(
                                    icon: Icon(
                                      Icons.tune_rounded,
                                      color: light
                                          ? StudentSoftUi.mutedBrown(cs)
                                          : Theme.of(context)
                                              .colorScheme
                                              .onSurfaceVariant,
                                    ),
                                    onPressed: () =>
                                        _showFilterSheet(context),
                                  ),
                                  filled: true,
                                  fillColor: light
                                      ? Colors.white
                                      : Theme.of(context)
                                          .colorScheme
                                          .surfaceContainerHigh,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(26),
                                    borderSide: BorderSide(
                                      color: light
                                          ? const Color(0xFFE8DDD4)
                                          : Theme.of(context)
                                              .colorScheme
                                              .outline
                                              .withValues(alpha: 0.2),
                                    ),
                                  ),
                                  enabledBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(26),
                                    borderSide: BorderSide(
                                      color: light
                                          ? const Color(0xFFE8DDD4)
                                          : Theme.of(context)
                                              .colorScheme
                                              .outline
                                              .withValues(alpha: 0.2),
                                    ),
                                  ),
                                  focusedBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(26),
                                    borderSide: BorderSide(
                                      color: cs.primary,
                                      width: 1.6,
                                    ),
                                  ),
                                  contentPadding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                    vertical: 14,
                                  ),
                                ),
                              ),
                            ),
                          ),
                          SliverPadding(
                            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                            sliver: SliverToBoxAdapter(
                              child: Text(
                                '${filtered.length} ${filtered.length == 1 ? 'member' : 'members'}',
                                style: Theme.of(context)
                                    .textTheme
                                    .labelLarge
                                    ?.copyWith(
                                      fontWeight: FontWeight.w700,
                                      color: light
                                          ? StudentSoftUi.mutedBrown(cs)
                                          : Theme.of(context)
                                              .colorScheme
                                              .onSurfaceVariant,
                                    ),
                              ),
                            ),
                          ),
                          if (filtered.isEmpty)
                            SliverFillRemaining(
                              hasScrollBody: false,
                              child: Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: Text(
                                    _rows.isEmpty
                                        ? 'No students in your classes yet.'
                                        : 'No matches. Try another search or filter.',
                                    textAlign: TextAlign.center,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(
                                          color: light
                                              ? StudentSoftUi.mutedBrown(cs)
                                              : Theme.of(context)
                                                  .colorScheme
                                                  .onSurfaceVariant,
                                        ),
                                  ),
                                ),
                              ),
                            )
                          else
                            SliverList(
                              delegate: SliverChildBuilderDelegate(
                                (context, i) {
                                  return Padding(
                                    padding: EdgeInsets.fromLTRB(
                                      16,
                                      0,
                                      16,
                                      i == filtered.length - 1 ? 28 : 12,
                                    ),
                                    child: JumpRevealOnScroll(
                                      scrollController: _scrollController,
                                      child: _studentCard(
                                        context,
                                        filtered[i],
                                      ),
                                    ),
                                  );
                                },
                                childCount: filtered.length,
                              ),
                            ),
                        ],
                      ),
                    ),
          Positioned(
            top: MediaQuery.paddingOf(context).top + 6,
            right: 12,
            child: Material(
              color: _fabLavender,
              elevation: 4,
              shadowColor: Colors.black26,
              shape: const CircleBorder(),
              child: InkWell(
                onTap: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Roster is managed by administrators. Pull down to refresh.',
                      ),
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
                customBorder: const CircleBorder(),
                child: const SizedBox(
                  width: 52,
                  height: 52,
                  child: Icon(Icons.add, color: Colors.white, size: 26),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
