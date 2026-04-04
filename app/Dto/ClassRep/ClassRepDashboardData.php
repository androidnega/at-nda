<?php

namespace App\Dto\ClassRep;

/**
 * Typed dashboard payload; converted to array for JSON only (hides internal shape from controllers).
 *
 * @phpstan-type CourseRow array{course_id:int,course_name:string,course_code:?string,class_id:?int,rep_role:string,can_open_session:bool,has_schedule:bool,active_session:mixed}
 */
final readonly class ClassRepDashboardData
{
    /**
     * @param  list<int>  $managedClassIds
     * @param  list<CourseRow>  $courses
     * @param  list<array<string, mixed>>  $attendanceTrend
     * @param  array<string, mixed>  $insights
     * @param  list<array<string, mixed>>  $flaggedStudents
     */
    public function __construct(
        public string $role,
        public array $managedClassIds,
        public array $courses,
        public bool $hasActiveSession,
        public int $activeSessionsCount,
        public int $studentsInClassesCount,
        public ?string $notice = null,
        public array $attendanceTrend = [],
        public array $insights = [],
        public array $flaggedStudents = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'role' => $this->role,
            'is_class_rep' => true,
            'managed_class_ids' => $this->managedClassIds,
            'courses' => $this->courses,
            'has_active_session' => $this->hasActiveSession,
            'active_sessions_count' => $this->activeSessionsCount,
            'students_in_classes_count' => $this->studentsInClassesCount,
            'attendance_trend' => $this->attendanceTrend,
            'insights' => $this->insights,
            'flagged_students' => $this->flaggedStudents,
            'flagged_students_count' => count($this->flaggedStudents),
        ];
        if ($this->notice !== null && $this->notice !== '') {
            $out['notice'] = $this->notice;
        }

        return $out;
    }
}
