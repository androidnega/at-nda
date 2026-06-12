<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceWeek;
use App\Models\ClassRep;
use App\Models\Course;
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
     * PDF preview for the whole semester. Only weeks that ACTUALLY
     * happened (had an attendance session opened) plus weeks that were
     * explicitly cancelled are shown — un-held / future weeks are
     * omitted so the grid never lies about what's been delivered.
     */
    public function export(Request $request, Course $course): Response
    {
        $this->authorizePdfExport($request, $course);
        $weeks = $this->materialWeeksForCourse($course, $request);
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

        // Pick portrait for narrow grids and landscape once we start
        // running out of horizontal room. Empirically a portrait A4
        // page comfortably fits ~7 week columns alongside the
        // #/Index/Program columns; beyond that landscape gives us
        // breathing room without shrinking the marks into illegibility.
        $weekCount = $weeks->count();
        $orientation = $weekCount > 7 ? 'landscape' : 'portrait';

        // Rough rows-per-page estimate so the CANCELLED stripe repeats
        // on every page of multi-page exports. Tuned against the
        // bundled DejaVu Sans at 10px / 5px-padding row spacing.
        $rowsPerPage = $orientation === 'landscape' ? 16 : 24;

        $cancelledLetterByWeekAndRow = $this->buildCancelledLetterMap(
            $weeks,
            count($attendanceByStudent),
            $rowsPerPage,
        );

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
            'orientation' => $orientation,
            'weekCount' => $weekCount,
        ])->setPaper('a4', $orientation);

        return $pdf->stream('attendance-'.\Str::slug($course->course_name).'-'.$slugSuffix.'.pdf', ['Attachment' => false]);
    }

    /**
     * Weeks that should appear on the semester PDF grid: those that
     * actually happened (at least one attendance session opened) plus
     * weeks explicitly cancelled (so the lecturer sees the gap and the
     * reason). Empty/un-held weeks are dropped so the grid doesn't lie
     * about the number of classes delivered.
     *
     * Scoped to the rep's class when a class-rep is logged in.
     *
     * @return Collection<int, AttendanceWeek>
     */
    private function materialWeeksForCourse(Course $course, Request $request): Collection
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

        $allWeeks = $weeksQuery->get();
        if ($allWeeks->isEmpty()) {
            return $allWeeks;
        }

        // Any week with at least one session was a class that ran (or
        // was attempted). We deliberately don't require attendance
        // rows — a held class with nobody marked is still a held class.
        $usedWeekIds = \App\Models\AttendanceSession::query()
            ->where('course_id', $course->id)
            ->whereIn('attendance_week_id', $allWeeks->pluck('id'))
            ->pluck('attendance_week_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->flip();

        return $allWeeks->filter(function (AttendanceWeek $w) use ($usedWeekIds) {
            if ($w->isCancelled()) {
                return true;
            }

            return $usedWeekIds->has((int) $w->id);
        })->values();
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
     * student row.
     *
     * The word is REPEATED per PDF page so multi-page exports still
     * read "CANCELLED" on every page (instead of once, centred across
     * the whole register). dompdf decides page breaks itself based on
     * row heights, so we estimate "rows per page" from the orientation
     * and use that as the repeat stride. Worst case the word lands a
     * row or two off the visual page centre — never invisible.
     *
     * @param  Collection<int, AttendanceWeek>  $weeks
     * @return array<int, array<int, string>>
     */
    private function buildCancelledLetterMap(Collection $weeks, int $rowCount, int $rowsPerPage): array
    {
        $word = 'CANCELLED';
        $len = strlen($word);
        $map = [];

        if ($rowCount <= 0 || $rowsPerPage <= 0) {
            foreach ($weeks as $week) {
                if ($week->isCancelled()) {
                    $map[(int) $week->week_number] = [];
                }
            }

            return $map;
        }

        // Compute the per-page letter positions once — same word lands
        // in the same in-page slot for every page, every cancelled
        // column — then expand across as many pages as we actually
        // have student rows for.
        $effectiveLen = min($len, $rowsPerPage);
        $startInPage = max(0, intdiv($rowsPerPage - $effectiveLen, 2));

        foreach ($weeks as $week) {
            if (! $week->isCancelled()) {
                continue;
            }
            $letters = [];
            for ($row = 0; $row < $rowCount; $row++) {
                $indexInPage = $row % $rowsPerPage;
                if ($indexInPage >= $startInPage
                    && $indexInPage < $startInPage + $effectiveLen
                ) {
                    $letters[$row] = $word[$indexInPage - $startInPage];
                }
            }
            $map[(int) $week->week_number] = $letters;
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
