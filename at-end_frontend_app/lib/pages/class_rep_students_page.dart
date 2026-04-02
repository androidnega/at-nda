import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../utils/app_selectable_scope.dart';
import 'login_page.dart';

/// Roster from `POST /api/class-rep/students` (server-enforced class rep only).
class ClassRepStudentsPage extends StatefulWidget {
  const ClassRepStudentsPage({super.key});

  @override
  State<ClassRepStudentsPage> createState() => _ClassRepStudentsPageState();
}

class _ClassRepStudentsPageState extends State<ClassRepStudentsPage> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _rows = [];

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
    return Scaffold(
      appBar: AppBar(
        title: const Text('Class roster'),
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
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    itemCount: _rows.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, i) {
                      final r = _rows[i];
                      final name = r['name']?.toString() ?? '—';
                      final idx = r['index_number']?.toString() ?? '';
                      final cls = r['class_name']?.toString();
                      return ListTile(
                        leading: CircleAvatar(
                          child: Text(
                            name.isNotEmpty ? name[0].toUpperCase() : '?',
                          ),
                        ),
                        title: Text(name),
                        subtitle: Text(
                          [idx, if (cls != null && cls.isNotEmpty) cls]
                              .join(' · '),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
