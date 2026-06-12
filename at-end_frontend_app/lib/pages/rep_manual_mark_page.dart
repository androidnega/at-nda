import 'dart:async';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/offline_sync_coordinator.dart';
import '../services/rep_mark_service.dart';
import '../utils/app_selectable_scope.dart';

/// Rep-side per-student marking for an active attendance session.
///
/// Workflow:
///   1. Page opens with [sessionId] (passed in from the rep home).
///   2. Pulls the roster via POST /api/class-rep/sessions/roster.
///   3. Each student is shown with their current mark status; tap
///      one to open a bottom sheet with Present / Late / Absent.
///   4. Submitting goes through [RepMarkService] which writes the
///      intent to the offline outbox first, then tries an inline
///      network attempt. If the network is down the row stays
///      queued and the user sees "Saved offline — will sync".
///   5. A small connectivity ribbon tells the user when queued
///      marks have synced (we poll the outbox + listen to the
///      sync coordinator so the badge updates without the rep
///      having to refresh).
class RepManualMarkPage extends StatefulWidget {
  const RepManualMarkPage({
    super.key,
    required this.sessionId,
    this.courseName,
    this.courseCode,
  });

  final int sessionId;
  final String? courseName;
  final String? courseCode;

  @override
  State<RepManualMarkPage> createState() => _RepManualMarkPageState();
}

