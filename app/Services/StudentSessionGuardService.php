<?php

namespace App\Services;

use App\Models\StudentActiveSession;
use App\Support\DeviceFingerprint;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Tracks which student is currently logged into a given browser session
 * and detects cross-account misuse on the same persistent device
 * fingerprint, so a student can't switch identities mid-attendance.
 */
class StudentSessionGuardService
{
    /**
     * Mark this server-side session as belonging to the given student and
     * revoke any older sessions for the same fingerprint.
     *
     * Returns true on success; false when the underlying table doesn't
     * exist yet (older deploys).
     */
    public function startSession(int $studentId, Request $request): bool
    {
        if (! SchemaFeatures::hasStudentActiveSessions()) {
            return false;
        }

        $fingerprint = DeviceFingerprint::ensure($request);
        $sessionId = $request->session()->getId();

        // Revoke older sessions tied to the same fingerprint that point at
        // a *different* student. They can't keep their old browser tab
        // hot and silently mark for another student.
        StudentActiveSession::query()
            ->where('device_fingerprint', $fingerprint)
            ->where('student_id', '!=', $studentId)
            ->where('is_active', true)
            ->update(['is_active' => false, 'revoked_at' => now()]);

        // Upsert the row for this exact session id.
        StudentActiveSession::query()->updateOrCreate(
            ['session_id' => $sessionId],
            [
                'student_id' => $studentId,
                'device_fingerprint' => $fingerprint,
                'ip' => (string) $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
                'is_active' => true,
                'last_active_at' => now(),
                'revoked_at' => null,
            ]
        );

        return true;
    }

    /**
     * Validate the current session matches an active record and that the
     * fingerprint hasn't been hijacked for a different student. Returns
     * a string error code on a problem ("session_revoked", "fraud_cross_student",
     * "stale_fingerprint") or null when everything is fine.
     */
    public function verify(int $studentId, Request $request): ?string
    {
        if (! SchemaFeatures::hasStudentActiveSessions()) {
            return null;
        }

        $fingerprint = DeviceFingerprint::ensure($request);
        $sessionId = $request->session()->getId();

        $row = StudentActiveSession::query()
            ->where('session_id', $sessionId)
            ->first();

        // If we have no record yet (e.g. user logged in before this code
        // shipped), lazily start one. Don't reject the request.
        if (! $row) {
            $this->startSession($studentId, $request);

            return null;
        }

        if ((int) $row->student_id !== $studentId) {
            // Server session id is reused for a different student id —
            // someone is poking at the cookie. Block immediately.
            return 'fraud_cross_student';
        }

        if (! $row->is_active) {
            return 'session_revoked';
        }

        // Another *active* row exists on this fingerprint for a different
        // student id. That means a second tab / private window logged in
        // as someone else after we marked this one active. Block this one
        // until they re-authenticate.
        $hijacked = StudentActiveSession::query()
            ->where('device_fingerprint', $fingerprint)
            ->where('student_id', '!=', $studentId)
            ->where('is_active', true)
            ->where('created_at', '>=', $row->created_at)
            ->exists();
        if ($hijacked) {
            $row->update(['is_active' => false, 'revoked_at' => now()]);

            return 'fraud_cross_student';
        }

        $row->update(['last_active_at' => now()]);

        return null;
    }

    public function revoke(?int $studentId, Request $request): void
    {
        if (! SchemaFeatures::hasStudentActiveSessions()) {
            return;
        }

        $sessionId = $request->session()->getId();
        try {
            StudentActiveSession::query()
                ->where('session_id', $sessionId)
                ->update(['is_active' => false, 'revoked_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('StudentSessionGuard revoke failed: '.$e->getMessage());
        }
    }
}
