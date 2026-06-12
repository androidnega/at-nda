@extends('layouts.home')

@section('title', 'Sign in — '.config('app.name'))

@section('content')
<x-auth-signin-layout hero-loading="eager">
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Student access</h2>
        <p class="text-[13px] text-gray-600 mt-1 leading-snug">Use your official student ID to open your {{ config('app.name') }} account.</p>
    </div>

    @if (session('success'))
        <div class="mb-3 p-2.5 bg-emerald-50 text-emerald-800 rounded-lg text-sm border border-emerald-100">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">{{ session('error') }}</div>
    @endif
    @if (session('info'))
        <div class="mb-3 p-2.5 bg-sky-50 text-sky-800 rounded-lg text-sm border border-sky-100">{{ session('info') }}</div>
    @endif

    <form method="POST" action="{{ route('student.lookup') }}" class="space-y-3">
        @csrf
        <div>
            <label for="index_number" class="block text-xs font-medium text-gray-700 mb-1.5">Student ID</label>
            <input type="text" id="index_number" name="index_number" required autofocus
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition uppercase"
                placeholder="e.g. BC/ITD/24/001" style="text-transform: uppercase;" value="{{ old('index_number') }}"
                autocomplete="username">
        </div>
        @error('index_number')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="w-full bg-gradient-to-r from-gray-900 to-gray-800 text-white py-2.5 rounded-xl text-sm font-semibold hover:from-gray-800 hover:to-gray-700 transition">
            Continue
        </button>
    </form>

    @if(\Illuminate\Support\Facades\Route::has('student.password.request.form'))
    <div class="mt-4 text-center">
        <a href="{{ route('student.password.request.form') }}" class="text-xs text-gray-600 hover:text-gray-900 transition font-medium">
            Forgot password?
        </a>
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Route::has('downloads.app.landing'))
    <a href="{{ route('downloads.app.landing') }}"
       class="mt-4 group flex items-center gap-3 rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-emerald-100/40 px-3.5 py-2.5 hover:from-emerald-100 hover:to-emerald-200/60 transition">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
            <i class="fab fa-android text-base"></i>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-[12px] font-semibold text-emerald-900">Get the mobile app</span>
            <span class="block text-[11px] text-emerald-700/80">Mark attendance from your phone, view weekly grids.</span>
        </span>
        <i class="fas fa-arrow-right text-emerald-700/60 group-hover:translate-x-0.5 transition"></i>
    </a>
    @endif

    @if(\Illuminate\Support\Facades\Route::has('about'))
    <div class="mt-2 text-center">
        <a href="{{ route('about') }}" class="text-[11px] text-gray-500 hover:text-gray-800 transition font-medium inline-flex items-center gap-1">
            <i class="fas fa-circle-info text-[10px]"></i> About
        </a>
    </div>
    @endif
</x-auth-signin-layout>
@endsection
