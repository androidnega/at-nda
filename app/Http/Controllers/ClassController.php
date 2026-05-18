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
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ClassController extends Controller
{
    use ResolvesLecturerScope;

    public function index(): View
    {
        $classes = SchoolClass::with(['university', 'faculty', 'department', 'semester'])
            ->withCount(['courses', 'students'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();
        $classesNeedingReview = $classes->filter(fn (SchoolClass $c) => $c->needsAcademicMetadataReview())->values();

        return view('admin.classes', compact('classes', 'classesNeedingReview'));
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
        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('index_number', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
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
            'semester_id' => 'required|exists:semesters,id',
        ]);
        if ($redirect = $this->ensureHierarchy($validated)) {
            return $redirect;
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
            'semester_id' => 'required|exists:semesters,id',
        ]);
        if ($redirect = $this->ensureHierarchy($validated)) {
            return $redirect;
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
