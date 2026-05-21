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
     * PDF preview for the whole semester (every teaching week in one grid).
     */
    public function export(Request $request, Course $course): Response
    {
        $this->authorizePdfExport($request, $course);
        $weeks = $course->attendanceWeeks()->orderBy('week_number')->get();
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
                } else {
                    $marked = Attendance::where('student_id', $student->id)
                        ->where('course_id', $course->id)
                        ->where('attendance_week_id', $week->id)
                        ->exists();
                    $row['weeks'][$week->week_number] = $marked;
                }
            }
            $attendanceByStudent[] = $row;
        }

        $courseTitle = trim($course->course_name.($course->course_code ? ' - '.$course->course_code : ''));

        // Map of student_id => role for everyone in this class who is a
        // class rep. We render a small "REP" / "ASSIST" badge next to their
        // index number on the PDF so lecturers know who their class
        // contacts are at a glance.
        $repRolesByStudent = $this->repRolesForStudents($students);

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
            'attendanceByStudent' => $attendanceByStudent,
            'repRolesByStudent' => $repRolesByStudent,
        ]);

        return $pdf->stream('attendance-'.\Str::slug($course->course_name).'-'.$slugSuffix.'.pdf', ['Attachment' => false]);
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