class _RepManualMarkPageState extends State<RepManualMarkPage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _session;
  Map<String, int>? _counts;
  List<Map<String, dynamic>> _roster = const [];
  int _pendingInQueue = 0;
  // Marks the user has submitted in this page session, by student
  // id, so the row badge updates immediately (before the next
  // roster refresh).
  final Map<int, _OptimisticMark> _optimistic = {};

  RepCredentials? _credentials;
  final TextEditingController _searchController = TextEditingController();
  String _search = '';
  Timer? _queueWatchdog;

  @override
  void initState() {
    super.initState();
    _searchController.addListener(() {
      if (_search != _searchController.text) {
        setState(() => _search = _searchController.text);
      }
    });
    // Poll the outbox every 5 s so the "X queued" pill stays
    // honest while the user lingers on the page.
    _queueWatchdog = Timer.periodic(const Duration(seconds: 5), (_) {
      _refreshPendingCount();
    });
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  @override
  void dispose() {
    _queueWatchdog?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final creds = await RepCredentials.load();
    if (!mounted) return;
    if (creds == null) {
      setState(() {
        _loading = false;
        _error = 'Sign in online once before marking attendance.';
      });
      return;
    }
    _credentials = creds;
    await _loadRoster();
    await _refreshPendingCount();
  }

  Future<void> _refreshPendingCount() async {
    final n = await RepMarkService.pendingMarkCount();
    if (!mounted) return;
    if (n != _pendingInQueue) {
      setState(() => _pendingInQueue = n);
    }
  }

  Future<void> _loadRoster({bool silent = false}) async {
    final creds = _credentials;
    if (creds == null) return;
    if (!silent && !_loading) {
      setState(() => _loading = true);
    }
    try {
      final res = await ApiService.classRepSessionRoster(
        sessionId: widget.sessionId,
        indexNumber: creds.index,
        password: creds.password,
      );
      final data = res.body.envelopeData;
      if (data == null) {
        if (!mounted) return;
        setState(() {
          _loading = false;
          _error = res.statusCode >= 400
              ? 'Could not load roster (${res.statusCode}).'
              : 'Could not load roster.';
        });
        return;
      }
      final roster = (data['students'] as List? ?? const [])
          .whereType<Map>()
          .map((m) => Map<String, dynamic>.from(m))
          .toList();
      final counts = (data['counts'] as Map? ?? const {})
          .map((k, v) => MapEntry(k.toString(), (v is int) ? v : int.tryParse('$v') ?? 0));
      final session = data['session'] is Map
          ? Map<String, dynamic>.from(data['session'] as Map)
          : null;

      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = null;
        _roster = roster;
        _counts = counts;
        _session = session;
        // Once the server roster matches an optimistic mark, drop
        // the optimistic entry so we render the authoritative row.
        _optimistic.removeWhere((studentId, _) {
          final hit = roster.firstWhere(
            (r) => (r['id'] as int? ?? -1) == studentId,
            orElse: () => const <String, dynamic>{},
          );
          if (hit.isEmpty) return false;
          return hit['is_present'] == true;
        });
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Roster fetch failed: $e';
      });
    }
  }

  List<Map<String, dynamic>> get _filteredRoster {
    final q = _search.trim().toLowerCase();
    if (q.isEmpty) return _roster;
    return _roster.where((r) {
      final name = (r['full_name'] ?? '').toString().toLowerCase();
      final index = (r['index_number'] ?? '').toString().toLowerCase();
      return name.contains(q) || index.contains(q);
    }).toList();
  }

  Future<void> _confirmAndMark(Map<String, dynamic> row) async {
    final creds = _credentials;
    if (creds == null) return;
    final studentId = row['id'] as int?;
    final studentIndex = (row['index_number'] ?? '').toString();
    if (studentId == null || studentIndex.isEmpty) return;

    final status = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _MarkActionSheet(row: row),
    );
    if (status == null) return;
    if (!mounted) return;

    // Optimistic update — the SyncEngine will reconcile later.
    setState(() {
      _optimistic[studentId] = _OptimisticMark(
        status: status,
        submittedAt: DateTime.now(),
      );
    });

    final result = await RepMarkService.markStudent(
      sessionId: widget.sessionId,
      studentId: studentId,
      studentIndex: studentIndex,
      repIndex: creds.index,
      repPassword: creds.password,
      status: status,
      reason: 'Marked by rep on mobile',
    );

    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    Color bg;
    IconData icon;
    switch (result.kind) {
      case RepMarkResultKind.syncedNow:
        bg = const Color(0xFF047857);
        icon = Icons.check_circle_rounded;
        // Bump the local counts immediately.
        if (status == 'present' || status == 'late') {
          setState(() {
            _counts = {
              ..._counts ?? <String, int>{},
              'present': (_counts?['present'] ?? 0) + 1,
            };
          });
        }
        break;
      case RepMarkResultKind.alreadyMarked:
        bg = const Color(0xFF0369A1);
        icon = Icons.info_rounded;
        break;
      case RepMarkResultKind.queuedOffline:
        bg = const Color(0xFFB45309);
        icon = Icons.cloud_off_rounded;
        break;
      case RepMarkResultKind.rejected:
        bg = const Color(0xFFB91C1C);
        icon = Icons.error_rounded;
        // Drop the optimistic entry — the server didn't accept it.
        setState(() => _optimistic.remove(studentId));
        break;
    }
    messenger.showSnackBar(
      SnackBar(
        backgroundColor: bg,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(12, 0, 12, 84),
        content: Row(
          children: [
            Icon(icon, color: Colors.white, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                '${row['index_number']}: ${result.message}',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
        duration: const Duration(seconds: 3),
      ),
    );

    await _refreshPendingCount();
    if (result.kind == RepMarkResultKind.queuedOffline) {
      // Kick the coordinator one more time after a moment — gives
      // the connectivity layer a beat to settle.
      unawaited(
        OfflineSyncCoordinator.instance.requestSync(reason: 'rep_mark_followup'),
      );
    }
    // Best-effort refresh from the server so other reps' marks
    // show up too.
    unawaited(_loadRoster(silent: true));
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final session = _session;
    final headerCourse = session?['course_name']?.toString() ??
        widget.courseName ??
        'Active session';
    final headerCode = session?['course_code']?.toString() ?? widget.courseCode;
    final filtered = _filteredRoster;
    final counts = _counts ?? const {};

    return appSelectableScope(
      Scaffold(
        appBar: AppBar(
          title: const Text('Mark students'),
          elevation: 0,
          actions: [
            IconButton(
              tooltip: 'Refresh',
              onPressed: _loading ? null : () => _loadRoster(),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
        body: Column(
          children: [
            _HeaderBanner(
              courseName: headerCourse,
              courseCode: headerCode,
              presentCount: counts['present'] ?? 0,
              totalCount: counts['total'] ?? 0,
              pendingInQueue: _pendingInQueue,
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
              child: TextField(
                controller: _searchController,
                decoration: InputDecoration(
                  hintText: 'Search by index or name',
                  prefixIcon: const Icon(Icons.search_rounded),
                  isDense: true,
                  filled: true,
                  fillColor: cs.surfaceContainerHighest.withValues(alpha: 0.45),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: BorderSide.none,
                  ),
                ),
                textInputAction: TextInputAction.search,
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => _loadRoster(),
                child: _buildBody(filtered),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(List<Map<String, dynamic>> rows) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return ListView(
        children: [
          const SizedBox(height: 80),
          Center(
            child: Icon(Icons.cloud_off_rounded,
                size: 56, color: Colors.grey.shade400),
          ),
          const SizedBox(height: 12),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Text(
                _error ?? 'Something went wrong.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.black87),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Center(
            child: FilledButton(
              onPressed: () {
                setState(() => _error = null);
                _bootstrap();
              },
              child: const Text('Try again'),
            ),
          ),
        ],
      );
    }
    if (rows.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 120),
          Center(child: Text('No students in this roster.')),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
      itemCount: rows.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        final row = rows[i];
        final id = row['id'] as int?;
        final optimistic = id != null ? _optimistic[id] : null;
        return _RosterRow(
          row: row,
          optimistic: optimistic,
          onTap: () => _confirmAndMark(row),
        );
      },
    );
  }
}

class _OptimisticMark {
  _OptimisticMark({required this.status, required this.submittedAt});
  final String status;
  final DateTime submittedAt;
}

// ────────────────────────────────────────────────────────────────────
// Sub-widgets
// ────────────────────────────────────────────────────────────────────

class _HeaderBanner extends StatelessWidget {
  const _HeaderBanner({
    required this.courseName,
    required this.courseCode,
    required this.presentCount,
    required this.totalCount,
    required this.pendingInQueue,
  });

  final String courseName;
  final String? courseCode;
  final int presentCount;
  final int totalCount;
  final int pendingInQueue;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      color: cs.primary.withValues(alpha: 0.07),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.radio_button_checked, color: cs.primary, size: 18),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  courseName +
                      (courseCode != null && courseCode!.isNotEmpty
                          ? ' · $courseCode'
                          : ''),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              _Pill(
                icon: Icons.check_circle_outline,
                label: '$presentCount / $totalCount present',
                color: const Color(0xFF065F46),
                bg: const Color(0xFFD1FAE5),
              ),
              const SizedBox(width: 8),
              if (pendingInQueue > 0)
                _Pill(
                  icon: Icons.cloud_upload_outlined,
                  label: '$pendingInQueue queued',
                  color: const Color(0xFFB45309),
                  bg: const Color(0xFFFEF3C7),
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
    required this.icon,
    required this.label,
    required this.color,
    required this.bg,
  });

  final IconData icon;
  final String label;
  final Color color;
  final Color bg;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              color: color,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _RosterRow extends StatelessWidget {
  const _RosterRow({
    required this.row,
    required this.optimistic,
    required this.onTap,
  });

  final Map<String, dynamic> row;
  final _OptimisticMark? optimistic;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name = (row['full_name'] ?? '').toString();
    final index = (row['index_number'] ?? '').toString();
    final serverStatus = row['status']?.toString();
    final markedBy = row['marked_by']?.toString();

    final effectiveStatus = optimistic?.status ?? serverStatus;
    final isPresent = effectiveStatus == 'present' || effectiveStatus == 'late';

    Color badgeBg;
    Color badgeFg;
    String badgeText;
    IconData badgeIcon;

    if (optimistic != null) {
      badgeBg = const Color(0xFFFEF3C7);
      badgeFg = const Color(0xFFB45309);
      badgeText = 'Queued · ${_pretty(optimistic!.status)}';
      badgeIcon = Icons.cloud_upload_outlined;
    } else if (serverStatus == null) {
      badgeBg = const Color(0xFFF1F5F9);
      badgeFg = const Color(0xFF475569);
      badgeText = 'Unmarked';
      badgeIcon = Icons.radio_button_unchecked;
    } else if (serverStatus == 'present') {
      badgeBg = const Color(0xFFD1FAE5);
      badgeFg = const Color(0xFF065F46);
      badgeText = markedBy == 'self' ? 'Self · Present' : 'Present';
      badgeIcon = Icons.check_circle_rounded;
    } else if (serverStatus == 'late') {
      badgeBg = const Color(0xFFFFEDD5);
      badgeFg = const Color(0xFF9A3412);
      badgeText = 'Late';
      badgeIcon = Icons.schedule_rounded;
    } else {
      badgeBg = const Color(0xFFFEE2E2);
      badgeFg = const Color(0xFFB91C1C);
      badgeText = 'Absent';
      badgeIcon = Icons.cancel_rounded;
    }

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: isPresent && optimistic == null ? null : onTap,
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: const Color(0xFFE0E7FF),
                child: Text(
                  _initials(name, index),
                  style: const TextStyle(
                    color: Color(0xFF1E3A8A),
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name.isEmpty ? index : name,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF111827),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      index,
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF475569),
                        fontFamily: 'monospace',
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                decoration: BoxDecoration(
                  color: badgeBg,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(badgeIcon, color: badgeFg, size: 12),
                    const SizedBox(width: 5),
                    Text(
                      badgeText,
                      style: TextStyle(
                        fontSize: 10.5,
                        color: badgeFg,
                        fontWeight: FontWeight.w700,
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

  static String _initials(String name, String fallback) {
    final src = name.trim().isNotEmpty ? name : fallback;
    final parts = src.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts[1].substring(0, 1)).toUpperCase();
  }

  static String _pretty(String s) {
    if (s.isEmpty) return '—';
    return s[0].toUpperCase() + s.substring(1);
  }
}

class _MarkActionSheet extends StatelessWidget {
  const _MarkActionSheet({required this.row});
  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) {
    final name = (row['full_name'] ?? '').toString();
    final index = (row['index_number'] ?? '').toString();
    return SafeArea(
      child: Container(
        margin: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const Icon(Icons.person_outline_rounded,
                      color: Color(0xFF1E3A8A)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name.isEmpty ? index : name,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        Text(
                          index,
                          style: const TextStyle(
                            fontSize: 11,
                            color: Color(0xFF475569),
                            fontFamily: 'monospace',
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _SheetButton(
                color: const Color(0xFF047857),
                label: 'Mark present',
                icon: Icons.check_circle_rounded,
                onTap: () => Navigator.pop(context, 'present'),
              ),
              const SizedBox(height: 8),
              _SheetButton(
                color: const Color(0xFFC2410C),
                label: 'Mark late',
                icon: Icons.schedule_rounded,
                onTap: () => Navigator.pop(context, 'late'),
              ),
              const SizedBox(height: 8),
              _SheetButton(
                color: const Color(0xFFB91C1C),
                label: 'Mark absent',
                icon: Icons.cancel_rounded,
                onTap: () => Navigator.pop(context, 'absent'),
              ),
              const SizedBox(height: 6),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Cancel'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SheetButton extends StatelessWidget {
  const _SheetButton({
    required this.color,
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final Color color;
  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return FilledButton.icon(
      style: FilledButton.styleFrom(
        backgroundColor: color,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
        textStyle: const TextStyle(fontWeight: FontWeight.w700),
      ),
      onPressed: onTap,
      icon: Icon(icon, size: 18),
      label: Align(
        alignment: Alignment.centerLeft,
        child: Text(label),
      ),
    );
  }
}
