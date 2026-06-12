<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceWeek;
use App\Models\ClassRep;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\University;
use App\Support\RepCourseAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AttendancePdfController extends Controller
{
    /**
     * PDF preview for the whole semester. ALL configured semester weeks
     * are shown — e.g. a class configured for 4 weeks gets exactly 4
     * week columns (W1..W4) whether or not each one has been held yet.
     * Cancelled weeks render with the vertical "CANCELLED" stripe; held
     * weeks render present/absent marks; un-held weeks render a dash.
     */
    public function export(Request $request, Course $course): Response
    {
        $this->authorizePdfExport($request, $course);
        $weeks = $this->fullSemesterWeeks($course, $request);
        return $this->renderPdf($request, $course, $weeks, 'all');
    }

    /**
     * PDF preview for a single teaching week of this course / rep class.
     */
    public function exportWeek(Request $request, Course $course, AttendanceWeek $attendanceWeek): Response
    {
        $this->authorizePdfExport($request, $course);
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }
        $weeks = collect([$attendanceWeek]);
        return $this->renderPdf($request, $course, $weeks, 'week-'.$attendanceWeek->week_number);
    }

    /**
     * @param  Collection<int, AttendanceWeek>  $weeks
     */
    private function renderPdf(Request $request, Course $course, Collection $weeks, string $slugSuffix): Response
    {
        $course->loadMissing(['lecturer', 'venueRelation', 'schoolClass.faculty.university', 'schoolClass.department']);

        $studentsQuery = $course->studentsQuery();
        $studentId = $request->session()->get('student_id');
        if ($studentId && ! $request->session()->has('admin_id')) {
            $rep = Student::find($studentId);
            if ($rep) {
                $scopedClassIds = RepCourseAccess::scopedClassIdsForCourse($rep, $course);
                if ($scopedClassIds !== []) {
                    $studentsQuery = Student::query()
                        ->whereIn('class_id', $scopedClassIds);
                }
            }
        }
        $students = $studentsQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $lecturerDisplay = $course->resolvedLecturerDisplay();

        $className = trim((string) $course->assignedClassesLabel());
        if ($className === '') {
            $className = trim((string) ($course->schoolClass?->name ?? ''));
        }
        if ($className === '') {
            $className = '—';
        }
        $institutionName = trim((string) ($course->schoolClass?->faculty?->university?->name ?? ''));
        if ($institutionName === '') {
            $institutionName = (string) (University::query()->orderBy('id')->value('name') ?? '');
        }
        if ($institutionName === '') {
            $institutionName = (string) config('app.institution_name', '');
        }
        if ($institutionName === '') {
            $institutionName = '—';
        }
        $facultyName = (string) ($course->schoolClass?->faculty?->name ?? '—');
        $departmentName = (string) ($course->schoolClass?->department?->name ?? '—');

        $classLogoDataUri = null;
        $university = $course->schoolClass?->resolveUniversity();
        $logoPath = $university?->logo_path ?: $course->schoolClass?->logo_path;
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $abs = Storage::disk('public')->path($logoPath);
            $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
            $bin = @file_get_contents($abs);
            if ($bin !== false && $bin !== '') {
                $classLogoDataUri = 'data:' . $mime . ';base64,' . base64_encode($bin);
            }
        }

        $venueDisplay = trim((string) ($course->venueRelation?->name ?? ''));
        if ($venueDisplay === '' && ! empty($course->venue)) {
            $venueDisplay = trim((string) $course->venue);
        }
        if ($venueDisplay === '') {
            $venueDisplay = '—';
        }

        $attendanceByStudent = [];
        foreach ($students as $student) {
            $row = ['student' => $student, 'weeks' => []];
            foreach ($weeks as $week) {
                if ($week->isCancelled()) {
                    $row['weeks'][$week->week_number] = 'cancelled';
                    continue;
                }
                // Pseudo / un-held week (id=0) — no class held yet,
                // so leave the slot unset; the view will draw a dash.
                if (! $week->id) {
                    continue;
                }
                $marked = Attendance::where('student_id', $student->id)
                    ->where('course_id', $course->id)
                    ->where('attendance_week_id', $week->id)
                    ->exists();
                $row['weeks'][$week->week_number] = $marked;
            }
            $attendanceByStudent[] = $row;
        }

        $courseTitle = trim($course->course_name.($course->course_code ? ' - '.$course->course_code : ''));

        // Map of student_id => role for everyone in this class who is a
        // class rep. We render a small "REP" / "ASSIST" badge next to their
        // index number on the PDF so lecturers know who their class
        // contacts are at a glance.
        $repRolesByStudent = $this->repRolesForStudents($students);

        // Cancelled-week summary printed below the grid so lecturers can
        // see *why* a column is marked CANCELLED without hovering tooltips.
        $cancelledWeeks = $weeks->filter(fn (AttendanceWeek $w) => $w->isCancelled())->values();

        // Pre-compute the vertical CANCELLED letter for each
        // (week_number, row_index) so the Blade view can render the
        // word stacked top-to-bottom inside each cancelled column,
        // matching the handwritten paper-register convention.
        $cancelledLetterByWeekAndRow = $this->buildCancelledLetterMap($weeks, count($attendanceByStudent));

        $pdf = Pdf::loadView('admin.pdf.attendance', [
            'course' => $course,
            'courseTitle' => $courseTitle,
            'institutionName' => $institutionName,
            'facultyName' => $facultyName,
            'departmentName' => $departmentName,
            'lecturerDisplay' => $lecturerDisplay,
            'className' => $className,
            'classLogoDataUri' => $classLogoDataUri,
            'venueDisplay' => $venueDisplay,
            'weeks' => $weeks,
            'cancelledWeeks' => $cancelledWeeks,
            'attendanceByStudent' => $attendanceByStudent,
            'repRolesByStudent' => $repRolesByStudent,
            'cancelledLetterByWeekAndRow' => $cancelledLetterByWeekAndRow,
        ]);

        return $pdf->stream('attendance-'.\Str::slug($course->course_name).'-'.$slugSuffix.'.pdf', ['Attachment' => false]);
    }

    /**
     * Build the full week-by-week grid for the semester export. The grid
     * is exactly `semester_weeks` columns wide (the value the admin set
     * on the class) — every week is shown, whether or not it has been
     * held. Real AttendanceWeek rows are returned where they exist (so
     * cancellations and attendance lookups continue to work); week
     * numbers with no row yet are filled with an unsaved placeholder so
     * the view can still render a column for them.
     *
     * Scoped to the rep's class when a class-rep is logged in, so reps
     * see only the weeks their own class actually had.
     *
     * @return Collection<int, AttendanceWeek>
     */
    private function fullSemesterWeeks(Course $course, Request $request): Collection
    {
        $weeksQuery = $course->attendanceWeeks()->orderBy('week_number');

        $scopedClassIds = $this->scopedClassIdsForRequest($course, $request);
        if ($scopedClassIds !== []
            && \App\Support\SchemaFeatures::hasAttendanceWeeksClassId()
        ) {
            $weeksQuery->where(function ($q) use ($scopedClassIds) {
                $q->whereIn('class_id', $scopedClassIds)->orWhereNull('class_id');
            });
        }

        $realWeeks = $weeksQuery->get()->keyBy(fn (AttendanceWeek $w) => (int) $w->week_number);

        $totalWeeks = $this->semesterWeekCountFor($course, $scopedClassIds);
        // Defensive: if a rep already opened more weeks than the class
        // was configured for, widen the grid to include them.
        $maxRealWeek = (int) ($realWeeks->keys()->max() ?? 0);
        if ($maxRealWeek > $totalWeeks) {
            $totalWeeks = $maxRealWeek;
        }
        if ($totalWeeks < 1) {
            return collect();
        }

        $slots = collect();
        for ($n = 1; $n <= $totalWeeks; $n++) {
            if ($realWeeks->has($n)) {
                $slots->push($realWeeks->get($n));
                continue;
            }
            // Placeholder for a week that hasn't been opened yet. id=0
            // means lookups against attendance_week_id will find nothing
            // (correct — there is nothing to find for an un-held week).
            $placeholder = new AttendanceWeek([
                'week_number' => $n,
            ]);
            $placeholder->id = 0;
            $slots->push($placeholder);
        }

        return $slots->values();
    }

    /**
     * Determine the semester length (in weeks) we should render for this
     * export. Reps see their own class's setting; admins/lecturers see
     * the course's primary class. Falls back to the system default when
     * nothing is configured.
     *
     * @param  list<int>  $scopedClassIds
     */
    private function semesterWeekCountFor(Course $course, array $scopedClassIds): int
    {
        $classId = $scopedClassIds[0] ?? null;
        if ($classId === null) {
            $classId = (int) ($course->class_id ?? 0) ?: null;
        }
        if ($classId !== null) {
            $cls = SchoolClass::find($classId);
            if ($cls) {
                return $cls->resolvedSemesterWeeks();
            }
        }
        if ($course->schoolClass) {
            return $course->schoolClass->resolvedSemesterWeeks();
        }

        return SchoolClass::DEFAULT_SEMESTER_WEEKS;
    }

    /**
     * Class ids the caller is allowed to see attendance for in this
     * export. Returns [] for admins/lecturers (no scoping) and the
     * rep-managed intersection for class reps.
     *
     * @return list<int>
     */
    private function scopedClassIdsForRequest(Course $course, Request $request): array
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId
            || $request->session()->has('admin_id')
            || $request->session()->has('lecturer_id')
        ) {
            return [];
        }
        $rep = Student::find($studentId);
        if (! $rep) {
            return [];
        }

        return RepCourseAccess::scopedClassIdsForCourse($rep, $course);
    }

    /**
     * Returns ['student_id' => 'rep'|'assist'] for any student in the
     * supplied collection that holds a class_reps row for their class.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Student>  $students
     * @return array<int, string>
     */
    private function repRolesForStudents($students): array
    {
        if ($students->isEmpty()) {
            return [];
        }
        $studentIds = $students->pluck('id')->all();
        try {
            $rows = ClassRep::query()
                ->whereIn('student_id', $studentIds)
                ->get(['student_id', 'class_id', 'role']);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
        // Build student_id => class_id lookup so we only credit a rep role
        // when the rep entry matches the student's *own* class (avoids
        // labelling someone as a rep for a class they no longer attend).
        $studentClassIds = $students->mapWithKeys(fn (Student $s) => [(int) $s->id => (int) ($s->class_id ?? 0)]);
        $map = [];
        foreach ($rows as $row) {
            $sid = (int) $row->student_id;
            $expectedClass = $studentClassIds->get($sid);
            if ($expectedClass !== null && $expectedClass !== 0 && (int) $row->class_id !== $expectedClass) {
                continue;
            }
            $existing = $map[$sid] ?? null;
            // ROLE_REP wins over ROLE_ASSIST when a student somehow has both.
            if ($existing !== ClassRep::ROLE_REP) {
                $map[$sid] = ((string) $row->role) === ClassRep::ROLE_REP
                    ? ClassRep::ROLE_REP
                    : ClassRep::ROLE_ASSIST;
            }
        }

        return $map;
    }

    /**
     * For each cancelled week, return a [week_number => [row_index =>
     * letter]] map so the view can stamp one letter of CANCELLED per
     * student row, vertically centred down the column — the same way a
     * lecturer would write it in a paper register. Rows above/below the
     * word are left blank.
     *
     * @param  Collection<int, AttendanceWeek>  $weeks
     * @return array<int, array<int, string>>
     */
    private function buildCancelledLetterMap(Collection $weeks, int $rowCount): array
    {
        $word = 'CANCELLED';
        $len = strlen($word);
        $map = [];

        foreach ($weeks as $week) {
            if (! $week->isCancelled()) {
                continue;
            }
            $weekNumber = (int) $week->week_number;

            if ($rowCount <= 0) {
                $map[$weekNumber] = [];
                continue;
            }

            // Centre CANCELLED vertically. If there are fewer rows than
            // letters, clip the trailing letters so we never overflow
            // the visible rows.
            $effectiveLen = min($len, $rowCount);
            $startRow = max(0, intdiv($rowCount - $effectiveLen, 2));

            $letters = [];
            for ($i = 0; $i < $effectiveLen; $i++) {
                $letters[$startRow + $i] = $word[$i];
            }
            $map[$weekNumber] = $letters;
        }

        return $map;
    }

    private function authorizePdfExport(Request $request, Course $course): void
    {
        if ($request->session()->has('admin_id')) {
            return;
        }

        $lecturerId = $request->session()->get('lecturer_id');
        if ($lecturerId) {
            $lecturer = \App\Models\Lecturer::find($lecturerId);
            if ($lecturer && $lecturer->managesCourse($course)) {
                return;
            }
            abort(403, 'This course is not assigned to you.');
        }

        $studentId = $request->session()->get('student_id');
        if ($studentId) {
            $student = Student::find($studentId);
            if (! $student || ! $student->classReps()->exists()) {
                abort(403);
            }
            $classIds = $student->repManagedClassIds();
            $allowed = collect($course->assignedClassIds())->intersect($classIds)->isNotEmpty();
            if (! $allowed) {
                abort(403, 'You can only export attendance for your class courses.');
            }

            return;
        }

        abort(403);
    }
}
