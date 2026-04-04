import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/app_selectable_scope.dart';
import '../widgets/modern_pull_to_refresh.dart';
import '../widgets/profile_avatar.dart';
import 'login_page.dart';

/// Class list from `POST /api/class-rep/students` (server-enforced class rep only).
class ClassRepStudentsPage extends StatefulWidget {
  const ClassRepStudentsPage({super.key});

  @override
  State<ClassRepStudentsPage> createState() => _ClassRepStudentsPageState();
}

class _ClassRepStudentsPageState extends State<ClassRepStudentsPage> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _rows = [];

  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  /// `null` = all classes; otherwise exact class name from API.
  String? _classFilter;

  static String _norm(String? s) => (s ?? '').trim();

  /// Meaningful name (not empty, not em dash, not identical to index).
  bool _hasDisplayName(Map<String, dynamic> r) {
    final n = _norm(r['name']?.toString());
    if (n.isEmpty || n == '—') return false;
    final idx = _norm(r['index_number']?.toString());
    if (idx.isNotEmpty && n == idx) return false;
    return true;
  }

  /// If every row shares the same class, show once in the header (not per row).
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
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

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    final classHint = _sharedClassLabel();
    final classes = _distinctClassNames();
    final filtered = _filteredRows;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Class list'),
            if (classHint != null)
              Text(
                classHint,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: cs.onSurfaceVariant,
                      fontWeight: FontWeight.w500,
                    ),
              ),
          ],
        ),
      ),
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
                          style: TextStyle(color: cs.error),
                        ),
                        const SizedBox(height: 16),
                        if (_error!.contains('Sign in'))
                          FilledButton(
                            onPressed: () => Navigator.of(context)
                                .pushReplacement(
                              MaterialPageRoute<void>(
                                builder: (_) =>
                                    appSelectableScope(const LoginPage()),
                              ),
                            ),
                            child: const Text('Go to login'),
                          ),
                      ],
                    ),
                  ),
                )
              : ModernPullToRefresh(
                  onRefresh: () => _load(silent: true),
                  child: CustomScrollView(
                    physics: modernPullToRefreshPhysics,
                    slivers: [
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                        sliver: SliverToBoxAdapter(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              TextField(
                                controller: _searchController,
                                decoration: InputDecoration(
                                  hintText: 'Search by name or index',
                                  prefixIcon: const Icon(Icons.search),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  isDense: true,
                                  contentPadding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 10,
                                  ),
                                ),
                                onChanged: (v) =>
                                    setState(() => _searchQuery = v),
                              ),
                              if (classes.length > 1) ...[
                                const SizedBox(height: 10),
                                InputDecorator(
                                  decoration: InputDecoration(
                                    labelText: 'Class',
                                    border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    isDense: true,
                                    contentPadding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 4,
                                    ),
                                  ),
                                  child: DropdownButtonHideUnderline(
                                    child: DropdownButton<String?>(
                                      value: _classFilter,
                                      isExpanded: true,
                                      hint: const Text('All classes'),
                                      items: [
                                        const DropdownMenuItem<String?>(
                                          value: null,
                                          child: Text('All classes'),
                                        ),
                                        for (final c in classes)
                                          DropdownMenuItem<String?>(
                                            value: c,
                                            child: Text(
                                              c,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                      ],
                                      onChanged: (v) =>
                                          setState(() => _classFilter = v),
                                    ),
                                  ),
                                ),
                              ],
                            ],
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
                                    : 'No matches. Try a different search or filter.',
                                textAlign: TextAlign.center,
                                style: tt.bodyMedium?.copyWith(
                                  color: cs.onSurfaceVariant,
                                ),
                              ),
                            ),
                          ),
                        )
                      else
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(12, 0, 12, 24),
                          sliver: SliverList(
                            delegate: SliverChildBuilderDelegate(
                              (context, i) {
                                final r = filtered[i];
                                final idx =
                                    _norm(r['index_number']?.toString());
                                final hasName = _hasDisplayName(r);
                                final displayName =
                                    _norm(r['name']?.toString());

                                final primaryLine = hasName
                                    ? displayName
                                    : (idx.isNotEmpty ? idx : '—');
                                final secondaryLine =
                                    hasName && idx.isNotEmpty ? idx : null;
                                final className =
                                    _norm(r['class_name']?.toString());

                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 8),
                                  child: Material(
                                    color: cs.surfaceContainerHighest
                                        .withValues(alpha: 0.45),
                                    borderRadius: BorderRadius.circular(14),
                                    clipBehavior: Clip.antiAlias,
                                    child: Padding(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 12,
                                          vertical: 10,
                                        ),
                                        child: Row(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.center,
                                          children: [
                                            ProfileAvatar(
                                              key: ValueKey(
                                                '${idx}_${r['id']}_${r['profile_image']}_${r['profile_picture']}',
                                              ),
                                              student: _rowToStudent(r),
                                              radius: 26,
                                            ),
                                            const SizedBox(width: 14),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    primaryLine,
                                                    style: tt.titleSmall
                                                        ?.copyWith(
                                                      fontWeight:
                                                          FontWeight.w600,
                                                    ),
                                                    maxLines: 1,
                                                    overflow:
                                                        TextOverflow.ellipsis,
                                                  ),
                                                  if (secondaryLine != null)
                                                    Text(
                                                      secondaryLine,
                                                      style: tt.bodySmall
                                                          ?.copyWith(
                                                        color: cs
                                                            .onSurfaceVariant,
                                                      ),
                                                    ),
                                                  if (className.isNotEmpty &&
                                                      classHint == null)
                                                    Padding(
                                                      padding:
                                                          const EdgeInsets.only(
                                                              top: 2),
                                                      child: Text(
                                                        className,
                                                        style: tt.labelSmall
                                                            ?.copyWith(
                                                          color: cs
                                                              .onSurfaceVariant,
                                                        ),
                                                        maxLines: 1,
                                                        overflow: TextOverflow
                                                            .ellipsis,
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
                              },
                              childCount: filtered.length,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
