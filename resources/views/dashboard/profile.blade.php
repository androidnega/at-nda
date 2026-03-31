@extends('layouts.admin')

@section('title', 'My profile')

@section('content')
<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Profile</h1>
    <p class="text-sm text-gray-500 mb-6">Update your account details and password.</p>

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-xl text-sm font-medium">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-4 bg-red-50 text-red-800 rounded-xl text-sm font-medium">{{ session('error') }}</div>
    @endif

    @if($dashboardRole === 'admin' && $user)
        <form method="POST" action="{{ route('dashboard.profile.update') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5">
                @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5">
                @error('username')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="pt-2 border-t border-gray-100">
                <p class="text-sm font-medium text-gray-800 mb-3">Change password</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Current password</label>
                        <input type="password" name="current_password" autocomplete="current-password" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">New password</label>
                        <input type="password" name="password" autocomplete="new-password" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Confirm new password</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5">
                    </div>
                </div>
                @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full sm:w-auto bg-primary text-white px-6 py-3 rounded-xl font-semibold hover:opacity-95">Save</button>
        </form>
    @endif
</div>
@endsection
