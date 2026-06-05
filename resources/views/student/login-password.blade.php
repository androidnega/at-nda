@extends('layouts.home')

@section('title', 'Enter password — '.config('app.name'))

@section('content')
<x-auth-signin-layout>
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Sign in</h2>
        <p class="text-[13px] text-gray-600 mt-1 leading-snug">Enter your password for <span class="font-medium text-gray-800">{{ config('app.name') }}</span>.</p>
    </div>

    @if (session('error'))
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 mb-0.5">Student ID</p>
        <p class="text-sm font-medium text-gray-900 font-mono tracking-tight" title="Partially hidden for privacy">{{ \App\Models\Student::maskIndexForDisplay($indexNumber) }}</p>
        <p class="text-[10px] text-gray-500 mt-1.5">Confirm this matches your ID, then enter your password.</p>
    </div>

    <form method="POST" action="{{ route('student.login.password') }}" class="space-y-3">
        @csrf
        <div>
            <label for="password" class="block text-xs font-medium text-gray-700 mb-1.5">Password</label>
            <input type="password" id="password" name="password" required autofocus
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition"
                placeholder="••••••••" autocomplete="current-password">
        </div>
        @error('password')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="w-full bg-gradient-to-r from-gray-900 to-gray-800 text-white py-2.5 rounded-xl text-sm font-semibold hover:from-gray-800 hover:to-gray-700 transition">
            Continue
        </button>
    </form>

    <div class="mt-4 flex flex-col items-center gap-2">
        @if(\Illuminate\Support\Facades\Route::has('student.password.request.form'))
        <a href="{{ route('student.password.request.form') }}" class="text-xs text-gray-600 hover:text-gray-900 transition font-medium">
            Forgot password?
        </a>
        @endif
        <a href="{{ route('student.login.cancel') }}" class="text-xs text-gray-500 hover:text-gray-800 transition">Use a different student ID</a>
    </div>
</x-auth-signin-layout>
@endsection
