<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceDeviceLog;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\SchemaFeatures;
use Illuminate\Support\Facades\Log;

/**
 * Non-blocking fraud scoring for online attendance.
 *
 * Called from OnlineAttendanceController AFTER the attendance row has
 * been persisted, so a failure here NEVER prevents an attendance from
 * being recorded — it just leaves the row unscored. The admin
 * "Suspicious Attendance" panel filters on risk_level so a missing
 * score simply means the row is never surfaced for review.
 *
 * Rules (per spec):
 *
 *   Rule 1  Same fingerprint used by multiple students in same session.   +50  HIGH
 *   Rule 2  More than N students share the IP in the same session.        +15  MEDIUM
 *   Rule 3  Fingerprint linked to >= M student accounts historically.     +40  HIGH
 *   Rule 4  Student frequently changes fingerprints.                      +10  LOW
 *
 * Risk LEVEL is derived from the SUM via thresholds in
 * config('attendance.risk_threshold_medium' / 'risk_threshold_high').
 */
class AttendanceRiskService
{
    /**
     * Score the just-created attendance row and persist risk metadata.
     * Silently no-ops when the schema doesn't have the risk columns or
     * the device log table.
     */
    public function score(
        Attendance $attendance,
        Student $student,
        AttendanceSession $session,
        ?AttendanceDeviceLog $log
    ): void {
        if (! SchemaFeatures::hasAttendancesRiskColumns()) {
            return;
        }

        try {
            $reasons = [];
            $score = 0;

            // Rules 1 / 2 / 3 / 4 — short-circuit when the device log
            // table isn't present (we'd have no fingerprint/IP to score).
            if (SchemaFeatures::hasAttendanceDeviceLogs() && $log !== null) {
                $score += $this->ruleSharedFingerprintInSession($log, $session, $student, $reasons);
                $score += $this->ruleSharedIpInSession($log, $session, $student, $reasons);
                $score += $this->ruleFingerprintAcrossManyAccounts($log, $student, $reasons);
                $score += $this->ruleFrequentDeviceChange($log, $student, $reasons);
            }

            $score = max(0, min(200, $score));
            $level = $this->levelFromScore($score);

            $attendance->risk_score   = $score;
            $attendance->risk_level   = $level;
            $attendance->risk_reasons = $reasons === [] ? null : array_values($reasons);
            $attendance->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('[risk] scoring failed (non-blocking)', [
                'attendance_id' => $attendance->id ?? null,
                'student_id'    => $student->id,
                'session_id'    => $session->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the actual accounts behind each rule that fired for
     * [$attendance]. Used by the admin "Suspicious Attendance"
     * panel so a reviewer can see WHO the flag is referring to
     * (instead of just "shared with 1 other account").
     *
     * Returns a structured payload keyed by rule:
     *
     *   [
     *     'shared_fingerprint_session' => [
     *       'message'       => 'Same browser fingerprint marked attendance in this session.',
     *       'count'         => 1,
     *       'fingerprint'   => 'aa11..ee99',
     *       'accounts'      => [['id','index_number','full_name','class_name','marked_at'], ...],
     *     ],
     *     'shared_ip_session' => [
     *       'message'       => 'Same network address used by multiple students in this session.',
     *       'count'         => 4,
     *       'ip'            => '197.255.1.10',
     *       'threshold'     => 3,
     *       'accounts'      => [...],
     *     ],
     *     'fingerprint_history' => [
     *       'message'       => 'This device has been seen on N accounts historically.',
     *       'count'         => 10,
     *       'fingerprint'   => 'aa11..ee99',
     *       'accounts'      => [...],  // capped to 10
     *     ],
     *     'frequent_device_change' => [
     *       'message'       => 'Student used N distinct devices in last 30 days.',
     *       'count'         => 5,
     *       'lookback_days' => 30,
     *       'fingerprints'  => ['aa11..', 'bb22..', ...],
     *     ],
     *   ]
     *
     * Only includes keys for rules that actually fired (i.e. they
     * would have contributed to the score). Empty array when none
     * applied — e.g. on a row with no device log at all.
     *
     * Lazy-evaluated: do not call this on every row in a long
     * report (run it for the visible page only).
     *
     * @return array<string, array<string, mixed>>
     */
    public function relatedAccountsFor(Attendance $attendance): array
    {
        if (! SchemaFeatures::hasAttendanceDeviceLogs()) {
            return [];
        }
        $sessionId = (int) ($attendance->attendance_session_id ?? 0);
        $studentId = (int) ($attendance->student_id ?? 0);
        if ($sessionId <= 0 || $studentId <= 0) {
            return [];
        }

        // Pick the device log this attendance was scored against:
        // the latest row for (session, student) — the OnlineAttendance
        // flow only writes one per submit, so this is unique in
        // practice. We still order to be safe.
        $log = AttendanceDeviceLog::query()
            ->where('session_id', $sessionId)
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if (! $log) {
            return [];
        }

        $out = [];
        $fp = trim((string) ($log->fingerprint_hash ?? ''));
        $ip = trim((string) ($log->ip_address ?? ''));

        // Rule 1: same fingerprint, this session, different students.
        if ($fp !== '') {
            $rows = AttendanceDeviceLog::query()
                ->with(['student.schoolClass'])
                ->where('session_id', $sessionId)
                ->where('fingerprint_hash', $fp)
                ->where('student_id', '!=', $studentId)
                ->orderBy('created_at')
                ->get();
            // Collapse to one row per student id (a student could in
            // theory have multiple log rows in the same session if
            // they reloaded; we only want each account once).
            $byStudent = [];
            foreach ($rows as $r) {
                $sid = (int) $r->student_id;
                if (! isset($byStudent[$sid])) {
                    $byStudent[$sid] = $r;
                }
            }
            if ($byStudent !== []) {
                $accounts = [];
                foreach ($byStudent as $r) {
                    $accounts[] = self::accountSnapshot($r);
                }
                $out['shared_fingerprint_session'] = [
                    'message' => 'Same browser fingerprint marked attendance in this session.',
                    'count' => count($accounts),
                    'fingerprint' => self::shortFingerprint($fp),
                    'accounts' => $accounts,
                ];
            }
        }

        // Rule 2: same IP, this session, more than N distinct students.
        if ($ip !== '') {
            $rows = AttendanceDeviceLog::query()
                ->with(['student.schoolClass'])
                ->where('session_id', $sessionId)
                ->where('ip_address', $ip)
                ->orderBy('created_at')
                ->get();
            $byStudent = [];
            foreach ($rows as $r) {
                $sid = (int) $r->student_id;
                if (! isset($byStudent[$sid])) {
                    $byStudent[$sid] = $r;
                }
            }
            $threshold = (int) config('attendance.risk_ip_distinct_students_threshold', 3);
            // Rule 2 only fires when the distinct count exceeds the
            // threshold. We still show every account on the IP so the
            // reviewer can confirm the cluster.
            if (count($byStudent) > $threshold) {
                $accounts = [];
                foreach ($byStudent as $r) {
                    $accounts[] = self::accountSnapshot($r);
                }
                $out['shared_ip_session'] = [
                    'message' => 'Same network address used by multiple students in this session.',
                    'count' => count($accounts),
                    'ip' => $ip,
                    'threshold' => $threshold,
                    'accounts' => $accounts,
                ];
            }
        }

        // Rule 3: this fingerprint touched many accounts across history.
        if ($fp !== '') {
            $threshold = (int) config('attendance.risk_fingerprint_distinct_accounts_threshold', 10);
            $distinct = AttendanceDeviceLog::query()
                ->where('fingerprint_hash', $fp)
                ->distinct('student_id')
                ->count('student_id');
            if ($distinct >= $threshold) {
                // Pull the most-recent log row per student (capped).
                $rows = AttendanceDeviceLog::query()
                    ->with(['student.schoolClass'])
                    ->where('fingerprint_hash', $fp)
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();
                $byStudent = [];
                foreach ($rows as $r) {
                    $sid = (int) $r->student_id;
                    if (! isset($byStudent[$sid])) {
                        $byStudent[$sid] = $r;
                    }
                    if (count($byStudent) >= 10) {
                        break;
                    }
                }
                $accounts = [];
                foreach ($byStudent as $r) {
                    $accounts[] = self::accountSnapshot($r);
                }
                $out['fingerprint_history'] = [
                    'message' => 'This device has been seen on multiple accounts historically.',
                    'count' => $distinct,
                    'fingerprint' => self::shortFingerprint($fp),
                    'accounts' => $accounts,
                    'truncated' => $distinct > count($accounts),
                ];
            }
        }

        // Rule 4: this student switched devices a lot recently.
        $switchThreshold = (int) config('attendance.risk_student_device_switch_threshold', 4);
        $lookback = (int) config('attendance.risk_student_device_switch_lookback_days', 30);
        $fingerprints = AttendanceDeviceLog::query()
            ->where('student_id', $studentId)
            ->where('created_at', '>=', now()->subDays($lookback))
            ->whereNotNull('fingerprint_hash')
            ->orderByDesc('created_at')
            ->pluck('fingerprint_hash')
            ->unique()
            ->values()
            ->all();
        if (count($fingerprints) >= $switchThreshold) {
            $out['frequent_device_change'] = [
                'message' => 'Student used several different devices recently.',
                'count' => count($fingerprints),
                'lookback_days' => $lookback,
                'fingerprints' => array_slice(
                    array_map([self::class, 'shortFingerprint'], $fingerprints),
                    0,
                    6,
                ),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function accountSnapshot(AttendanceDeviceLog $log): array
    {
        $student = $log->student;

        return [
            'id' => $student?->id ? (int) $student->id : null,
            'index_number' => (string) ($student?->index_number ?? '—'),
            'full_name' => trim((string) (
                $student?->getDisplayName()
                ?? trim(implode(' ', array_filter([
                    $student?->first_name,
                    $student?->middle_name,
                    $student?->last_name,
                ])))
            )) ?: null,
            'class_name' => $student?->schoolClass?->name,
            'ip' => $log->ip_address,
            'marked_at' => optional($log->created_at)->toIso8601String(),
        ];
    }

    private static function shortFingerprint(string $fp): string
    {
        $fp = trim($fp);
        if ($fp === '') {
            return '—';
        }
        if (strlen($fp) <= 12) {
            return $fp;
        }

        return substr($fp, 0, 6).'…'.substr($fp, -4);
    }

    /**
     * Map a 0..200 score to a level. Defaults: <25 low, 25..49 medium, >=50 high.
     * Returns null when score is 0 (nothing to surface).
     */
    public function levelFromScore(int $score): ?string
    {
        if ($score <= 0) {
            return null;
        }
        $high   = (int) config('attendance.risk_threshold_high', 50);
        $medium = (int) config('attendance.risk_threshold_medium', 25);
        if ($score >= $high) {
            return 'high';
        }
        if ($score >= $medium) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Rule 1: same fingerprint used by other students in THIS session.
     *
     * @param  array<int,string>  $reasons
     */
    private function ruleSharedFingerprintInSession(
        AttendanceDeviceLog $log,
        AttendanceSession $session,
        Student $student,
        array &$reasons
    ): int {
        $fp = (string) ($log->fingerprint_hash ?? '');
        if ($fp === '') {
            return 0;
        }

        $otherCount = AttendanceDeviceLog::query()
            ->where('session_id', $session->id)
            ->where('fingerprint_hash', $fp)
            ->where('student_id', '!=', $student->id)
            ->distinct('student_id')
            ->count('student_id');

        if ($otherCount > 0) {
            $reasons[] = sprintf('Shared fingerprint with %d other account(s) in this session', $otherCount);

            return (int) config('attendance.risk_score_shared_fingerprint_session', 50);
        }

        return 0;
    }

    /**
     * Rule 2: same IP used by more than N distinct students in THIS session.
     *
     * @param  array<int,string>  $reasons
     */
    private function ruleSharedIpInSession(
        AttendanceDeviceLog $log,
        AttendanceSession $session,
        Student $student,
        array &$reasons
    ): int {
        $ip = (string) ($log->ip_address ?? '');
        if ($ip === '') {
            return 0;
        }

        $distinct = AttendanceDeviceLog::query()
            ->where('session_id', $session->id)
            ->where('ip_address', $ip)
            ->distinct('student_id')
            ->count('student_id');

        $threshold = (int) config('attendance.risk_ip_distinct_students_threshold', 3);
        if ($distinct > $threshold) {
            $reasons[] = sprintf('Shared IP (%s) with %d students in this session', $ip, $distinct);

            return (int) config('attendance.risk_score_shared_ip_session', 15);
        }

        return 0;
    }

    /**
     * Rule 3: fingerprint linked to a high number of distinct student accounts
     * across the entire history.
     *
     * @param  array<int,string>  $reasons
     */
    private function ruleFingerprintAcrossManyAccounts(
        AttendanceDeviceLog $log,
        Student $student,
        array &$reasons
    ): int {
        $fp = (string) ($log->fingerprint_hash ?? '');
        if ($fp === '') {
            return 0;
        }

        $distinct = AttendanceDeviceLog::query()
            ->where('fingerprint_hash', $fp)
            ->distinct('student_id')
            ->count('student_id');

        $threshold = (int) config('attendance.risk_fingerprint_distinct_accounts_threshold', 10);
        if ($distinct >= $threshold) {
            $reasons[] = sprintf('Device used by %d different accounts historically', $distinct);

            return (int) config('attendance.risk_score_fingerprint_many_accounts', 40);
        }

        return 0;
    }

    /**
     * Rule 4: student has used many distinct fingerprints recently — a soft
     * signal worth surfacing but not strongly weighted.
     *
     * @param  array<int,string>  $reasons
     */
    private function ruleFrequentDeviceChange(
        AttendanceDeviceLog $log,
        Student $student,
        array &$reasons
    ): int {
        $threshold = (int) config('attendance.risk_student_device_switch_threshold', 4);
        $lookback  = (int) config('attendance.risk_student_device_switch_lookback_days', 30);

        $distinct = AttendanceDeviceLog::query()
            ->where('student_id', $student->id)
            ->where('created_at', '>=', now()->subDays($lookback))
            ->whereNotNull('fingerprint_hash')
            ->distinct('fingerprint_hash')
            ->count('fingerprint_hash');

        if ($distinct >= $threshold) {
            $reasons[] = sprintf('%d distinct devices in last %d days', $distinct, $lookback);

            return (int) config('attendance.risk_score_frequent_device_changes', 10);
        }

        return 0;
    }
}
