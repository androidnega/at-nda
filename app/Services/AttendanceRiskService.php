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
