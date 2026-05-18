<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\SchoolClass;
use App\Support\SchemaFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerController extends Controller
{
    public function index(): View
    {
        $with = ['schoolClass'];
        if (SchemaFeatures::hasClassLecturerPivot()) {
            $with[] = 'schoolClasses';
        }
        $lecturers = Lecturer::with($with)->latest()->paginate(15);

        return view('admin.lecturers', compact('lecturers'));
    }

    public function create(): View
    {
        $classes = SchoolClass::with(['faculty', 'department'])->orderBy('name')->get();

        return view('admin.lecturer-form', [
            'lecturer' => null,
            'classes' => $classes,
            'assignedClassIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'integer|exists:classes,id',
        ]);

        $classIds = array_values(array_unique(array_map('intval', $validated['class_ids'] ?? [])));
        $lecturer = Lecturer::create([
            'name' => $validated['name'],
            'class_id' => $classIds[0] ?? null,
        ]);
        $lecturer->syncAssignedClasses($classIds);

        return redirect()->route('dashboard.lecturers.index')->with('success', 'Lecturer added.');
    }

    public function edit(Lecturer $lecturer): View
    {
        $classes = SchoolClass::with(['faculty', 'department'])->orderBy('name')->get();
        if (SchemaFeatures::hasClassLecturerPivot()) {
            $lecturer->load('schoolClasses');
        }
        $assignedClassIds = $lecturer->assignedClassIds()->all();

        return view('admin.lecturer-form', [
            'lecturer' => $lecturer,
            'classes' => $classes,
            'assignedClassIds' => $assignedClassIds,
        ]);
    }

    public function update(Request $request, Lecturer $lecturer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'integer|exists:classes,id',
        ]);
        $classIds = array_values(array_unique(array_map('intval', $validated['class_ids'] ?? [])));
        $lecturer->update([
            'name' => $validated['name'],
            'class_id' => $classIds[0] ?? null,
        ]);
        $lecturer->syncAssignedClasses($classIds);

        return redirect()->route('dashboard.lecturers.index')->with('success', 'Lecturer updated.');
    }

    public function destroy(Request $request, Lecturer $lecturer): RedirectResponse
    {
        if (! $request->session()->has('admin_id')) {
            return redirect()->route('dashboard.lecturers.index')
                ->with('error', 'Only administrators can remove lecturers.');
        }

        $lecturer->courses()->update(['lecturer_id' => null]);
        if (SchemaFeatures::hasClassLecturerPivot()) {
            $lecturer->schoolClasses()->detach();
        }
        $lecturer->delete();

        return redirect()->route('dashboard.lecturers.index')->with('success', 'Lecturer removed.');
    }
}
