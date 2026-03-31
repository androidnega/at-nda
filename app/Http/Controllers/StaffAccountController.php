<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administrator accounts only (staff login). Lecturers are directory records under Lecturers.
 */
class StaffAccountController extends Controller
{
    public function index(): View
    {
        $hasUsernameColumn = Schema::hasColumn('lecturers', 'username');
        $hasMustChangeColumn = Schema::hasColumn('lecturers', 'must_change_password');
        $admins = User::orderBy('name')->get();
        $lecturersWithLogin = Lecturer::query()
            ->whereNotNull('password')
            ->orderBy('name')
            ->get();

        $lecturerIds = $lecturersWithLogin->pluck('id')->all();
        $presenceRows = empty($lecturerIds)
            ? collect()
            : DB::table('attendance_sessions')
                ->join('courses', 'attendance_sessions.course_id', '=', 'courses.id')
                ->leftJoin('classes', 'courses.class_id', '=', 'classes.id')
                ->whereIn('attendance_sessions.lecturer_id', $lecturerIds)
                ->where('attendance_sessions.lecturer_status', 'present')
                ->selectRaw('attendance_sessions.lecturer_id, courses.id as course_id, courses.course_name, courses.course_code, classes.name as class_name, COUNT(*) as present_sessions')
                ->groupBy('attendance_sessions.lecturer_id', 'courses.id', 'courses.course_name', 'courses.course_code', 'classes.name')
                ->orderByDesc('present_sessions')
                ->get()
                ->groupBy('lecturer_id');

        $lecturerPresenceSummary = [];
        foreach ($lecturersWithLogin as $lecturer) {
            $rows = collect($presenceRows->get($lecturer->id, []));
            $lecturerPresenceSummary[$lecturer->id] = [
                'total_present' => (int) $rows->sum('present_sessions'),
                'by_course' => $rows->map(fn ($r) => [
                    'course_name' => $r->course_name,
                    'course_code' => $r->course_code,
                    'class_name' => $r->class_name,
                    'present_sessions' => (int) $r->present_sessions,
                ])->values(),
            ];
        }

        return view('admin.staff-accounts.index', compact(
            'admins',
            'lecturersWithLogin',
            'lecturerPresenceSummary',
            'hasUsernameColumn',
            'hasMustChangeColumn'
        ));
    }

