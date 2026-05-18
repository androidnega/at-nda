@extends('layouts.home')

@section('title', 'Create password — '.config('app.name'))

@section('content')
<x-auth-signin-layout>
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">First-time setup</h2>
        <p class="text-[13px] text-gray-600 mt-1 leading-snug">Create a password for your <span class="font-medium text-gray-800">{{ config('app.name') }}</span> account.</p>
    </div>

    @if (session('error'))
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 mb-0.5">Student ID</p>
        <p class="text-sm font-medium text-gray-900 font-mono tracking-tight" title="Partially hidden for privacy">{{ \App\Models\Student::maskIndexForDisplay($indexNumber) }}</p>
        <p class="text-[10px] text-gray-500 mt-1.5">Pick a password you’ll remember — you’ll use it for every sign-in.</p>
    </div>

    <form method="POST" action="{{ route('student.set-password.post') }}" class="space-y-3">
        @csrf
        <div>
            <label for="password" class="block text-xs font-medium text-gray-700 mb-1.5">Password</label>
            <input type="password" id="password" name="password" required minlength="6" autofocus
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition"
                placeholder="At least 6 characters" autocomplete="new-password">
        </div>
        @error('password')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
        <div>
            <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1.5">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition"
                placeholder="Repeat password" autocomplete="new-password">
        </div>
        <button type="submit" class="w-full bg-gradient-to-r from-gray-900 to-gray-800 text-white py-2.5 rounded-xl text-sm font-semibold hover:from-gray-800 hover:to-gray-700 transition">
            Create password &amp; continue
        </button>
    </form>

    <a href="{{ route('student.login.cancel') }}" class="mt-4 block text-center text-xs text-gray-500 hover:text-gray-800 transition">Use a different student ID</a>
</x-auth-signin-layout>
@endsection
