@extends('layouts.home')

@section('title', 'Enter password — '.config('app.name'))

@php
    $heroImage = config('app.auth_hero_image');
@endphp

@section('content')
<div class="min-h-screen min-h-[100dvh] flex items-center justify-center p-4 sm:p-5 lg:p-8">
    <div class="w-full max-w-5xl flex flex-col gap-4 lg:gap-0 lg:flex-row lg:items-center lg:justify-center">
        <section class="order-2 lg:order-1 w-full max-w-md mx-auto lg:mx-0 lg:w-[min(100%,22rem)] shrink-0 lg:z-20 lg:-mr-8 xl:-mr-12
            bg-white rounded-2xl border border-gray-200/90 flex flex-col justify-center py-5 px-5 sm:px-6 sm:py-6">
            <div class="w-full">
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

                <a href="{{ route('student.login.cancel') }}" class="mt-4 block text-center text-xs text-gray-500 hover:text-gray-800 transition">Use a different student ID</a>
            </div>
        </section>

        @include('partials.auth-hero-panel', ['heroImage' => $heroImage])
    </div>
</div>
@endsection
