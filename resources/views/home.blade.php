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
            linear-gradient(180deg, rgba(8, 12, 22, .72) 0%, rgba(11, 18, 32, .82) 100%),
            url('{{ asset('images/auth/home-campus-bg.jpg') }}') center center / cover no-repeat;
        position: relative;
        overflow: hidden;
    }
    .signin-stage::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse 80% 60% at 50% 100%, rgba(0, 0, 0, .35), transparent 70%);
        pointer-events: none;
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

    .app-download-chip {
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }
    .app-download-chip:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px -8px rgba(16, 185, 129, .55);
    }

    @media (prefers-reduced-motion: reduce) {
        .brand-wordmark {
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
    <div class="signin-card relative z-10 w-full max-w-sm rounded-3xl px-6 py-7 sm:px-7 sm:py-8">

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

        <div class="mt-5 flex items-center justify-between gap-3">
            @if(\Illuminate\Support\Facades\Route::has('about'))
                <a href="{{ route('about') }}"
                   class="text-[12px] text-slate-500 hover:text-rose-600 transition font-medium inline-flex items-center gap-1.5">
                    <i class="fas fa-circle-info text-[10px]" aria-hidden="true"></i>
                    About us
                </a>
            @else
                <span></span>
            @endif

            @if(\Illuminate\Support\Facades\Route::has('downloads.app.landing'))
                <a href="{{ route('downloads.app.landing') }}"
                   class="app-download-chip inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-2 py-1.5 text-white shadow-md shadow-emerald-600/25"
                   aria-label="Download the Android app{{ $hasBuild ? ' — v'.$latestApp['version_name'] : '' }}"
                   @if($hasBuild) title="v{{ $latestApp['version_name'] }} · {{ $latestApp['size_human'] }}" @endif>
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/15">
                        <i class="fab fa-android text-sm" aria-hidden="true"></i>
                    </span>
                    <i class="fas fa-arrow-right text-[10px] pr-1" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
