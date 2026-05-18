<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Services\AttendanceDataResetNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminAttendanceWeekController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        $courses = Course::with('schoolClass')->orderBy('course_name')->get();
        $stats = [
            'classes' => $classes->count(),
            'courses' => $courses->count(),
            'weekRows' => AttendanceWeek::count(),
        ];

        return view('admin.attendance-weeks', compact('classes', 'courses', 'stats'));
    }

    /**
     * Next session that needs a new week row will use this number (then increments stored seed on the course).
     */
    public function setNextForCourse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'next_week_number' => 'required|integer|min:1|max:500',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $course->update(['next_week_number' => (int) $validated['next_week_number']]);

        return back()->with('success', 'Next new week for «' . $course->course_name . '» will use week ' . (int) $validated['next_week_number'] . ' (then continue sequentially).');
    }

    public function setNextForClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'next_week_number' => 'required|integer|min:1|max:500',
        ]);

        $n = (int) $validated['next_week_number'];
        $updated = Course::where('class_id', $validated['class_id'])->update(['next_week_number' => $n]);

        return back()->with('success', 'Set next week number to ' . $n . ' for ' . $updated . ' course(s) in this class.');
    }

    public function resetCourse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'confirm' => 'required|accepted',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->purgeWeekDataForCourseIds([(int) $course->id], 'course');

        return back()->with('success', 'Attendance weeks, sessions, and marks cleared for «' . $course->course_name . '». The next session will use Week 1 (per-course session index also restarts).');
    }

    public function resetClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'confirm' => 'required|accepted',
        ]);

        $ids = Course::forClass((int) $validated['class_id'])->pluck('id')->all();
        $this->purgeWeekDataForCourseIds($ids, 'class');

        $class = SchoolClass::find($validated['class_id']);

        return back()->with('success', 'Attendance data cleared for all courses in «' . ($class?->name ?? 'class') . '». Each course’s next week is seeded to Week 1.');
    }

    public function resetAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_reset_all' => 'required|in:RESET',
        ]);

        $ids = Course::pluck('id')->all();
        $this->purgeWeekDataForCourseIds($ids, 'all');

        return back()->with('success', 'All attendance weeks, sessions, and marks have been cleared. Each course’s next week is seeded to Week 1; session IDs may continue from the database unless this was a full wipe with no other sessions.');
    }

    /**
     * @param  array<int, int>  $courseIds
     */
    private function purgeWeekDataForCourseIds(array $courseIds, string $scope): void
    {
        if ($courseIds === []) {
            return;
        }

        DB::transaction(function () use ($courseIds) {
            foreach (Attendance::whereIn('course_id', $courseIds)->cursor() as $a) {
                $a->delete();
            }
            AttendanceSession::whereIn('course_id', $courseIds)->delete();
            AttendanceWeek::whereIn('course_id', $courseIds)->delete();
            // Seed next week label to 1 so new sessions start at Week 1 (not max+1 from stale state).
            Course::whereIn('id', $courseIds)->update(['next_week_number' => 1]);
        });

        $this->resetAttendanceSessionsAutoIncrementIfEmpty();

        AttendanceDataResetNotifier::notify($courseIds, $scope);
    }

    private function resetAttendanceSessionsAutoIncrementIfEmpty(): void
    {
        if (AttendanceSession::query()->count() !== 0) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE attendance_sessions AUTO_INCREMENT = 1');
        }
    }
}
