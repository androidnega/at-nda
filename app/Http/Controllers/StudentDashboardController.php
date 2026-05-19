<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StudentDashboardController extends Controller
{
    /**
     * Step 1: index number only — route to set-password (first time) or password step.
     */
    public function lookupIndex(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string|max:64',
        ]);
        $indexNumber = strtoupper(trim($validated['index_number']));

        $student = Student::findByIndex($indexNumber);
        if (!$student) {
            return redirect()->route('home')->with('error', 'We couldn’t find a student account for that ID. Double-check and try again.');
        }

        $request->session()->forget(['pending_password_login_index', 'pending_set_password_index']);

        $settings = SystemSetting::get();
        if (empty($student->password)) {
            if ($settings->require_password_on_first_login ?? true) {
                $request->session()->put('pending_set_password_index', $student->index_number);

                return redirect()->route('student.set-password');
            }
            session(['student_id' => $student->id, 'student_index' => $student->index_number]);

            return $this->redirectAfterStudentAuth($student);
        }

        $request->session()->put('pending_password_login_index', $student->index_number);

        return redirect()->route('student.login.password.form');
    }

    public function showPasswordForm(Request $request): View|RedirectResponse
    {
        $indexNumber = $request->session()->get('pending_password_login_index');
        if (!$indexNumber || ! Student::findByIndex($indexNumber)) {
            $request->session()->forget('pending_password_login_index');

            return redirect()->route('home')->with('info', 'Please start from the sign-in page.');
        }

        return view('student.login-password', [
            'indexNumber' => $indexNumber,
        ]);
    }

    public function authenticateWithPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $indexNumber = $request->session()->get('pending_password_login_index');
        if (!$indexNumber) {
            return redirect()->route('home')->with('info', 'Please start from the sign-in page.');
        }

        $student = Student::findByIndex($indexNumber);
        if (! $student || empty($student->password)) {
            $request->session()->forget('pending_password_login_index');

            return redirect()->route('home')->with('error', 'This step timed out. Please sign in again from the start.');
        }

        if (! Hash::check($validated['password'], $student->password)) {
            return redirect()->back()->withInput()->with('error', 'That password doesn’t match. Try again.');
        }

        $request->session()->forget('pending_password_login_index');
        session(['student_id' => $student->id, 'student_index' => $student->index_number]);

        return $this->redirectAfterStudentAuth($student);
    }

    /**
     * Abandon password or set-password step and return to home (index entry).
     */
    public function cancelPendingLogin(Request $request): RedirectResponse
    {
        $request->session()->forget(['pending_password_login_index', 'pending_set_password_index']);

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        $request->session()->forget([
            'student_id',
            'student_index',
            'pending_password_login_index',
            'pending_set_password_index',
        ]);

        return redirect()->route('home');
    }

    public function dashboard(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to open your dashboard.');
        }

        $student = Student::with('department.faculty')->find($studentId);
        if (!$student) {
            $request->session()->forget('student_id');
            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (!$student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        $totalPresent = Attendance::query()
            ->where('student_id', $student->id)
            ->countedAsPresent()
            ->count();
        $totalWeeks = (int) DB::table('attendances')->where('student_id', $student->id)->distinct()->count('attendance_week_id');

        $liveAttendanceSessions = $this->collectLiveAttendanceSessionsForStudent($student)
            ->filter(fn (array $row) => ! $row['already_marked'])
            ->values();
        $settings = SystemSetting::get();
        $studentDashboardTheme = SystemSetting::hasStudentDashboardThemeColumn()
            ? (string) ($settings->student_dashboard_theme ?: 'classic')
            : 'classic';

        $cancelledWeeks = collect();
        if ($student->class_id) {
            $cancelledWeeks = AttendanceWeek::query()
                ->whereIn('course_id', Course::query()->forManagedClasses([$student->class_id])->select('id'))
                ->whereNotNull('cancelled_at')
                ->with(['course:id,course_name,course_code'])
                ->orderByDesc('week_date')
                ->limit(30)
                ->get();
        }

        return view('student.dashboard', compact(
            'student',
            'totalPresent',
            'totalWeeks',
            'liveAttendanceSessions',
            'cancelledWeeks',
            'studentDashboardTheme'
        ));
    }

    public function attendanceHistory(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to view attendance history.');
        }

        $student = Student::with('department.faculty')->find($studentId);
        if (!$student) {
            $request->session()->forget('student_id');
            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (!$student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        $sessions = collect();
        $attendanceBySession = collect();
        $attendanceRows = Attendance::query()
            ->where('student_id', $student->id)
            ->with(['course', 'attendanceWeek'])
            ->latest('attendance_time')
            ->get();
        if ($student->class_id) {
            $attendedSessionIds = $attendanceRows->pluck('attendance_session_id')->filter()->unique()->values();
            $sessions = AttendanceSession::query()
                ->whereHas('course', fn ($q) => $q->forManagedClasses([$student->class_id]))
                ->where(function ($q) use ($attendedSessionIds) {
                    $q->ended()
                        ->orWhere('is_active', false)
                        ->orWhereIn('id', $attendedSessionIds);
                })
                ->with(['course', 'attendanceWeek'])
                ->orderByRaw('COALESCE(end_time, expires_at, created_at) DESC')
                ->limit(400)
                ->get();

            $sessionIds = $sessions->pluck('id')->all();
            $attendanceBySession = $attendanceRows
                ->filter(fn ($row) => $row->attendance_session_id && in_array($row->attendance_session_id, $sessionIds, true))
                ->keyBy('attendance_session_id');
        }

        if ($sessions->isEmpty() && $attendanceRows->isNotEmpty()) {
            $history = $attendanceRows->map(function (Attendance $attendance) {
                return [
                    'session' => null,
                    'course' => $attendance->course,
                    'week' => $attendance->attendanceWeek?->week_number,
                    'is_present' => Attendance::countsAsPresent($attendance->status),
                    'attendance' => $attendance,
                    'time' => $attendance->attendance_time,
                ];
            });
        } else {
            $history = $sessions->map(function (AttendanceSession $session) use ($attendanceBySession) {
            $attendance = $attendanceBySession->get($session->id);
            $isPresent = $attendance !== null && Attendance::countsAsPresent($attendance->status);

            return [
                'session' => $session,
                'course' => $session->course,
                'week' => $session->attendanceWeek?->week_number,
                'is_present' => $isPresent,
                'attendance' => $attendance,
                'time' => $attendance?->attendance_time ?? $session->end_time ?? $session->expires_at ?? $session->created_at,
            ];
            });
        }

        $presentCount = $history->where('is_present', true)->count();
        $absentCount = $history->where('is_present', false)->count();
        $totalSessions = $history->count();
        $attendanceRate = $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 1) : 0.0;

        $attendances = Attendance::where('student_id', $student->id)
            ->with(['course', 'attendanceWeek'])
            ->orderByDesc('attendance_time')
            ->limit(50)
            ->get();

        $byCourse = Attendance::where('student_id', $student->id)
            ->join('courses', 'attendances.course_id', '=', 'courses.id')
            ->select('courses.course_name', 'courses.course_code', DB::raw('COUNT(*) as count'))
            ->groupBy('courses.id', 'courses.course_name', 'courses.course_code')
            ->get();

        $byWeek = Attendance::where('student_id', $student->id)
            ->join('attendance_weeks', 'attendances.attendance_week_id', '=', 'attendance_weeks.id')
            ->select('attendance_weeks.week_number', DB::raw('COUNT(*) as count'))
            ->groupBy('attendance_weeks.week_number')
            ->orderBy('attendance_weeks.week_number')
            ->get();

        $courseStats = $history
            ->groupBy(fn (array $row) => $row['course']?->id ?? 0)
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $present = $rows->where('is_present', true)->count();
                $total = $rows->count();

                return [
                    'course_name' => $first['course']?->course_name ?? 'Unknown course',
                    'course_code' => $first['course']?->course_code,
                    'present' => $present,
                    'absent' => $total - $present,
                    'total' => $total,
                    'rate' => $total > 0 ? round(($present / $total) * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        $trend = $history
            ->filter(fn (array $row) => !empty($row['week']))
            ->groupBy('week')
            ->map(function (Collection $rows, $week) {
                $present = $rows->where('is_present', true)->count();
                $total = $rows->count();
                $weekNumber = (int) $week;

                return [
                    'week' => $weekNumber,
                    'label' => 'Week ' . $weekNumber,
                    'present' => $present,
                    'absent' => $total - $present,
                    'total' => $total,
                    'rate' => $total > 0 ? round(($present / $total) * 100) : 0,
                ];
            })
            ->sortBy('week')
            ->values()
            ->take(-8)
            ->values();

        return view('student.attendance-history', compact(
            'student',
            'history',
            'presentCount',
            'absentCount',
            'totalSessions',
            'attendanceRate',
            'attendances',
            'byCourse',
            'byWeek',
            'courseStats',
            'trend'
        ));
    }

    public function profileForm(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to manage your profile.');
        }

        $student = Student::with(['department.faculty', 'schoolClass.department', 'schoolClass.faculty'])->find($studentId);
        if (!$student) {
            $request->session()->forget('student_id');
            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->class_id && !$student->department_id) {
            $class = $student->schoolClass;
            if ($class?->department_id) {
                $student->update(['department_id' => $class->department_id]);
                return redirect()->route('dashboard.dashboard')->with('success', 'Profile updated from your class.');
            }
        }

        $faculties = $this->getUniqueFacultiesWithDepartments();

        $rawFacultyId = old('faculty_id') ?? $student->department?->faculty_id ?? $student->schoolClass?->faculty_id;
        $rawDepartmentId = old('department_id') ?? $student->department_id ?? $student->schoolClass?->department_id;
        $prefillFacultyId = $this->resolveCanonicalFacultyId($faculties, $rawFacultyId);
        $prefillDepartmentId = $rawDepartmentId;

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        $profileLayout = $student->isRep() ? 'classrep' : 'student';

        return view('student.profile', compact('student', 'faculties', 'prefillFacultyId', 'prefillDepartmentId', 'profileLayout'));
    }

    public function onboardingForm(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        $student = Student::find($studentId);
        if (!$student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        $settings = SystemSetting::get();
        $requirePhoto = $settings->require_profile_image_on_onboarding ?? true;

        if (!$student->needsBasicOnboarding($requirePhoto)) {
            return redirect()->route(
                $student->hasCompletedProfile()
                    ? 'dashboard.dashboard'
                    : 'student.profile'
            );
        }

        $layout = $student->isRep() ? 'classrep' : 'student';
        $missingFields = $student->missingBasicOnboardingFields($requirePhoto);

        return view('student.onboarding', compact('student', 'layout', 'missingFields'));
    }

    public function onboardingStore(Request $request): RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        $student = Student::find($studentId);
        if (!$student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        $settings = SystemSetting::get();
        $requirePhoto = $settings->require_profile_image_on_onboarding ?? true;
        $missing = $student->missingBasicOnboardingFields($requirePhoto);
        if ($missing === []) {
            return redirect()->route(
                $student->hasCompletedProfile()
                    ? 'dashboard.dashboard'
                    : 'student.profile'
            );
        }

        $rules = [];
        foreach ($missing as $field) {
            if ($field === 'phone_number') {
                $rules['phone_number'] = 'required|string|min:10|max:30';
            } elseif ($field === 'profile_image') {
                $rules['profile_photo'] = 'required|image|mimes:jpg,jpeg,png,webp|max:10240';
            } else {
                $rules[$field] = 'required|string|max:255';
            }
        }

        $validated = $request->validate($rules);

        if (isset($validated['first_name'])) {
            $student->first_name = $validated['first_name'];
        }
        if (isset($validated['last_name'])) {
            $student->last_name = $validated['last_name'];
        }
        if (isset($validated['phone_number'])) {
            $student->phone_number = preg_replace('/[^0-9+]/', '', $validated['phone_number']);
        }
        if (in_array('profile_image', $missing, true) && $request->hasFile('profile_photo')) {
            if (!$student->saveProfileImageFromUpload($request->file('profile_photo'))) {
                return redirect()->back()->withInput()->with('error', 'Could not process that profile image. Use a clear JPG, PNG, or WEBP photo.');
            }
        }
        $student->save();

        if (!$student->hasCompletedProfile()) {
            return redirect()->route('student.profile')->with('success', 'Now choose your faculty and department.');
        }

        return redirect()->route('dashboard.dashboard')
            ->with('success', 'Welcome to a-tenda!');
    }

    /**
     * After login / password set: onboarding → profile (if needed) → dashboard.
     */
    /**
     * “Mark attendance” (sidebar / web): jump straight into the attendance flow when there is exactly one open session.
     */
    public function attendanceWebEntry(Request $request): RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        if (! $studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to mark attendance.');
        }

        $student = Student::find($studentId);
        if (! $student) {
            $request->session()->forget('student_id');

            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        if ($student->isRep()) {
            return redirect()->route('dashboard.dashboard')->with('info', 'Open session from your dashboard when you’re running a class; use Mark attendance when you’re marking as a student.');
        }

        $rows = $this->collectLiveAttendanceSessionsForStudent($student);
        $unmarked = $rows->filter(fn (array $r) => ! $r['already_marked']);

        if ($unmarked->isEmpty()) {
            return redirect()->route('dashboard.dashboard')->with('info', 'No open attendance session to mark right now.');
        }

        if ($unmarked->count() === 1) {
            return redirect()->route('web.attendance.form', $unmarked->first()['course']);
        }

        return redirect()->route('dashboard.dashboard')->with('info', 'Choose your course below to mark attendance.');
    }

    /**
     * @return Collection<int, array{session: AttendanceSession, course: Course, already_marked: bool}>
     */
    private function collectLiveAttendanceSessionsForStudent(Student $student): Collection
    {
        if (! $student->class_id) {
            return collect();
        }

        return AttendanceSession::query()
            ->whereHas('course', fn ($q) => $q->forManagedClasses([$student->class_id]))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('attendance_week_id')
                    ->orWhereHas('attendanceWeek', fn ($w) => $w->whereNull('cancelled_at'));
            })
            ->with(['course.lecturer', 'course.venueRelation', 'attendanceWeek'])
            ->orderBy('expires_at')
            ->get()
            ->map(function (AttendanceSession $session) use ($student) {
                $attendance = Attendance::where('student_id', $student->id)
                    ->where('attendance_session_id', $session->id)
                    ->first();
                $alreadyMarked = $attendance !== null && Attendance::countsAsPresent($attendance->status);
                if ($session->isCheckInCheckoutMode()) {
                    $alreadyMarked = $attendance !== null
                        && Attendance::countsAsPresent($attendance->status)
                        && ! empty($attendance->check_out_time);
                }

                return [
                    'session' => $session,
                    'course' => $session->course,
                    'already_marked' => $alreadyMarked,
                    'my_attendance' => $attendance,
                ];
            });
    }

    private function redirectAfterStudentAuth(Student $student): RedirectResponse
    {
        if ($student->needsBasicOnboarding()) {
            return redirect()->route('student.onboarding');
        }

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.profile');
        }

        if ($student->isRep()) {
            return redirect()->route('dashboard.dashboard');
        }

        $rows = $this->collectLiveAttendanceSessionsForStudent($student);
        $unmarked = $rows->filter(fn (array $r) => ! $r['already_marked']);
        if ($unmarked->count() === 1) {
            return redirect()->route('web.attendance.form', $unmarked->first()['course']);
        }

        return redirect()->route('dashboard.dashboard');
    }

    public function profileUpdate(Request $request)
    {
        $studentId = $request->session()->get('student_id');
        if (!$studentId) {
            return redirect()->route('home')->with('info', 'Please sign in to update your profile.');
        }

        $student = Student::find($studentId);
        if (!$student) {
            $request->session()->forget('student_id');
            return redirect()->route('home')->with('error', 'Your session ended. Please sign in again.');
        }

        $settings = SystemSetting::get();
        $requirePhoto = $settings->require_profile_image_on_onboarding ?? true;

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'phone_number' => 'nullable|string|max:30',
            'profile_photo' => ($requirePhoto && !$student->profile_image)
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']
                : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $department = \App\Models\Department::find($validated['department_id']);
        if ($department && $department->faculty_id != $validated['faculty_id']) {
            return redirect()->back()->with('error', 'That department doesn’t match the faculty you selected.');
        }

        if ($request->hasFile('profile_photo')) {
            if (!$student->saveProfileImageFromUpload($request->file('profile_photo'))) {
                return redirect()->back()->withInput()->with('error', 'Could not process that profile image. Use a clear JPG, PNG, or WEBP photo.');
            }
        }

        if (array_key_exists('phone_number', $validated) && $validated['phone_number'] !== null && $validated['phone_number'] !== '') {
            $student->phone_number = preg_replace('/[^0-9+]/', '', $validated['phone_number']);
        }

        $middle = $validated['middle_name'] ?? null;
        $student->fill([
            'first_name' => $validated['first_name'],
            'middle_name' => ($middle !== null && trim((string) $middle) !== '') ? trim((string) $middle) : null,
            'last_name' => $validated['last_name'],
            'department_id' => $validated['department_id'],
        ]);
        $student->save();

        return redirect()->route('dashboard.dashboard')->with('success', 'Profile updated');
    }

    private function getUniqueFacultiesWithDepartments(): \Illuminate\Support\Collection
    {
        return Faculty::with('departments')->orderBy('name')->get()->map(function ($f) {
            return (object) [
                'id' => $f->id,
                'name' => $f->name,
                'departments' => $f->departments->sortBy('name')->map(fn ($d) => (object) ['id' => $d->id, 'name' => $d->name])->values()->all(),
            ];
        });
    }

    private function resolveCanonicalFacultyId(?\Illuminate\Support\Collection $faculties, ?int $facultyId): ?int
    {
        return $facultyId;
    }

    public function departmentsByFaculty(int $facultyId)
    {
        $departments = Department::where('faculty_id', $facultyId)->orderBy('name')->get(['id', 'name']);
        return response()->json($departments);
    }

    public function setPasswordForm(Request $request): View|RedirectResponse
    {
        $indexNumber = $request->session()->get('pending_set_password_index');
        if (! $indexNumber || ! Student::findByIndex($indexNumber)) {
            $request->session()->forget('pending_set_password_index');

            return redirect()->route('home')->with('info', 'Let’s start from the sign-in page.');
        }

        return view('student.set-password', compact('indexNumber'));
    }

    public function setPassword(Request $request): RedirectResponse
    {
        $indexNumber = $request->session()->get('pending_set_password_index');
        if (! $indexNumber) {
            return redirect()->route('home')->with('error', 'Something went wrong. Please start sign-in again.');
        }

        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $student = Student::findByIndex($indexNumber);
        if (! $student) {
            $request->session()->forget('pending_set_password_index');

            return redirect()->route('home')->with('error', 'We couldn’t verify your account. Please try again.');
        }

        if (! empty($student->password)) {
            $request->session()->forget('pending_set_password_index');

            return redirect()->route('home')->with('error', 'You already have a password. Sign in from the start.');
        }

        $student->update(['password' => Hash::make($validated['password'])]);

        $request->session()->forget('pending_set_password_index');
        session(['student_id' => $student->id, 'student_index' => $student->index_number]);

        return $this->redirectAfterStudentAuth($student)->with('success', 'You’re all set. Welcome to a-tenda!');
    }
}
