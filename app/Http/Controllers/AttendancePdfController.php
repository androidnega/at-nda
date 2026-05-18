<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Student;
use App\Models\University;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AttendancePdfController extends Controller
{
    /**
     * PDF preview (opens in browser). Authorized: admin or class rep for this course’s class.
     */
    public function export(Request $request, Course $course): Response
    {
        $this->authorizePdfExport($request, $course);

        $course->loadMissing(['lecturer', 'venueRelation', 'schoolClass.faculty.university', 'schoolClass.department']);

        $weeks = $course->attendanceWeeks()->orderBy('week_number')->get();

        $students = $course->studentsQuery()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $lecturerDisplay = trim((string) ($course->lecturer_name ?? ''));
        if ($lecturerDisplay === '' && $course->lecturer) {
            $lecturerDisplay = trim((string) ($course->lecturer->name ?? ''));
        }
        if ($lecturerDisplay === '') {
            $lecturerDisplay = 'Not assigned';
        }

        $className = trim((string) ($course->schoolClass?->name ?? ''));
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
        ]);

        return $pdf->stream('attendance-'.\Str::slug($course->course_name).'.pdf', ['Attachment' => false]);
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
