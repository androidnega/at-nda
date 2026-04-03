import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../theme/dashboard_surfaces.dart';
import '../utils/constants.dart';

class AttendanceRecordsPage extends StatefulWidget {
  const AttendanceRecordsPage({super.key});

  @override
  State<AttendanceRecordsPage> createState() => _AttendanceRecordsPageState();
}

class _AttendanceRecordsPageState extends State<AttendanceRecordsPage> {
  final _sessionCtrl = TextEditingController();
  bool _loading = false;
  String? _error;
  List<Map<String, dynamic>> _records = [];

  @override
  void dispose() {
    _sessionCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadRecords() async {
    final sessionId = int.tryParse(_sessionCtrl.text.trim());
    if (sessionId == null || sessionId <= 0) {
      setState(() => _error = 'Enter a valid session ID.');
      return;
    }

    final student = await OfflineService.getCurrentStudent();
    final password = await OfflineService.getApiSessionPassword();
    if (student == null) {
      setState(() => _error = 'Please log in again.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _records = [];
    });

    try {
      final uri = Uri.parse(
        '${Constants.baseUrl}/attendance/$sessionId/records',
      ).replace(queryParameters: {
        if (password != null && password.isNotEmpty) 'index_number': student.indexNumber,
        if (password != null && password.isNotEmpty) 'password': password,
      });

      final res = await http.get(uri, headers: ApiService.requestHeaders());
      final raw = jsonDecode(res.body);
      if (res.statusCode >= 200 &&
          res.statusCode < 300 &&
          raw is Map &&
          raw['success'] == true &&
          raw['data'] is Map) {
        final data = Map<String, dynamic>.from(raw['data'] as Map);
        final rows = <Map<String, dynamic>>[];
        final list = data['records'];
        if (list is List) {
          for (final item in list) {
            if (item is Map) rows.add(Map<String, dynamic>.from(item));
          }
        }
        if (!mounted) return;
        setState(() {
          _records = rows;
          _loading = false;
        });
      } else {
        final msg = raw is Map && raw['message'] != null
            ? raw['message'].toString()
            : 'Could not load attendance records.';
        if (!mounted) return;
        setState(() {
          _loading = false;
          _error = msg;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Error: $e';
      });
    }
  }

  Future<void> _downloadAndShare(String format) async {
    final sessionId = int.tryParse(_sessionCtrl.text.trim());
    if (sessionId == null || sessionId <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a valid session ID first.')),
      );
      return;
    }
    final student = await OfflineService.getCurrentStudent();
    final password = await OfflineService.getApiSessionPassword();
    if (student == null) return;
    if (!mounted) return;

    setState(() => _loading = true);
    try {
      final uri = Uri.parse(
        '${Constants.baseUrl}/attendance/$sessionId/export/$format',
      ).replace(queryParameters: {
        if (password != null && password.isNotEmpty) 'index_number': student.indexNumber,
        if (password != null && password.isNotEmpty) 'password': password,
      });
      final res = await http.get(uri, headers: ApiService.requestHeaders());
      if (!mounted) return;
      if (res.statusCode < 200 || res.statusCode >= 300) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Export failed (${res.statusCode}).')),
        );
        return;
      }

      final dir = await getTemporaryDirectory();
      final ext = format == 'excel' ? 'xlsx' : format;
      final file = File('${dir.path}/attendance_session_$sessionId.$ext');
      await file.writeAsBytes(res.bodyBytes, flush: true);

      if (!mounted) return;
      await Share.shareXFiles([XFile(file.path)]);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Export error: $e')),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showExportSheet() {
    if (_records.isEmpty) return;
    final cs = Theme.of(context).colorScheme;
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 0, 8, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  'Share attendance',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              ListTile(
                leading: Icon(Icons.table_chart_outlined, color: cs.primary),
                title: const Text('Export CSV'),
                onTap: () {
                  Navigator.pop(ctx);
                  _downloadAndShare('csv');
                },
              ),
              ListTile(
                leading: Icon(Icons.grid_on_outlined, color: cs.primary),
                title: const Text('Export Excel'),
                onTap: () {
                  Navigator.pop(ctx);
                  _downloadAndShare('excel');
                },
              ),
              ListTile(
                leading: Icon(Icons.picture_as_pdf_outlined, color: cs.primary),
                title: const Text('Export PDF'),
                onTap: () {
                  Navigator.pop(ctx);
                  _downloadAndShare('pdf');
                },
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
    final hasData = _records.isNotEmpty;

    return Scaffold(
      appBar: AppBar(title: const Text('Attendance records')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: DashboardSurfaces.cardDecoration(context),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _sessionCtrl,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Session ID',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton(
                          onPressed: _loading ? null : _loadRecords,
                          child: const Text('Load records'),
                        ),
                      ),
                      if (hasData) ...[
                        const SizedBox(width: 10),
                        IconButton.filledTonal(
                          tooltip: 'Share or export',
                          onPressed: _loading ? null : _showExportSheet,
                          icon: const Icon(Icons.ios_share_rounded),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(
                _error!,
                style: TextStyle(color: cs.error),
              ),
            ],
            const SizedBox(height: 12),
            if (_loading) const Center(child: CircularProgressIndicator()),
            if (!_loading)
              Expanded(
                child: hasData
                    ? ListView.separated(
                        itemCount: _records.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, i) {
                          final row = _records[i];
                          return Container(
                            padding: const EdgeInsets.all(12),
                            decoration: DashboardSurfaces.cardDecoration(context, radius: 12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  row['name']?.toString() ?? '—',
                                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                        fontWeight: FontWeight.w700,
                                      ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  row['index_number']?.toString() ?? '',
                                  style: Theme.of(context).textTheme.bodyMedium,
                                ),
                                Text(
                                  row['marked_at']?.toString() ?? '',
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                        color: cs.onSurfaceVariant,
                                      ),
                                ),
                              ],
                            ),
                          );
                        },
                      )
                    : Center(
                        child: Text(
                          'Load a session to see attendance rows.',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: cs.onSurfaceVariant,
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
