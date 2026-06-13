@extends('layouts.home')

@section('title', 'Sign in — '.config('app.name'))

@push('scripts')
<style>
    /* ───────── Home page download CTA — purely additive ─────────
       All custom CSS is namespaced under .app-cta so it can't leak
       into the rest of the auth shell. Honours
       prefers-reduced-motion: reduce. */
    .app-cta {
        position: relative;
        overflow: hidden;
        isolation: isolate;
    }
    .app-cta__halo {
        position: absolute;
        inset: -2px;
        border-radius: inherit;
        background: linear-gradient(135deg, rgba(16,185,129,.55), rgba(5,150,105,.0) 55%, rgba(16,185,129,.55));
        opacity: .55;
        z-index: -1;
        animation: app-cta-halo 3.4s ease-in-out infinite;
    }
    .app-cta__shine {
        position: absolute;
        top: 0; bottom: 0;
        width: 35%;
        left: -40%;
        background: linear-gradient(110deg, transparent 20%, rgba(255,255,255,.45) 50%, transparent 80%);
        transform: skewX(-18deg);
        animation: app-cta-shine 4.2s ease-in-out infinite;
        pointer-events: none;
    }
    .app-cta__icon {
        animation: app-cta-bob 2.6s ease-in-out infinite;
    }
    .app-cta__ping {
        animation: app-cta-ping 1.8s cubic-bezier(0,0,.2,1) infinite;
    }
    .app-cta__arrow {
        animation: app-cta-arrow 1.6s ease-in-out infinite;
    }
    @keyframes app-cta-halo {
        0%, 100% { opacity: .35; filter: blur(8px); }
        50%       { opacity: .75; filter: blur(14px); }
    }
    @keyframes app-cta-shine {
        0%   { left: -45%; }
        55%  { left: 115%; }
        100% { left: 115%; }
    }
    @keyframes app-cta-bob {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-3px); }
    }
    @keyframes app-cta-ping {
        0%   { transform: scale(.9); opacity: .9; }
        80%  { transform: scale(2.4); opacity: 0; }
        100% { transform: scale(2.4); opacity: 0; }
    }
    @keyframes app-cta-arrow {
        0%, 100% { transform: translateX(0); }
        50%      { transform: translateX(3px); }
    }
    @media (prefers-reduced-motion: reduce) {
        .app-cta__halo,
        .app-cta__shine,
        .app-cta__icon,
        .app-cta__ping,
        .app-cta__arrow { animation: none !important; }
    }
</style>
@endpush

@section('content')
<x-auth-signin-layout hero-loading="eager">
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-5'])

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
            <label for="index_number" class="block text-xs font-semibold text-gray-700 mb-1.5 tracking-wide">Student ID</label>
            <input type="text" id="index_number" name="index_number" required autofocus
                class="w-full border border-gray-200 rounded-xl px-3.5 py-3 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition uppercase placeholder:normal-case placeholder:text-gray-400"
                placeholder="BC/ITD/24/001" style="text-transform: uppercase;" value="{{ old('index_number') }}"
                autocomplete="username">
        </div>
        @error('index_number')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white py-3 rounded-xl text-sm font-semibold transition shadow-sm">
            Continue
        </button>
    </form>

    @if(\Illuminate\Support\Facades\Route::has('student.password.request.form'))
    <div class="mt-3 text-center">
        <a href="{{ route('student.password.request.form') }}" class="text-xs text-gray-500 hover:text-gray-900 transition">
            Forgot password?
        </a>
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Route::has('downloads.app.landing'))
    @php($hasBuild = ! empty($latestApp ?? null))
    <div class="mt-5 pt-4 border-t border-dashed border-gray-200">
        <a href="{{ route('downloads.app.landing') }}"
           class="app-cta group relative flex items-center gap-3 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 px-4 py-3.5 text-white shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-shadow"
           aria-label="Download the mobile app">
            <span class="app-cta__halo" aria-hidden="true"></span>
            <span class="app-cta__shine" aria-hidden="true"></span>

            <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25">
                <i class="fab fa-android text-xl app-cta__icon" aria-hidden="true"></i>
                @if($hasBuild)
                    <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-amber-300 app-cta__ping"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-emerald-600"></span>
                    </span>
                @endif
            </span>

            <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-bold leading-tight">
                    Get the Android app
                    @if($hasBuild)
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-amber-400/95 text-amber-950 px-1.5 py-px text-[9px] font-extrabold uppercase tracking-wider align-middle">NEW</span>
                    @endif
                </span>
                <span class="block text-[11px] text-emerald-50/85 mt-0.5 truncate">
                    @if($hasBuild)
                        v{{ $latestApp['version_name'] }} &middot; {{ $latestApp['size_human'] }} &middot; mark from your phone
                    @else
                        Mark attendance from your phone &middot; view weekly grids
                    @endif
                </span>
            </span>

            <span class="relative inline-flex items-center gap-1 text-xs font-semibold pl-1 pr-1.5">
                <span class="hidden sm:inline">Install</span>
                <i class="fas fa-arrow-right app-cta__arrow text-sm" aria-hidden="true"></i>
            </span>
        </a>
    </div>
    @endif

    @if(\Illuminate\Support\Facades\Route::has('about'))
    <div class="mt-3 text-center">
        <a href="{{ route('about') }}" class="text-[11px] text-gray-400 hover:text-gray-700 transition inline-flex items-center gap-1">
            <i class="fas fa-circle-info text-[10px]"></i> About {{ config('app.name') }}
        </a>
    </div>
    @endif
</x-auth-signin-layout>
@endsection
