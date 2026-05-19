<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Support\Collection;

class StudentAttendanceHistoryBuilder
{
    private const RECENT_PRUNE_DAYS = 60;

    /**
     * @return array{
     *     history: Collection<int, array<string, mixed>>,
     *     presentCount: int,
     *     absentCount: int,
     *     totalSessions: int,
     *     attendanceRate: float,
     *     courseStats: Collection,
     *     trend: Collection
     * }
     */
    public function build(Student $student): array
    {
        $this->pruneStaleRecentDuplicates($student);

        $attendanceRows = Attendance::query()
            ->where('student_id', $student->id)
            ->with(['course', 'attendanceWeek', 'attendanceSession'])
            ->latest('attendance_time')
            ->get();

        $history = collect();

        if ($student->class_id) {
            $history = $this->buildSessionBasedHistory($student, $attendanceRows);
        }

        if ($history->isEmpty() && $attendanceRows->isNotEmpty()) {
            $history = $attendanceRows->map(fn (Attendance $attendance) => $this->rowFromAttendance($attendance, null));
        }

        $history = $this->dedupeByCourseWeek($history);

        $presentCount = $history->where('is_present', true)->count();
        $absentCount = $history->where('is_present', false)->count();
        $totalSessions = $history->count();
        $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 1) : 0.0;

        $courseStats = $history
            ->groupBy(fn (array $row) => $row['course']?->id ?? 0)
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $present = $rows->where('is_present', true)->count();
                $total = $rows->count();

