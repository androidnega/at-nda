@extends('layouts.classrep')

@section('title', 'Dashboard')

@push('styles')
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

    /* (Attendance map styles moved to classrep/attendance-map.blade.php
        — the map now lives on its own dedicated page so the dashboard
        can stay focused on counters + trends.) */
</style>
@endpush

@section('content')
<div class="w-full min-w-0 space-y-5">
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

    @php $flaggedColl = collect($flaggedStudents ?? $repFlaggedStudents ?? []); @endphp
    @if($flaggedColl->isNotEmpty())
        <div class="rounded-2xl border border-rose-200 bg-gradient-to-r from-rose-50 to-orange-50 px-4 sm:px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                    <i class="fas fa-triangle-exclamation"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-rose-900">
                        {{ $flaggedColl->count() }} student{{ $flaggedColl->count() === 1 ? '' : 's' }} missed 3+ classes in a row
                    </p>
                    <p class="text-xs text-rose-800/80 mt-0.5 leading-relaxed max-w-2xl">
                        These students have not marked present for three or more consecutive ended sessions in at least one course. Review and follow up early.
                    </p>
                </div>
            </div>
            <a href="{{ route('dashboard.flagged-students') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-rose-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-rose-700 shadow-sm">
                Review list <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    @endif

    {{-- KPI tiles: vivid gradient cards with floating decorations --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rep-card lg:col-span-2">
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
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
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

    {{-- Today's schedule — unified with .rep-card so the page reads
         as one design system. Shortcuts panel was removed because it
         duplicated the sidebar nav verbatim. --}}
    <div class="rep-card">
        <div class="rep-card__head">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="rep-card__icon rep-card__icon--sky"><i class="fas fa-calendar-day"></i></span>
                <div>
                    <h2>Today's schedule</h2>
                    <p class="mt-0.5">{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>
            <a href="{{ route('dashboard.timetable') }}" class="shrink-0 text-[11px] font-semibold text-primary hover:underline inline-flex items-center gap-1">
                Full week <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
        <div class="rep-card__body">
            @forelse($todayCourses as $c)
                <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-3 mb-2.5 last:mb-0">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                        <i class="fas fa-book-open text-xs"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-semibold text-slate-800 leading-snug">
                                {{ $c->course_name }}
                                @if(!empty($c->course_code))
                                    <span class="ml-1 text-[11px] font-mono text-slate-500">{{ $c->course_code }}</span>
                                @endif
                            </p>
                            @if(!empty($c->has_active_session))
                                <span class="shrink-0 inline-flex items-center gap-1.5 rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
                                    Live
                                </span>
                            @endif
                        </div>
                        @if(!empty($c->schedule_label))
                            <p class="text-xs text-slate-500 mt-1">{{ $c->schedule_label }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-10 text-center">
                    <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i class="fas fa-mug-hot text-lg"></i>
                    </span>
                    <p class="text-sm font-medium text-slate-700">Nothing on your timetable today</p>
                    <p class="text-xs text-slate-500 mt-1.5 max-w-xs mx-auto">Add a course to <a href="{{ route('dashboard.timetable.manage') }}" class="text-primary underline">your timetable</a> for {{ now()->format('l') }} to see it here.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Pointer to the full attendance map — keeps the dashboard
         lean and gives the rep a one-click jump to the dedicated
         page when they want to see where students marked from. --}}
    <a href="{{ route('dashboard.attendance-map') }}" class="rep-card flex items-center gap-4 p-4 sm:p-5 hover:border-rose-200 hover:shadow-md transition-shadow group">
        <span class="rep-card__icon rep-card__icon--rose shrink-0"><i class="fas fa-map-location-dot"></i></span>
        <div class="min-w-0 flex-1">
            <h2 class="text-sm font-bold text-slate-900">Open the attendance map</h2>
            <p class="text-xs text-slate-500 mt-0.5">See where every student marked from on an interactive map · filter by course or window</p>
        </div>
        <i class="fas fa-arrow-right text-slate-400 group-hover:text-rose-500 group-hover:translate-x-0.5 transition-all"></i>
    </a>
</div>
@endsection

@push('scripts')
{{-- Chart.js bootstrap: bar trend + capture-mode donut. Defer-loaded
     so it never blocks first paint. We guard on Chart being defined
     so a CDN failure degrades gracefully (the empty-state markdown
     above just stays visible). --}}
<script>
(function () {
    // Idempotent bootstrap: start() is called from BOTH the
    // DOMContentLoaded handler and the Chart-defined polling
    // fallback below. Without a per-canvas guard, the second call
    // would throw "Canvas is already in use. Chart with ID '0'
    // must be destroyed before the canvas with ID 'repTrendChart'
    // can be reused." We mark each canvas with a data flag so the
    // second invocation is a clean no-op.
    function start() {
        if (typeof Chart === 'undefined') return;

        // ─── Bar chart: daily attendance trend ───
        var trendEl = document.getElementById('repTrendChart');
        if (trendEl && !trendEl.dataset.chartInit) {
            trendEl.dataset.chartInit = '1';
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
        if (modeEl && !modeEl.dataset.chartInit) {
            modeEl.dataset.chartInit = '1';
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