    public function create(): View
    {
        $lecturers = Lecturer::orderBy('name')->get();

        return view('admin.staff-accounts.create', compact('lecturers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accountType = (string) $request->input('account_type', 'admin');
        if ($accountType === 'lecturer') {
            $validated = $request->validate([
                'lecturer_id' => 'required|exists:lecturers,id',
            ]);

            $lecturer = Lecturer::findOrFail($validated['lecturer_id']);
            $hasUsernameColumn = Schema::hasColumn('lecturers', 'username');
            $hasMustChangeColumn = Schema::hasColumn('lecturers', 'must_change_password');
            $username = ($hasUsernameColumn ? $lecturer->username : null) ?: $this->buildFancyLecturerUsername($lecturer->name, $lecturer->id);
            $baseUsername = Str::slug($username, '');
            if ($baseUsername === '') {
                $baseUsername = 'lecturer' . $lecturer->id;
            }
            $usernameCandidate = $baseUsername;
            $suffix = 1;
            if ($hasUsernameColumn) {
                while (
                    Lecturer::where('id', '!=', $lecturer->id)->where('username', $usernameCandidate)->exists()
                    || User::where('username', $usernameCandidate)->exists()
                ) {
                    $suffix++;
                    $usernameCandidate = $baseUsername . $suffix;
                }
            }

            $generatedPassword = Str::upper(Str::random(10));
            $lecturer->password = Hash::make($generatedPassword);
            if ($hasUsernameColumn) {
                $lecturer->username = $usernameCandidate;
            }
            if ($hasMustChangeColumn) {
                $lecturer->must_change_password = true;
            }
            $lecturer->save();

            $message = 'Lecturer account created. ';
            if ($hasUsernameColumn) {
                $message .= 'Username: ' . $usernameCandidate . ' | ';
            }
            $message .= 'Temporary password: ' . $generatedPassword;

            $redirect = redirect()->route('dashboard.staff-accounts.index')->with('success', $message);
            if (! $hasUsernameColumn || ! $hasMustChangeColumn) {
                $redirect->with('error', 'Database upgrade pending: run migrations to enable lecturer username + forced first password change.');
            }

            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'username' => 'nullable|string|max:255|unique:users,username',
            'password' => 'required|string|min:6|confirmed',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?: null,
            'password' => $validated['password'],
        ]);

        return redirect()->route('dashboard.staff-accounts.index')
            ->with('success', 'Administrator account created.');
    }

    public function resetLecturerPassword(Lecturer $lecturer): RedirectResponse
    {
        $hasUsernameColumn = Schema::hasColumn('lecturers', 'username');
        $hasMustChangeColumn = Schema::hasColumn('lecturers', 'must_change_password');

        $generatedPassword = Str::upper(Str::random(10));
        if ($hasUsernameColumn && empty($lecturer->username)) {
            $lecturer->username = $this->buildFancyLecturerUsername($lecturer->name, $lecturer->id);
        }
        $lecturer->password = Hash::make($generatedPassword);
        if ($hasMustChangeColumn) {
            $lecturer->must_change_password = true;
        }
        $lecturer->save();

        return redirect()->route('dashboard.staff-accounts.index')
            ->with('success', 'Lecturer temporary password reset. ' . ($hasUsernameColumn ? 'Username: ' . $lecturer->username . ' | ' : '') . 'Temporary password: ' . $generatedPassword);
    }

    public function removeLecturerAccount(Lecturer $lecturer): RedirectResponse
    {
        $hasUsernameColumn = Schema::hasColumn('lecturers', 'username');
        $hasMustChangeColumn = Schema::hasColumn('lecturers', 'must_change_password');

        $lecturer->password = null;
        if ($hasUsernameColumn) {
            $lecturer->username = null;
        }
        if ($hasMustChangeColumn) {
            $lecturer->must_change_password = false;
        }
        $lecturer->save();

        return redirect()->route('dashboard.staff-accounts.index')->with('success', 'Lecturer login account removed.');
    }

    public function editAdmin(User $user): View
    {
        return view('admin.staff-accounts.edit-admin', ['user' => $user]);
    }

    public function updateAdmin(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->username = $validated['username'] ?: null;
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        return redirect()->route('dashboard.staff-accounts.index')
            ->with('success', 'Administrator account updated.');
    }

    public function destroyAdmin(Request $request, User $user): RedirectResponse
    {
        if ($request->session()->get('admin_id') === $user->id) {
            return redirect()->route('dashboard.staff-accounts.index')
                ->with('error', 'You cannot delete your own administrator account while signed in.');
        }

        if (User::count() <= 1) {
            return redirect()->route('dashboard.staff-accounts.index')
                ->with('error', 'Keep at least one administrator account.');
        }

        $user->delete();

        return redirect()->route('dashboard.staff-accounts.index')
            ->with('success', 'Administrator account removed.');
    }

    private function buildFancyLecturerUsername(string $name, int $id): string
    {
        $words = preg_split('/\s+/', strtolower(trim($name))) ?: [];
        $first = $words[0] ?? 'lecturer';
        $last = $words[count($words) - 1] ?? 'staff';
        $baseA = preg_replace('/[^a-z0-9]/', '', $first . '.' . $last);
        $baseB = preg_replace('/[^a-z0-9]/', '', substr($first, 0, 1) . $last);
        $suffixes = ['byte', 'core', 'logic', 'stack', 'matrix', 'vector', 'quant', 'kernel'];
        $baseC = preg_replace('/[^a-z0-9]/', '', $first . $suffixes[$id % count($suffixes)]);
        $candidates = array_values(array_unique(array_filter([$baseA, $baseB, $baseC, 'lecturer' . $id])));

        foreach ($candidates as $candidate) {
            if (! Lecturer::where('id', '!=', $id)->where('username', $candidate)->exists()
                && ! User::where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'lecturer' . $id;
    }
}
