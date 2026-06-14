@extends('layouts.home')

@section('title', 'Sign in — '.config('app.name'))

@push('scripts')
<style>
    /* ───────── Minimal sign-in shell ─────────
       Just the brand, the student-id field, and the download CTA.
       Self-contained CSS, respects prefers-reduced-motion. */

    .signin-stage {
        min-height: 100dvh;
        background:
            radial-gradient(ellipse 70% 60% at 12% 18%, rgba(244,63,94,.20), transparent 55%),
            radial-gradient(ellipse 60% 50% at 88% 82%, rgba(251,191,36,.16), transparent 55%),
            radial-gradient(ellipse 80% 70% at 50% 110%, rgba(99,102,241,.18), transparent 55%),
            linear-gradient(180deg, #0b1220 0%, #111827 100%);
        position: relative;
        overflow: hidden;
    }
    .signin-stage::before,
    .signin-stage::after {
        content: '';
        position: absolute;
        border-radius: 9999px;
        filter: blur(60px);
        opacity: .55;
        pointer-events: none;
    }
    .signin-stage::before {
        width: 360px; height: 360px;
        left: -90px; top: -120px;
        background: radial-gradient(circle, rgba(244,63,94,.55), rgba(244,63,94,0) 70%);
        animation: signin-drift 14s ease-in-out infinite;
    }
    .signin-stage::after {
        width: 420px; height: 420px;
        right: -120px; bottom: -160px;
        background: radial-gradient(circle, rgba(251,191,36,.45), rgba(251,191,36,0) 70%);
        animation: signin-drift 18s ease-in-out infinite reverse;
    }
    @keyframes signin-drift {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50%      { transform: translate(40px, 30px) scale(1.08); }
    }

    .signin-card {
        background: rgba(255, 255, 255, .96);
        backdrop-filter: blur(24px) saturate(140%);
        -webkit-backdrop-filter: blur(24px) saturate(140%);
        border: 1px solid rgba(255, 255, 255, .55);
        box-shadow:
            0 30px 60px -20px rgba(2, 6, 23, .55),
            0 12px 32px -16px rgba(244, 63, 94, .22);
    }

    .brand-monogram {
        background: linear-gradient(135deg, #fb7185 0%, #e11d48 50%, #9f1239 100%);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .4),
            0 10px 24px -8px rgba(225, 29, 72, .55);
    }
    .brand-wordmark {
        background-image: linear-gradient(95deg, #fb7185 0%, #e11d48 35%, #f59e0b 95%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        background-size: 200% 100%;
        animation: brand-wash 7s ease-in-out infinite;
    }
    @keyframes brand-wash {
        0%, 100% { background-position: 0% 50%; }
        50%      { background-position: 100% 50%; }
    }

    .signin-input {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all .18s ease;
    }
    .signin-input:focus {
        background: #ffffff;
        border-color: #e11d48;
        box-shadow: 0 0 0 4px rgba(225, 29, 72, .14);
    }
    .signin-submit {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        box-shadow: 0 12px 24px -10px rgba(15, 23, 42, .55);
    }
    .signin-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 30px -12px rgba(15, 23, 42, .65);
        filter: brightness(1.08);
    }
    .signin-submit:active { transform: translateY(0); }

    /* Download CTA — halo + shine + ping */
    .app-cta {
        position: relative;
        overflow: hidden;
        isolation: isolate;
    }
    .app-cta__halo {
        position: absolute;
        inset: -2px;
        border-radius: inherit;
        background: linear-gradient(135deg, rgba(16,185,129,.55), transparent 55%, rgba(16,185,129,.55));
        opacity: .55;
        z-index: -1;
        animation: app-cta-halo 3.4s ease-in-out infinite;
    }
    .app-cta__shine {
        position: absolute;
        top: 0; bottom: 0;
        width: 35%;
        left: -45%;
        background: linear-gradient(110deg, transparent 20%, rgba(255,255,255,.45) 50%, transparent 80%);
        transform: skewX(-18deg);
        animation: app-cta-shine 4.2s ease-in-out infinite;
        pointer-events: none;
    }
    .app-cta__icon  { animation: app-cta-bob 2.6s ease-in-out infinite; }
    .app-cta__ping  { animation: app-cta-ping 1.8s cubic-bezier(0,0,.2,1) infinite; }
    .app-cta__arrow { animation: app-cta-arrow 1.6s ease-in-out infinite; }

    @keyframes app-cta-halo {
        0%, 100% { opacity: .35; filter: blur(8px); }
        50%      { opacity: .75; filter: blur(14px); }
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
        .signin-stage::before,
        .signin-stage::after,
        .brand-wordmark,
        .app-cta__halo,
        .app-cta__shine,
        .app-cta__icon,
        .app-cta__ping,
        .app-cta__arrow {
            animation: none !important;
        }
    }
</style>
@endpush

@section('content')
@php
    $appName = config('app.name', 'at-enda');
    $hasBuild = ! empty($latestApp ?? null);
@endphp

<div class="signin-stage relative w-full h-[100dvh] flex items-center justify-center px-4 py-6">
    <div class="signin-card w-full max-w-sm rounded-3xl px-6 py-7 sm:px-7 sm:py-8">

        <div class="flex flex-col items-center text-center mb-6">
            <div class="brand-monogram h-14 w-14 rounded-2xl flex items-center justify-center text-white font-black text-2xl mb-3">
                a
            </div>
            <h1 class="brand-wordmark text-3xl font-black tracking-tight leading-none">
                {{ $appName }}
            </h1>
        </div>

        @if (session('success'))
            <div class="mb-3 px-3 py-2 bg-emerald-50 text-emerald-800 rounded-xl text-[13px] border border-emerald-100">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-3 px-3 py-2 bg-rose-50 text-rose-800 rounded-xl text-[13px] border border-rose-100">{{ session('error') }}</div>
        @endif
        @if (session('info'))
            <div class="mb-3 px-3 py-2 bg-sky-50 text-sky-800 rounded-xl text-[13px] border border-sky-100">{{ session('info') }}</div>
        @endif

        <form method="POST" action="{{ route('student.lookup') }}" class="space-y-3">
            @csrf
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                    <i class="fas fa-id-card text-xs"></i>
                </span>
                <input type="text" id="index_number" name="index_number" required autofocus
                    class="signin-input w-full rounded-xl pl-10 pr-3 py-3 text-sm text-slate-900 outline-none uppercase placeholder:normal-case placeholder:text-slate-400 font-semibold tracking-wide"
                    placeholder="Student ID"
                    style="text-transform: uppercase;"
                    value="{{ old('index_number') }}"
                    autocomplete="username">
                @error('index_number')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="signin-submit w-full text-white py-3 rounded-xl text-sm font-bold tracking-wide flex items-center justify-center gap-2">
                <span>Continue</span>
                <i class="fas fa-arrow-right text-xs"></i>
            </button>
        </form>

        @if(\Illuminate\Support\Facades\Route::has('student.password.request.form'))
            <div class="mt-2.5 text-center">
                <a href="{{ route('student.password.request.form') }}" class="text-[11px] text-slate-400 hover:text-rose-600 transition font-medium">
                    Forgot password?
                </a>
            </div>
        @endif

        @if(\Illuminate\Support\Facades\Route::has('downloads.app.landing'))
        <a href="{{ route('downloads.app.landing') }}"
           class="app-cta group relative mt-5 flex items-center gap-3 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 px-4 py-3 text-white shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-shadow"
           aria-label="Download the mobile app">
            <span class="app-cta__halo" aria-hidden="true"></span>
            <span class="app-cta__shine" aria-hidden="true"></span>

            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25">
                <i class="fab fa-android text-base app-cta__icon" aria-hidden="true"></i>
                @if($hasBuild)
                    <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-amber-300 app-cta__ping"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-emerald-600"></span>
                    </span>
                @endif
            </span>

            <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-bold leading-tight">Download the app</span>
                @if($hasBuild)
                    <span class="block text-[10.5px] text-emerald-50/90 mt-0.5 truncate">v{{ $latestApp['version_name'] }} &middot; {{ $latestApp['size_human'] }}</span>
                @endif
            </span>

            <i class="fas fa-arrow-right app-cta__arrow text-sm" aria-hidden="true"></i>
        </a>
        @endif
    </div>
</div>
@endsection
