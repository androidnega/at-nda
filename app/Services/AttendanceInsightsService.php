<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance % trends and consecutive-miss detection for dashboards (rep / student / lecturer).
 */
class AttendanceInsightsService
{
    /**
     * Weekly attendance rate % for sessions in the given courses (last N ISO weeks).
     *
     * @param  list<int>  $courseIds
     * @return list<array{week_key: string, label: string, rate: float, sessions: int}>
     */
    public function weeklyAttendanceTrend(array $courseIds, int $weeks = 8): array
    {
        if ($courseIds === []) {
            return [];
        }
        $since = Carbon::now()->startOfWeek()->subWeeks($weeks - 1);

        $sessions = AttendanceSession::query()
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('start_time')
            ->where('start_time', '>=', $since)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('end_time')
                        ->where('end_time', '<', now());
                })->orWhere('is_active', false);
            })
            ->orderBy('start_time')
            ->get(['id', 'course_id', 'start_time']);

        if ($sessions->isEmpty()) {
            return [];
        }

        $courses = Course::query()->whereIn('id', $courseIds)->get()->keyBy('id');
        $byWeek = [];

        foreach ($sessions as $session) {
            $course = $courses->get($session->course_id);
            if (! $course || ! $course->class_id) {
                continue;
            }
            $classStudentCount = (int) Student::query()->where('class_id', $course->class_id)->count();
            if ($classStudentCount <= 0) {
                continue;
            }
            $weekStart = Carbon::parse($session->start_time)->startOfWeek();
            $key = $weekStart->format('Y-m-d');
            if (! isset($byWeek[$key])) {
                $byWeek[$key] = [
                    'start' => $weekStart,
                    'expected' => 0,
                    'present' => 0,
                    'session_count' => 0,
                ];
            }
            $byWeek[$key]['expected'] += $classStudentCount;
            $byWeek[$key]['session_count']++;
            $byWeek[$key]['present'] += (int) Attendance::query()
                ->where('attendance_session_id', $session->id)
                ->where('status', 'present')
                ->count();
        }

        ksort($byWeek);
        $out = [];
        foreach ($byWeek as $row) {
            $expected = max(1, (int) $row['expected']);
            $out[] = [
                'week_key' => $row['start']->format('Y-m-d'),
                'label' => $row['start']->format('M j'),
                'rate' => round(min(100.0, ($row['present'] / $expected) * 100.0), 1),
                'sessions' => (int) $row['session_count'],
            ];
        }

        return $out;
    }

    /**
     * Compare last week vs previous week average rate (from trend points).
     *
     * @param  list<array{rate: float}>  $trend
     * @return array{delta_pct: float, direction: string}
     */
    public function trendInsights(array $trend): array
    {
        $n = count($trend);
        if ($n === 0) {
            return ['delta_pct' => 0.0, 'direction' => 'flat'];
        }
        if ($n === 1) {
            return ['delta_pct' => 0.0, 'direction' => 'flat'];
        }
        $last = (float) $trend[$n - 1]['rate'];
        $prev = (float) $trend[$n - 2]['rate'];
        $delta = round($last - $prev, 1);
        $dir = $delta > 0.5 ? 'up' : ($delta < -0.5 ? 'down' : 'flat');

        return ['delta_pct' => $delta, 'direction' => $dir];
    }

    /**
     * Consecutive missed (no present) sessions from the most recent completed session backward.
     */
    public function consecutiveMissStreakStudentCourse(int $studentId, int $courseId, int $maxSessions = 40): int
    {
        $sessions = AttendanceSession::query()
            ->where('course_id', $courseId)
            ->whereNotNull('start_time')
            ->where('start_time', '<', now())
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('end_time')
                        ->where('end_time', '<', now());
                })->orWhere('is_active', false);
            })
            ->orderByDesc('start_time')
            ->limit($maxSessions)
            ->pluck('id');

        $streak = 0;
        foreach ($sessions as $sid) {
            $present = Attendance::query()
                ->where('attendance_session_id', $sid)
                ->where('student_id', $studentId)
                ->where('status', 'present')
                ->exists();
            if ($present) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * @param  Collection<int, int>|array<int>  $classIds
     * @return list<array{student_id: int, index_number: string, name: string, consecutive_missed: int, course_id: ?int, course_name: ?string}>
     */
    public function flaggedStudents(Collection|array $classIds, int $threshold = 3): array
    {
        $ids = $classIds instanceof Collection ? $classIds->values()->all() : array_values($classIds);
        if ($ids === []) {
            return [];
        }
        $students = Student::query()
            ->whereIn('class_id', $ids)
            ->get(['id', 'index_number', 'first_name', 'last_name', 'class_id']);

        $courses = Course::query()
            ->forManagedClasses($ids)
            ->get(['id', 'class_id', 'course_name']);

        $flagged = [];
        foreach ($students as $student) {
            $best = 0;
            $bestCourse = null;
            foreach ($courses as $course) {
                if (! $course->isAssignedToClass((int) $student->class_id)) {
                    continue;
                }
                $streak = $this->consecutiveMissStreakStudentCourse((int) $student->id, (int) $course->id);
                if ($streak > $best) {
                    $best = $streak;
                    $bestCourse = $course;
                }
            }
            if ($best >= $threshold) {
                $flagged[] = [
                    'student_id' => (int) $student->id,
                    'index_number' => (string) $student->index_number,
                    'name' => $student->getDisplayNameOrIndex(),
                    'consecutive_missed' => $best,
                    'course_id' => $bestCourse ? (int) $bestCourse->id : null,
                    'course_name' => $bestCourse?->course_name,
                ];
            }
        }

        usort($flagged, fn ($a, $b) => $b['consecutive_missed'] <=> $a['consecutive_missed']);

        return $flagged;
    }

    /**
     * Max consecutive miss across all courses for this student's class.
     */
    public function studentMaxConsecutiveMiss(Student $student): int
    {
        if (! $student->class_id) {
            return 0;
        }
        $courses = Course::query()->where('class_id', $student->class_id)->pluck('id');
        $max = 0;
        foreach ($courses as $cid) {
            $max = max($max, $this->consecutiveMissStreakStudentCourse((int) $student->id, (int) $cid));
        }

        return $max;
    }

    /**
     * @param  list<int>  $courseIds
     * @return array{total_classes: int, avg_attendance_pct: float, at_risk_count: int, active_sessions: int}
     */
    public function lecturerSummary(int $lecturerId, array $courseIds): array
    {
        if ($courseIds === []) {
            return [
                'total_classes' => 0,
                'avg_attendance_pct' => 0.0,
                'at_risk_count' => 0,
                'active_sessions' => 0,
            ];
        }
        $trend = $this->weeklyAttendanceTrend($courseIds, 4);
        $rates = array_column($trend, 'rate');
        $avg = $rates === [] ? 0.0 : round(array_sum($rates) / count($rates), 1);

        $classIds = Course::query()->whereIn('id', $courseIds)->pluck('class_id')->filter()->unique()->values();
        $atRisk = count($this->flaggedStudents($classIds, 3));

        $activeSessions = (int) AttendanceSession::query()
            ->whereIn('course_id', $courseIds)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_time')->orWhere('end_time', '>', now());
            })
            ->count();

        return [
            'total_classes' => count($courseIds),
            'avg_attendance_pct' => $avg,
            'at_risk_count' => $atRisk,
            'active_sessions' => $activeSessions,
        ];
    }

    /**
     * Per-class row for lecturer list.
     *
     * @return list<array{course_id: int, course_name: string, course_code: ?string, student_count: int, attendance_pct: float}>
     */
    public function lecturerClassRows(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }
        $courses = Course::query()->whereIn('id', $courseIds)->orderBy('course_name')->get();
        $out = [];
        foreach ($courses as $course) {
            $studentCount = $course->class_id
                ? (int) Student::query()->where('class_id', $course->class_id)->count()
                : 0;
            $trend = $this->weeklyAttendanceTrend([(int) $course->id], 4);
            $rates = array_column($trend, 'rate');
            $pct = $rates === [] ? 0.0 : round(array_sum($rates) / count($rates), 1);
            $out[] = [
                'course_id' => (int) $course->id,
                'course_name' => (string) $course->course_name,
                'course_code' => $course->course_code,
                'student_count' => $studentCount,
                'attendance_pct' => $pct,
            ];
        }

        return $out;
    }
}
