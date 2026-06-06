<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\FlutterSessionFormatter;
use Illuminate\Support\Collection;
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
     * @param  Collection<int, AttendanceSession>|\Illuminate\Database\Eloquent\Collection<int, AttendanceSession>  $sessions
     * @return Collection<int, AttendanceSession>
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
            ->where('check_in_time', '>=', now()->subHours(72))
            ->whereHas('attendanceSession', function ($q) use ($courseId, $student) {
                $q->where('attendance_mode', 'checkin_checkout');
                if ($courseId !== null) {
                    $q->where('course_id', $courseId);
                }
                if ($student->class_id !== null) {
                    $q->whereHas('course', fn ($cq) => $cq->where('class_id', $student->class_id));
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
     * @param  Collection<int, AttendanceSession>|\Illuminate\Database\Eloquent\Collection<int, AttendanceSession>  $sessions
     * @return list<array<string, mixed>>
     */
    public static function buildRows($sessions, ?Student $student): array
    {
        // Normalise to a generic Collection so we can pluck() / filter() without
        // assuming an Eloquent collection (callers pass either kind).
        $sessionList = $sessions instanceof Collection
            ? $sessions
            : collect($sessions);

        // Bulk-fetch this student's attendance rows for every candidate session
        // in ONE query, keyed by attendance_session_id. Replaces the previous
        // N+1 pattern that ran two queries per session (one for "already_marked"
        // and one inside isPendingCheckoutForStudent / isUsableInFlutterList).
        $sessionIds = $sessionList
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $myAttendances = collect();
        if ($student && $sessionIds !== []) {
            $myAttendances = Attendance::query()
                ->where('student_id', $student->id)
                ->whereIn('attendance_session_id', $sessionIds)
                ->get()
                ->keyBy('attendance_session_id');
        }

        $list = [];
        foreach ($sessionList as $session) {
            if (! $session) {
                continue;
            }

            $mine = $student && $session->id !== null
                ? $myAttendances->get($session->id)
                : null;

            if (! self::isUsableInFlutterListWithAttendance($session, $student, $mine)) {
                continue;
            }

            try {
                $row = FlutterSessionFormatter::format($session);
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

    /**
     * Same semantics as {@see isUsableInFlutterList()} but reuses a pre-loaded
     * Attendance row instead of running a fresh `isPendingCheckoutForStudent`
     * query. Internal helper for the bulk-fetch path inside buildRows().
     */
    private static function isUsableInFlutterListWithAttendance(
        AttendanceSession $session,
        ?Student $student,
        ?Attendance $mine,
    ): bool {
        $session->loadMissing(['course', 'venue', 'lecturer']);

        if ($session->course === null) {
            return false;
        }

        if (self::isUsableActiveSession($session)) {
            return true;
        }

        if (! $student || ! $session->isCheckInCheckoutMode()) {
            return false;
        }

        return $mine !== null
            && $mine->check_in_time !== null
            && $mine->check_out_time === null;
    }
}
