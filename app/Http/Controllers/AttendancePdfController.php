<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Student;
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

        $course->loadMissing(['lecturer', 'venueRelation', 'schoolClass.faculty', 'schoolClass.department']);

        $weeks = $course->attendanceWeeks()->orderBy('week_number')->get();

        $studentsQuery = Student::query()->orderBy('last_name')->orderBy('first_name');
        if ($course->class_id) {
            $studentsQuery->where('class_id', $course->class_id);
        }
        $students = $studentsQuery->get();

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
        $institutionName = (string) (config('app.institution_name') ?: config('app.name', 'Attendance System'));
        $facultyName = (string) ($course->schoolClass?->faculty?->name ?? '—');
        $departmentName = (string) ($course->schoolClass?->department?->name ?? '—');

        $classLogoDataUri = null;
        $logoPath = $course->schoolClass?->logo_path;
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
                $marked = Attendance::where('student_id', $student->id)
                    ->where('attendance_week_id', $week->id)
                    ->exists();
                $row['weeks'][$week->week_number] = $marked;
            }
            $attendanceByStudent[] = $row;
        }

        $title = trim($course->course_name.($course->course_code ? ' - '.$course->course_code : ''));
        $latestSession = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->latest('id')
            ->first(['lecturer_status']);

        $pdf = Pdf::loadView('admin.pdf.attendance', [
            'course' => $course,
            'title' => $title,
            'institutionName' => $institutionName,
            'facultyName' => $facultyName,
            'departmentName' => $departmentName,
            'lecturerDisplay' => $lecturerDisplay,
            'lecturerStatus' => $latestSession?->lecturer_status ?? 'present',
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

        $studentId = $request->session()->get('student_id');
        if ($studentId) {
            $student = Student::find($studentId);
            if (! $student || (! $student->classReps()->exists() && ! $student->courseReps()->exists())) {
                abort(403);
            }
            $classIds = $student->repManagedClassIds();
            if (! $course->class_id || ! $classIds->contains((int) $course->class_id)) {
                abort(403, 'You can only export attendance for your class courses.');
            }

            return;
        }

        abort(403);
    }
}
