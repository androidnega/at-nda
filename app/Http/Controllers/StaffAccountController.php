<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administrator accounts only (staff login). Lecturers are directory records under Lecturers.
 */
class StaffAccountController extends Controller
{
    public function index(): View
    {
        $admins = User::orderBy('name')->get();

        return view('admin.staff-accounts.index', compact('admins'));
    }

    public function create(): View
    {
        return view('admin.staff-accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
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
}
