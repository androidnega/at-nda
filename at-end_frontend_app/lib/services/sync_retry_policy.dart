import 'dart:math';

/// Exponential back-off schedule for the attendance outbox.
///
/// Matches the architecture review (`SECOND_PASS_ARCHITECTURE_REVIEW`):
///   attempt 1 → immediate
///   attempt 2 → 350 ms
///   attempt 3 → 5 s
///   attempt 4 → 30 s
///   attempt 5 → 2 min
///   attempt 6 → 10 min
///   attempt 7 → 30 min
///   attempt 8 → quarantined (no more automatic retries)
///
/// Random jitter of ±20 % is layered on top so a fleet of phones coming
/// back online together don't all knock on the API at the same instant.
class SyncRetryPolicy {
  static const List<Duration> _schedule = [
    Duration.zero,                  // attempt 1 — used at submit time
    Duration(milliseconds: 350),    // attempt 2
    Duration(seconds: 5),           // attempt 3
    Duration(seconds: 30),          // attempt 4
    Duration(minutes: 2),           // attempt 5
    Duration(minutes: 10),          // attempt 6
    Duration(minutes: 30),          // attempt 7
  ];

  static const int quarantineAttempt = 8;

  static final Random _rng = Random();

  /// Returns the delay AFTER [attemptCount] failed attempts before the
  /// next automatic attempt should fire. Returns null when the row should
  /// be quarantined.
  static Duration? nextDelayAfter(int attemptCount) {
    if (attemptCount <= 0) return Duration.zero;
    if (attemptCount >= quarantineAttempt - 1) {
      // attempt N already happened; the (N+1)th would be the
      // quarantine attempt — bail out.
      return null;
    }
    // _schedule[i] is the delay BEFORE attempt (i + 1). After
    // attemptCount failures we want the gap before attempt
    // (attemptCount + 1).
    final base = attemptCount < _schedule.length
        ? _schedule[attemptCount]
        : _schedule.last;
    return _withJitter(base);
  }

  /// Computes the next absolute `next_attempt_after` timestamp.
  /// Returns null when the row should be quarantined (no more retries).
  static DateTime? nextAttemptAfter(int attemptCount, {DateTime? now}) {
    final delay = nextDelayAfter(attemptCount);
    if (delay == null) return null;
    final base = now ?? DateTime.now().toUtc();
    return base.add(delay);
  }

  /// True when the row has hit the quarantine ceiling.
  static bool shouldQuarantine(int attemptCount) {
    return attemptCount >= quarantineAttempt - 1;
  }

  static Duration _withJitter(Duration base) {
    if (base <= const Duration(milliseconds: 100)) return base;
    final spreadMs = (base.inMilliseconds * 0.2).round();
    final delta = _rng.nextInt(spreadMs * 2 + 1) - spreadMs;
    final adjusted = base.inMilliseconds + delta;
    return Duration(milliseconds: adjusted < 0 ? 0 : adjusted);
  }
}
