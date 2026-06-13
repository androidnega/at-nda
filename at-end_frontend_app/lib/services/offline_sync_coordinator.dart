import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/outbox_status.dart';
import '../utils/connectivity_util.dart';
import 'attendance_outbox_repository.dart';
import 'offline_service.dart';
import 'sync_engine.dart';
import 'sync_service.dart';

/// Centralised, page-independent driver for the attendance outbox.
///
/// The coordinator is the only place the app ties sync work to runtime
/// events:
///
///   * App lifecycle: when the app comes back to the foreground, kick a
///     drain after a small grace period.
///   * Connectivity: when the network flips, debounce 5 s (avoid Wi-Fi
///     flapping triggering N drains a minute) and then drain.
///   * Heartbeat: every 60 s while in foreground, refresh the status
///     counts so the UI can render fresh badges.
///   * Manual: pages and the submit service can call [requestSync] to
///     ask the coordinator to schedule a drain.
///
/// One coordinator per process — instantiate from `main.dart`.
class OfflineSyncCoordinator with WidgetsBindingObserver {
  OfflineSyncCoordinator._();

  static final OfflineSyncCoordinator instance = OfflineSyncCoordinator._();

  static const Duration connectivityDebounce = Duration(seconds: 5);
  static const Duration heartbeatInterval = Duration(seconds: 60);
  static const Duration minDrainGap = Duration(seconds: 4);

  /// Outbox retention window — `Synced` rows older than this are pruned.
  /// Matches POST_IMPLEMENTATION_ARCHITECTURE_AUDIT §P1.4 (30 days).
  static const Duration outboxRetention = Duration(days: 30);

  /// Minimum time between two VACUUMs. SQLite's VACUUM rewrites the
  /// whole file, which can be slow on big databases — we cap it at
  /// roughly once a week so it never runs on hot foreground sessions.
  static const Duration vacuumInterval = Duration(days: 7);

  /// SharedPreferences key for the last VACUUM timestamp. Used to
  /// honour [vacuumInterval] across cold launches.
  static const String _lastVacuumKey = 'attendance_outbox_last_vacuum_at';

  /// Guard so retention work runs at most once per app process.
  bool _retentionRanThisLaunch = false;

  bool _started = false;
  bool _draining = false;
  DateTime? _lastDrainAt;

  StreamSubscription<List<ConnectivityResult>>? _connSub;
  Timer? _debounceTimer;
  Timer? _heartbeatTimer;
  Timer? _backoffTimer;

  /// Broadcast stream of status-count maps for any UI that wants live
  /// badges. Keys are [OutboxStatus.wireValue].
  final ValueNotifier<Map<String, int>> statusCounts =
      ValueNotifier<Map<String, int>>({});

  /// Broadcast stream of the most recent drain summary, for the Sync
  /// Status page.
  final ValueNotifier<DrainSummary?> lastDrain =
      ValueNotifier<DrainSummary?>(null);

  /// Wire connectivity + lifecycle hooks. Idempotent.
  void start() {
    if (_started || kIsWeb) {
      _started = true;
      return;
    }
    _started = true;

    WidgetsBinding.instance.addObserver(this);

    try {
      _connSub = Connectivity()
          .onConnectivityChanged
          .listen(_onConnectivityChanged);
    } catch (_) {
      // connectivity_plus can fail on rare devices — coordinator still
      // works on app-resume + manual requestSync, just without the
      // automatic restore hook.
    }

    _heartbeatTimer = Timer.periodic(heartbeatInterval, (_) {
      _refreshStatusCounts();
    });

    // First boot — refresh counts and try a drain (covers a previous
    // crash leaving rows in `syncing` state).
    Future.microtask(() async {
      await _refreshStatusCounts();
      await requestSync(reason: 'startup', delay: const Duration(milliseconds: 800));
      // Retention is deferred to AFTER the first drain so the UI is
      // fully painted and any in-flight network work is done first.
      // VACUUM is a synchronous SQLite operation; running it later
      // keeps the cold-launch fast.
      unawaited(_runRetentionMaintenance());
    });
  }

  /// One-shot per process maintenance: prune Synced rows older than
  /// [outboxRetention], then run VACUUM if more than [vacuumInterval]
  /// has passed since the last run.
  ///
  /// Failed / Quarantined / Pending rows are NEVER touched — see
  /// AttendanceOutboxRepository.pruneSyncedOlderThan.
  ///
  /// Errors are swallowed; retention failures must never break the
  /// app surface. Logged with debugPrint only in profile/debug builds.
  Future<void> _runRetentionMaintenance() async {
    if (_retentionRanThisLaunch || kIsWeb) return;
    _retentionRanThisLaunch = true;

    // Delay so first paint and the first drain are not competing for
    // the SQLite write lock.
    await Future<void>.delayed(const Duration(seconds: 6));

    int pruned = 0;
    try {
      pruned = await AttendanceOutboxRepository.pruneSyncedOlderThan(
        olderThan: outboxRetention,
      );
    } catch (e) {
      if (kDebugMode) {
        debugPrint('OfflineSyncCoordinator: prune failed: $e');
      }
    }

    // VACUUM only when (a) we actually freed space, OR (b) the weekly
    // cadence has elapsed. The first condition keeps the file lean
    // after a big prune; the second guarantees forward progress even
    // when nothing was pruned this run.
    final shouldVacuum = pruned > 0 || await _vacuumIsDue();
    if (!shouldVacuum) return;

    try {
      final ok = await AttendanceOutboxRepository.vacuum();
      if (ok) {
        await _stampVacuumNow();
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('OfflineSyncCoordinator: vacuum failed: $e');
      }
    }
  }