                return [
                    'course_name' => $first['course']?->course_name ?? 'Unknown course',
                    'course_code' => $first['course']?->course_code,
                    'present' => $present,
                    'absent' => $total - $present,
                    'total' => $total,
                    'rate' => $total > 0 ? round(($present / $total) * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        $trend = $history
            ->filter(fn (array $row) => ! empty($row['week']))
            ->groupBy('week')
            ->map(function (Collection $rows, $week) {
                $present = $rows->where('is_present', true)->count();
                $total = $rows->count();
                $weekNumber = (int) $week;

                return [
                    'week' => $weekNumber,
                    'label' => 'Week '.$weekNumber,
                    'present' => $present,
                    'absent' => $total - $present,
                    'total' => $total,
                    'rate' => $total > 0 ? round(($present / $total) * 100) : 0,
                ];
            })
            ->sortBy('week')
            ->values()
            ->take(-8)
            ->values();

        return [
            'history' => $history,
            'presentCount' => $presentCount,
            'absentCount' => $absentCount,
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'courseStats' => $courseStats,
            'trend' => $trend,
        ];
    }

    /**
     * Remove duplicate absent rows when a present mark exists for the same course/week.
     */
    public function pruneStaleRecentDuplicates(Student $student): void
    {
        $since = now()->subDays(self::RECENT_PRUNE_DAYS);

        $groups = Attendance::query()
            ->where('student_id', $student->id)
            ->where('attendance_time', '>=', $since)
            ->get()
            ->groupBy(fn (Attendance $a) => $a->course_id.'-'.($a->attendance_week_id ?? 0));

        foreach ($groups as $group) {
            $keeper = $group->first(fn (Attendance $a) => $this->attendanceCountsAsPresent($a))
                ?? $group->sortByDesc(fn (Attendance $a) => $a->attendance_time?->timestamp ?? 0)->first();

            if ($keeper === null) {
                continue;
            }

            foreach ($group as $row) {
                if ((int) $row->id === (int) $keeper->id) {
                    continue;
                }
                if ($this->attendanceCountsAsPresent($row)) {
                    continue;
                }
                if ($row->check_in_time !== null && $this->attendanceCountsAsPresent($keeper)) {
                    $row->delete();

                    continue;
                }
                if (! $this->attendanceCountsAsPresent($row) && $this->attendanceCountsAsPresent($keeper)) {
                    $row->delete();
                }
            }
        }
    }

    /**
     * @param  Collection<int, Attendance>  $attendanceRows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSessionBasedHistory(Student $student, Collection $attendanceRows): Collection
    {
        $attendanceBySession = $attendanceRows
            ->filter(fn (Attendance $row) => $row->attendance_session_id)
            ->keyBy('attendance_session_id');

        $presentWeekKeys = $attendanceRows
            ->filter(fn (Attendance $a) => $this->attendanceCountsAsPresent($a))
            ->map(fn (Attendance $a) => $this->courseWeekKey($a->course_id, $a->attendance_week_id))
            ->flip();

        $attendedSessionIds = $attendanceBySession->keys();

        $sessions = AttendanceSession::query()
            ->whereHas('course', fn ($q) => $q->forManagedClasses([$student->class_id]))
            ->where(function ($q) use ($attendedSessionIds) {
                $q->ended()
                    ->orWhere(function ($q2) {
                        $q2->where('is_active', false)
                            ->whereRaw('COALESCE(end_time, expires_at) IS NOT NULL');
                    })
                    ->orWhereIn('id', $attendedSessionIds);
            })
            ->with(['course', 'attendanceWeek'])
            ->orderByRaw('COALESCE(end_time, expires_at, created_at) DESC')
            ->limit(400)
            ->get();

        $sessionIds = $sessions->pluck('id')->all();

        $history = $sessions
            ->map(function (AttendanceSession $session) use ($attendanceBySession, $presentWeekKeys) {
                $attendance = $attendanceBySession->get($session->id);
                $weekKey = $this->courseWeekKey($session->course_id, $session->attendance_week_id);

                if ($attendance === null && $presentWeekKeys->has($weekKey)) {
                    return null;
                }

                if ($attendance === null && $this->sessionStillInProgress($session)) {
                    return null;
                }

                return $this->rowFromAttendance($attendance, $session);
            })
            ->filter();

        $linkedSessionIds = $sessions->pluck('id')->all();
        $orphanAttendances = $attendanceRows->filter(
            fn (Attendance $a) => ! $a->attendance_session_id
                || ! in_array($a->attendance_session_id, $linkedSessionIds, true)
        );

        foreach ($orphanAttendances as $attendance) {
            $weekKey = $this->courseWeekKey($attendance->course_id, $attendance->attendance_week_id);
            if (! $this->attendanceCountsAsPresent($attendance) && $presentWeekKeys->has($weekKey)) {
                continue;
            }
            $history->push($this->rowFromAttendance($attendance, $attendance->attendanceSession));
        }

        return $history->sortByDesc(fn (array $row) => $row['time']?->timestamp ?? 0)->values();
    }

    private function sessionStillInProgress(AttendanceSession $session): bool
    {
        if (! $session->is_active) {
            return false;
        }

        $end = $session->end_time ?? $session->expires_at;

        return $end === null || $end->isFuture();
    }

    private function attendanceCountsAsPresent(Attendance $attendance): bool
    {
        if (Attendance::countsAsPresent($attendance->status)) {
            return true;
        }

        $status = $attendance->status;

        return ($status === null || $status === '')
            && ($attendance->check_in_time !== null || $attendance->attendance_time !== null);
    }

    private function courseWeekKey(int $courseId, ?int $weekId): string
    {
        return $courseId.'-'.($weekId ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromAttendance(?Attendance $attendance, ?AttendanceSession $session): array
    {
        $course = $session?->course ?? $attendance?->course;
        $week = $session?->attendanceWeek?->week_number ?? $attendance?->attendanceWeek?->week_number;
        $isPresent = $attendance !== null && $this->attendanceCountsAsPresent($attendance);

        return [
            'session' => $session,
            'course' => $course,
            'week' => $week,
            'is_present' => $isPresent,
            'attendance' => $attendance,
            'time' => $attendance?->attendance_time
                ?? $session?->end_time
                ?? $session?->expires_at
                ?? $session?->created_at,
            '_week_key' => $this->courseWeekKey(
                (int) ($course?->id ?? $attendance?->course_id ?? 0),
                $session?->attendance_week_id ?? $attendance?->attendance_week_id
            ),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $history
     * @return Collection<int, array<string, mixed>>
     */
    private function dedupeByCourseWeek(Collection $history): Collection
    {
        return $history
            ->groupBy(fn (array $row) => $row['_week_key'] ?? '0-0')
            ->map(function (Collection $rows) {
                $present = $rows->where('is_present', true);
                if ($present->isNotEmpty()) {
                    return $present->sortByDesc(fn (array $r) => $r['time']?->timestamp ?? 0)->first();
                }

                return $rows->sortByDesc(fn (array $r) => $r['time']?->timestamp ?? 0)->first();
            })
            ->values()
            ->map(function (array $row) {
                unset($row['_week_key']);

                return $row;
            });
    }
}
