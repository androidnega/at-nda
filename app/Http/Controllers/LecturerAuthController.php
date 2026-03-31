<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
class LecturerAuthController extends Controller
{
    public function loginForm(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $lecturer = Lecturer::where('email', $validated['email'])->first();
        if (!$lecturer || !Hash::check($validated['password'], $lecturer->password)) {
            return back()->with('error', 'Invalid email or password');
        }

        $request->session()->put('lecturer_id', $lecturer->id);
        return redirect()->route('dashboard.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('lecturer_id');
        return redirect()->route('admin.login');
    }

    public function changePasswordForm(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $lecturerId = $request->session()->get('lecturer_id');
        if (! $lecturerId) {
            return redirect()->route('admin.login')->with('error', 'Please sign in first.');
        }

        $lecturer = Lecturer::find($lecturerId);
        if (! $lecturer) {
            $request->session()->forget('lecturer_id');
            return redirect()->route('admin.login')->with('error', 'Session expired. Sign in again.');
        }

        return view('lecturer.change-password', compact('lecturer'));
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $lecturerId = $request->session()->get('lecturer_id');
        if (! $lecturerId) {
            return redirect()->route('admin.login')->with('error', 'Please sign in first.');
        }

        $lecturer = Lecturer::find($lecturerId);
        if (! $lecturer) {
            $request->session()->forget('lecturer_id');
            return redirect()->route('admin.login')->with('error', 'Session expired. Sign in again.');
        }

        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $lecturer->password = Hash::make($validated['password']);
        if (Schema::hasColumn('lecturers', 'must_change_password')) {
            $lecturer->must_change_password = false;
        }
        $lecturer->save();

        return redirect()->route('dashboard.dashboard')->with('success', 'Password updated successfully.');
    }
}
