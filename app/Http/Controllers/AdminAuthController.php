<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function loginForm(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('admin_id')) {
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
            $request->session()->put('admin_id', $admin->id);
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
