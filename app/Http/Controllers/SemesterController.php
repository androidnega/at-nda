<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(): View
    {
        $semesters = Semester::query()
            ->withCount('schoolClasses')
            ->orderByDesc('year_label')
            ->orderByDesc('term')
            ->get();

        return view('admin.semesters', compact('semesters'));
    }

    public function create(): View
    {
        return view('admin.semester-form', ['semester' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_label' => 'required|string|max:32',
            'term' => 'required|integer|in:1,2',
            'label' => 'nullable|string|max:128',
        ]);

        Semester::create($validated);

        return redirect()->route('dashboard.semesters.index')->with('success', 'Semester added.');
    }

    public function edit(Semester $semester): View
    {
        return view('admin.semester-form', compact('semester'));
    }

    public function update(Request $request, Semester $semester): RedirectResponse
    {
        $validated = $request->validate([
            'year_label' => 'required|string|max:32',
            'term' => 'required|integer|in:1,2',
            'label' => 'nullable|string|max:128',
        ]);

        $semester->update($validated);

        return redirect()->route('dashboard.semesters.index')->with('success', 'Semester updated.');
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        if ($semester->schoolClasses()->exists()) {
            return back()->with('error', 'Cannot delete a semester that is assigned to classes. Reassign or clear classes first.');
        }
        $semester->delete();

        return redirect()->route('dashboard.semesters.index')->with('success', 'Semester removed.');
    }
}
