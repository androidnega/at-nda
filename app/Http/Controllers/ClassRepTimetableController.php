<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Venue;
use App\Support\ClassTimetableAccess;
use App\Support\SchemaFeatures;
use App\Support\TimetableTextParser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Per-class timetable CRUD for class reps. Each class owns its own slots so
 * edits never affect another class that shares the same course (e.g. two
 * cohorts sitting Software Engineering with different lecturers / times).
 */
class ClassRepTimetableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $classes = $this->managedClasses($student);
        if ($classes->isEmpty()) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'You are not assigned to any class.');
        }

        $selectedClassId = $this->resolveSelectedClassId($request, $classes);
        $selectedClass = $classes->firstWhere('id', $selectedClassId);

        $entries = ClassTimetableAccess::entriesForClass($selectedClassId);
        $availableCourses = ClassTimetableAccess::coursesAssignableToClass($selectedClass);
        $availableLecturers = $this->availableLecturersForClass($selectedClass);
        $availableVenues = Venue::query()->orderBy('name')->get();

        return view('classrep.timetable', [
            'student' => $student,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'entries' => $entries,
            'availableCourses' => $availableCourses,
            'availableLecturers' => $availableLecturers,
            'availableVenues' => $availableVenues,
            'days' => ClassTimetable::DAYS,
            'dashboardRole' => 'classrep',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $validated = $this->validateEntry($request, $student);
        $validated['start_time'] = $this->normalizeTime($validated['start_time']);
        $validated['end_time'] = $this->normalizeTime($validated['end_time']);
        // Reps only choose existing venues — clear any legacy free-text value.
        $validated['venue'] = null;
        $entry = ClassTimetable::create($validated + [
            'created_by_student_id' => $student->id,
        ]);

        return redirect()
            ->route('dashboard.timetable.manage', ['class_id' => $entry->class_id])
            ->with('success', 'Timetable slot added.');
    }

    public function update(Request $request, ClassTimetable $entry): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $this->ensureRepOwnsClass($student, (int) $entry->class_id);

        $validated = $this->validateEntry($request, $student, $entry);
        $validated['start_time'] = $this->normalizeTime($validated['start_time']);
        $validated['end_time'] = $this->normalizeTime($validated['end_time']);
        $validated['venue'] = null;
        $entry->update($validated);

        return redirect()
            ->route('dashboard.timetable.manage', ['class_id' => $entry->class_id])
            ->with('success', 'Timetable slot updated.');
    }

    public function destroy(Request $request, ClassTimetable $entry): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $this->ensureRepOwnsClass($student, (int) $entry->class_id);
        $classId = (int) $entry->class_id;
        $entry->delete();

        return redirect()
            ->route('dashboard.timetable.manage', ['class_id' => $classId])
            ->with('success', 'Timetable slot removed.');
    }

    public function bulkTemplate(): Response
    {
        $body = $this->templateText();
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="timetable-template.txt"',
        ]);
    }

    public function bulkImport(Request $request): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $data = $request->validate([
            'class_id' => 'required|integer',
            'pasted_text' => 'nullable|string|max:50000',
            'file' => 'nullable|file|max:2048|mimetypes:text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/octet-stream',
        ]);
        $classId = (int) $data['class_id'];
        $this->ensureRepOwnsClass($student, $classId);

        $class = SchoolClass::query()->findOrFail($classId);

        $text = $this->resolveImportText($request, $data['pasted_text'] ?? null);
        if ($text === null || trim($text) === '') {
            return redirect()
                ->route('dashboard.timetable.manage', ['class_id' => $classId])
                ->with('error', 'Paste the timetable text or upload a .txt / .docx file before importing.');
        }

        $parsed = TimetableTextParser::parse($text);
        if ($parsed['slots'] === []) {
            return redirect()
                ->route('dashboard.timetable.manage', ['class_id' => $classId])
                ->with('error', 'No timetable rows could be read. Make sure each day is on its own line, followed by Time / Course / Lecturer / VENUE lines.');
        }

        return $this->applyParsedSlots($student, $class, $parsed);
    }

    /**
     * @param  array{slots: list<array<string, mixed>>, warnings: list<string>}  $parsed
     */
    private function applyParsedSlots(Student $student, SchoolClass $class, array $parsed): RedirectResponse
    {
        $assignableCourses = ClassTimetableAccess::coursesAssignableToClass($class);
        $lecturers = Lecturer::query()->orderBy('name')->get();
        $venues = Venue::query()->orderBy('name')->get();

        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($parsed['slots'] as $slot) {
            $course = $this->matchCourse($assignableCourses, $slot['course_code'] ?? null, $slot['course_name'] ?? null);
            if (! $course) {
                $skipped[] = $slot['day'].' '.$slot['start_time'].' — course not linked to this class ('
                    .($slot['course_code'] ?? $slot['course_name'] ?? 'unknown').')';
                continue;
            }

            $start = $slot['start_time'];
            $end = $slot['end_time'];

            $lecturer = $this->matchLecturer($lecturers, $slot['lecturer'] ?? null);
            $venue = $this->matchVenue($venues, $slot['venue'] ?? null);

            if (! $venue) {
                $skipped[] = $slot['day'].' '.$start.' — '.($course->course_code ?: $course->course_name)
                    .': venue "'.($slot['venue'] ?? 'unspecified').'" is not registered. Ask admin to add it under Venues, then re-import.';
                continue;
            }

            $payload = [
                'day_of_week' => $slot['day'],
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'lecturer_id' => $lecturer?->id,
                'venue_id' => $venue->id,
                'venue' => null,
            ];

            // One course can only have ONE slot per class — re-importing
            // overwrites the time / lecturer / venue for that course, but
            // never touches attendance, sessions or other class data.
            $existing = ClassTimetable::query()
                ->where('class_id', $class->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                ClassTimetable::create($payload + [
                    'class_id' => $class->id,
                    'course_id' => $course->id,
                    'created_by_student_id' => $student->id,
                ]);
                $created++;
            }
        }

        $messages = $parsed['warnings'];
        foreach ($skipped as $line) {
            $messages[] = $line;
        }

        $redirect = redirect()->route('dashboard.timetable.manage', ['class_id' => $class->id]);
        if ($created === 0 && $updated === 0) {
            return $redirect->with('error', 'No slots were added or updated. '.($messages[0] ?? 'Check that the courses exist for this class.'))
                ->with('import_messages', $messages);
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = 'added '.$created.' new slot'.($created === 1 ? '' : 's');
        }
        if ($updated > 0) {
            $parts[] = 'updated '.$updated.' existing slot'.($updated === 1 ? '' : 's');
        }
        $summary = 'Timetable import: '.implode(' and ', $parts).'.';
        if ($skipped !== []) {
            $summary .= ' Skipped '.count($skipped).' row'.(count($skipped) === 1 ? '' : 's').' — see details below.';
        }
        return $redirect->with('success', $summary)->with('import_messages', $messages);
    }

    private function resolveImportText(Request $request, ?string $pasted): ?string
    {
        $pasted = trim((string) ($pasted ?? ''));
        if ($pasted !== '') {
            return $pasted;
        }

        $file = $request->file('file');
        if (! $file) {
            return null;
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'docx' || $file->getMimeType() === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return TimetableTextParser::extractDocxText($file->getRealPath());
        }

        $contents = @file_get_contents($file->getRealPath());
        return $contents !== false ? $contents : null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Course>  $courses
     */
    private function matchCourse(\Illuminate\Database\Eloquent\Collection $courses, ?string $code, ?string $name): ?Course
    {
        $normalize = static fn (?string $s) => $s === null ? '' : strtolower(preg_replace('/\s+/u', '', $s) ?? $s);

        if ($code !== null) {
            $needle = $normalize($code);
            foreach ($courses as $c) {
                if ($needle !== '' && $normalize($c->course_code) === $needle) {
                    return $c;
                }
            }
        }
        if ($name !== null) {
            $needle = $normalize($name);
            foreach ($courses as $c) {
                if ($needle !== '' && $normalize($c->course_name) === $needle) {
                    return $c;
                }
            }
            foreach ($courses as $c) {
                if ($needle !== '' && str_contains($normalize($c->course_name), $needle)) {
                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Lecturer>  $lecturers
     */
    private function matchLecturer(\Illuminate\Database\Eloquent\Collection $lecturers, ?string $raw): ?Lecturer
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $needle = $this->normaliseLecturerName($raw);
        if ($needle === '') {
            return null;
        }
        foreach ($lecturers as $l) {
            if ($this->normaliseLecturerName((string) $l->name) === $needle) {
                return $l;
            }
        }
        foreach ($lecturers as $l) {
            $n = $this->normaliseLecturerName((string) $l->name);
            if ($n !== '' && (str_contains($n, $needle) || str_contains($needle, $n))) {
                return $l;
            }
        }
        return null;
    }

    private function normaliseLecturerName(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/\b(mr|mrs|ms|miss|prof|dr|sir|madam|prof\.?)\b\.?/u', '', $value) ?? $value;
        $value = preg_replace('/[^a-z]+/u', '', $value) ?? $value;
        return $value;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Venue>  $venues
     */
    private function matchVenue(\Illuminate\Database\Eloquent\Collection $venues, ?string $raw): ?Venue
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $normalize = static fn (string $v) => strtolower(preg_replace('/\s+/u', '', $v) ?? $v);
        $needle = $normalize($raw);
        foreach ($venues as $v) {
            if ($v->code && $normalize((string) $v->code) === $needle) {
                return $v;
            }
            if ($normalize((string) $v->name) === $needle) {
                return $v;
            }
        }
        foreach ($venues as $v) {
            if ($v->code && str_contains($needle, $normalize((string) $v->code))) {
                return $v;
            }
            if (str_contains($needle, $normalize((string) $v->name))) {
                return $v;
            }
        }
        return null;
    }

    private function templateText(): string
    {
        return <<<'TXT'
TIMETABLE

# Replace the example rows below with your real schedule. Keep the layout:
# DAY (Monday-Sunday)
# Time range (e.g. 9AM - 11AM or 09:00-11:00)
# Course code - Course name (course must already be linked to your class)
# Lecturer name (any honorifics are fine: MR., MRS., MISS, DR., PROF.)
# VENUE: Lecture hall name or code

MONDAY
9AM - 11AM
BIT236 - SOFTWARE ENGINEERING
MR. HILARY ACKAH-ARTHUR
VENUE: OBTF 7

TUESDAY
1PM - 3PM
DTM202 - PRINCIPLES OF MANAGEMENT
MR. AMOS KWASI AMOFA
VENUE: OBFF 6

3PM - 5PM
BIT238 - COMPUTER NETWORKS
MR. FRANK AMOANI ARTHUR
VENUE: OBFF 6

WEDNESDAY
9AM - 11AM
BIT240 - E-COMMERCE
MISS ANGELA ABA OTCHERE
VENUE: OBTF 1

THURSDAY
9AM - 11AM
BIT242 - OPERATING SYSTEM CONCEPTS
MR. JOSEPH DANSO
VENUE: OBTF 7

FRIDAY
7AM - 9AM
BIT232 - HUMAN COMPUTER INTERACTION
PAPA KWEKU ABAIDOO
VENUE: OBFF 6

9AM - 11AM
BIT230 - JAVA PROGRAMMING
MR. ROBERT FRENCH-BAIDOO
VENUE: OBSF 7
TXT;
    }

    private function requireRep(Request $request): Student|RedirectResponse
    {
        $student = Student::find($request->session()->get('student_id'));
        if (! $student) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }
        if (! $student->isClassRep()) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'Only class reps can manage the timetable.');
        }
        if (! SchemaFeatures::hasClassTimetables()) {
            return redirect()->route('dashboard.timetable')
                ->with('error', 'Timetable management is not yet available. Run migrations to enable it.');
        }

        return $student;
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function managedClasses(Student $student): Collection
    {
        $ids = $student->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return new Collection;
        }

        return SchoolClass::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    private function resolveSelectedClassId(Request $request, Collection $classes): int
    {
        $requested = (int) $request->query('class_id', 0);
        if ($requested > 0 && $classes->firstWhere('id', $requested)) {
            return $requested;
        }

        return (int) $classes->first()->id;
    }

    /**
     * Lecturers a rep may assign: explicit class_lecturer pivot plus anyone
     * teaching at least one course in this class (so reps see the full pool
     * without depending on admin to populate the pivot).
     *
     * @return Collection<int, Lecturer>
     */
    private function availableLecturersForClass(SchoolClass $class): Collection
    {
        $ids = collect();

        if (SchemaFeatures::hasClassLecturerPivot()) {
            $ids = $ids->merge($class->lecturers()->pluck('lecturers.id'));
        }

        $courseIds = ClassTimetableAccess::coursesAssignableToClass($class)->pluck('id');
        if ($courseIds->isNotEmpty()) {
            $ids = $ids->merge(
                Lecturer::query()
                    ->whereIn('id', function ($sub) use ($courseIds) {
                        $sub->select('lecturer_id')
                            ->from('courses')
                            ->whereIn('id', $courseIds)
                            ->whereNotNull('lecturer_id');
                    })
                    ->pluck('id')
            );
        }

        $ids = $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return Lecturer::query()->orderBy('name')->get();
        }

        return Lecturer::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function ensureRepOwnsClass(Student $student, int $classId): void
    {
        $managed = $student->repManagedClassIds()->map(fn ($id) => (int) $id);
        if (! $managed->contains($classId)) {
            abort(403, 'You may only edit the timetable of a class you rep.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntry(Request $request, Student $student, ?ClassTimetable $existing = null): array
    {
        $managed = $student->repManagedClassIds()->map(fn ($id) => (int) $id)->all();
        if ($managed === []) {
            abort(403, 'You are not assigned to any class.');
        }

        $rules = [
            'class_id' => ['required', 'integer', function ($attr, $value, $fail) use ($managed) {
                if (! in_array((int) $value, $managed, true)) {
                    $fail('You may only edit the timetable of a class you rep.');
                }
            }],
            'course_id' => 'required|integer|exists:courses,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'lecturer_id' => 'nullable|integer|exists:lecturers,id',
            'venue_id' => 'required|integer|exists:venues,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request, $existing) {
            $classId = (int) $request->input('class_id');
            $courseId = (int) $request->input('course_id');
            $day = (string) $request->input('day_of_week');
            $start = (string) $request->input('start_time');
            if ($classId <= 0 || $courseId <= 0 || $day === '' || $start === '') {
                return;
            }

            $query = ClassTimetable::query()
                ->where('class_id', $classId)
                ->where('course_id', $courseId)
                ->where('day_of_week', $day)
                ->where(function ($q) use ($start) {
                    $q->where('start_time', $start)
                        ->orWhere('start_time', $start.':00');
                });
            if ($existing) {
                $query->where('id', '!=', $existing->id);
            }
            if ($query->exists()) {
                $v->errors()->add('start_time', 'This class already has a slot for this course at that day & time.');
            }
        });

        return $validator->validated();
    }
}
