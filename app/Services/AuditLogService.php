<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Support\DeviceFingerprint;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Write-once audit trail for security-sensitive events.
 * Never throws upward — a logging failure should never break the action.
 */
class AuditLogService
{
    /** Attendance session lifecycle. */
    public const SESSION_OPENED = 'session_opened';
    public const SESSION_REOPENED = 'session_reopened';
    public const SESSION_CLOSED = 'session_closed';
    public const SESSION_EXTENDED = 'session_extended';

    /** Per-student attendance changes. */
    public const MARK_CREATED = 'mark_created';
    public const MARK_MANUAL = 'mark_manual';
    public const MARK_DELETED = 'mark_deleted';
    public const WEEK_DELETED = 'week_deleted';

    /** Session integrity & fraud. */
    public const FRAUD_DETECTED = 'fraud_detected';
    public const SESSION_INTEGRITY_REVOKED = 'session_integrity_revoked';

    /** Auth. */
    public const STUDENT_LOGIN = 'student_login';
    public const STUDENT_LOGOUT = 'student_logout';

    /**
     * Record an event. Pass actor explicitly when called from a job /
     * scheduled context that has no `request()`.
     *
     * @param  array<string,mixed>  $context
     */
    public static function record(string $action, array $context = []): ?AuditLog
    {
        if (! SchemaFeatures::hasAuditLogs()) {
            return null;
        }

        $req = $context['request'] ?? (function_exists('request') ? request() : null);
        unset($context['request']);

        $actor = self::resolveActor($context, $req);

        $payload = [
            'actor_id' => $context['actor_id'] ?? $actor['id'],
            'actor_role' => $context['actor_role'] ?? $actor['role'],
            'actor_name' => mb_substr((string) ($context['actor_name'] ?? $actor['name']), 0, 190),
            'class_id' => $context['class_id'] ?? null,
            'course_id' => $context['course_id'] ?? null,
            'attendance_session_id' => $context['attendance_session_id'] ?? null,
            'action' => $action,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'ip' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 480) : null,
            'device_fingerprint' => $context['device_fingerprint']
                ?? ($req ? DeviceFingerprint::ensure($req) : DeviceFingerprint::current()),
            'payload' => self::sanitisePayload($context['payload'] ?? null),
        ];

        try {
            return AuditLog::create($payload);
        } catch (\Throwable $e) {
            Log::warning('audit_log write failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{id: ?int, role: ?string, name: ?string}
     */
    private static function resolveActor(array $context, ?Request $req): array
    {
        $session = $req?->hasSession() ? $req->session() : (function_exists('session') ? session() : null);

        if ($session) {
            if ($sid = $session->get('student_id')) {
                $s = Student::query()->find($sid);
                $role = $s?->isClassRep() ? 'rep' : 'student';
                $name = $s ? trim(implode(' ', array_filter([$s->first_name, $s->middle_name, $s->last_name]))) : null;

                return ['id' => (int) $sid, 'role' => $role, 'name' => $name];
            }
            if ($lid = $session->get('lecturer_id')) {
                $l = Lecturer::query()->find($lid);

                return ['id' => (int) $lid, 'role' => 'lecturer', 'name' => $l?->name];
            }
            if ($aid = $session->get('admin_id')) {
                $u = User::query()->find($aid);

                return ['id' => (int) $aid, 'role' => 'admin', 'name' => $u?->name];
            }
        }

        return ['id' => null, 'role' => null, 'name' => null];
    }

    private static function sanitisePayload(mixed $payload): ?array
    {
        if ($payload === null) {
            return null;
        }
        if (! is_array($payload)) {
            return ['value' => (string) $payload];
        }

        // Strip massive blobs and obviously sensitive keys.
        $blocked = ['password', 'password_confirmation', 'mail_password', 'token'];
        $clean = [];
        foreach ($payload as $k => $v) {
            if (in_array(strtolower((string) $k), $blocked, true)) {
                continue;
            }
            if (is_string($v) && mb_strlen($v) > 500) {
                $clean[$k] = mb_substr($v, 0, 500).'…';
            } else {
                $clean[$k] = $v;
            }
        }

        return $clean;
    }
}
