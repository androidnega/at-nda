import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/app_selectable_scope.dart';
import '../widgets/modern_pull_to_refresh.dart';
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
    final classHint = _sharedClassLabel();

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
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loading ? null : _load,
          ),
        ],
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
                  onRefresh: _load,
                  child: ListView.separated(
                    physics: modernPullToRefreshPhysics,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    itemCount: _rows.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, i) {
                      final r = _rows[i];
                      final idx = _norm(r['index_number']?.toString());
                      final hasName = _hasDisplayName(r);
                      final displayName = _norm(r['name']?.toString());

                      final primaryLine = hasName ? displayName : (idx.isNotEmpty ? idx : '—');
                      final secondaryLine = hasName && idx.isNotEmpty ? idx : null;

                      return ListTile(
                        leading: CircleAvatar(
                          child: Text(
                            hasName && displayName.isNotEmpty
                                ? displayName[0].toUpperCase()
                                : (idx.isNotEmpty ? idx[0] : '?'),
                          ),
                        ),
                        title: Text(primaryLine),
                        subtitle: secondaryLine != null ? Text(secondaryLine) : null,
                      );
                    },
                  ),
                ),
    );
  }
}
