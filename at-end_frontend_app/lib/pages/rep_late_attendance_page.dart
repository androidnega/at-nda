import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../services/api_service.dart';
import '../utils/api_user_message.dart';
import '../utils/constants.dart';

/// Class rep / lecturer review queue for offline attendance that arrived
/// late. Backs Phase 5 of the architecture review.
///
/// Each row corresponds to a `attendance_late_unrecorded` record on the
/// server. Approving inserts a real `attendances` row; denying just
/// stamps the record so the student's mobile outbox can transition
/// Quarantined → Rejected on next status poll.
class RepLateAttendancePage extends StatefulWidget {
  const RepLateAttendancePage({super.key});

  static const String routeName = '/rep/late-attendance';

  @override
  State<RepLateAttendancePage> createState() => _RepLateAttendancePageState();
}

class _RepLateAttendancePageState extends State<RepLateAttendancePage> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = const [];
  bool _includeDecided = false;

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
    try {
      final uri = Uri.parse(
        '${Constants.baseUrl}/attendance/late?include_decided=${_includeDecided ? 1 : 0}',
      );
      final res = await http
          .get(uri, headers: ApiService.requestHeaders())
          .timeout(const Duration(seconds: 20));
      if (!ApiService.isSuccessfulHttp(res.statusCode)) {
        setState(() {
          _loading = false;
          _error = sanitizeApiUserMessage(
            ApiService.messageFromHttpResponse(res),
            fallback: 'Could not load late attendance records.',
          );
        });
        return;
      }
      final body = jsonDecode(res.body);
      // Server wraps responses in {ok: true, data: {…}} via ApiEnvelope.
      final data = body is Map<String, dynamic>
          ? (body['data'] is Map<String, dynamic>
              ? body['data'] as Map<String, dynamic>
              : body)
          : <String, dynamic>{};
      final items = data['items'];
      final list = items is List
          ? items
              .whereType<Map<String, dynamic>>()
              .map(Map<String, dynamic>.from)
              .toList()
          : <Map<String, dynamic>>[];
      setState(() {
        _loading = false;
        _items = list;
      });
    } catch (e) {
      setState(() {
        _loading = false;
        _error = 'Could not load: $e';
      });
    }
  }

  Future<void> _decide(int id, String action) async {
    try {
      final res = await http
          .post(
            Uri.parse('${Constants.baseUrl}/attendance/late/$id/$action'),
            headers: ApiService.requestHeaders(jsonBody: true),
            body: jsonEncode(const <String, dynamic>{}),
          )
          .timeout(const Duration(seconds: 20));
      if (!mounted) return;
      if (ApiService.isSuccessfulHttp(res.statusCode)) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              action == 'approve'
                  ? 'Approved. Attendance recorded.'
                  : 'Denied.',
            ),
          ),
        );
        _load();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              sanitizeApiUserMessage(
                ApiService.messageFromHttpResponse(res),
                fallback: 'Request failed.',
              ),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Request failed: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Late attendance review'),
        actions: [
          IconButton(
            tooltip: _includeDecided ? 'Hide decided' : 'Show decided',
            icon: Icon(
              _includeDecided ? Icons.history_toggle_off : Icons.history,
            ),
            onPressed: () {
              setState(() => _includeDecided = !_includeDecided);
              _load();
            },
          ),
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: _load,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return ListView(
        children: [
          const SizedBox(height: 80),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Text(
                _error!,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ),
          ),
        ],
      );
    }
    if (_items.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 120),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Column(
                children: [
                  Icon(
                    Icons.inbox_outlined,
                    size: 56,
                    color: Theme.of(context)
                        .colorScheme
                        .primary
                        .withValues(alpha: 0.5),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'No late attendance to review',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'When an offline submission arrives after the session '
                    'has ended, it shows up here for your decision.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: _items.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, i) => _LateCard(
        item: _items[i],
        onApprove: () => _decide(_items[i]['id'] as int, 'approve'),
        onDeny: () => _decide(_items[i]['id'] as int, 'deny'),
      ),
    );
  }
}

class _LateCard extends StatelessWidget {
  const _LateCard({
    required this.item,
    required this.onApprove,
    required this.onDeny,
  });

  final Map<String, dynamic> item;
  final VoidCallback onApprove;
  final VoidCallback onDeny;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final decision = (item['decision'] ?? 'pending').toString();
    final reason = (item['reason'] ?? '').toString();
    final firstName = (item['first_name'] ?? '').toString();
    final lastName = (item['last_name'] ?? '').toString();
    final indexNumber = (item['index_number'] ?? '').toString();
    final courseCode = (item['course_code'] ?? '').toString();
    final courseName = (item['course_name'] ?? '').toString();
    final capturedAt = (item['captured_at'] ?? '').toString();

    final isPending = decision == 'pending';

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: cs.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${firstName.isEmpty && lastName.isEmpty ? indexNumber : '$firstName $lastName'.trim()} · $indexNumber',
                    style: Theme.of(context)
                        .textTheme
                        .titleSmall
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: _badgeBg(cs, decision),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    decision.toUpperCase(),
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: _badgeFg(cs, decision),
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.4,
                        ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              [
                if (courseCode.isNotEmpty) courseCode,
                if (courseName.isNotEmpty) courseName,
              ].join(' — '),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 6),
            Text(
              [
                if (reason.isNotEmpty) 'Reason: $reason',
                if (capturedAt.isNotEmpty) 'Captured: $capturedAt',
              ].join(' · '),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                  ),
            ),
            if (isPending) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  OutlinedButton.icon(
                    onPressed: onDeny,
                    icon: const Icon(Icons.close),
                    label: const Text('Deny'),
                  ),
                  const SizedBox(width: 8),
                  FilledButton.icon(
                    onPressed: onApprove,
                    icon: const Icon(Icons.check),
                    label: const Text('Approve'),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Color _badgeBg(ColorScheme cs, String decision) {
    switch (decision) {
      case 'approved':
        return cs.primary.withValues(alpha: 0.13);
      case 'denied':
        return cs.error.withValues(alpha: 0.13);
      default:
        return cs.tertiary.withValues(alpha: 0.15);
    }
  }

  Color _badgeFg(ColorScheme cs, String decision) {
    switch (decision) {
      case 'approved':
        return cs.primary;
      case 'denied':
        return cs.error;
      default:
        return cs.tertiary;
    }
  }
}
