<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLecturerScope;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Student;
use App\Services\CourseAttendanceBackupService;
use App\Support\LecturerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerAttendanceController extends Controller
{
    use ResolvesLecturerScope;

    public function index(Request $request): View|RedirectResponse
    {
        $lecturer = $this->requireLecturer($request);
        if (! $lecturer) {
            return redirect()->route('lecturer.login');
        }

        $courses = $lecturer->teachingCourses()
            ->load([
                'schoolClass',
                'schoolClasses',
                'attendanceSessions' => fn ($q) => $q->latest('id')->limit(1),
            ])
            ->loadCount('attendances');

        return view('lecturer.attendance-index', [
            'courses' => $courses,
            'dashboardRole' => 'lecturer',
        ]);
    }

    public function forCourse(Request $request, Course $course): View|RedirectResponse
    {
        $lecturer = $this->authorizeCourse($request, $course);

        $course->loadMissing(['schoolClass', 'schoolClasses', 'lecturer', 'venueRelation']);

        // All students enrolled in the class(es) this course belongs to.
        $students = $course->studentsQuery()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $enrolledCount = $students->count();

        $attendanceWeeks = $course->attendanceWeeks()->orderBy('week_number')->get();

        // Build a present-set per week for fast lookup, then derive the absent
        // list from the enrolled students that didn't appear. We fetch the
        // status too because the online roll-call flow writes status='absent'
        // rows: only present/late rows should land in the present bucket.
        $weekIds = $attendanceWeeks->pluck('id')->all();
        $marksByWeek = $weekIds === []
            ? collect()
            : Attendance::query()
                ->whereIn('attendance_week_id', $weekIds)
                ->where('course_id', $course->id)
                ->get(['student_id', 'attendance_week_id', 'attendance_time', 'status'])
                ->groupBy('attendance_week_id');

        $weeklyAttendees = $attendanceWeeks->map(function (AttendanceWeek $week) use ($marksByWeek, $students): array {
            $marks = $marksByWeek->get((int) $week->id, collect());
            $presentMarks = $marks->filter(fn ($m) => Attendance::countsAsPresent($m->status));
            $presentIds = $presentMarks->pluck('student_id')->unique()->map(fn ($id) => (int) $id);
            $presentSet = $presentIds->flip();
            $latestByStudent = $presentMarks->groupBy(fn ($m) => (int) $m->student_id)
                ->map(fn ($rows) => $rows->sortByDesc('attendance_time')->first());

            $present = $students->filter(fn (Student $s) => $presentSet->has((int) $s->id))
                ->values()
                ->map(fn (Student $s) => [
                    'student' => $s,
                    'time' => optional($latestByStudent[(int) $s->id] ?? null)->attendance_time,
                ]);

            $absent = $students->reject(fn (Student $s) => $presentSet->has((int) $s->id))->values();

            return [
                'week' => $week,
                'present' => $present,
                'absent' => $absent,
                'present_count' => $present->count(),
                'absent_count' => $absent->count(),
                'present_ids' => $presentSet,
            ];
        });

        return view('lecturer.attendance-course', [
            'course' => $course,
            'attendanceWeeks' => $attendanceWeeks,
            'weeklyAttendees' => $weeklyAttendees,
            'enrolledCount' => $enrolledCount,
            'enrolledStudents' => $students,
            'dashboardRole' => 'lecturer',
        ]);
    }

    public function exportJson(Request $request, Course $course, CourseAttendanceBackupService $backup): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $payload = $backup->buildExportPayload($course);
        $filename = 'attendance-course-'.$course->id.'-'.now()->format('Y-m-d_His').'.json';

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function importJson(Request $request, Course $course, CourseAttendanceBackupService $backup): RedirectResponse
    {
        $this->authorizeCourse($request, $course);

        $request->validate([
            'backup' => 'required|file|max:51200',
        ]);

        $path = $request->file('backup')->getRealPath();
        if ($path === false) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return back()->with('error', 'Invalid JSON file.');
        }

        try {
            $result = $backup->importFromPayload($course, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Import complete: {$result['imported']} records imported, {$result['skipped']} skipped.");
    }

    private function authorizeCourse(Request $request, Course $course): Lecturer
    {
        $lecturer = $this->requireLecturer($request);
        if (! $lecturer) {
            abort(403, 'Lecturer sign-in required.');
        }
        if (! LecturerAccess::canManageCourse($lecturer, $course)) {
            abort(403, 'This course is not assigned to you.');
        }

        return $lecturer;
    }
}
