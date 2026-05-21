<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\SchoolClass;
use App\Support\SchemaFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $courses = Course::with(['schoolClasses', 'lecturer.schoolClasses', 'lecturer.schoolClass', 'venueRelation'])
            ->latest()
            ->paginate(10);

        return view('admin.courses', compact('courses'));
    }

    public function create(): View
    {
        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.course-form', ['course' => null, 'classes' => $classes]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Day & time are now owned by class reps in the per-class timetable,
        // so the admin form only captures the catalog metadata + defaults.
        $validated = $request->validate([
            'course_name' => 'required|string|max:255',
            'course_code' => 'nullable|string|max:50',
            'qualification' => 'nullable|in:'.implode(',', Course::QUALIFICATIONS),
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'integer|exists:classes,id',
            'attendance_window_minutes' => 'nullable|integer|min:1',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'attendance_range_m' => 'nullable|integer|min:1|max:5000',
            'next_week_number' => 'nullable|integer|min:1|max:500',
        ]);
        $validated['attendance_window_minutes'] = $validated['attendance_window_minutes'] ?? 60;
        foreach (['location_lat', 'location_lng', 'attendance_range_m', 'next_week_number'] as $k) {
            if (array_key_exists($k, $validated) && $validated[$k] === '') {
                $validated[$k] = null;
            }
        }
        $validated['qualification'] = $this->normaliseQualification($validated['qualification'] ?? null);
        if (! SchemaFeatures::hasCoursesQualification()) {
            unset($validated['qualification']);
        }

        $classIds = $this->mergeClassIds($validated);
        if (! empty($validated['qualification'])) {
            $classIds = $this->filterClassIdsByQualification($classIds, $validated['qualification']);
            if ($classIds === []) {
                return back()->withInput()->withErrors([
                    'class_ids' => 'No selected class matches the chosen qualification.',
                ]);
            }
        }
        $course = Course::create(collect($validated)->except(['class_ids', 'class_id'])->all());
        $course->syncAssignedClasses($classIds);

        return redirect()->route('dashboard.courses.index')->with('success', 'Course created.');
    }

    public function edit(Course $course): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        if (SchemaFeatures::hasCourseClassPivot()) {
            $course->load('schoolClasses');
        }

        return view('admin.course-form', compact('course', 'classes'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $rules = [
            'course_name' => 'required|string|max:255',
            'course_code' => 'nullable|string|max:50',
            'qualification' => 'nullable|in:'.implode(',', Course::QUALIFICATIONS),
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'integer|exists:classes,id',
        ];
        if ($request->session()->has('admin_id')) {
            $rules['attendance_window_minutes'] = 'nullable|integer|min:1';
            $rules['location_lat'] = 'nullable|numeric';
            $rules['location_lng'] = 'nullable|numeric';
            $rules['attendance_range_m'] = 'nullable|integer|min:1|max:5000';
            $rules['next_week_number'] = 'nullable|integer|min:1|max:500';
        }
        $validated = $request->validate($rules);
        if ($request->session()->has('admin_id')) {
            $validated['attendance_window_minutes'] = $validated['attendance_window_minutes'] ?? $course->attendance_window_minutes ?? 60;
            foreach (['location_lat', 'location_lng', 'attendance_range_m', 'next_week_number'] as $k) {
                if (array_key_exists($k, $validated) && $validated[$k] === '') {
                    $validated[$k] = null;
                }
            }
        } else {
            unset($validated['attendance_window_minutes']);
        }
        $validated['qualification'] = $this->normaliseQualification($validated['qualification'] ?? null);
        if (! SchemaFeatures::hasCoursesQualification()) {
            unset($validated['qualification']);
        }

        $classIds = $this->mergeClassIds($validated);
        if (! empty($validated['qualification'])) {
            $classIds = $this->filterClassIdsByQualification($classIds, $validated['qualification']);
            if ($classIds === []) {
                return back()->withInput()->withErrors([
                    'class_ids' => 'No selected class matches the chosen qualification.',
                ]);
            }
        }
        $course->update(collect($validated)->except(['class_ids', 'class_id'])->all());
        $course->syncAssignedClasses($classIds);

        return redirect()->route('dashboard.courses.index')->with('success', 'Course updated.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()->route('dashboard.courses.index')->with('success', 'Course deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function mergeClassIds(array $validated): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $validated['class_ids'] ?? []))));
    }

    /**
     * Normalise an inbound qualification value to one of the canonical
     * keys (or null when the catalog applies to all qualifications).
     */
    private function normaliseQualification(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || ! in_array($value, Course::QUALIFICATIONS, true)) {
            return null;
        }

        return $value;
    }

    /**
     * Drop any class IDs whose qualification doesn't match the target. Used
     * to prevent silently smuggling a DEGREE class onto an HND-only course
     * (e.g. via a stale form submission).
     *
     * @param  list<int>  $classIds
     * @return list<int>
     */
    private function filterClassIdsByQualification(array $classIds, string $qualification): array
    {
        if ($classIds === [] || ! SchemaFeatures::hasClassesQualification()) {
            return $classIds;
        }

        return SchoolClass::query()
            ->whereIn('id', $classIds)
            ->where(function ($q) use ($qualification) {
                $q->where('qualification', $qualification)
                    ->orWhereNull('qualification');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
