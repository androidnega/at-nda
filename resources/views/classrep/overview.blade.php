@extends('layouts.classrep')

@section('title', 'Dashboard')

@push('styles')
{{-- Leaflet (OpenStreetMap) loaded from CDN — no API key required. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
{{-- Chart.js for the dashboard bar + donut charts. Loaded from a
     pinned CDN version so we never inherit a breaking minor bump. --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
<style>
    /* ──────────────── Dashboard chrome ────────────────
       Colourful KPI tiles are pure CSS — no images, no heavy assets.
       The decorative blobs in the hero are radial-gradients pinned
       to the corners so they survive any background image. */
    .rep-hero-blob-1, .rep-hero-blob-2 { position: absolute; border-radius: 50%; pointer-events: none; }
    .rep-hero-blob-1 { width: 420px; height: 420px; right: -120px; top: -160px;
        background: radial-gradient(circle, rgba(20,184,166,0.55) 0%, rgba(20,184,166,0) 70%); }
    .rep-hero-blob-2 { width: 320px; height: 320px; left: -100px; bottom: -140px;
        background: radial-gradient(circle, rgba(244,114,182,0.45) 0%, rgba(244,114,182,0) 70%); }

    .rep-kpi {
        position: relative;
        border-radius: 1rem;
        padding: 1rem 1.1rem;
        color: #ffffff;
        overflow: hidden;
        isolation: isolate;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
        transition: transform 220ms ease, box-shadow 220ms ease;
    }
    .rep-kpi:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12); }
    .rep-kpi::after {
        content: ""; position: absolute; right: -40px; top: -40px;
        width: 140px; height: 140px; border-radius: 50%;
        background: rgba(255, 255, 255, 0.12); z-index: -1;
    }
    .rep-kpi--teal     { background: linear-gradient(135deg, #14b8a6 0%, #0f766e 100%); }
    .rep-kpi--amber    { background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); }
    .rep-kpi--emerald  { background: linear-gradient(135deg, #34d399 0%, #047857 100%); }
    .rep-kpi--violet   { background: linear-gradient(135deg, #a78bfa 0%, #6d28d9 100%); }
    .rep-kpi--sky      { background: linear-gradient(135deg, #38bdf8 0%, #0369a1 100%); }
    .rep-kpi__icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 38px; height: 38px; border-radius: 0.75rem;
        background: rgba(255, 255, 255, 0.22); color: #ffffff;
        backdrop-filter: blur(6px);
    }

    .rep-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04); overflow: hidden; }
    .rep-card__head { padding: 0.85rem 1.1rem; border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap; }
    .rep-card__head h2 { font-size: 0.95rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em; }
    .rep-card__head p { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
    .rep-card__icon { width: 36px; height: 36px; border-radius: 0.75rem;
        display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; }
    .rep-card__icon--sky      { background: #e0f2fe; color: #0369a1; }
    .rep-card__icon--violet   { background: #ede9fe; color: #6d28d9; }
    .rep-card__icon--emerald  { background: #d1fae5; color: #047857; }
    .rep-card__icon--amber    { background: #fef3c7; color: #b45309; }
    .rep-card__icon--rose     { background: #ffe4e6; color: #be123c; }
    .rep-card__body { padding: 1rem 1.1rem; }

    /* ──────────────── Attendance map ────────────────
       The map sits inside a softly bevelled card. Each per-student pin
       is a custom DivIcon (CSS-only) so we can animate `scale`, `ring`
       and `shadow` on hover without depending on a marker library. */
    #rep-attendance-map {
        width: 100%;
        height: 100%;
        border-radius: 14px;
        z-index: 0;
    }
    #rep-attendance-map .leaflet-control-attribution {
        font-size: 10px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(6px);
    }
    .rep-pin {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18),
                    0 6px 14px rgba(2, 132, 199, 0.35);
        border: 2px solid #ffffff;
        cursor: pointer;
        position: relative;
        transition: transform 220ms cubic-bezier(.2,.8,.2,1),
                    box-shadow 220ms cubic-bezier(.2,.8,.2,1);
    }
    .rep-pin::after {
        content: "";
        position: absolute;
        inset: -10px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(14,165,233,0.25) 0%, rgba(14,165,233,0) 70%);
        opacity: 0;
        transition: opacity 220ms ease;
        pointer-events: none;
    }
    .rep-pin:hover {
        transform: scale(1.45);
        box-shadow: 0 0 0 5px rgba(14, 165, 233, 0.25),
                    0 10px 22px rgba(2, 132, 199, 0.45);
        z-index: 800;
    }
    .rep-pin:hover::after { opacity: 1; }

    /* Per-mode pin tint so a quick glance reveals which capture method
       a row used (QR vs hybrid vs raw location). */
    .rep-pin--qr { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18), 0 6px 14px rgba(79,70,229,0.35); }
    .rep-pin--hybrid { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.18), 0 6px 14px rgba(217,119,6,0.35); }
    .rep-pin--wifi { background: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,0.18), 0 6px 14px rgba(13,148,136,0.35); }
    .rep-pin--qr:hover::after { background: radial-gradient(circle, rgba(99,102,241,0.28) 0%, rgba(99,102,241,0) 70%); }
    .rep-pin--hybrid:hover::after { background: radial-gradient(circle, rgba(245,158,11,0.30) 0%, rgba(245,158,11,0) 70%); }
    .rep-pin--wifi:hover::after { background: radial-gradient(circle, rgba(20,184,166,0.28) 0%, rgba(20,184,166,0) 70%); }

    /* Subtle pulse on the latest mark so the rep can spot it. */
    @keyframes repPinPulse {
        0% { box-shadow: 0 0 0 3px rgba(14,165,233,0.5), 0 6px 14px rgba(2,132,199,0.35); }
        70% { box-shadow: 0 0 0 14px rgba(14,165,233,0), 0 6px 14px rgba(2,132,199,0.35); }
        100% { box-shadow: 0 0 0 3px rgba(14,165,233,0.18), 0 6px 14px rgba(2,132,199,0.35); }
    }
    .rep-pin--fresh { animation: repPinPulse 1.8s ease-out infinite; }

    .leaflet-popup-content-wrapper {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
    }
    .leaflet-popup-content {
        margin: 10px 14px;
        font-family: Inter, system-ui, sans-serif;
        font-size: 12.5px;
        line-height: 1.45;
        color: #1e293b;
    }
    .leaflet-popup-content b { color: #0f172a; }
    .leaflet-popup-content .pop-meta {
        margin-top: 4px;
        color: #64748b;
        font-size: 11px;
    }
    .leaflet-popup-content .pop-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .map-filter-chip {
        cursor: pointer;
        user-select: none;
        transition: background 160ms ease, color 160ms ease, transform 160ms ease;
    }
    .map-filter-chip:hover { transform: translateY(-1px); }
    .map-filter-chip[aria-pressed="true"] { background: #0f172a; color: #ffffff; }
</style>
@endpush

@section('content')
<div class="w-full min-w-0 space-y-6">
    @php
        $repGreetingName = trim((string) ($student->last_name ?? ''));
        if ($repGreetingName === '') {
            $repGreetingName = (string) ($student->index_number ?? 'there');
        }
        // Trend helpers — turn raw counts into a percent change vs. the
        // previous equivalent window so the hero can show a friendly
        // ↑ / ↓ chip instead of a bare number.
        $trendDaysSafe = $trendDays ?? 14;
        $weeklyTrendColl = collect($weeklyTrend ?? []);
        $lastHalf = $weeklyTrendColl->slice(intdiv($trendDaysSafe, 2))->sum('count');
        $firstHalf = $weeklyTrendColl->slice(0, intdiv($trendDaysSafe, 2))->sum('count');
        $deltaPct = $firstHalf > 0
            ? round((($lastHalf - $firstHalf) / $firstHalf) * 100)
            : ($lastHalf > 0 ? 100 : 0);
        $deltaUp = $deltaPct >= 0;
        $modeBreakdownColl = collect($modeBreakdown ?? []);
        $topCoursesColl = collect($topCourses ?? []);
        $topStudentsColl = collect($topStudents ?? []);
    @endphp

    {{-- Hero: gradient + decorative blobs + greeting + quick actions --}}
    <div class="relative overflow-hidden rounded-2xl text-white"
         style="background: linear-gradient(135deg, #0f766e 0%, #115e59 45%, #1e1b4b 100%);">
        <div class="rep-hero-blob-1" aria-hidden="true"></div>
        <div class="rep-hero-blob-2" aria-hidden="true"></div>
        <div class="relative z-10 px-5 py-6 sm:px-8 sm:py-9">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-teal-100/90 text-[11px] font-semibold uppercase tracking-[0.18em] inline-flex items-center gap-1.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Class Rep · Dashboard
                    </p>
                    <h1 class="text-2xl sm:text-[1.85rem] font-bold mt-1.5 tracking-tight">
                        Hello, {{ $repGreetingName }} 👋
                    </h1>
                    <p class="text-teal-100/80 text-sm mt-1.5 max-w-xl">
                        {{ now()->format('l, F j, Y') }} · Here's your class activity at a glance.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('dashboard.session') }}" class="inline-flex items-center gap-2 rounded-xl bg-white text-teal-800 px-4 py-2.5 text-sm font-semibold hover:bg-teal-50 shadow-md shadow-teal-900/20">
                            <i class="fas fa-play-circle"></i> Open session
                        </a>
                        <a href="{{ route('dashboard.class-attendance.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 border border-white/25 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 backdrop-blur">
                            <i class="fas fa-clipboard-list"></i> Attendance
                        </a>
                        <a href="{{ route('dashboard.students.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 border border-white/25 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 backdrop-blur">
                            <i class="fas fa-user-friends"></i> Students
                        </a>
                    </div>
                </div>
                <div class="hidden md:flex flex-col items-end gap-2">
                    <div class="inline-flex items-center gap-1.5 rounded-full {{ $deltaUp ? 'bg-emerald-400/20 text-emerald-100 ring-emerald-300/30' : 'bg-rose-400/20 text-rose-100 ring-rose-300/30' }} ring-1 px-3 py-1 text-xs font-bold">
                        <i class="fas {{ $deltaUp ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-[11px]"></i>
                        {{ $deltaUp ? '+' : '' }}{{ $deltaPct }}% vs prior
                    </div>
                    <p class="text-[11px] text-teal-100/70 max-w-[14rem] text-right leading-snug">
                        Last {{ intdiv($trendDaysSafe, 2) }} days compared to the {{ intdiv($trendDaysSafe, 2) }} before.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI tiles: vivid gradient cards with floating decorations --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
        <div class="rep-kpi rep-kpi--teal">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-white/85">Students</span>
                <span class="rep-kpi__icon"><i class="fas fa-users text-sm"></i></span>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $studentsCount }}</p>
            <p class="text-[10px] text-white/75 mt-1 font-medium">In your class</p>
        </div>
        <div class="rep-kpi rep-kpi--amber">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-white/85">Courses</span>
                <span class="rep-kpi__icon"><i class="fas fa-book text-sm"></i></span>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $coursesCount }}</p>
            <p class="text-[10px] text-white/75 mt-1 font-medium">Assigned to you</p>
        </div>
        <div class="rep-kpi rep-kpi--emerald">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-white/85">7 days</span>
                <span class="rep-kpi__icon"><i class="fas fa-chart-line text-sm"></i></span>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $weekAttendanceMarks }}</p>
            <p class="text-[10px] text-white/75 mt-1 font-medium">Marks this week</p>
        </div>
        <div class="rep-kpi rep-kpi--violet">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-white/85">Today</span>
                <span class="rep-kpi__icon"><i class="fas fa-sun text-sm"></i></span>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $todayAttendanceMarks }}</p>
            <p class="text-[10px] text-white/75 mt-1 font-medium">Marks today</p>
        </div>
        <div class="rep-kpi rep-kpi--sky">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-white/85">All-time</span>
                <span class="rep-kpi__icon"><i class="fas fa-clipboard-check text-sm"></i></span>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $totalAttendanceMarks }}</p>
            <p class="text-[10px] text-white/75 mt-1 font-medium">Total recorded</p>
        </div>
    </div>

    {{-- ─────────── Trends row ───────────
         Bar chart (last {{ $trendDaysSafe }} days) + capture-mode donut.
         Empty data sets render a friendly placeholder rather than an
         empty chart so a fresh class doesn't look broken. --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="rep-card xl:col-span-2">
            <div class="rep-card__head">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="rep-card__icon rep-card__icon--sky"><i class="fas fa-chart-column"></i></span>
                    <div>
                        <h2>Attendance trend</h2>
                        <p class="mt-0.5">Marks per day · last {{ $trendDaysSafe }} days</p>
                    </div>
                </div>
                <span class="text-[11px] inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold {{ $deltaUp ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-100' }}">
                    <i class="fas {{ $deltaUp ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[10px]"></i>
                    {{ $deltaUp ? '+' : '' }}{{ $deltaPct }}%
                </span>
            </div>
            <div class="rep-card__body">
                @if($weeklyTrendColl->sum('count') === 0)
                    <div class="py-10 text-center text-sm text-slate-500">
                        <i class="fas fa-chart-column text-2xl text-slate-300 mb-2 block"></i>
                        No marks recorded in this window yet.
                    </div>
                @else
                    <div class="relative h-[220px] sm:h-[260px]">
                        <canvas id="repTrendChart"
                                data-labels='@json($weeklyTrendColl->pluck('short')->values())'
                                data-values='@json($weeklyTrendColl->pluck('count')->values())'
                                data-tooltips='@json($weeklyTrendColl->pluck('label')->values())'></canvas>
                    </div>
                @endif
            </div>
        </div>

        <div class="rep-card">
            <div class="rep-card__head">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="rep-card__icon rep-card__icon--violet"><i class="fas fa-circle-half-stroke"></i></span>
                    <div>
                        <h2>By capture mode</h2>
                        <p class="mt-0.5">How students are marking</p>
                    </div>
                </div>
            </div>
            <div class="rep-card__body">
                @if($modeBreakdownColl->isEmpty())
                    <div class="py-10 text-center text-sm text-slate-500">
                        <i class="fas fa-circle-half-stroke text-2xl text-slate-300 mb-2 block"></i>
                        Nothing to chart yet.
                    </div>
                @else
                    <div class="relative h-[180px]">
                        <canvas id="repModeChart"
                                data-labels='@json($modeBreakdownColl->pluck('label')->values())'
                                data-modes='@json($modeBreakdownColl->pluck('mode')->values())'
                                data-values='@json($modeBreakdownColl->pluck('count')->values())'></canvas>
                    </div>
                    <ul class="mt-3 grid grid-cols-2 gap-1.5 text-xs">
                        @foreach($modeBreakdownColl as $m)
                            @php
                                $dot = match($m['mode']) {
                                    'qr' => 'bg-indigo-500',
                                    'hybrid' => 'bg-amber-500',
                                    'wifi' => 'bg-teal-500',
                                    default => 'bg-sky-500',
                                };
                            @endphp
                            <li class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 bg-slate-50">
                                <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700 truncate">
                                    <span class="w-2 h-2 rounded-full {{ $dot }} shrink-0"></span>
                                    {{ $m['label'] }}
                                </span>
                                <span class="tabular-nums font-bold text-slate-700">{{ $m['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ─────────── Leaderboards row ───────────
         Top courses (bar list) + top students (avatar leaderboard) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rep-card">
            <div class="rep-card__head">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="rep-card__icon rep-card__icon--emerald"><i class="fas fa-book-open"></i></span>
                    <div><h2>Top courses</h2><p class="mt-0.5">By recorded marks</p></div>
                </div>
            </div>
            <div class="rep-card__body">
                @if($topCoursesColl->isEmpty())
                    <p class="text-center text-sm text-slate-500 py-6">No course-level data yet.</p>
                @else
                    @php $maxCourse = max($topCoursesColl->max('count') ?: 1, 1); @endphp
                    <ul class="space-y-3">
                        @foreach($topCoursesColl as $i => $c)
                            @php
                                $pct = round(($c['count'] / $maxCourse) * 100);
                                $palette = ['from-emerald-400 to-emerald-600', 'from-sky-400 to-sky-600', 'from-violet-400 to-violet-600', 'from-amber-400 to-amber-600', 'from-rose-400 to-rose-600'];
                                $tone = $palette[$i % count($palette)];
                            @endphp
                            <li>
                                <div class="flex items-center justify-between text-sm mb-1.5">
                                    <span class="font-semibold text-slate-700 truncate">
                                        {{ $c['name'] }}
                                        @if($c['code'])<span class="ml-1 text-xs text-slate-400 font-mono">{{ $c['code'] }}</span>@endif
                                    </span>
                                    <span class="font-bold text-slate-700 tabular-nums shrink-0">{{ $c['count'] }}</span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r {{ $tone }} transition-all duration-700" style="width: {{ $pct }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="rep-card">
            <div class="rep-card__head">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="rep-card__icon rep-card__icon--amber"><i class="fas fa-trophy"></i></span>
                    <div><h2>Top students</h2><p class="mt-0.5">Most consistent attenders</p></div>
                </div>
            </div>
            <div class="rep-card__body">
                @if($topStudentsColl->isEmpty())
                    <p class="text-center text-sm text-slate-500 py-6">No student marks yet.</p>
                @else
                    @php $maxStu = max($topStudentsColl->max('count') ?: 1, 1); @endphp
                    <ol class="space-y-2">
                        @foreach($topStudentsColl as $i => $s)
                            @php
                                $pct = round(($s['count'] / $maxStu) * 100);
                                $rank = $i + 1;
                                $rankTone = match($rank) {
                                    1 => 'bg-gradient-to-br from-amber-400 to-amber-600 text-white',
                                    2 => 'bg-gradient-to-br from-slate-300 to-slate-500 text-white',
                                    3 => 'bg-gradient-to-br from-orange-300 to-orange-500 text-white',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                                $initials = collect(explode(' ', $s['name']))
                                    ->filter()->take(2)
                                    ->map(fn($p) => strtoupper(substr($p, 0, 1)))
                                    ->join('');
                            @endphp
                            <li class="flex items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-slate-50 transition-colors">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold {{ $rankTone }}">{{ $rank }}</span>
                                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-sky-100 to-indigo-100 text-sky-700 flex items-center justify-center text-xs font-bold shrink-0">{{ $initials ?: '?' }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $s['name'] }}</p>
                                    <p class="text-[11px] text-slate-500 font-mono truncate">{{ $s['index'] }}</p>
                                </div>
                                <div class="hidden sm:block w-24">
                                    <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-indigo-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-slate-700 tabular-nums shrink-0">{{ $s['count'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-[#c5d4e0] bg-[#f5f9fc] overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-[#c5d4e0] flex flex-wrap items-center justify-between gap-2 bg-[#edf3f8]">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#d4e4ef] text-[#1f4558]">
                        <i class="fas fa-calendar-day text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-[#1a3344] tracking-tight">Today&rsquo;s schedule</h2>
                    </div>
                </div>
                <span class="shrink-0 inline-flex items-center rounded-lg border border-[#b8ccdb] bg-white/90 px-3 py-1.5 text-xs font-semibold tabular-nums text-[#2d4a5c]">{{ now()->format('M j, Y') }}</span>
            </div>
            <div class="p-3 sm:p-4 space-y-2.5">
                @forelse($todayCourses as $c)
                    <div class="flex items-stretch gap-3 rounded-xl border border-[#dce7ee] bg-white p-3 sm:p-3.5">
                        <span class="hidden sm:flex w-1 shrink-0 rounded-full bg-[#8eb4c8]" aria-hidden="true"></span>
                        <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div class="min-w-0 flex gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#eef5fa] text-[#2d5a6e] ring-1 ring-[#dce7ee]">
                                    <i class="fas fa-book-open text-[11px]"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-[#142a38] leading-snug truncate">
                                        {{ $c->course_name }}
                                        @if(!empty($c->course_code))
                                            <span class="ml-1 text-[11px] font-mono text-[#5a6f7c]">{{ $c->course_code }}</span>
                                        @endif
                                    </p>
                                    @if(!empty($c->schedule_label))
                                        <p class="text-[12px] text-[#5a6f7c] mt-1 leading-relaxed">
                                            <span class="text-[#3d5a6e] font-medium">{{ $c->schedule_label }}</span>
                                        </p>
                                    @endif
                                </div>
                            </div>
                            @if(!empty($c->has_active_session))
                                <span class="shrink-0 self-start inline-flex items-center gap-1.5 rounded-lg border border-[#b5d9c4] bg-[#ecf6f0] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#1f5c36]">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#2d8f4e]" aria-hidden="true"></span>
                                    Live
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#c5d4e0] bg-[#fafcfd] px-4 py-10 text-center">
                        <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#e8f0f6] text-[#6b8fa3]">
                            <i class="fas fa-mug-hot text-lg"></i>
                        </span>
                        <p class="text-sm font-medium text-[#3d5a6e]">Nothing on your timetable today</p>
                        <p class="text-xs text-[#7a919c] mt-1.5 max-w-xs mx-auto">When you add a course to <a href="{{ route('dashboard.timetable.manage') }}" class="text-[#2d5a6e] underline">your timetable</a> for {{ now()->format('l') }}, it&rsquo;ll show up here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Shortcuts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <a href="{{ route('dashboard.students.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-user-friends text-primary w-5 text-center"></i> Students
                </a>
                <a href="{{ route('dashboard.timetable') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-calendar-alt text-primary w-5 text-center"></i> Timetable
                </a>
                <a href="{{ route('dashboard.my-class') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-layer-group text-primary w-5 text-center"></i> My class
                </a>
                <a href="{{ route('dashboard.class-attendance.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-clipboard-list text-primary w-5 text-center"></i> Attendance
                </a>
            </div>
        </div>
    </div>

    {{-- ───────── Attendance map (Leaflet / OpenStreetMap) ─────────
         Every per-student mark from the last 14 days that includes
         coordinates is dropped onto the map as a coloured pin. Course
         anchors (when configured on the course) are shown as faint
         circles so the rep can see who marked inside vs. outside the
         configured radius at a glance. Tints by mode:
           • sky      → location-only
           • indigo   → QR only
           • amber    → hybrid (QR + GPS)
           • teal     → wifi (same-network)
         Hover any pin for a soft scale + halo, click for the popup. --}}
    @php
        $mapPoints = collect($attendanceMapPoints ?? []);
        $anchors = collect($courseAnchors ?? []);
        $uniqueCourses = $mapPoints->pluck('course')->filter()->unique()->values();
        $modeCounts = $mapPoints->groupBy('mode')->map->count();
    @endphp
    <div class="rounded-2xl border border-slate-200/80 bg-white overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-slate-100 bg-gradient-to-r from-sky-50/70 via-white to-emerald-50/40">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                    <i class="fas fa-map-location-dot text-sm"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Attendance map</h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Where students marked from over the last 14 days · {{ $mapPoints->count() }} pin{{ $mapPoints->count() === 1 ? '' : 's' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider">
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                    <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span> Location · {{ $modeCounts->get('location', 0) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span> QR · {{ $modeCounts->get('qr', 0) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-100">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span> Hybrid · {{ $modeCounts->get('hybrid', 0) }}
                </span>
                @if($modeCounts->get('wifi', 0) > 0)
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                        <span class="inline-block w-2 h-2 rounded-full bg-teal-500"></span> Wi-Fi · {{ $modeCounts->get('wifi', 0) }}
                    </span>
                @endif
            </div>
        </div>

        @if($uniqueCourses->isNotEmpty())
            <div class="px-4 sm:px-5 py-3 border-b border-slate-100 bg-white/60">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 shrink-0">Course filter</span>
                    <button type="button" class="map-filter-chip px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" data-course-filter="" aria-pressed="true">All ({{ $mapPoints->count() }})</button>
                    @foreach($uniqueCourses as $c)
                        @php $n = $mapPoints->where('course', $c)->count(); @endphp
                        <button type="button" class="map-filter-chip px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" data-course-filter="{{ $c }}" aria-pressed="false">
                            {{ \Illuminate\Support\Str::limit($c, 26) }} · {{ $n }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if($mapPoints->isEmpty())
            <div class="px-6 py-16 text-center">
                <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <i class="fas fa-map-pin text-xl"></i>
                </span>
                <p class="text-sm font-medium text-slate-700">No location data yet</p>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">When students mark attendance from a location / hybrid session, their pins will appear here.</p>
            </div>
        @else
            <div class="relative h-[360px] sm:h-[440px] lg:h-[520px] bg-slate-50">
                <div id="rep-attendance-map"></div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if(!empty($attendanceMapPoints) && count($attendanceMapPoints) > 0)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script>
(function () {
    var mapEl = document.getElementById('rep-attendance-map');
    if (!mapEl || typeof L === 'undefined') return;

    var POINTS  = @json($attendanceMapPoints);
    var ANCHORS = @json($courseAnchors);

    // Build the Leaflet map with a friendly tile layer. CartoDB Positron
    // works without API keys and reads cleanly against our coloured pins.
    var map = L.map(mapEl, {
        zoomControl: true,
        scrollWheelZoom: true,
        attributionControl: true,
    }).setView([0, 0], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
        maxZoom: 19,
        subdomains: 'abcd',
    }).addTo(map);

    // Helper for a custom DivIcon pin so we can CSS-animate it on hover.
    function pinIcon(mode, isFresh) {
        var cls = 'rep-pin rep-pin--' + (mode || 'location');
        if (isFresh) cls += ' rep-pin--fresh';
        return L.divIcon({
            html: '<div class="' + cls + '"></div>',
            className: '',
            iconSize: [22, 22],
            iconAnchor: [11, 11],
            popupAnchor: [0, -10],
        });
    }

    // Tint course anchor circles by the same per-mode palette so the
    // visual mapping stays consistent throughout the panel.
    var anchorLayer = L.layerGroup().addTo(map);
    ANCHORS.forEach(function (a) {
        if (typeof a.lat !== 'number' || typeof a.lng !== 'number') return;
        L.circle([a.lat, a.lng], {
            radius: a.radius_m || 75,
            color: '#0284c7',
            weight: 1.2,
            opacity: 0.5,
            fillColor: '#0ea5e9',
            fillOpacity: 0.07,
        }).bindTooltip(a.name + (a.code ? ' · ' + a.code : ''), { sticky: true }).addTo(anchorLayer);
    });

    // Add per-student pins. We track each marker so the course filter
    // chips can hide/show without rebuilding the layer.
    var pinLayer = L.layerGroup().addTo(map);
    var bounds = L.latLngBounds([]);
    var markers = [];
    var freshCutoff = Date.now() - 30 * 60 * 1000; // 30 min ago

    POINTS.forEach(function (p) {
        if (!isFinite(p.lat) || !isFinite(p.lng)) return;
        var ts = p.time_iso ? Date.parse(p.time_iso) : 0;
        var isFresh = ts && ts >= freshCutoff;

        var marker = L.marker([p.lat, p.lng], { icon: pinIcon(p.mode, isFresh) });

        var modeLabel = ({
            qr: 'QR scan',
            hybrid: 'Hybrid (QR + GPS)',
            wifi: 'Wi-Fi',
            location: 'Location'
        })[p.mode] || 'Location';

        var html =
            '<b>' + escapeHtml(p.student) + '</b>' +
            (p.index ? ' <span class="text-xs text-slate-500 font-mono">· ' + escapeHtml(p.index) + '</span>' : '') +
            '<div class="pop-meta">' + escapeHtml(p.course) +
            (p.course_code ? ' <span class="font-mono">(' + escapeHtml(p.course_code) + ')</span>' : '') + '</div>' +
            (p.time ? '<div class="pop-meta">' + escapeHtml(p.time) + '</div>' : '') +
            '<span class="pop-chip"><i class="fas fa-location-dot"></i>' + escapeHtml(modeLabel) + '</span>';

        marker.bindPopup(html);
        marker._courseName = p.course;
        marker.addTo(pinLayer);
        markers.push(marker);
        bounds.extend([p.lat, p.lng]);
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
    } else {
        map.setView([0, 0], 2);
    }

    // Course-filter chips — toggle marker visibility client-side.
    document.querySelectorAll('.map-filter-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var name = chip.dataset.courseFilter || '';
            document.querySelectorAll('.map-filter-chip').forEach(function (c) {
                c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
            });
            var visible = L.latLngBounds([]);
            markers.forEach(function (m) {
                var match = !name || m._courseName === name;
                if (match) {
                    m.addTo(pinLayer);
                    visible.extend(m.getLatLng());
                } else {
                    pinLayer.removeLayer(m);
                }
            });
            if (visible.isValid()) {
                map.fitBounds(visible, { padding: [40, 40], maxZoom: 17 });
            }
        });
    });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
})();
</script>
@endif

{{-- Chart.js bootstrap: bar trend + capture-mode donut. Defer-loaded
     so it never blocks first paint. We guard on Chart being defined
     so a CDN failure degrades gracefully (the empty-state markdown
     above just stays visible). --}}
<script>
(function () {
    function start() {
        if (typeof Chart === 'undefined') return;

        // ─── Bar chart: daily attendance trend ───
        var trendEl = document.getElementById('repTrendChart');
        if (trendEl) {
            var labels = JSON.parse(trendEl.dataset.labels || '[]');
            var values = JSON.parse(trendEl.dataset.values || '[]');
            var tooltips = JSON.parse(trendEl.dataset.tooltips || '[]');
            // Vertical gradient on the bar fill — pure canvas, no plugin.
            var ctx = trendEl.getContext('2d');
            var grad = ctx.createLinearGradient(0, 0, 0, trendEl.offsetHeight || 240);
            grad.addColorStop(0, 'rgba(56, 189, 248, 0.95)');
            grad.addColorStop(1, 'rgba(99, 102, 241, 0.65)');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Marks',
                        data: values,
                        backgroundColor: grad,
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 28,
                        hoverBackgroundColor: 'rgba(99, 102, 241, 0.95)',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.92)',
                            padding: 10,
                            displayColors: false,
                            cornerRadius: 8,
                            callbacks: {
                                title: function (ctx) {
                                    var i = ctx[0].dataIndex;
                                    return tooltips[i] || ctx[0].label;
                                },
                                label: function (ctx) {
                                    return ctx.parsed.y + ' mark' + (ctx.parsed.y === 1 ? '' : 's');
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { size: 11 } },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8', font: { size: 11 }, precision: 0,
                                stepSize: 1,
                            },
                            grid: { color: 'rgba(148, 163, 184, 0.18)', drawBorder: false },
                        },
                    },
                    animation: { duration: 700, easing: 'easeOutCubic' },
                },
            });
        }

        // ─── Donut chart: capture-mode breakdown ───
        var modeEl = document.getElementById('repModeChart');
        if (modeEl) {
            var mLabels = JSON.parse(modeEl.dataset.labels || '[]');
            var mModes = JSON.parse(modeEl.dataset.modes || '[]');
            var mValues = JSON.parse(modeEl.dataset.values || '[]');
            // Per-mode palette mirrors the legend below the chart so the
            // visual identity stays consistent everywhere it's shown.
            var palette = mModes.map(function (m) {
                return ({
                    location: '#0ea5e9',
                    qr:       '#6366f1',
                    hybrid:   '#f59e0b',
                    wifi:     '#14b8a6',
                }[m]) || '#0ea5e9';
            });
            new Chart(modeEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: mLabels,
                    datasets: [{
                        data: mValues,
                        backgroundColor: palette,
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '64%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.92)',
                            padding: 10, cornerRadius: 8,
                            callbacks: {
                                label: function (ctx) {
                                    var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                                    return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                },
                            },
                        },
                    },
                    animation: { animateRotate: true, duration: 750, easing: 'easeOutCubic' },
                },
            });
        }
    }

    // Chart.js is loaded with `defer` so it may not exist yet at parse
    // time — wait for DOMContentLoaded, then retry briefly if the CDN
    // is slow. Keeps us out of the way of the (sync) Leaflet block above.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
    if (typeof Chart === 'undefined') {
        var tries = 0;
        var iv = setInterval(function () {
            tries++;
            if (typeof Chart !== 'undefined') { clearInterval(iv); start(); }
            else if (tries > 20) clearInterval(iv);
        }, 200);
    }
})();
</script>
@endpush
