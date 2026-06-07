@extends('layouts.student')

@section('title', 'Attendance History')

@section('breadcrumb')
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('dashboard.dashboard') }}" class="hover:text-sky-700 dark:hover:text-sky-400 transition-colors">Dashboard</a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="font-semibold text-slate-800 dark:text-slate-100 truncate">Attendance history</span>
    </nav>
@endsection

@section('content')
@php
    $lastName = trim((string) $student->last_name);
    $displayName = $lastName !== ''
        ? \Illuminate\Support\Str::title($lastName)
        : $student->index_number;
    $initials = $student->avatarInitials();
@endphp

<div class="max-w-md mx-auto w-full lg:max-w-3xl space-y-5 sm:space-y-6">

    {{-- Header ribbon mirroring the dashboard greeting --}}
    <div class="flex items-center justify-between gap-3 pt-1">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 w-11 h-11 rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center text-sm font-bold ring-2 ring-white dark:ring-slate-900 shadow-sm select-none">
                {{ $initials }}
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight uppercase tracking-wider font-semibold">Attendance overview</p>
                <p class="text-base font-bold text-slate-900 dark:text-slate-100 leading-tight truncate">{{ $displayName }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $student->index_number }}</p>
            </div>
        </div>
        <a href="{{ route('dashboard.dashboard') }}" aria-label="Back to dashboard"
           class="shrink-0 w-11 h-11 rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">
            <i class="fas fa-arrow-left text-base"></i>
        </a>
    </div>

    {{-- Headline stats — banking app vibe --}}
    <div class="rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white p-5 sm:p-6 shadow-lg shadow-sky-500/20 dark:shadow-sky-500/10">
        <p class="text-[10px] uppercase tracking-widest font-semibold text-sky-100/80">Attendance rate</p>
        <p class="text-4xl sm:text-5xl font-bold tabular-nums mt-1 leading-none">{{ number_format($attendanceRate, 1) }}<span class="text-2xl text-sky-200">%</span></p>
        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-[11px]">
            <div class="rounded-xl bg-white/10 backdrop-blur px-2 py-2">
                <p class="uppercase tracking-wider font-semibold text-sky-100/80 text-[9px]">Sessions</p>
                <p class="text-lg font-bold tabular-nums mt-0.5">{{ $totalSessions }}</p>
            </div>
            <div class="rounded-xl bg-white/10 backdrop-blur px-2 py-2">
                <p class="uppercase tracking-wider font-semibold text-sky-100/80 text-[9px]">Present</p>
                <p class="text-lg font-bold tabular-nums mt-0.5">{{ $presentCount }}</p>
            </div>
            <div class="rounded-xl bg-white/10 backdrop-blur px-2 py-2">
                <p class="uppercase tracking-wider font-semibold text-sky-100/80 text-[9px]">Absent</p>
                <p class="text-lg font-bold tabular-nums mt-0.5">{{ $absentCount }}</p>
            </div>
        </div>
    </div>

    @if($byCourse->isNotEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-4 sm:p-6">
        <h2 class="font-bold text-slate-900 dark:text-slate-100 mb-4 text-sm sm:text-base">Attendance by course</h2>
        <div class="h-56 sm:h-64">
            <canvas id="courseChart"></canvas>
        </div>
    </div>
    @endif

    @if($byWeek->isNotEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-4 sm:p-6">
        <h2 class="font-bold text-slate-900 dark:text-slate-100 mb-4 text-sm sm:text-base">Trend by week</h2>
        <div class="h-56 sm:h-64">
            <canvas id="weekChart"></canvas>
        </div>
    </div>
    @endif

    @if($trend->isNotEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-4 sm:p-5">
        <h2 class="font-bold text-slate-900 dark:text-slate-100 mb-4 text-sm sm:text-base">Attendance trend</h2>
        <div class="space-y-3">
            @foreach($trend as $t)
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 mb-1">
                        <span>{{ $t['label'] }}</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $t['present'] }}/{{ $t['total'] }} present</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                        <div class="h-full rounded-full bg-sky-500 dark:bg-sky-400" style="width: {{ $t['rate'] }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($courseStats->isNotEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-4 sm:p-5">
        <h2 class="font-bold text-slate-900 dark:text-slate-100 mb-4 text-sm sm:text-base">By course</h2>
        <div class="space-y-2.5">
            @foreach($courseStats as $c)
                <div class="rounded-xl border border-slate-100 dark:border-slate-800 p-3 bg-slate-50/40 dark:bg-slate-800/30">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-slate-100 text-sm truncate">{{ $c['course_name'] }}</p>
                            @if($c['course_code'])
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-mono">{{ $c['course_code'] }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 tabular-nums">{{ $c['rate'] }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500 dark:bg-emerald-400" style="width: {{ max(2, $c['rate']) }}%"></div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">{{ $c['present'] }} present · {{ $c['absent'] }} absent</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h2 class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base">Recent attendance</h2>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($attendances as $a)
                @php $present = \App\Models\Attendance::countsAsPresent($a->status); @endphp
                <li class="p-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="shrink-0 w-10 h-10 rounded-full {{ $present ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' }} flex items-center justify-center">
                            <i class="fas {{ $present ? 'fa-arrow-down-left' : 'fa-xmark' }} text-sm"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-slate-100 text-sm truncate">{{ $a->course?->course_name ?? '—' }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Week {{ $a->attendanceWeek?->week_number ?? '—' }} · {{ $a->attendance_time?->format('M d, Y H:i') ?? '—' }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-sm font-bold tabular-nums {{ $present ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">
                        {{ $present ? 'Present' : 'Absent' }}
                    </span>
                </li>
            @empty
                <li class="p-10 text-center text-slate-500 dark:text-slate-400 text-sm">No attendance records yet</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base">Attendance records</h2>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($history as $row)
                @php
                    $course = $row['course'];
                    $time = $row['time'];
                @endphp
                <li class="p-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="shrink-0 w-10 h-10 rounded-full {{ $row['is_present'] ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300' }} flex items-center justify-center">
                            <i class="fas {{ $row['is_present'] ? 'fa-arrow-down-left' : 'fa-xmark' }} text-sm"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-slate-100 text-sm truncate">{{ $course?->course_name ?? 'Unknown course' }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Week {{ $row['week'] ?? '—' }} · {{ $time ? $time->format('M d, Y H:i') : '—' }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-sm font-bold tabular-nums {{ $row['is_present'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $row['is_present'] ? 'Present' : 'Absent' }}
                    </span>
                </li>
            @empty
                <li class="p-10 text-center text-slate-500 dark:text-slate-400 text-sm">No attendance sessions found yet for your class.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection

@push('scripts')
@if($byCourse->isNotEmpty() || $byWeek->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const byCourse = @json($byCourse);
    const byWeek = @json($byWeek);

    // Resolve theme-aware axis/tick colors so the charts read well on
    // both light and dark backgrounds. Re-pick on theme flip.
    function colors() {
        const dark = document.documentElement.classList.contains('dark');
        return {
            grid: dark ? 'rgba(148,163,184,0.15)' : 'rgba(15,23,42,0.06)',
            tick: dark ? '#cbd5e1' : '#475569',
            barBg: dark ? 'rgba(56,189,248,0.45)' : 'rgba(14,165,233,0.4)',
            barBorder: dark ? 'rgb(14,165,233)' : 'rgb(2,132,199)',
            lineBg: dark ? 'rgba(125,211,252,0.18)' : 'rgba(2,132,199,0.12)',
            lineBorder: dark ? 'rgb(125,211,252)' : 'rgb(2,132,199)',
        };
    }

    let courseChart = null, weekChart = null;

    function build() {
        const c = colors();
        const opts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: c.tick }, grid: { color: c.grid } },
                y: { beginAtZero: true, ticks: { stepSize: 1, color: c.tick }, grid: { color: c.grid } }
            }
        };
        if (courseChart) { courseChart.destroy(); courseChart = null; }
        if (weekChart) { weekChart.destroy(); weekChart = null; }

        if (byCourse.length > 0 && document.getElementById('courseChart')) {
            courseChart = new Chart(document.getElementById('courseChart'), {
                type: 'bar',
                data: {
                    labels: byCourse.map(x => (x.course_code ? x.course_name + ' (' + x.course_code + ')' : x.course_name)),
                    datasets: [{
                        label: 'Present',
                        data: byCourse.map(x => x.count),
                        backgroundColor: c.barBg,
                        borderColor: c.barBorder,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: opts
            });
        }

        if (byWeek.length > 0 && document.getElementById('weekChart')) {
            weekChart = new Chart(document.getElementById('weekChart'), {
                type: 'line',
                data: {
                    labels: byWeek.map(w => 'Week ' + w.week_number),
                    datasets: [{
                        label: 'Attendance',
                        data: byWeek.map(w => w.count),
                        borderColor: c.lineBorder,
                        backgroundColor: c.lineBg,
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: opts
            });
        }
    }

    build();

    // Re-render when the theme toggle flips the `dark` class so the
    // chart colours stay legible.
    const obs = new MutationObserver(build);
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();
</script>
@endif
@endpush
