@extends('layouts.student')

@section('title', 'Dashboard')

@section('breadcrumb')
    @if($liveAttendanceSessions->isNotEmpty())
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm">
            <span class="inline-flex items-center gap-2 text-emerald-700 font-semibold">
                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>
                Live sessions
            </span>
        </nav>
    @else
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm">
            <span class="inline-flex items-center gap-1.5 text-slate-500">
                <i class="fas fa-house text-slate-400 text-[10px]"></i>
                <span class="font-semibold text-slate-800">Dashboard</span>
            </span>
        </nav>
    @endif
@endsection

@section('content')
@if (session('success'))
    <div class="mb-4 p-3 sm:p-4 bg-emerald-50 text-emerald-900 rounded-xl text-sm border border-emerald-100">{{ session('success') }}</div>
@endif
@if (session('info'))
    <div class="mb-4 p-3 sm:p-4 bg-sky-50 text-sky-900 rounded-xl text-sm border border-sky-100">{{ session('info') }}</div>
@endif

@if($liveAttendanceSessions->isNotEmpty())
    <div class="max-w-lg mx-auto w-full space-y-5 md:max-w-none">
        <div class="text-center md:text-left pt-safe">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Live sessions</p>
            <p class="text-xs text-slate-500 mt-1">Each open session is listed separately — mark each one before it closes.</p>
        </div>

        <ul class="space-y-5 list-none p-0 m-0">
            @foreach($liveAttendanceSessions as $row)
                @php
                    $session = $row['session'];
                    $course = $row['course'];
                    $alreadyMarked = $row['already_marked'];
                    $mode = $session->mode ?? 'location';
                    $modeLabel = match ($mode) {
                        'qr' => 'QR code',
                        'hybrid' => 'QR + venue',
                        'wifi' => 'Wi‑Fi',
                        default => 'Venue',
                    };
                @endphp
                <li class="rounded-[1.25rem] sm:rounded-2xl border-2 border-emerald-400/80 bg-gradient-to-br from-emerald-50 via-white to-sky-50/90 p-4 sm:p-6 shadow-lg shadow-emerald-900/10 ring-1 ring-emerald-500/15 flex flex-col gap-4 touch-manipulation">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-500/30">
                            <i class="fas fa-clipboard-check text-base" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900 text-[15px] leading-snug">{{ $course->course_name }}</p>
                            @if($course->course_code)
                                <p class="text-xs text-slate-500 font-mono mt-1">{{ $course->course_code }}</p>
                            @endif
                            <p class="text-[11px] text-slate-600 mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>Week {{ $session->attendanceWeek?->week_number ?? '—' }}</span>
                                <span class="text-slate-300" aria-hidden="true">·</span>
                                <span class="inline-flex items-center gap-1 rounded-lg bg-white/90 border border-emerald-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">{{ $modeLabel }}</span>
                                @if($session->expires_at)
                                    <span class="text-slate-400">·</span>
                                    <span class="text-amber-800 font-medium">Closes {{ $session->expires_at->timezone(config('app.timezone'))->format('g:i A') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="w-full pt-0.5 border-t border-emerald-200/60">
                        @if($alreadyMarked)
                            <span class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50/90 py-3.5 text-sm font-semibold text-emerald-900">
                                <i class="fas fa-check-circle"></i> Marked for this session
                            </span>
                        @else
                            <a href="{{ route('web.attendance.form', $course) }}"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-4 text-sm font-bold shadow-md shadow-emerald-600/25 transition-colors">
                                <i class="fas fa-arrow-right-to-bracket"></i>
                                Mark attendance
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

@else

<div class="space-y-5 sm:space-y-6">
    <div class="rounded-2xl bg-gradient-to-br from-sky-500 via-sky-600 to-blue-700 text-white p-5 sm:p-6 shadow-lg shadow-sky-500/20">
        <p class="text-sky-100 text-xs font-medium uppercase tracking-wider">Welcome back</p>
        <h1 class="text-xl sm:text-2xl font-bold mt-1 truncate">{{ $student->getDisplayNameOrIndex() }}</h1>
        <p class="text-sky-100/90 text-sm mt-1 font-mono">{{ $student->index_number }}</p>
        @if($student->department?->name)
            <p class="text-white/90 text-sm mt-2 flex items-center gap-2">
                <i class="fas fa-building-columns text-sky-200"></i>
                {{ $student->department->name }}
            </p>
        @endif
    </div>

    @if(!$student->class_id)
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-xs sm:text-sm text-amber-950">
            <strong class="font-semibold">Class not set.</strong> Ask an administrator to assign your class so open attendance sessions appear here.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <div class="rounded-2xl bg-white border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600"><i class="fas fa-check-double text-sm"></i></span>
                Present
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $totalPresent }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center text-violet-600"><i class="fas fa-calendar-week text-sm"></i></span>
                Weeks
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $totalWeeks }}</p>
        </div>
    </div>

    @if($byCourse->isNotEmpty())
    <div class="rounded-2xl bg-white border border-slate-200/80 p-4 sm:p-6 shadow-sm">
        <h2 class="font-semibold text-slate-800 mb-4 text-sm sm:text-base">Attendance by course</h2>
        <div class="h-56 sm:h-64">
            <canvas id="courseChart"></canvas>
        </div>
    </div>
    @endif

    @if($byWeek->isNotEmpty())
    <div class="rounded-2xl bg-white border border-slate-200/80 p-4 sm:p-6 shadow-sm">
        <h2 class="font-semibold text-slate-800 mb-4 text-sm sm:text-base">Trend by week</h2>
        <div class="h-56 sm:h-64">
            <canvas id="weekChart"></canvas>
        </div>
    </div>
    @endif

    <div class="rounded-2xl bg-white border border-slate-200/80 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800 text-sm sm:text-base">Recent attendance</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($attendances as $a)
            <div class="p-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium text-slate-900 text-sm">{{ $a->course?->course_name ?? '—' }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">Week {{ $a->attendanceWeek?->week_number ?? '—' }} · {{ $a->attendance_time?->format('M d, Y H:i') ?? '—' }}</p>
                </div>
                <span class="shrink-0 px-2 py-1 bg-emerald-50 text-emerald-800 rounded-lg text-xs font-semibold">Present</span>
            </div>
            @empty
            <div class="p-10 text-center text-slate-500 text-sm">No attendance records yet</div>
            @endforelse
        </div>
    </div>

    <p class="text-xs text-slate-500 text-center sm:text-left">
        When a lecturer opens attendance for your class, it will show at the top of this page. You can mark on the web or with the a-tenda mobile app.
    </p>
</div>

@endif
@endsection

@push('scripts')
@if($liveAttendanceSessions->isEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const byCourse = @json($byCourse);
    const byWeek = @json($byWeek);

    if (byCourse.length > 0 && document.getElementById('courseChart')) {
        new Chart(document.getElementById('courseChart'), {
            type: 'bar',
            data: {
                labels: byCourse.map(c => (c.course_code ? c.course_name + ' (' + c.course_code + ')' : c.course_name)),
                datasets: [{
                    label: 'Present',
                    data: byCourse.map(c => c.count),
                    backgroundColor: 'rgba(14, 165, 233, 0.65)',
                    borderColor: 'rgb(2, 132, 199)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    if (byWeek.length > 0 && document.getElementById('weekChart')) {
        new Chart(document.getElementById('weekChart'), {
            type: 'line',
            data: {
                labels: byWeek.map(w => 'Week ' + w.week_number),
                datasets: [{
                    label: 'Attendance',
                    data: byWeek.map(w => w.count),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
})();
</script>
@endif
@endpush
