<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\University;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'school_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $university = University::create([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
        ]);

        $this->syncFaculties($university, $validated['faculty_ids'] ?? []);
        $this->storeLogo($request, $university);

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
            'school_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_school_logo' => 'nullable|boolean',
        ]);

        $university->update([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
        ]);

        $this->syncFaculties($university, $validated['faculty_ids'] ?? []);
        $this->storeLogo($request, $university, $validated);

        return redirect()->route('dashboard.universities.index')->with('success', 'School updated');
    }

    public function destroy(University $university): RedirectResponse
    {
        if ($university->logo_path) {
            Storage::disk('public')->delete($university->logo_path);
        }
        Faculty::query()->where('university_id', $university->id)->update(['university_id' => null]);
        $university->delete();

        return redirect()->route('dashboard.universities.index')->with('success', 'School deleted');
    }

    /**
     * @param  list<int|string>  $facultyIds
     */
    private function syncFaculties(University $university, array $facultyIds): void
    {
        $facultyIds = collect($facultyIds)->map(fn ($id) => (int) $id)->all();

        Faculty::query()
            ->where('university_id', $university->id)
            ->whereNotIn('id', $facultyIds)
            ->update(['university_id' => null]);

        if ($facultyIds !== []) {
            Faculty::query()->whereIn('id', $facultyIds)->update(['university_id' => $university->id]);
        }
    }

  /**
     * @param  array<string, mixed>|null  $validated
     */
    private function storeLogo(Request $request, University $university, ?array $validated = null): void
    {
        if (! Schema::hasColumn('universities', 'logo_path')) {
            return;
        }

        if ($validated && $request->boolean('remove_school_logo') && $university->logo_path) {
            Storage::disk('public')->delete($university->logo_path);
            $university->logo_path = null;
            $university->save();
        }

        if ($request->hasFile('school_logo')) {
            if ($university->logo_path) {
                Storage::disk('public')->delete($university->logo_path);
            }
            $university->logo_path = $request->file('school_logo')->store('school-logos', 'public');
            $university->save();
        }
    }
}
