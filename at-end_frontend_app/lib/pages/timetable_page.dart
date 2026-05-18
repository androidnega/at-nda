import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/offline_service.dart';
import '../services/session_cache_prefs.dart';
import '../utils/api_user_message.dart';

/// Weekly schedule from `GET /api/timetable` (Sanctum Bearer).
class TimetablePage extends StatefulWidget {
  const TimetablePage({super.key});

  @override
  State<TimetablePage> createState() => _TimetablePageState();
}

class _TimetablePageState extends State<TimetablePage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _weekProgress;
  List<String> _orderedDays = [];
  final Map<String, List<Map<String, dynamic>>> _byDay = {};
  String _cacheIndexNumber = '';

  late DateTime _pickedDay;

  static const List<String> _calendarDayNames = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];

  static const List<String> _stripLabels = [
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
    'Sun',
  ];

  @override
  void initState() {
    super.initState();
    final n = DateTime.now();
    _pickedDay = DateTime(n.year, n.month, n.day);
    _fetch();
  }

  List<Map<String, dynamic>> _slotsForPickedDay() {
    final name = _calendarDayNames[_pickedDay.weekday - 1];
    final raw = List<Map<String, dynamic>>.from(_byDay[name] ?? []);
    raw.sort((a, b) {
      final sa = a['start_time']?.toString() ?? '';
      final sb = b['start_time']?.toString() ?? '';
      return sa.compareTo(sb);
    });
    return raw;
  }

  String _monthYearLong(DateTime d) {
    const m = [
      '',
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
    if (d.month < 1 || d.month > 12) return '${d.year}';
    return '${m[d.month]} ${d.year}';
  }

  String _weekdayLong(DateTime d) {
    return _calendarDayNames[d.weekday - 1];
  }

  Future<void> _pickMonthDay() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _pickedDay,
      firstDate: DateTime(now.year - 2),
      lastDate: DateTime(now.year + 3, 12, 31),
    );
    if (picked != null && mounted) {
      setState(() => _pickedDay = DateTime(picked.year, picked.month, picked.day));
    }
  }

  void _goToday() {
    final n = DateTime.now();
    setState(() => _pickedDay = DateTime(n.year, n.month, n.day));
  }

  void _applyTimetableRoot(Map<String, dynamic> root) {
    _orderedDays = [];
    _byDay.clear();
    final od = root['ordered_days'];
    if (od is List) {
      _orderedDays = od.map((e) => e.toString()).toList();
    }
    final by = root['by_day'];
    if (by is Map) {
      by.forEach((k, v) {
        if (v is! List) return;
        _byDay[k.toString()] = v
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      });
    }
    final wp = root['week_progress'];
    _weekProgress = wp is Map ? Map<String, dynamic>.from(wp) : null;
  }

  Future<bool> _loadCachedTimetable({
    String? infoMessage,
  }) async {
    if (_cacheIndexNumber.isEmpty) return false;
    final cached = await SessionCachePrefs.getTimetable(_cacheIndexNumber);
    if (cached == null) return false;
    _applyTimetableRoot(cached);
    if (!mounted) return true;
    setState(() {
      _loading = false;
      _error = infoMessage;
    });
    return true;
  }

  Future<void> _fetch() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final student = await OfflineService.getCurrentStudent();
      _cacheIndexNumber = student?.indexNumber.trim() ?? '';
    } catch (_) {
      _cacheIndexNumber = '';
    }
    try {
      final res = await ApiService.getTimetable();
      if (res.statusCode == 401) {
        final usedCache = await _loadCachedTimetable(
          infoMessage: 'Offline — showing last synced timetable.',
        );
        if (usedCache) return;
        setState(() {
          _loading = false;
          _error = 'Please sign in again.';
        });
        return;
      }
      if (!ApiService.isSuccessfulHttp(res.statusCode)) {
        final usedCache = await _loadCachedTimetable(
          infoMessage: 'Offline — showing last synced timetable.',
        );
        if (usedCache) return;
        final msg = ApiService.messageFromHttpResponse(res);
        setState(() {
          _loading = false;
          _error = msg.isEmpty ? 'Could not load your timetable.' : msg;
        });
        return;
      }
      final decoded = jsonDecode(res.body);
      if (decoded is! Map) {
        final usedCache = await _loadCachedTimetable(
          infoMessage: 'Offline — showing last synced timetable.',
        );
        if (usedCache) return;
        setState(() {
          _loading = false;
          _error = 'Could not read timetable data.';
        });
        return;
      }
      var root = Map<String, dynamic>.from(decoded);
      if (!root.containsKey('ordered_days') &&
          !root.containsKey('by_day') &&
          root['data'] is Map) {
        root = Map<String, dynamic>.from(root['data'] as Map);
      }
      _applyTimetableRoot(root);
      if (_cacheIndexNumber.isNotEmpty) {
        await SessionCachePrefs.saveTimetable(_cacheIndexNumber, root);
      }
      if (mounted) {
        setState(() {
          _loading = false;
          _error = null;
        });
      }
    } catch (e) {
      final usedCache = await _loadCachedTimetable(
        infoMessage: 'Offline — showing last synced timetable.',
      );
      if (usedCache) return;
      if (mounted) {
        setState(() {
          _loading = false;
          _error = sanitizeApiUserMessage(e.toString());
        });
      }
    }
  }

  /// Optional: lectures/credit left — compact line if API sends it.
  Widget? _weekProgressLine(TextTheme tt, ColorScheme cs) {
    final wp = _weekProgress;
    if (wp == null) return null;
    final rem = wp['lectures_remaining'];
    final ch = wp['credit_hours_remaining'];
    if (rem == null && ch == null) return null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        [
          if (rem != null) '$rem lectures left',
          if (ch != null) '$ch credit hrs left',
        ].join(' · '),
        style: tt.bodySmall?.copyWith(
          color: cs.onPrimary.withValues(alpha: 0.9),
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  List<DateTime?> _calendarGridDays(DateTime month) {
    final first = DateTime(month.year, month.month, 1);
    final leading = first.weekday - 1; // Monday = 0
    final daysInMonth = DateTime(month.year, month.month + 1, 0).day;
    final out = List<DateTime?>.filled(42, null);
    for (var i = 0; i < daysInMonth; i++) {
      out[leading + i] = DateTime(month.year, month.month, i + 1);
    }
    return out;
  }

  void _changeMonth(int delta) {
    final target = DateTime(_pickedDay.year, _pickedDay.month + delta, 1);
    final maxDay = DateTime(target.year, target.month + 1, 0).day;
    final safeDay = _pickedDay.day <= maxDay ? _pickedDay.day : maxDay;
    setState(() => _pickedDay = DateTime(target.year, target.month, safeDay));
  }

  bool _isSameDate(DateTime a, DateTime b) {
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }

  String _fmt24(String? t) {
    if (t == null || t.isEmpty) return '--:--';
    final p = t.split(':');
    final h = int.tryParse(p[0].trim()) ?? 0;
    final mBits = p.length > 1 ? p[1].trim().split(RegExp(r'[^\d]')) : <String>[];
    final m = mBits.isEmpty ? 0 : (int.tryParse(mBits.first) ?? 0);
    return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
  }

  Color _slotAccent(ColorScheme cs, int index) {
    final palette = <Color>[
      cs.primary,
      cs.tertiary,
      cs.secondary,
      cs.primary.withValues(alpha: 0.78),
      cs.secondary.withValues(alpha: 0.78),
    ];
    return palette[index % palette.length];
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;

    return Scaffold(
      backgroundColor: cs.surface,
      body: Column(
        children: [
          _buildHeader(context, cs, tt),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _fetch,
              color: cs.primary,
              child: _buildBody(context, tt),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, ColorScheme cs, TextTheme tt) {
    return SafeArea(
      bottom: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(4, 6, 12, 6),
        child: Row(
          children: [
            if (Navigator.canPop(context))
              IconButton(
                onPressed: () => Navigator.of(context).maybePop(),
                icon: Icon(
                  Icons.arrow_back_ios_new_rounded,
                  color: cs.onSurface,
                  size: 20,
                ),
              )
            else
              const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Timetable',
                textAlign: TextAlign.center,
                style: tt.titleLarge?.copyWith(
                  color: cs.onSurface,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            TextButton(
              onPressed: _goToday,
              style: TextButton.styleFrom(
                foregroundColor: cs.primary,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              ),
              child: const Text(
                'Today',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context, TextTheme tt) {
    final cs = Theme.of(context).colorScheme;
    if (_loading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(height: MediaQuery.sizeOf(context).height * 0.25),
          Center(child: CircularProgressIndicator(color: cs.primary)),
        ],
      );
    }
    if (_error != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          SizedBox(height: MediaQuery.sizeOf(context).height * 0.2),
          Icon(Icons.error_outline, size: 48, color: Theme.of(context).colorScheme.error),
          const SizedBox(height: 16),
          Text(_error!, textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ],
      );
    }
    if (_orderedDays.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          SizedBox(height: MediaQuery.sizeOf(context).height * 0.15),
          Icon(Icons.calendar_today_outlined, size: 52, color: Theme.of(context).colorScheme.outline),
          const SizedBox(height: 16),
          Text(
            'No timetable yet',
            textAlign: TextAlign.center,
            style: tt.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
            child: Text(
              'Your courses may not have days and times set yet, or you may need a class assigned.',
              textAlign: TextAlign.center,
              style: tt.bodyMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                height: 1.45,
              ),
            ),
          ),
        ],
      );
    }

    final slots = _slotsForPickedDay();
    final grid = _calendarGridDays(_pickedDay);
    final today = DateTime.now();
    final progress = _weekProgressLine(tt, cs);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
          decoration: BoxDecoration(
            color: cs.surfaceContainerLow,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.38)),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  IconButton(
                    icon: Icon(Icons.chevron_left_rounded, color: cs.onSurfaceVariant),
                    onPressed: () => _changeMonth(-1),
                    tooltip: 'Previous month',
                  ),
                  Expanded(
                    child: InkWell(
                      onTap: _pickMonthDay,
                      borderRadius: BorderRadius.circular(8),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Text(
                          _monthYearLong(_pickedDay),
                          textAlign: TextAlign.center,
                          style: tt.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: cs.onSurface,
                          ),
                        ),
                      ),
                    ),
                  ),
                  IconButton(
                    icon: Icon(Icons.chevron_right_rounded, color: cs.onSurfaceVariant),
                    onPressed: () => _changeMonth(1),
                    tooltip: 'Next month',
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: _stripLabels
                    .map(
                      (d) => Expanded(
                        child: Text(
                          d.substring(0, 1),
                          textAlign: TextAlign.center,
                          style: tt.labelMedium?.copyWith(
                            color: cs.onSurfaceVariant,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 6),
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: grid.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 7,
                  mainAxisSpacing: 6,
                  crossAxisSpacing: 6,
                  childAspectRatio: 1.1,
                ),
                itemBuilder: (context, i) {
                  final d = grid[i];
                  if (d == null) return const SizedBox.shrink();
                  final selected = _isSameDate(d, _pickedDay);
                  final isToday = _isSameDate(d, today);
                  return Material(
                    color: Colors.transparent,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(999),
                      onTap: () => setState(() => _pickedDay = d),
                      child: Container(
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: selected ? cs.primary : Colors.transparent,
                          border: isToday && !selected
                              ? Border.all(color: cs.primary, width: 1.4)
                              : null,
                        ),
                        child: Text(
                          '${d.day}',
                          style: tt.bodyMedium?.copyWith(
                            fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                            color: selected ? cs.onPrimary : cs.onSurface,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
        if (progress != null) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 2),
            decoration: BoxDecoration(
              color: cs.primaryContainer.withValues(alpha: 0.55),
              borderRadius: BorderRadius.circular(14),
            ),
            child: progress,
          ),
        ],
        const SizedBox(height: 14),
        Text(
          _weekdayLong(_pickedDay),
          style: tt.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
            color: cs.onSurface,
          ),
        ),
        const SizedBox(height: 10),
        if (slots.isEmpty)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: cs.surfaceContainerLow,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Text(
              'No classes scheduled for this day.',
              style: tt.bodyMedium?.copyWith(color: cs.onSurfaceVariant),
            ),
          )
        else
          Column(
            children: [
              for (var i = 0; i < slots.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: 58,
                        child: Padding(
                          padding: const EdgeInsets.only(top: 14),
                          child: Text(
                            _fmt24(slots[i]['start_time']?.toString()),
                            style: tt.labelLarge?.copyWith(
                              color: cs.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.fromLTRB(0, 12, 10, 12),
                          decoration: BoxDecoration(
                            color: cs.surfaceContainerLow,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: cs.outlineVariant.withValues(alpha: 0.28),
                            ),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                width: 4,
                                height: 48,
                                margin: const EdgeInsets.only(left: 8, right: 10),
                                decoration: BoxDecoration(
                                  color: _slotAccent(cs, i),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                              ),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      (slots[i]['class_name']?.toString().trim().isNotEmpty ==
                                              true)
                                          ? slots[i]['class_name'].toString()
                                          : (slots[i]['course_name']?.toString() ??
                                              'Class'),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: tt.titleSmall?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: cs.onSurface,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        Icon(
                                          Icons.access_time_rounded,
                                          size: 14,
                                          color: cs.onSurfaceVariant,
                                        ),
                                        const SizedBox(width: 6),
                                        Flexible(
                                          child: Text(
                                            '${_fmt24(slots[i]['start_time']?.toString())} - '
                                            '${_fmt24(slots[i]['end_time']?.toString())}',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: tt.bodySmall?.copyWith(
                                              color: cs.onSurfaceVariant,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    if ((slots[i]['venue']?.toString().trim().isNotEmpty ??
                                        false)) ...[
                                      const SizedBox(height: 2),
                                      Text(
                                        slots[i]['venue'].toString(),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: tt.bodySmall?.copyWith(
                                          color: cs.onSurfaceVariant,
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                              Icon(
                                Icons.more_vert_rounded,
                                size: 18,
                                color: cs.onSurfaceVariant,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
      ],
    );
  }
}
