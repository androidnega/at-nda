@extends('layouts.home')

@section('title', 'Sign in — '.config('app.name'))

@push('scripts')
<style>
    /* ───────── at-enda sign-in (v3): aurora glass shell ─────────
       Self-contained. Honours prefers-reduced-motion. No JS deps. */

    :root {
        --brand-50:  #fff1f2;
        --brand-500: #f43f5e;
        --brand-600: #e11d48;
        --brand-700: #be123c;
        --ink-900:   #0b1220;
        --ink-800:   #111827;
        --ink-700:   #1f2937;
    }

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

    /* Glass card */
    .signin-card {
        background: rgba(255, 255, 255, .92);
        backdrop-filter: blur(24px) saturate(140%);
        -webkit-backdrop-filter: blur(24px) saturate(140%);
        border: 1px solid rgba(255, 255, 255, .55);
        box-shadow:
            0 30px 60px -20px rgba(2, 6, 23, .55),
            0 12px 32px -16px rgba(244, 63, 94, .25);
    }

    /* Brand monogram */
    .brand-monogram {
        background: linear-gradient(135deg, #fb7185 0%, #e11d48 50%, #9f1239 100%);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .4),
            0 12px 28px -8px rgba(225, 29, 72, .55);
    }

    /* Brand wordmark gradient */
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

    /* Feature row — clean horizontal strips with a tinted icon tile */
    .feature-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .feature-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 14px;
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, rgba(248,250,252,.9) 100%);
        border: 1px solid rgba(15, 23, 42, .07);
        box-shadow: 0 1px 0 rgba(255,255,255,.7) inset, 0 1px 2px rgba(15,23,42,.04);
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }
    .feature-row:hover {
        transform: translateX(2px);
        border-color: rgba(244, 63, 94, .28);
        box-shadow:
            0 1px 0 rgba(255,255,255,.85) inset,
            0 8px 18px -10px rgba(225, 29, 72, .25);
    }
    .feature-tile {
        flex-shrink: 0;
        height: 36px; width: 36px;
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        background-image: linear-gradient(135deg, #fb7185, #e11d48);
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.35),
            0 6px 14px -6px rgba(225, 29, 72, .55);
    }
    .feature-tile--blue   { background-image: linear-gradient(135deg, #38bdf8, #0ea5e9); box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 6px 14px -6px rgba(14,165,233,.55); }
    .feature-tile--amber  { background-image: linear-gradient(135deg, #fbbf24, #d97706); box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 6px 14px -6px rgba(217,119,6,.55); }
    .feature-tile--violet { background-image: linear-gradient(135deg, #a78bfa, #7c3aed); box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 6px 14px -6px rgba(124,58,237,.5); }
    .feature-label {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
        letter-spacing: -0.005em;
    }
    .feature-sub {
        font-size: 11.5px;
        color: #64748b;
        line-height: 1.35;
        margin-top: 1px;
    }

    /* Sign-in input + button */
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

    /* Download CTA with shine + halo + ping */
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

    /* Mobile scroll behaviour */
    .signin-scroll {
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
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

<div class="signin-stage relative w-full h-[100dvh] flex items-center justify-center px-4 sm:px-6 py-6 sm:py-10">
    <div class="signin-scroll w-full max-w-5xl signin-card rounded-3xl overflow-hidden grid grid-cols-1 md:grid-cols-12 max-h-full">

        {{-- ─── LEFT: Brand + product story ──────────────────── --}}
        <aside class="md:col-span-7 relative px-6 pt-7 pb-4 sm:px-8 sm:pt-9 sm:pb-6 md:px-10 md:pt-11 md:pb-10 flex flex-col gap-5
                      bg-gradient-to-br from-white via-rose-50/40 to-amber-50/40 border-b md:border-b-0 md:border-r border-rose-100/60">
            <div class="flex items-center gap-3.5">
                <div class="brand-monogram h-11 w-11 sm:h-12 sm:w-12 rounded-2xl flex items-center justify-center text-white font-black text-lg sm:text-xl tracking-tight">
                    a
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-black leading-none brand-wordmark tracking-tight">
                        {{ $appName }}
                    </h1>
                    <p class="text-[11px] sm:text-xs text-slate-500 mt-1 font-medium tracking-wide uppercase">
                        Digital attendance system
                    </p>
                </div>
            </div>

            <div>
                <h2 class="text-xl sm:text-2xl md:text-[26px] font-bold text-slate-900 leading-tight tracking-tight">
                    Mark attendance in seconds.
                </h2>
                <p class="mt-1.5 text-sm sm:text-[15px] text-slate-500 leading-relaxed">
                    No queues, no paper, no missed weeks &mdash; just a tap from your phone.
                </p>
            </div>

            <div class="feature-list">
                @foreach([
                    ['icon' => 'fa-bolt',          'tile' => '',                'label' => 'Live sessions',    'sub' => 'Open and close attendance in real time.'],
                    ['icon' => 'fa-cloud-arrow-up','tile' => 'feature-tile--blue',   'label' => 'Works offline',    'sub' => 'Marks sync automatically when you reconnect.'],
                    ['icon' => 'fa-shield-halved', 'tile' => 'feature-tile--amber',  'label' => 'Tamper-resistant', 'sub' => 'Check-ins are bound to one student per device.'],
                ] as $f)
                    <div class="feature-row">
                        <span class="feature-tile {{ $f['tile'] }}">
                            <i class="fas {{ $f['icon'] }} text-sm" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="feature-label">{{ $f['label'] }}</p>
                            <p class="feature-sub">{{ $f['sub'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(\Illuminate\Support\Facades\Route::has('downloads.app.landing'))
            <a href="{{ route('downloads.app.landing') }}"
               class="app-cta group relative mt-1 flex items-center gap-3 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 px-4 py-3.5 text-white shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition-shadow"
               aria-label="Download the mobile app">
                <span class="app-cta__halo" aria-hidden="true"></span>
                <span class="app-cta__shine" aria-hidden="true"></span>

                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25">
                    <i class="fab fa-android text-lg app-cta__icon" aria-hidden="true"></i>
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
                    <span class="block text-[11px] text-emerald-50/90 mt-0.5 truncate">
                        @if($hasBuild)
                            v{{ $latestApp['version_name'] }} &middot; {{ $latestApp['size_human'] }} &middot; mark from your phone
                        @else
                            Mark attendance from your phone &middot; view weekly grids
                        @endif
                    </span>
                </span>

                <span class="relative inline-flex items-center gap-1 text-xs font-bold pl-1 pr-1.5">
                    <span class="hidden sm:inline">Install</span>
                    <i class="fas fa-arrow-right app-cta__arrow text-sm" aria-hidden="true"></i>
                </span>
            </a>
            @endif

            <div class="mt-auto pt-3 flex items-center gap-3 text-[11px] text-slate-500">
                @if(\Illuminate\Support\Facades\Route::has('about'))
                    <a href="{{ route('about') }}" class="inline-flex items-center gap-1 hover:text-slate-900 transition font-medium">
                        <i class="fas fa-circle-info text-[10px]"></i> About
                    </a>
                    <span class="text-slate-300">·</span>
                @endif
                <span>Made by Manuel · Takoradi Technical University</span>
            </div>
        </aside>

        {{-- ─── RIGHT: Sign-in form ──────────────────────────── --}}
        <section class="md:col-span-5 px-6 py-7 sm:px-8 sm:py-9 md:px-10 md:py-11 flex flex-col justify-center bg-white">
            <div class="mb-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-rose-600">Welcome back</p>
                <h2 class="text-2xl sm:text-[26px] font-black text-slate-900 mt-1.5 leading-tight">Sign in</h2>
                <p class="text-[13px] text-slate-500 mt-1">Use your official student ID to continue.</p>
            </div>

            @if (session('success'))
                <div class="mb-3 px-3 py-2.5 bg-emerald-50 text-emerald-800 rounded-xl text-sm border border-emerald-100 flex items-start gap-2">
                    <i class="fas fa-circle-check mt-0.5 text-emerald-600"></i>
                    <span class="flex-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-3 px-3 py-2.5 bg-rose-50 text-rose-800 rounded-xl text-sm border border-rose-100 flex items-start gap-2">
                    <i class="fas fa-circle-xmark mt-0.5 text-rose-600"></i>
                    <span class="flex-1">{{ session('error') }}</span>
                </div>
            @endif
            @if (session('info'))
                <div class="mb-3 px-3 py-2.5 bg-sky-50 text-sky-800 rounded-xl text-sm border border-sky-100 flex items-start gap-2">
                    <i class="fas fa-circle-info mt-0.5 text-sky-600"></i>
                    <span class="flex-1">{{ session('info') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('student.lookup') }}" class="space-y-3.5">
                @csrf
                <div>
                    <label for="index_number" class="block text-[11px] font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Student ID</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                            <i class="fas fa-id-card text-xs"></i>
                        </span>
                        <input type="text" id="index_number" name="index_number" required autofocus
                            class="signin-input w-full rounded-xl pl-10 pr-3 py-3 text-sm text-slate-900 outline-none uppercase placeholder:normal-case placeholder:text-slate-400 font-semibold tracking-wide"
                            placeholder="BC/ITD/24/001"
                            style="text-transform: uppercase;"
                            value="{{ old('index_number') }}"
                            autocomplete="username">
                    </div>
                    @error('index_number')
                        <p class="mt-1.5 text-xs text-rose-600 flex items-center gap-1">
                            <i class="fas fa-circle-exclamation text-[10px]"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit"
                    class="signin-submit w-full text-white py-3 rounded-xl text-sm font-bold tracking-wide flex items-center justify-center gap-2">
                    <span>Continue</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
            </form>

            <div class="mt-4 flex items-center gap-3 text-[12px]">
                @if(\Illuminate\Support\Facades\Route::has('student.password.request.form'))
                    <a href="{{ route('student.password.request.form') }}" class="text-slate-500 hover:text-rose-600 transition font-medium">
                        Forgot password?
                    </a>
                    <span class="text-slate-300">·</span>
                @endif
                <span class="text-slate-400">First time? Enter your ID to set up.</span>
            </div>
        </section>
    </div>
</div>
@endsection
