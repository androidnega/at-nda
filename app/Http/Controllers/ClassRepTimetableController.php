<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Lecturer;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Venue;
use App\Support\ClassTimetableAccess;
use App\Support\SchemaFeatures;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Per-class timetable CRUD for class reps. Each class owns its own slots so
 * edits never affect another class that shares the same course (e.g. two
 * cohorts sitting Software Engineering with different lecturers / times).
 */
class ClassRepTimetableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $classes = $this->managedClasses($student);
        if ($classes->isEmpty()) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'You are not assigned to any class.');
        }

        $selectedClassId = $this->resolveSelectedClassId($request, $classes);
        $selectedClass = $classes->firstWhere('id', $selectedClassId);

        $entries = ClassTimetableAccess::entriesForClass($selectedClassId);
        $availableCourses = ClassTimetableAccess::coursesAssignableToClass($selectedClass);
        $availableLecturers = $this->availableLecturersForClass($selectedClass);
        $availableVenues = Venue::query()->orderBy('name')->get();

        // Courses already on this class's timetable — hide them from the "Add
        // slot" dropdown so each course can only be added once per class.
        $usedCourseIds = $entries
            ->pluck('course_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $addableCourses = $availableCourses->reject(
            fn ($c) => $usedCourseIds->contains((int) $c->id)
        )->values();

        // courseId → lecturerId default, so the lecturer field can auto-fill
        // when the rep selects a course. Falls back to the most recent
        // class_timetable lecturer for the same course (any class) when the
        // course catalog itself doesn't carry a default lecturer.
        $courseLecturerMap = $availableCourses->mapWithKeys(function ($c) {
            $lecturerId = $c->lecturer_id ? (int) $c->lecturer_id : null;
            if ($lecturerId === null) {
                $lecturerId = (int) (ClassTimetable::query()
                    ->where('course_id', $c->id)
                    ->whereNotNull('lecturer_id')
                    ->orderByDesc('id')
                    ->value('lecturer_id') ?? 0) ?: null;
            }
            return [(int) $c->id => $lecturerId];
        });

        return view('classrep.timetable', [
            'student' => $student,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'entries' => $entries,
            'availableCourses' => $availableCourses,
            'addableCourses' => $addableCourses,
            'usedCourseIds' => $usedCourseIds,
            'courseLecturerMap' => $courseLecturerMap,
            'availableLecturers' => $availableLecturers,
            'availableVenues' => $availableVenues,
            'days' => ClassTimetable::DAYS,
            'dashboardRole' => 'classrep',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $validated = $this->validateEntry($request, $student);
        $validated['start_time'] = $this->normalizeTime($validated['start_time']);
        $validated['end_time'] = $this->normalizeTime($validated['end_time']);
        // Reps only choose existing venues — clear any legacy free-text value.
        $validated['venue'] = null;
        $entry = ClassTimetable::create($validated + [
            'created_by_student_id' => $student->id,
        ]);

        return redirect()
            ->route('dashboard.timetable.manage', ['class_id' => $entry->class_id])
            ->with('success', 'Timetable slot added.');
    }

    public function update(Request $request, ClassTimetable $entry): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $this->ensureRepOwnsClass($student, (int) $entry->class_id);

        $validated = $this->validateEntry($request, $student, $entry);
        $validated['start_time'] = $this->normalizeTime($validated['start_time']);
        $validated['end_time'] = $this->normalizeTime($validated['end_time']);
        $validated['venue'] = null;
        $entry->update($validated);

        return redirect()
            ->route('dashboard.timetable.manage', ['class_id' => $entry->class_id])
            ->with('success', 'Timetable slot updated.');
    }

    public function destroy(Request $request, ClassTimetable $entry): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $this->ensureRepOwnsClass($student, (int) $entry->class_id);
        $classId = (int) $entry->class_id;
        $entry->delete();

        return redirect()
            ->route('dashboard.timetable.manage', ['class_id' => $classId])
            ->with('success', 'Timetable slot removed.');
    }

    private function requireRep(Request $request): Student|RedirectResponse
    {
        $student = Student::find($request->session()->get('student_id'));
        if (! $student) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }
        if (! $student->isClassRep()) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'Only class reps can manage the timetable.');
        }
        if (! SchemaFeatures::hasClassTimetables()) {
            return redirect()->route('dashboard.timetable')
                ->with('error', 'Timetable management is not yet available. Run migrations to enable it.');
        }

        return $student;
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function managedClasses(Student $student): Collection
    {
        $ids = $student->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return new Collection;
        }

        return SchoolClass::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    private function resolveSelectedClassId(Request $request, Collection $classes): int
    {
        $requested = (int) $request->query('class_id', 0);
        if ($requested > 0 && $classes->firstWhere('id', $requested)) {
            return $requested;
        }

        return (int) $classes->first()->id;
    }

    /**
     * Lecturers a rep may assign: explicit class_lecturer pivot plus anyone
     * teaching at least one course in this class (so reps see the full pool
     * without depending on admin to populate the pivot).
     *
     * @return Collection<int, Lecturer>
     */
    private function availableLecturersForClass(SchoolClass $class): Collection
    {
        $ids = collect();

        if (SchemaFeatures::hasClassLecturerPivot()) {
            $ids = $ids->merge($class->lecturers()->pluck('lecturers.id'));
        }

        $courseIds = ClassTimetableAccess::coursesAssignableToClass($class)->pluck('id');
        if ($courseIds->isNotEmpty()) {
            $ids = $ids->merge(
                Lecturer::query()
                    ->whereIn('id', function ($sub) use ($courseIds) {
                        $sub->select('lecturer_id')
                            ->from('courses')
                            ->whereIn('id', $courseIds)
                            ->whereNotNull('lecturer_id');
                    })
                    ->pluck('id')
            );
        }

        $ids = $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return Lecturer::query()->orderBy('name')->get();
        }

        return Lecturer::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function ensureRepOwnsClass(Student $student, int $classId): void
    {
        $managed = $student->repManagedClassIds()->map(fn ($id) => (int) $id);
        if (! $managed->contains($classId)) {
            abort(403, 'You may only edit the timetable of a class you rep.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntry(Request $request, Student $student, ?ClassTimetable $existing = null): array
    {
        $managed = $student->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        if ($managed === []) {
            abort(403, 'You are not assigned to any class.');
        }

        $rules = [
            'class_id' => ['required', 'integer', function ($attr, $value, $fail) use ($managed) {
                if (! in_array((int) $value, $managed, true)) {
                    $fail('You may only edit the timetable of a class you rep.');
                }
            }],
            'course_id' => 'required|integer|exists:courses,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'lecturer_id' => 'nullable|integer|exists:lecturers,id',
            'venue_id' => 'required|integer|exists:venues,id',
            'credit_hours' => 'required|integer|min:1|max:12',
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request, $existing) {
            $classId = (int) $request->input('class_id');
            $courseId = (int) $request->input('course_id');
            $day = (string) $request->input('day_of_week');
            $start = (string) $request->input('start_time');
            if ($classId <= 0 || $courseId <= 0) {
                return;
            }

            // Each course may appear at most once per class on the timetable.
            $duplicateCourseQuery = ClassTimetable::query()
                ->where('class_id', $classId)
                ->where('course_id', $courseId);
            if ($existing) {
                $duplicateCourseQuery->where('id', '!=', $existing->id);
            }
            if ($duplicateCourseQuery->exists()) {
                $v->errors()->add('course_id', 'This course is already on this class\'s timetable. Edit the existing slot instead.');
                return;
            }

            if ($day === '' || $start === '') {
                return;
            }

            $query = ClassTimetable::query()
                ->where('class_id', $classId)
                ->where('course_id', $courseId)
                ->where('day_of_week', $day)
                ->where(function ($q) use ($start) {
                    $q->where('start_time', $start)
                        ->orWhere('start_time', $start.':00');
                });
            if ($existing) {
                $query->where('id', '!=', $existing->id);
            }
            if ($query->exists()) {
                $v->errors()->add('start_time', 'This class already has a slot for this course at that day & time.');
            }
        });

        return $validator->validated();
    }
}
