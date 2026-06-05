@extends('layouts.home')

@section('title', 'Enter reset code — '.config('app.name'))

@section('content')
<x-auth-signin-layout>
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Reset password</h2>
        <p class="text-[13px] text-gray-600 mt-1 leading-snug">Enter the 6-digit code from your email and choose a new password.</p>
    </div>

    @if (session('success'))
        <div class="mb-3 p-2.5 bg-emerald-50 text-emerald-800 rounded-lg text-sm border border-emerald-100">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('student.password.confirm') }}" class="space-y-3">
        @csrf
        <div>
            <label for="index_number" class="block text-xs font-medium text-gray-700 mb-1.5">Student Index Number</label>
            <input type="text" id="index_number" name="index_number"
                value="{{ old('index_number', $indexNumber ?? '') }}"
                required inputmode="text" autocapitalize="characters" autocomplete="username"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono uppercase focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition">
        </div>

        <div>
            <label for="code" class="block text-xs font-medium text-gray-700 mb-1.5">6-digit code</label>
            <input type="text" id="code" name="code" required autofocus inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-base font-mono tracking-[0.4em] text-center focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition"
                placeholder="• • • • • •">
        </div>
        @error('code')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <div>
            <label for="password" class="block text-xs font-medium text-gray-700 mb-1.5">New password</label>
            <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition"
                placeholder="At least 6 characters">
        </div>
        @error('password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <div>
            <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1.5">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6" autocomplete="new-password"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition">
        </div>

        <button type="submit" class="w-full bg-gradient-to-r from-gray-900 to-gray-800 text-white py-2.5 rounded-xl text-sm font-semibold hover:from-gray-800 hover:to-gray-700 transition">
            <i class="fas fa-lock mr-2 text-xs"></i> Set new password
        </button>
    </form>

    <div class="mt-4 flex items-center justify-between gap-3 text-xs text-gray-500">
        <a href="{{ route('student.password.request.form') }}" class="hover:text-gray-800 transition">
            <i class="fas fa-arrow-left mr-1 text-[10px]"></i> Get a new code
        </a>
        <a href="{{ route('home') }}" class="hover:text-gray-800 transition">
            Back to sign in <i class="fas fa-arrow-right ml-1 text-[10px]"></i>
        </a>
    </div>
</x-auth-signin-layout>
@endsection
