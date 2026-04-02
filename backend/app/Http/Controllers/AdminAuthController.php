<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function loginForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('admin_id') || $request->session()->has('lecturer_id')) {
            return redirect()->route('dashboard.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);
        $identifier = trim($validated['identifier']);
        $password = $validated['password'];

        $admin = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();
        if ($admin && Hash::check($password, $admin->password)) {
            $request->session()->forget('lecturer_id');
            $request->session()->put('admin_id', $admin->id);
            return redirect()->route('dashboard.dashboard');
        }

        $hasUsernameColumn = Schema::hasColumn('lecturers', 'username');
        $hasMustChangeColumn = Schema::hasColumn('lecturers', 'must_change_password');
        $lecturerQuery = Lecturer::query()->where('email', $identifier);
        if ($hasUsernameColumn) {
            $lecturerQuery->orWhere('username', $identifier);
        }
        $lecturer = $lecturerQuery->first();
        if ($lecturer && ! empty($lecturer->password) && Hash::check($password, $lecturer->password)) {
            $request->session()->forget('admin_id');
            $request->session()->put('lecturer_id', $lecturer->id);
            if ($hasMustChangeColumn && $lecturer->must_change_password) {
                return redirect()->route('lecturer.password.change.form');
            }

            return redirect()->route('dashboard.dashboard');
        }

        return back()->with('error', 'Invalid email/username or password.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['admin_id']);
        return redirect()->route('admin.login');
    }
}
