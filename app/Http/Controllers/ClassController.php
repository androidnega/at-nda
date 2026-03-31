<?php

namespace App\Http\Controllers;

use App\Imports\ClassStudentsImport;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\SchoolClass;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ClassController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::with(['faculty', 'department', 'semester'])
            ->withCount(['courses', 'students'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();
        $classesNeedingReview = $classes->filter(fn (SchoolClass $c) => $c->needsAcademicMetadataReview())->values();

        return view('admin.classes', compact('classes', 'classesNeedingReview'));
    }

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $schoolClass->load(['faculty', 'department']);
        $query = $schoolClass->students()->orderBy('last_name')->orderBy('first_name');

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
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        Excel::import(new ClassStudentsImport($schoolClass), $request->file('file'));

        return redirect()->route('dashboard.classes.show', $schoolClass)->with('success', 'Students imported successfully.');
    }

    public function create(): View
    {
        $faculties = Faculty::with('departments')->orderBy('name')->get();
        $semesters = Semester::orderByDesc('year_label')->orderByDesc('term')->get();

        return view('admin.class-form', ['schoolClass' => null, 'faculties' => $faculties, 'semesters' => $semesters]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255',
            'level' => 'required|in:100,200,300,400',
            'semester_id' => 'required|exists:semesters,id',
            'class_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
        $dept = Department::find($validated['department_id']);
        if ($dept && $dept->faculty_id != $validated['faculty_id']) {
            return back()->withInput()->with('error', 'Department must belong to selected faculty');
        }
        $class = SchoolClass::create(collect($validated)->except('class_logo')->all());
        if ($request->hasFile('class_logo')) {
            $class->logo_path = $request->file('class_logo')->store('class-logos', 'public');
            $class->save();
        }
        return redirect()->route('dashboard.classes.index')->with('success', 'Class created');
    }

    public function edit(SchoolClass $schoolClass): View
    {
        $faculties = Faculty::with('departments')->orderBy('name')->get();
        $semesters = Semester::orderByDesc('year_label')->orderByDesc('term')->get();

        return view('admin.class-form', ['schoolClass' => $schoolClass, 'faculties' => $faculties, 'semesters' => $semesters]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validate([
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255',
            'level' => 'required|in:100,200,300,400',
            'semester_id' => 'required|exists:semesters,id',
            'class_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_class_logo' => 'nullable|boolean',
        ]);
        $dept = Department::find($validated['department_id']);
        if ($dept && $dept->faculty_id != $validated['faculty_id']) {
            return back()->withInput()->with('error', 'Department must belong to selected faculty');
        }
        $schoolClass->update(collect($validated)->except(['class_logo', 'remove_class_logo'])->all());
        if ($request->boolean('remove_class_logo') && $schoolClass->logo_path) {
            Storage::disk('public')->delete($schoolClass->logo_path);
            $schoolClass->logo_path = null;
        }
        if ($request->hasFile('class_logo')) {
            if ($schoolClass->logo_path) {
                Storage::disk('public')->delete($schoolClass->logo_path);
            }
            $schoolClass->logo_path = $request->file('class_logo')->store('class-logos', 'public');
        }
        $schoolClass->save();
        return redirect()->route('dashboard.classes.index')->with('success', 'Class updated');
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
}
