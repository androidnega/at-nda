<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\University;
use App\Support\UniversityLogoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'school_logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $university = University::create([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
        ]);

        $this->syncFaculties($university, $validated['faculty_ids'] ?? []);
        $logoMessage = $this->handleLogo($request, $university);

        if ($logoMessage) {
            return redirect()
                ->route('dashboard.universities.edit', $university)
                ->with('error', $logoMessage)
                ->with('success', 'School saved, but the logo could not be stored.');
        }

        return redirect()->route('dashboard.universities.index')->with('success', 'School created');
    }

    public function edit(University $university): View
    {
        UniversityLogoStorage::purgeMissingFile($university);
        $university->refresh();

        $faculties = Faculty::query()->orderBy('name')->get();
        $assignedFacultyIds = $university->faculties()->pluck('id')->all();
        $logoPreviewSrc = UniversityLogoStorage::previewDataUri($university);

        return view('admin.universities.form', compact('university', 'faculties', 'assignedFacultyIds', 'logoPreviewSrc'));
    }

    public function update(Request $request, University $university): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'faculty_ids' => 'nullable|array',
            'faculty_ids.*' => 'integer|exists:faculties,id',
            'school_logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_school_logo' => 'nullable|boolean',
        ]);

        $university->update([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
        ]);

        $this->syncFaculties($university, $validated['faculty_ids'] ?? []);
        $logoMessage = $this->handleLogo($request, $university, $validated);

        if ($logoMessage) {
            return redirect()
                ->route('dashboard.universities.edit', $university)
                ->withInput()
                ->with('error', $logoMessage);
        }

        return redirect()->route('dashboard.universities.index')->with('success', 'School updated');
    }

    public function destroy(University $university): RedirectResponse
    {
        UniversityLogoStorage::remove($university);
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
     * @return string|null Error message when logo handling failed
     */
    private function handleLogo(Request $request, University $university, ?array $validated = null): ?string
    {
        if (! UniversityLogoStorage::ensureColumn()) {
            if ($request->hasFile('school_logo')) {
                return 'Could not save the logo: run database migrations (logo_path column missing).';
            }

            return null;
        }

        if ($university->logo_path && ! Storage::disk('public')->exists($university->logo_path)) {
            $university->forceFill(['logo_path' => null])->save();
            $university->refresh();
        }

        if ($validated && $request->boolean('remove_school_logo')) {
            UniversityLogoStorage::remove($university);
            $university->refresh();
        }

        if (! $request->hasFile('school_logo')) {
            return null;
        }

        $file = $request->file('school_logo');
        if (! $file || ! $file->isValid()) {
            return 'The logo file could not be uploaded. Check file size (max 4 MB) and try again.';
        }

        if (! UniversityLogoStorage::store($university, $file)) {
            return 'The logo was uploaded but could not be saved to storage. Run php artisan storage:link on the server.';
        }

        $university->refresh();

        return null;
    }
}
