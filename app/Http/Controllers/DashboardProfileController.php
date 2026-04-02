<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DashboardProfileController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('admin_id')) {
            return redirect()->route('dashboard.dashboard')->with('error', 'Sign in as an administrator to manage your profile.');
        }

        $user = User::find($request->session()->get('admin_id'));
        if (! $user) {
            $request->session()->forget('admin_id');

            return redirect()->route('admin.login')->with('error', 'Session expired.');
        }

        return view('dashboard.profile', [
            'dashboardRole' => 'admin',
            'user' => $user,
            'lecturer' => null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $request->session()->has('admin_id')) {
            return redirect()->route('dashboard.dashboard')->with('error', 'Unauthorized.');
        }

        $user = User::findOrFail($request->session()->get('admin_id'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return redirect()->back()->with('error', 'Current password is incorrect.');
            }
            $user->password = $validated['password'];
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?? $user->username,
        ]);
        $user->save();

        return redirect()->route('dashboard.profile.edit')->with('success', 'Profile updated.');
    }
}
