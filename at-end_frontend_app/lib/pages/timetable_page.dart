import 'dart:convert';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
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

  /// Monday 00:00 of the ISO week containing [day].
  DateTime _mondayOfWeek(DateTime day) {
    final d = DateTime(day.year, day.month, day.day);
    return d.subtract(Duration(days: d.weekday - 1));
  }

  List<DateTime> _weekStripDates() {
    final mon = _mondayOfWeek(_pickedDay);
    return List.generate(7, (i) => mon.add(Duration(days: i)));
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

  Future<void> _fetch() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiService.getTimetable();
      if (res.statusCode == 401) {
        setState(() {
          _loading = false;
          _error = 'Please sign in again.';
        });
        return;
      }
      if (!ApiService.isSuccessfulHttp(res.statusCode)) {
        final msg = ApiService.messageFromHttpResponse(res);
        setState(() {
          _loading = false;
          _error = msg.isEmpty ? 'Could not load your timetable.' : msg;
        });
        return;
      }
      final decoded = jsonDecode(res.body);
      if (decoded is! Map) {
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
      if (mounted) {
        setState(() => _loading = false);
      }
    } catch (e) {
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

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
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
    final strip = _weekStripDates();
    final progress = _weekProgressLine(tt, cs);

    final onHeader = cs.onPrimary;

    return Material(
      color: cs.primary,
      elevation: 0,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(4, 4, 12, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  if (Navigator.canPop(context))
                    IconButton(
                      onPressed: () => Navigator.of(context).maybePop(),
                      icon: Icon(Icons.arrow_back_ios_new_rounded, color: onHeader, size: 20),
                    )
                  else
                    const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Timetable',
                      textAlign: TextAlign.center,
                      style: tt.titleLarge?.copyWith(
                        color: onHeader,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.2,
                      ),
                    ),
                  ),
                  const SizedBox(width: 48),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${_pickedDay.day}',
                    style: tt.displaySmall?.copyWith(
                      color: onHeader,
                      fontWeight: FontWeight.w800,
                      height: 1,
                      fontSize: 44,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: _pickMonthDay,
                      borderRadius: BorderRadius.circular(8),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _weekdayLong(_pickedDay),
                              style: tt.titleMedium?.copyWith(
                                color: onHeader,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Row(
                              children: [
                                Flexible(
                                  child: Text(
                                    _monthYearLong(_pickedDay),
                                    style: tt.bodyMedium?.copyWith(
                                      color: onHeader.withValues(alpha: 0.92),
                                    ),
                                  ),
                                ),
                                Icon(
                                  Icons.keyboard_arrow_down_rounded,
                                  color: onHeader.withValues(alpha: 0.9),
                                  size: 22,
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  TextButton(
                    onPressed: _goToday,
                    style: TextButton.styleFrom(
                      backgroundColor: cs.primaryContainer,
                      foregroundColor: cs.onPrimaryContainer,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: const Text('Today', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ],
              ),
              if (progress != null) progress,
              const SizedBox(height: 12),
              SizedBox(
                height: 72,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  itemCount: strip.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemBuilder: (context, i) {
                    final d = strip[i];
                    final sel = d.year == _pickedDay.year &&
                        d.month == _pickedDay.month &&
                        d.day == _pickedDay.day;
                    return Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: () => setState(() => _pickedDay = d),
                        borderRadius: BorderRadius.circular(14),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 180),
                          width: 52,
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          decoration: BoxDecoration(
                            color: sel ? cs.primaryContainer : cs.surface,
                            borderRadius: BorderRadius.circular(14),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.08),
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                _stripLabels[i],
                                style: tt.labelSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: sel ? cs.onPrimaryContainer : cs.onSurfaceVariant,
                                  fontSize: 11,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${d.day}',
                                style: tt.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: sel ? cs.onPrimaryContainer : cs.onSurface,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context, TextTheme tt) {
    if (_loading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(height: MediaQuery.sizeOf(context).height * 0.25),
          Center(child: CircularProgressIndicator(color: Theme.of(context).colorScheme.primary)),
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
    if (slots.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 32),
          Text(
            'No classes on ${_weekdayLong(_pickedDay)}',
            textAlign: TextAlign.center,
            style: tt.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          Text(
            'Nothing scheduled for this weekday in your timetable.',
            textAlign: TextAlign.center,
            style: tt.bodyMedium?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      itemCount: slots.length,
      itemBuilder: (context, index) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: _VisitStyleSlotCard(
            slot: slots[index],
            pickedDay: _pickedDay,
            slotIndex: index,
            allSlots: slots,
          ),
        );
      },
    );
  }
}

class _VisitStyleSlotCard extends StatelessWidget {
  const _VisitStyleSlotCard({
    required this.slot,
    required this.pickedDay,
    required this.slotIndex,
    required this.allSlots,
  });

  final Map<String, dynamic> slot;
  final DateTime pickedDay;
  final int slotIndex;
  final List<Map<String, dynamic>> allSlots;

  static String _fmt24(String? t) {
    if (t == null || t.isEmpty) return '—';
    final p = t.split(':');
    final h = int.tryParse(p[0].trim()) ?? 0;
    final minuteBits = p.length > 1 ? p[1].trim().split(RegExp(r'[^\d]')) : <String>[];
    final m = minuteBits.isEmpty ? 0 : (int.tryParse(minuteBits.first) ?? 0);
    return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
  }

  static ({int h, int m})? _parse(String? t) {
    if (t == null || t.isEmpty) return null;
    final p = t.split(':');
    final h = int.tryParse(p[0].trim());
    if (h == null) return null;
    final minuteBits = p.length > 1 ? p[1].trim().split(RegExp(r'[^\d]')) : <String>[];
    final m = minuteBits.isEmpty ? 0 : (int.tryParse(minuteBits.first) ?? 0);
    return (h: h, m: m);
  }

  /// Primary = main action for this day (nearest upcoming / in session); others muted.
  bool _primaryAction() {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final pd = DateTime(pickedDay.year, pickedDay.month, pickedDay.day);
    if (pd != today) return true;

    int? firstActive;
    for (var i = 0; i < allSlots.length; i++) {
      final st = _parse(allSlots[i]['start_time']?.toString());
      final en = _parse(allSlots[i]['end_time']?.toString());
      if (st == null || en == null) continue;
      final end = DateTime(now.year, now.month, now.day, en.h, en.m);
      if (now.isBefore(end)) {
        firstActive = i;
        break;
      }
    }
    if (firstActive == null) return false;
    return slotIndex == firstActive;
  }

  String _statusLine() {
    final now = DateTime.now();
    final pd = DateTime(pickedDay.year, pickedDay.month, pickedDay.day);
    final today = DateTime(now.year, now.month, now.day);
    if (pd != today) return 'Scheduled';

    final st = _parse(slot['start_time']?.toString());
    final en = _parse(slot['end_time']?.toString());
    if (st == null || en == null) return 'Scheduled';

    final start = DateTime(now.year, now.month, now.day, st.h, st.m);
    final end = DateTime(now.year, now.month, now.day, en.h, en.m);
    if (now.isBefore(start)) return 'Not started yet';
    if (now.isAfter(end)) return 'Completed';
    return 'In session';
  }

  Color _statusColor(ColorScheme cs) {
    final s = _statusLine();
    if (s == 'Not started yet') return cs.error;
    if (s == 'In session') return cs.tertiary;
    if (s == 'Completed') return cs.outline;
    return cs.onSurfaceVariant;
  }

  @override
  Widget build(BuildContext context) {
    final tt = Theme.of(context).textTheme;
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final name = slot['class_name']?.toString().trim().isNotEmpty == true
        ? slot['class_name']!.toString()
        : (slot['course_name']?.toString() ?? 'Class');
    final venue = slot['venue']?.toString() ?? '—';
    final start = slot['start_time']?.toString() ?? '';
    final end = slot['end_time']?.toString() ?? '';
    final timeRange = '${_fmt24(start)} - ${_fmt24(end)}';
    final dateLine = _dateLine(pickedDay);
    final primary = _primaryAction();

    return Container(
      decoration: BoxDecoration(
        color: isDark ? Theme.of(context).colorScheme.surfaceContainerHigh : Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: isDark
            ? null
            : [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.06),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
      ),
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            name,
            style: tt.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
              color: isDark ? Theme.of(context).colorScheme.onSurface : const Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 12),
          _row('Date', dateLine, tt, isDark),
          _row('Venue', venue, tt, isDark),
          _row('Status', _statusLine(), tt, isDark, valueColor: _statusColor(cs), strong: true),
          _row('Class time', timeRange, tt, isDark),
          const SizedBox(height: 14),
          Align(
            alignment: Alignment.centerRight,
            child: FilledButton(
              onPressed: () {
                final code = slot['course_code']?.toString() ?? '';
                final lec = slot['lecturer_name']?.toString() ?? '';
                showModalBottomSheet<void>(
                  context: context,
                  showDragHandle: true,
                  builder: (ctx) => Padding(
                    padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(name, style: tt.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                        if (code.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text(code, style: tt.titleSmall?.copyWith(color: Theme.of(ctx).colorScheme.primary)),
                        ],
                        const SizedBox(height: 12),
                        Text('Lecturer: $lec', style: tt.bodyLarge),
                        const SizedBox(height: 8),
                        Text(timeRange, style: tt.bodyMedium?.copyWith(color: Theme.of(ctx).colorScheme.onSurfaceVariant)),
                      ],
                    ),
                  ),
                );
              },
              style: FilledButton.styleFrom(
                backgroundColor: primary ? cs.primary : cs.surfaceContainerHighest,
                foregroundColor: primary ? cs.onPrimary : cs.onSurfaceVariant,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: const Text('Visit', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }

  String _dateLine(DateTime d) {
    const names = [
      '',
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${d.day} ${names[d.month]} ${d.year}';
  }

  Widget _row(
    String label,
    String value,
    TextTheme tt,
    bool isDark, {
    Color? valueColor,
    bool strong = false,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: RichText(
        text: TextSpan(
          style: tt.bodyMedium?.copyWith(
            color: isDark ? Colors.white70 : const Color(0xFF424242),
            height: 1.35,
          ),
          children: [
            TextSpan(text: '$label: ', style: const TextStyle(fontWeight: FontWeight.w600)),
            TextSpan(
              text: value,
              style: TextStyle(
                fontWeight: strong ? FontWeight.w700 : FontWeight.w500,
                color: valueColor ?? (isDark ? Colors.white : const Color(0xFF424242)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
