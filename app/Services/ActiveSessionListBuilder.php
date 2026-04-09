<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\FlutterSessionFormatter;
use Illuminate\Support\Facades\Log;

/**
 * Shared session rows for legacy and v1 active-session endpoints (same session JSON shape).
 */
class ActiveSessionListBuilder
{
    public static function isUsableActiveSession(?AttendanceSession $session): bool
    {
        if (! $session || ! $session->isValid()) {
            return false;
        }

        $session->loadMissing(['course', 'venue', 'lecturer']);

        return $session->course !== null;
    }

    public static function alreadyMarkedForSession(?AttendanceSession $session, ?Student $student): bool
    {
        if (! $session || ! $student) {
            return false;
        }

        return Attendance::where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->whereDate('created_at', now())
            ->exists();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AttendanceSession>|\Illuminate\Database\Eloquent\Collection<int, AttendanceSession>  $sessions
     * @return list<array<string, mixed>>
     */
    public static function buildRows($sessions, ?Student $student): array
    {
        $list = [];
        foreach ($sessions as $session) {
            if (! self::isUsableActiveSession($session)) {
                continue;
            }
            try {
                $row = FlutterSessionFormatter::format($session);
                $mine = null;
                if ($student) {
                    $mine = Attendance::query()
                        ->where('student_id', $student->id)
                        ->where('attendance_session_id', $session->id)
                        ->first();
                }
                $row['already_marked'] = $mine !== null;
                $row['my_status'] = $mine?->status;
                $row['check_in_time'] = $mine?->check_in_time?->toIso8601String();
                $row['check_out_time'] = $mine?->check_out_time?->toIso8601String();
                $row['time_spent_seconds'] = $mine?->time_spent_seconds;
                $row['can_check_out'] = $session->isCheckInCheckoutMode()
                    && $session->checkout_enabled
                    && $mine !== null
                    && $mine->check_out_time === null;
                $list[] = $row;
            } catch (\Throwable $e) {
                Log::error('Flutter session row failed', [
                    'session_id' => $session->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $list;
    }
}
