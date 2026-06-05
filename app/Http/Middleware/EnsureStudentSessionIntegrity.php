<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use App\Services\StudentSessionGuardService;
use App\Support\DeviceFingerprint;
use Closure;
use Illuminate\Http\Request;

/**
 * Runs after the existing `student.auth` / `student.attendance` middleware.
 * Confirms the server-side session id is still bound to the same student
 * record + persistent device fingerprint. Blocks the request and logs an
 * audit event if anything looks like cross-account abuse.
 */
class EnsureStudentSessionIntegrity
{
    public function __construct(private StudentSessionGuardService $guard)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $studentId = (int) $request->session()->get('student_id');
        if ($studentId <= 0) {
            return $next($request);
        }

        // Ensure the long-lived fingerprint cookie is set on the response
        // (no-op when one already exists).
        DeviceFingerprint::ensure($request);

        $problem = $this->guard->verify($studentId, $request);
        if ($problem === null) {
            return $next($request);
        }

        AuditLogService::record(AuditLogService::SESSION_INTEGRITY_REVOKED, [
            'request' => $request,
            'payload' => [
                'reason' => $problem,
                'student_id' => $studentId,
                'session_id' => $request->session()->getId(),
            ],
        ]);

        // Tear down the offending session and bounce to the login page so
        // the rightful student can re-authenticate.
        $request->session()->forget(['student_id', 'student_index', 'pending_set_password_index']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = match ($problem) {
            'fraud_cross_student' => 'This browser is already signed in as a different student. Please sign in again.',
            'session_revoked' => 'Your session was ended on another device. Please sign in again.',
            default => 'Your session needs to be refreshed. Please sign in again.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => $problem,
                'message' => $message,
            ], 401);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