  Future<bool> _vacuumIsDue() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final iso = prefs.getString(_lastVacuumKey);
      if (iso == null || iso.isEmpty) {
        return true;
      }
      final last = DateTime.tryParse(iso);
      if (last == null) return true;
      return DateTime.now().toUtc().difference(last.toUtc()) >= vacuumInterval;
    } catch (_) {
      return false;
    }
  }

  Future<void> _stampVacuumNow() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _lastVacuumKey,
        DateTime.now().toUtc().toIso8601String(),
      );
    } catch (_) {
      // ignore — best-effort persistence
    }
  }

  /// Tear down. Call from a test harness or when the user signs out.
  Future<void> stop() async {
    _started = false;
    WidgetsBinding.instance.removeObserver(this);
    await _connSub?.cancel();
    _connSub = null;
    _debounceTimer?.cancel();
    _debounceTimer = null;
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    _backoffTimer?.cancel();
    _backoffTimer = null;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      requestSync(
        reason: 'app_resume',
        delay: const Duration(milliseconds: 600),
      );
    }
  }

  void _onConnectivityChanged(List<ConnectivityResult> results) {
    final usable = results.any(
      (r) =>
          r == ConnectivityResult.wifi ||
          r == ConnectivityResult.mobile ||
          r == ConnectivityResult.ethernet ||
          r == ConnectivityResult.vpn ||
          r == ConnectivityResult.other,
    );
    if (!usable) return;

    // Debounce — collapses Wi-Fi flap / hand-off events.
    _debounceTimer?.cancel();
    _debounceTimer = Timer(connectivityDebounce, () {
      requestSync(reason: 'connectivity_restored');
    });
  }

  /// Ask the coordinator to drain the outbox. Honours:
  ///   * the per-process re-entrancy lock,
  ///   * a small minimum gap between drains so a flurry of `requestSync`
  ///     calls collapses to a single attempt.
  Future<void> requestSync({
    required String reason,
    Duration delay = Duration.zero,
  }) async {
    if (delay > Duration.zero) {
      await Future<void>.delayed(delay);
    }
    final now = DateTime.now();
    if (_lastDrainAt != null && now.difference(_lastDrainAt!) < minDrainGap) {
      // Last drain still warm — let it finish first.
      return;
    }
    if (_draining) return;

    final online = await hasInternetConnectivity();
    if (!online) {
      await _refreshStatusCounts();
      return;
    }

    _draining = true;
    _lastDrainAt = now;
    final summary = DrainSummary(reason: reason, startedAt: now);
    try {
      // 1. Drain new-style outbox via the batch engine.
      final outboxMoved = await SyncEngine.drain();
      summary.outboxTransitions = outboxMoved;

      // 2. Drain legacy queue (`attendance` table) for older rows the
      //    user marked before the upgrade. Safe to keep running until
      //    that table is provably empty across the user base.
      try {
        final legacyMoved = await SyncService.syncAttendance();
        summary.legacyTransitions = legacyMoved;
      } catch (_) {
        summary.legacyTransitions = 0;
      }
    } catch (e) {
      summary.error = e.toString();
    } finally {
      summary.completedAt = DateTime.now();
      lastDrain.value = summary;
      _draining = false;
      await _refreshStatusCounts();
    }
  }

  Future<void> _refreshStatusCounts() async {
    if (kIsWeb) return;
    try {
      final student = await OfflineService.getCurrentStudent();
      final counts = await AttendanceOutboxRepository.statusCounts(
        studentIndex: student?.indexNumber,
      );
      statusCounts.value = counts;
    } catch (_) {
      // ignore — status counts are non-critical
    }
  }
}

/// Snapshot of a single drain, surfaced to the UI via [OfflineSyncCoordinator.lastDrain].
class DrainSummary {
  DrainSummary({
    required this.reason,
    required this.startedAt,
  });

  final String reason;
  final DateTime startedAt;
  DateTime? completedAt;
  int outboxTransitions = 0;
  int legacyTransitions = 0;
  String? error;

  bool get isSuccess => error == null;

  int get totalTransitions => outboxTransitions + legacyTransitions;
}
