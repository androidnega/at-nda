<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerController extends Controller
{
    public function index(): View
    {
        $lecturers = Lecturer::with('schoolClass')->latest()->paginate(15);

        return view('admin.lecturers', compact('lecturers'));
    }

    public function create(): View
    {
        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.lecturer-form', ['lecturer' => null, 'classes' => $classes]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        Lecturer::create([
            'name' => $validated['name'],
            'class_id' => $validated['class_id'] ?? null,
        ]);

        return redirect()->route('dashboard.lecturers.index')->with('success', 'Lecturer added.');
    }

    public function edit(Lecturer $lecturer): View
    {
        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.lecturer-form', ['lecturer' => $lecturer, 'classes' => $classes]);
    }

    public function update(Request $request, Lecturer $lecturer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'nullable|exists:classes,id',
        ]);
        $lecturer->update($validated);

        return redirect()->route('dashboard.lecturers.index')->with('success', 'Lecturer updated.');
    }

    public function destroy(Request $request, Lecturer $lecturer): RedirectResponse
    {
        if (! $request->session()->has('admin_id')) {
            return redirect()->route('dashboard.lecturers.index')
                ->with('error', 'Only administrators can remove lecturers.');
        }

        $lecturer->courses()->update(['lecturer_id' => null]);
        $lecturer->delete();

        return redirect()->route('dashboard.lecturers.index')->with('success', 'Lecturer removed.');
    }
}
