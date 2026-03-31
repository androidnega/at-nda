<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
}
