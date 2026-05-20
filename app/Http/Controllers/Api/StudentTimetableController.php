<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\Student;
use App\Support\ClassTimetableAccess;
use App\Support\SchemaFeatures;
use App\Support\StudentCourseAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated student timetable (Bearer Sanctum). Mirrors web dashboard timetable data.
 */
class StudentTimetableController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Student) {
            return response()->json([
                'success' => false,
                'message' => 'Please sign in again.',
            ], 401);
        }

        $classId = (int) ($user->class_id ?? 0);
        $slots = [];

        if ($classId > 0 && SchemaFeatures::hasClassTimetables() && ClassTimetableAccess::classHasEntries($classId)) {
            $entries = ClassTimetableAccess::entriesForClass($classId);
            foreach ($entries as $entry) {
                $slots[] = $this->serializeEntry($entry);
            }
        } elseif ($classId > 0) {
            $courses = StudentCourseAccess::coursesQueryForStudent($user)
                ->with(['schoolClass', 'schoolClasses', 'lecturer', 'venueRelation'])
                ->whereNotNull('day_of_week')
                ->whereNotNull('start_time')
                ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7 ELSE 99 END")
                ->orderBy('start_time')
                ->get();
            foreach ($courses as $course) {
                $slots[] = $this->serializeCourseSlot($course);
            }
        }

        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $byDay = [];
        $orderedDays = [];

        foreach ($slots as $slot) {
            $dayKey = ucfirst(strtolower(trim((string) ($slot['day_of_week'] ?? ''))));
            $byDay[$dayKey] ??= [];
            $byDay[$dayKey][] = $slot;
        }
        foreach ($dayOrder as $d) {
            if (! empty($byDay[$d])) {
                $orderedDays[] = $d;
            }
        }

        return response()->json([
            'success' => true,
            'week_progress' => $user->weeklyTimetableSummary(),
            'ordered_days' => $orderedDays,
            'by_day' => $byDay,
            'courses' => array_map(function (array $slot): array {
                $slot['day_key'] = ucfirst(strtolower(trim((string) ($slot['day_of_week'] ?? ''))));

                return $slot;
            }, $slots),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(ClassTimetable $entry): array
    {
        $course = $entry->course;
        $startStr = $entry->start_time ? Carbon::parse($entry->start_time)->format('H:i') : null;
        $endStr = $entry->end_time ? Carbon::parse($entry->end_time)->format('H:i') : null;

        return [
            'id' => $course?->id ?? 0,
            'course_code' => $course?->course_code,
            'course_name' => $course?->course_name,
            'day_of_week' => $entry->day_of_week,
            'start_time' => $startStr,
            'end_time' => $endStr,
            'credit_hours' => (int) ($course?->credit_hours ?? 0),
            'lecturer_name' => $entry->resolvedLecturerName() ?: ($course?->lecturer_name ?? null),
            'venue' => $entry->resolvedVenueName() ?: ($course?->venue ?? null),
            'class_name' => $entry->schoolClass?->name ?? $course?->schoolClass?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCourseSlot(Course $course): array
    {
        $start = $course->start_time;
        $end = $course->end_time;
        $startStr = $start ? Carbon::parse($start)->format('H:i') : null;
        $endStr = $end ? Carbon::parse($end)->format('H:i') : null;

        return [
            'id' => $course->id,
            'course_code' => $course->course_code,
            'course_name' => $course->course_name,
            'day_of_week' => $course->day_of_week,
            'start_time' => $startStr,
            'end_time' => $endStr,
            'credit_hours' => (int) ($course->credit_hours ?? 0),
            'lecturer_name' => $course->lecturer_name ?: ($course->lecturer?->name),
            'venue' => $course->venueRelation?->name ?? $course->venue,
            'class_name' => $course->schoolClass?->name,
        ];
    }
}
