<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lecturer;
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
        $lecturers = $this->lecturersForForm();
        $venues = \App\Models\Venue::orderBy('name')->get();

        return view('admin.course-form', ['course' => null, 'classes' => $classes, 'lecturers' => $lecturers, 'venues' => $venues]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Day / time / lecturer / venue are now optional on the admin form —
        // class reps build the per-class timetable from this catalog entry.
        $validated = $request->validate([
            'course_name' => 'required|string|max:255',
            'course_code' => 'nullable|string|max:50',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'integer|exists:classes,id',
            'day_of_week' => 'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'nullable|date_format:H:i|required_with:end_time',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'credit_hours' => 'required|integer|min:1|max:12',
            'venue' => 'nullable|string|max:255',
            'venue_id' => 'nullable|exists:venues,id',
            'lecturer_name' => 'nullable|string|max:255',
            'lecturer_id' => 'nullable|exists:lecturers,id',
            'attendance_window_minutes' => 'nullable|integer|min:1',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'attendance_range_m' => 'nullable|integer|min:1|max:5000',
            'next_week_number' => 'nullable|integer|min:1|max:500',
        ]);
        $validated['attendance_window_minutes'] = $validated['attendance_window_minutes'] ?? 60;
        $validated['credit_hours'] = $validated['credit_hours'] ?? 2;
        $validated['lecturer_name'] = $this->syncLecturerName($validated);
        foreach (['location_lat', 'location_lng', 'attendance_range_m', 'next_week_number'] as $k) {
            if (array_key_exists($k, $validated) && $validated[$k] === '') {
                $validated[$k] = null;
            }
        }

        $classIds = $this->mergeClassIds($validated);
        $course = Course::create(collect($validated)->except(['class_ids', 'class_id'])->all());
        $course->syncAssignedClasses($classIds);

        return redirect()->route('dashboard.courses.index')->with('success', 'Course created.');
    }

    public function edit(Course $course): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        $lecturers = $this->lecturersForForm();
        $venues = \App\Models\Venue::orderBy('name')->get();
        if (SchemaFeatures::hasCourseClassPivot()) {
            $course->load('schoolClasses');
        }

        return view('admin.course-form', compact('course', 'classes', 'lecturers', 'venues'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $rules = [
            'course_name' => 'required|string|max:255',
            'course_code' => 'nullable|string|max:50',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'integer|exists:classes,id',
            'day_of_week' => 'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'nullable|date_format:H:i|required_with:end_time',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'credit_hours' => 'required|integer|min:1|max:12',
            'venue' => 'nullable|string|max:255',
            'venue_id' => 'nullable|exists:venues,id',
            'lecturer_name' => 'nullable|string|max:255',
            'lecturer_id' => 'nullable|exists:lecturers,id',
        ];
        if ($request->session()->has('admin_id')) {
            $rules['attendance_window_minutes'] = 'nullable|integer|min:1';
            $rules['location_lat'] = 'nullable|numeric';
            $rules['location_lng'] = 'nullable|numeric';
            $rules['attendance_range_m'] = 'nullable|integer|min:1|max:5000';
            $rules['next_week_number'] = 'nullable|integer|min:1|max:500';
        }
        $validated = $request->validate($rules);
        $validated['lecturer_name'] = $this->syncLecturerName($validated);
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

        $classIds = $this->mergeClassIds($validated);
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
     * Keep lecturer_name in sync when lecturer_id is set (for PDFs / legacy display).
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncLecturerName(array $validated): string
    {
        if (! empty($validated['lecturer_id'])) {
            $lecturer = Lecturer::find($validated['lecturer_id']);

            return $lecturer ? trim((string) $lecturer->name) : '';
        }

        return trim((string) ($validated['lecturer_name'] ?? ''));
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
     * @return \Illuminate\Database\Eloquent\Collection<int, Lecturer>
     */
    private function lecturersForForm(): \Illuminate\Database\Eloquent\Collection
    {
        $with = SchemaFeatures::hasClassLecturerPivot()
            ? ['schoolClasses', 'schoolClass']
            : ['schoolClass'];

        return Lecturer::with($with)->orderBy('name')->get();
    }
}
