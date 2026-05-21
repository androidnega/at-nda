<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLecturerScope;
use App\Imports\ClassStudentsImport;
use App\Support\LecturerAccess;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\University;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ClassController extends Controller
{
    use ResolvesLecturerScope;

    public function index(): View
    {
        $classes = SchoolClass::with(['university', 'faculty', 'department', 'semester'])
            ->withCount(['students'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        // A course can be linked to a class either via the legacy
        // `courses.class_id` column or via the `course_class` pivot. The
        // built-in withCount('courses') only sees the legacy column, so
        // classes that have their courses attached purely through the pivot
        // (the normal flow now that reps build per-class timetables) end
        // up showing "0 courses" even when they're scheduled for many.
        // Same story for lecturers via `class_lecturer`. Compute both
        // counts manually so the dashboard reflects the live picture.
        $classIds = $classes->pluck('id')->all();
        $coursesByClass = collect();
        $lecturersByClass = collect();
        if ($classIds !== []) {
            $coursesByClass = $this->countCoursesPerClass($classIds);
            $lecturersByClass = $this->countLecturersPerClass($classIds);
        }
        foreach ($classes as $class) {
            $cid = (int) $class->id;
            $class->courses_count_all = (int) ($coursesByClass[$cid] ?? 0);
            $class->lecturers_count_all = (int) ($lecturersByClass[$cid] ?? 0);
        }

        $classesNeedingReview = $classes->filter(fn (SchoolClass $c) => $c->needsAcademicMetadataReview())->values();

        return view('admin.classes', compact('classes', 'classesNeedingReview'));
    }

    /**
     * @param  list<int>  $classIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function countCoursesPerClass(array $classIds): \Illuminate\Support\Collection
    {
        $counts = collect();
        // Legacy: courses.class_id directly references the class.
        $legacy = \DB::table('courses')
            ->whereIn('class_id', $classIds)
            ->select('class_id', \DB::raw('id'))
            ->get()
            ->groupBy('class_id')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->all());

        // Pivot: course_class assigns a course to one or many classes.
        $pivot = collect();
        if (\App\Support\SchemaFeatures::hasCourseClassPivot()) {
            $pivot = \DB::table('course_class')
                ->whereIn('class_id', $classIds)
                ->get()
                ->groupBy('class_id')
                ->map(fn ($rows) => $rows->pluck('course_id')->map(fn ($id) => (int) $id)->all());
        }

        foreach ($classIds as $cid) {
            $ids = collect($legacy[$cid] ?? [])->merge($pivot[$cid] ?? [])->unique()->values();
            $counts[$cid] = $ids->count();
        }

        return $counts;
    }

    /**
     * @param  list<int>  $classIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function countLecturersPerClass(array $classIds): \Illuminate\Support\Collection
    {
        $counts = collect();
        // Direct: lecturers.class_id (one lecturer pinned to a single class).
        $direct = \DB::table('lecturers')
            ->whereIn('class_id', $classIds)
            ->get()
            ->groupBy('class_id')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->all());

        // Pivot: class_lecturer (modern flow).
        $pivot = collect();
        if (\App\Support\SchemaFeatures::hasClassLecturerPivot()) {
            $pivot = \DB::table('class_lecturer')
                ->whereIn('class_id', $classIds)
                ->get()
                ->groupBy('class_id')
                ->map(fn ($rows) => $rows->pluck('lecturer_id')->map(fn ($id) => (int) $id)->all());
        }

        // Implicit: any lecturer that teaches a course assigned to this class.
        $coursesByClass = $this->countCoursesPerClass($classIds);
        $implicitLecturerIds = collect();
        // Reuse the lookups from above by querying the actual courses rows.
        $courseLecturers = \DB::table('courses')
            ->whereNotNull('lecturer_id')
            ->where(function ($q) use ($classIds) {
                $q->whereIn('class_id', $classIds);
                if (\App\Support\SchemaFeatures::hasCourseClassPivot()) {
                    $q->orWhereExists(function ($sub) use ($classIds) {
                        $sub->select(\DB::raw(1))
                            ->from('course_class')
                            ->whereColumn('course_class.course_id', 'courses.id')
                            ->whereIn('course_class.class_id', $classIds);
                    });
                }
            })
            ->select(['id', 'class_id', 'lecturer_id'])
            ->get();

        // Walk courses → resolve which class each course belongs to (both
        // direct class_id and pivot) → bucket the lecturer.
        $courseClassMap = collect();
        if (\App\Support\SchemaFeatures::hasCourseClassPivot()) {
            $courseIds = $courseLecturers->pluck('id')->unique()->values()->all();
            if ($courseIds !== []) {
                $courseClassMap = \DB::table('course_class')
                    ->whereIn('course_id', $courseIds)
                    ->whereIn('class_id', $classIds)
                    ->get()
                    ->groupBy('course_id')
                    ->map(fn ($rows) => $rows->pluck('class_id')->map(fn ($id) => (int) $id)->all());
            }
        }
        $implicit = collect();
        foreach ($courseLecturers as $row) {
            $cls = [];
            if ($row->class_id && in_array((int) $row->class_id, $classIds, true)) {
                $cls[] = (int) $row->class_id;
            }
            foreach ($courseClassMap[$row->id] ?? [] as $pcid) {
                $cls[] = $pcid;
            }
            foreach (array_unique($cls) as $pcid) {
                $implicit[$pcid] = collect($implicit[$pcid] ?? [])->push((int) $row->lecturer_id);
            }
        }

        foreach ($classIds as $cid) {
            $ids = collect($direct[$cid] ?? [])
                ->merge($pivot[$cid] ?? [])
                ->merge($implicit[$cid] ?? [])
                ->unique()
                ->values();
            $counts[$cid] = $ids->count();
        }

        return $counts;
    }

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $this->authorizeLecturerForClass($request, $schoolClass);
        $schoolClass->load(['university', 'faculty', 'department']);
        $query = $schoolClass->students()
            ->with(['classReps' => fn ($q) => $q->where('class_id', $schoolClass->id)])
            ->orderBy('last_name')
            ->orderBy('first_name');

        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $query->searchTerm($search);
        }

        $students = $query->paginate(24)->withQueryString();

        return view('admin.class-students', compact('schoolClass', 'students'));
    }

    public function importStudents(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeLecturerForClass($request, $schoolClass);
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $import = new ClassStudentsImport($schoolClass);
        Excel::import($import, $request->file('file'));

        return redirect()->route('dashboard.classes.show', $schoolClass)->with('success',
            "Import complete: {$import->created} added, {$import->updated} overwritten, {$import->skipped} skipped.");
    }

    public function storeStudent(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeLecturerForClass($request, $schoolClass);
        $validated = $request->validate([
            'index_number' => 'required|string|max:64',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        $schoolClass->loadMissing('department');
        $payload = [
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'class_id' => $schoolClass->id,
        ];
        if ($schoolClass->department_id) {
            $payload['department_id'] = $schoolClass->department_id;
        }

        $student = \App\Models\Student::upsertFromRoster($validated['index_number'], $payload);
        $verb = $student->wasRecentlyCreated ? 'added' : 'updated';

        return redirect()->route('dashboard.classes.show', $schoolClass)
            ->with('success', 'Student '.$student->index_number.' '.$verb.' for this class.');
    }

    public function create(): View
    {
        $universities = University::orderBy('name')->get();
        $faculties = Faculty::with('departments')->orderBy('name')->get();
        $semesters = Semester::orderByDesc('year_label')->orderByDesc('term')->get();

        return view('admin.class-form', [
            'schoolClass' => null,
            'universities' => $universities,
            'faculties' => $faculties,
            'semesters' => $semesters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'university_id' => 'required|exists:universities,id',
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255',
            'level' => 'required|in:100,200,300,400',
            'qualification' => 'nullable|in:'.implode(',', SchoolClass::QUALIFICATIONS),
            'semester_id' => 'required|exists:semesters,id',
        ]);
        $validated['qualification'] = $validated['qualification'] ?? 'degree';
        if ($redirect = $this->ensureHierarchy($validated)) {
            return $redirect;
        }
        if (! \App\Support\SchemaFeatures::hasClassesQualification()) {
            unset($validated['qualification']);
        }
        SchoolClass::create($validated);

        return redirect()->route('dashboard.classes.index')->with('success', 'Class created.');
    }

    public function edit(SchoolClass $schoolClass): View
    {
        $universities = University::orderBy('name')->get();
        $faculties = Faculty::with('departments')->orderBy('name')->get();
        $semesters = Semester::orderByDesc('year_label')->orderByDesc('term')->get();
        $schoolClass->load(['university', 'faculty', 'department']);

        return view('admin.class-form', [
            'schoolClass' => $schoolClass,
            'universities' => $universities,
            'faculties' => $faculties,
            'semesters' => $semesters,
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validate([
            'university_id' => 'required|exists:universities,id',
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255',
            'level' => 'required|in:100,200,300,400',
            'qualification' => 'nullable|in:'.implode(',', SchoolClass::QUALIFICATIONS),
            'semester_id' => 'required|exists:semesters,id',
        ]);
        $validated['qualification'] = $validated['qualification'] ?? 'degree';
        if ($redirect = $this->ensureHierarchy($validated)) {
            return $redirect;
        }
        if (! \App\Support\SchemaFeatures::hasClassesQualification()) {
            unset($validated['qualification']);
        }
        $schoolClass->update($validated);

        return redirect()->route('dashboard.classes.index')->with('success', 'Class updated.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        if ($schoolClass->courses()->count() > 0) {
            return back()->with('error', 'Cannot delete class with courses. Reassign courses first.');
        }
        if ($schoolClass->students()->count() > 0) {
            return back()->with('error', 'Cannot delete class with students. Reassign students first.');
        }
        $schoolClass->delete();

        return back()->with('success', 'Class deleted');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureHierarchy(array $validated): ?RedirectResponse
    {
        $dept = Department::find($validated['department_id']);
        if ($dept && (int) $dept->faculty_id !== (int) $validated['faculty_id']) {
            return back()->withInput()->with('error', 'Department must belong to the selected faculty.');
        }

        $faculty = Faculty::find($validated['faculty_id']);
        if ($faculty && $faculty->university_id && (int) $faculty->university_id !== (int) $validated['university_id']) {
            return back()->withInput()->with('error', 'Faculty must belong to the selected school.');
        }

        return null;
    }

    private function authorizeLecturerForClass(Request $request, SchoolClass $schoolClass): void
    {
        $lecturer = LecturerAccess::lecturerFromSession($request);
        if (! $lecturer) {
            return;
        }
        if (! LecturerAccess::canAccessClass($lecturer, $schoolClass)) {
            abort(403, 'You can only manage students in your assigned classes.');
        }
    }
}
