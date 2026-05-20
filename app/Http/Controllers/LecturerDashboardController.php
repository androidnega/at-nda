<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ClassTimetable;
use App\Models\Lecturer;
use App\Support\SchemaFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LecturerDashboardController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        $lecturerId = $request->session()->get('lecturer_id');
        if (! $lecturerId) {
            return redirect()->route('lecturer.login');
        }

        $lecturer = Lecturer::query()
            ->with(SchemaFeatures::hasClassLecturerPivot() ? ['schoolClasses'] : ['schoolClass'])
            ->find($lecturerId);
        if (! $lecturer) {
            $request->session()->forget('lecturer_id');

            return redirect()->route('lecturer.login');
        }
        if ($lecturer->must_change_password) {
            return redirect()->route('lecturer.password.change.form');
        }

        $assignedClasses = $lecturer->assignedSchoolClasses();
        $courses = $lecturer->teachingCourses();
        $classIds = $lecturer->assignedClassIds();
        $classIdsArr = $classIds->all();
        $courseIds = $courses->pluck('id')->all();

        $classGroups = $assignedClasses->map(function ($schoolClass) use ($courses) {
            return [
                'class' => $schoolClass,
                'courses' => $courses->filter(
                    fn ($course) => $course->isAssignedToClass((int) $schoolClass->id)
                )->values(),
            ];
        });

        $orphanCourses = $courses->filter(function ($course) use ($classIds) {
            $linked = collect($course->assignedClassIds());

            return $linked->isEmpty() || $linked->intersect($classIds)->isEmpty();
        })->values();

        $totalStudents = (int) $assignedClasses->sum('students_count');

        $marksThisWeek = empty($courseIds) ? 0 : Attendance::query()
            ->whereIn('course_id', $courseIds)
            ->where('attendance_time', '>=', now()->startOfWeek())
            ->count();

        $activeSessions = empty($courseIds)
            ? collect()
            : AttendanceSession::query()
                ->whereIn('course_id', $courseIds)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->with(['course'])
                ->latest('id')
                ->get();

        $todaySlots = $this->buildTodaysSlots($courses, $assignedClasses, $courseIds, $classIdsArr);

        return view('dashboard.lecturer', [
            'lecturer' => $lecturer,
            'courses' => $courses,
            'classGroups' => $classGroups,
            'orphanCourses' => $orphanCourses,
            'totalStudents' => $totalStudents,
            'marksThisWeek' => $marksThisWeek,
            'activeSessions' => $activeSessions,
            'todaySlots' => $todaySlots,
            'today' => now(),
            'dashboardRole' => 'lecturer',
        ]);
    }

    /**
     * Build a flat, sorted list of today's teaching slots for the lecturer,
     * preferring per-class timetable entries and falling back to the legacy
     * course-level day/time columns when no per-class row exists yet.
     *
     * @return Collection<int, array{course: \App\Models\Course, class: \App\Models\SchoolClass|null, start_time: ?string, end_time: ?string, venue: ?string}>
     */
    private function buildTodaysSlots(
        Collection $courses,
        Collection $assignedClasses,
        array $courseIds,
        array $classIdsArr
    ): Collection {
        $todayDow = now()->format('l');
        $slots = collect();

        if (SchemaFeatures::hasClassTimetables() && ! empty($courseIds) && ! empty($classIdsArr)) {
            $entries = ClassTimetable::query()
                ->whereIn('course_id', $courseIds)
                ->whereIn('class_id', $classIdsArr)
                ->where('day_of_week', $todayDow)
                ->with(['course', 'schoolClass', 'venueRelation'])
                ->get();
            $coursesById = $courses->keyBy('id');
            foreach ($entries as $entry) {
                $courseModel = $coursesById->get($entry->course_id) ?? $entry->course;
                if (! $courseModel) {
                    continue;
                }
                $slots->push([
                    'course' => $courseModel,
                    'class' => $entry->schoolClass,
                    'start_time' => (string) $entry->start_time,
                    'end_time' => (string) $entry->end_time,
                    'venue' => $entry->venueRelation?->name ?? $entry->venue,
                ]);
            }
        }

        $coveredKeys = $slots->map(fn ($r) => $r['course']->id.'-'.($r['class']?->id ?? 0))->all();
        foreach ($courses as $course) {
            if ((string) $course->day_of_week !== $todayDow) {
                continue;
            }
            $emittedForCourse = false;
            foreach ($assignedClasses as $sc) {
                if (! $course->isAssignedToClass((int) $sc->id)) {
                    continue;
                }
                $key = $course->id.'-'.$sc->id;
                if (in_array($key, $coveredKeys, true)) {
                    continue;
                }
                $slots->push([
                    'course' => $course,
                    'class' => $sc,
                    'start_time' => (string) $course->start_time,
                    'end_time' => (string) $course->end_time,
                    'venue' => $course->venueRelation?->name ?? $course->venue,
                ]);
                $emittedForCourse = true;
            }
            if (! $emittedForCourse && $course->assignedClassIds()->isEmpty()) {
                $slots->push([
                    'course' => $course,
                    'class' => null,
                    'start_time' => (string) $course->start_time,
                    'end_time' => (string) $course->end_time,
                    'venue' => $course->venueRelation?->name ?? $course->venue,
                ]);
            }
        }

        return $slots->sortBy(fn ($r) => $r['start_time'] ?: '99:99')->values();
    }
}
