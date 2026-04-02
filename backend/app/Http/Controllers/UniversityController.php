<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\University;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UniversityController extends Controller
{
    public function index(): View
    {
        $universities = University::query()
            ->withCount('faculties')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.universities.index', compact('universities'));
    }

    public function create(): View
    {
        $faculties = Faculty::query()->orderBy('name')->get();

        return view('admin.universities.form', [
            'university' => null,
            'faculties' => $faculties,
            'assignedFacultyIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'faculty_ids' => 'nullable|array',
            'faculty_ids.*' => 'integer|exists:faculties,id',
        ]);

        $university = new University();
        $university->name = $validated['name'];
        $university->location = $validated['location'] ?? null;
        $university->save();

        $facultyIds = $validated['faculty_ids'] ?? [];
        if (!empty($facultyIds)) {
            Faculty::query()->whereIn('id', $facultyIds)->update(['university_id' => $university->id]);
        }

        return redirect()->route('dashboard.universities.index')->with('success', 'School created');
    }

    public function edit(University $university): View
    {
        $faculties = Faculty::query()->orderBy('name')->get();
        $assignedFacultyIds = $university->faculties()->pluck('id')->all();

        return view('admin.universities.form', compact('university', 'faculties', 'assignedFacultyIds'));
    }

    public function update(Request $request, University $university): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'faculty_ids' => 'nullable|array',
            'faculty_ids.*' => 'integer|exists:faculties,id',
        ]);

        $university->name = $validated['name'];
        $university->location = $validated['location'] ?? null;
        $university->save();

        $facultyIds = collect($validated['faculty_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        Faculty::query()
            ->where('university_id', $university->id)
            ->whereNotIn('id', $facultyIds)
            ->update(['university_id' => null]);

        if (!empty($facultyIds)) {
            Faculty::query()->whereIn('id', $facultyIds)->update(['university_id' => $university->id]);
        }

        return redirect()->route('dashboard.universities.index')->with('success', 'School updated');
    }

    public function destroy(University $university): RedirectResponse
    {
        Faculty::query()->where('university_id', $university->id)->update(['university_id' => null]);
        $university->delete();

        return redirect()->route('dashboard.universities.index')->with('success', 'School deleted');
    }
}
