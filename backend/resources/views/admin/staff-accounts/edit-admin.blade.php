@extends('layouts.admin')

@section('title', 'Edit administrator')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.staff-accounts.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> User management
    </a>
    <h1 class="text-2xl font-bold">Edit administrator</h1>
</div>

<form method="POST" action="{{ route('dashboard.staff-accounts.admins.update', $user) }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden max-w-2xl">
    @csrf @method('PUT')
    <div class="p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" id="username" name="username" value="{{ old('username', $user->username) }}"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('username')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">New password <span class="text-gray-400 font-normal">(leave blank to keep)</span></label>
            <input type="password" id="password" name="password" minlength="6" autocomplete="new-password"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('password')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" minlength="6" autocomplete="new-password"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
        </div>
    </div>
    <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">Save</button>
        <a href="{{ route('dashboard.staff-accounts.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection
