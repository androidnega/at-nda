<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLecturerScope;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Lecturer;
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
        $recentSessions = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'attendance_week_id', 'lecturer_status', 'start_time', 'created_at']);

        $query = Attendance::query()
            ->with(['student'])
            ->where('course_id', $course->id)
            ->latest('attendance_time');

        if ($request->filled('date_from')) {
            $query->whereDate('attendance_time', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('attendance_time', '<=', $request->query('date_to'));
        }
        if ($request->filled('search')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->query('search'))).'%';
            $query->whereHas('student', fn ($q) => $q->where('index_number', 'like', $term));
        }

        $attendances = $query->paginate(30)->withQueryString();
        $attendanceWeeks = $course->attendanceWeeks()->orderBy('week_number')->get();

        return view('lecturer.attendance-course', [
            'course' => $course,
            'attendances' => $attendances,
            'recentSessions' => $recentSessions,
            'attendanceWeeks' => $attendanceWeeks,
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
