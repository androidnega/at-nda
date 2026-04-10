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

    /**
     * Student checked in but has not checked out yet (check-in/check-out mode).
     */
    public static function isPendingCheckoutForStudent(?AttendanceSession $session, ?Student $student): bool
    {
        if (! $session || ! $student || ! $session->isCheckInCheckoutMode()) {
            return false;
        }

        $mine = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_session_id', $session->id)
            ->first();

        return $mine !== null
            && $mine->check_in_time !== null
            && $mine->check_out_time === null;
    }

    /**
     * Include in Flutter active list: normal live session OR pending checkout after session ended/closed.
     */
    public static function isUsableInFlutterList(?AttendanceSession $session, ?Student $student): bool
    {
        if (! $session) {
            return false;
        }

        $session->loadMissing(['course', 'venue', 'lecturer']);

        if ($session->course === null) {
            return false;
        }

        if (self::isUsableActiveSession($session)) {
            return true;
        }

        return self::isPendingCheckoutForStudent($session, $student);
    }

    /**
     * Append sessions where the student still owes a checkout (same class / optional course filter).
     *
     * @param  \Illuminate\Support\Collection<int, AttendanceSession>|\Illuminate\Database\Eloquent\Collection<int, AttendanceSession>  $sessions
     * @return \Illuminate\Support\Collection<int, AttendanceSession>
     */
    public static function mergePendingCheckoutSessions($sessions, ?Student $student, ?int $courseId = null)
    {
        if (! $student) {
            return $sessions;
        }

        $existingIds = $sessions->pluck('id')->filter()->all();

        $pending = Attendance::query()
            ->where('student_id', $student->id)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->whereHas('attendanceSession', function ($q) use ($courseId) {
                $q->where('attendance_mode', 'checkin_checkout');
                if ($courseId !== null) {
                    $q->where('course_id', $courseId);
                }
            })
            ->with([
                'attendanceSession.course.lecturer',
                'attendanceSession.course.venueRelation',
                'attendanceSession.lecturer',
                'attendanceSession.venue',
                'attendanceSession.attendanceWeek',
            ])
            ->get()
            ->map(fn (Attendance $a) => $a->attendanceSession)
            ->filter()
            ->unique('id')
            ->values()
            ->filter(fn (AttendanceSession $s) => ! in_array($s->id, $existingIds, true));

        return $sessions->concat($pending);
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
            if (! self::isUsableInFlutterList($session, $student)) {
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
                    && $mine !== null
                    && $mine->check_in_time !== null
                    && $mine->check_out_time === null
                    && (
                        $session->checkout_enabled
                        || ! $session->isValid()
                    );
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
