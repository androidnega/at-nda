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
        $courses = Course::with(['schoolClass', 'schoolClasses'])
            ->orderBy('course_name')
            ->get();

        // Build a course → class IDs map for the client-side "filter courses by
        // class" picker. Honours both the legacy class_id column and the
        // course_class pivot table.
        $courseClassMap = $courses->mapWithKeys(function (Course $c) {
            $ids = collect();
            if ($c->relationLoaded('schoolClasses') && $c->schoolClasses->isNotEmpty()) {
                $ids = $ids->merge($c->schoolClasses->pluck('id'));
            }
            if (!empty($c->class_id)) {
                $ids->push((int) $c->class_id);
            }
            return [
                (int) $c->id => $ids
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        });

        $stats = [
            'classes' => $classes->count(),
            'courses' => $courses->count(),
            'weekRows' => AttendanceWeek::count(),
        ];

        return view('admin.attendance-weeks', compact('classes', 'courses', 'courseClassMap', 'stats'));
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
            'class_id' => 'required|exists:classes,id',
            'course_id' => 'required|exists:courses,id',
            'confirm' => 'required|accepted',
        ]);

        $classId = (int) $validated['class_id'];
        $course = Course::findOrFail($validated['course_id']);
        $class = SchoolClass::findOrFail($classId);

        $this->purgeWeekDataForCourseAndClass((int) $course->id, $classId);

        return back()->with(
            'success',
            'Attendance cleared for «'.$course->course_name.'» in «'.$class->name.'». Next session for this class will start at Week 1.'
        );
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
     * Wipe a single class's slice of one course (weeks, sessions, marks).
     * Other classes that share the same course keep their data.
     */
    private function purgeWeekDataForCourseAndClass(int $courseId, int $classId): void
    {
        DB::transaction(function () use ($courseId, $classId) {
            $weekIds = AttendanceWeek::query()
                ->where('course_id', $courseId)
                ->where('class_id', $classId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Collect every session that points at this class+course slice,
            // including orphan sessions whose attendance_week_id is NULL or
            // points at a row we're about to delete. Skipping those was the
            // reason cleared classes kept showing up as "missed" in student
            // history.
            $sessionQuery = AttendanceSession::query()
                ->where('course_id', $courseId)
                ->where(function ($q) use ($weekIds, $classId) {
                    if ($weekIds !== []) {
                        $q->whereIn('attendance_week_id', $weekIds);
                    }
                    $q->orWhereNull('attendance_week_id')
                        ->orWhereHas('attendances.student', fn ($sq) => $sq->where('class_id', $classId));
                });
            $sessionIds = $sessionQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

            // Delete attendance rows for this class+course. Match by week_id
            // when available, by the student's class_id when not, and also
            // by attendance_session_id for any rows tied to a session we're
            // about to drop (covers reps marked from another class context).
            $attendanceQuery = Attendance::query()
                ->where('course_id', $courseId)
                ->where(function ($q) use ($weekIds, $classId, $sessionIds) {
                    if ($weekIds !== []) {
                        $q->whereIn('attendance_week_id', $weekIds);
                    }
                    $q->orWhereHas('student', fn ($sq) => $sq->where('class_id', $classId));
                    if ($sessionIds !== []) {
                        $q->orWhereIn('attendance_session_id', $sessionIds);
                    }
                });

            foreach ($attendanceQuery->cursor() as $a) {
                $a->delete();
            }

            if ($sessionIds !== []) {
                AttendanceSession::whereIn('id', $sessionIds)->delete();
            }
            if ($weekIds !== []) {
                AttendanceWeek::whereIn('id', $weekIds)->delete();
            }

            // Final sweep: any attendance row still tied to a now-missing
            // session or week for THIS class+course needs to go. Scoped
            // strictly to students in the class so a sister class on the
            // same shared course keeps its rows intact.
            Attendance::query()
                ->where('course_id', $courseId)
                ->whereHas('student', fn ($sq) => $sq->where('class_id', $classId))
                ->where(function ($q) {
                    $q->whereNull('attendance_week_id')
                        ->orWhereDoesntHave('attendanceWeek');
                })
                ->delete();
        });

        // Only reseed the course's next_week_number when no other class still
        // owns weeks for it — otherwise we'd renumber siblings unexpectedly.
        $remainingWeeks = AttendanceWeek::where('course_id', $courseId)->count();
        if ($remainingWeeks === 0) {
            Course::where('id', $courseId)->update(['next_week_number' => 1]);
        }

        $this->resetAttendanceSessionsAutoIncrementIfEmpty();

        AttendanceDataResetNotifier::notify([$courseId], 'course');
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
            // Delete every attendance row for these courses regardless of
            // its week/session linkage so orphan rows (NULL week_id, missing
            // session) don't survive the wipe and pop up as "missed" later.
            foreach (Attendance::query()->whereIn('course_id', $courseIds)->cursor() as $a) {
                $a->delete();
            }
            AttendanceSession::whereIn('course_id', $courseIds)->delete();
            AttendanceWeek::whereIn('course_id', $courseIds)->delete();
            // Belt-and-braces: drop anything still pointing at these courses
            // through dangling FK columns. The model events above keep audit
            // log entries; this catches rows the loop somehow skipped.
            Attendance::query()->whereIn('course_id', $courseIds)->delete();
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
