@extends('layouts.classrep')

@section('title', $course->course_name . ' — Attendance')

@section('content')
<div class="mb-4">
    <a href="{{ route('dashboard.class-attendance.index') }}" class="text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1 mb-2">
        <i class="fas fa-arrow-left text-[10px]"></i> All courses
    </a>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-lg sm:text-xl font-bold text-gray-900 leading-snug">{{ $course->course_name }}</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                @if($course->schoolClass)
                    {{ $course->schoolClass->name }}
                    <span class="text-gray-300">·</span>
                @endif
                Attendance marks for this course only
            </p>
            @if($recentSessions->isNotEmpty())
                @php $latest = $recentSessions->first(); @endphp
                <p class="mt-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold {{ $latest->lecturer_status === 'absent' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                        <i class="fas fa-user-tie"></i>
                        Lecturer {{ $latest->lecturer_status === 'absent' ? 'Absent' : 'Present' }}
                    </span>
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('dashboard.class-attendance.course.pdf', $course) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50/80 px-3 py-2 text-xs font-semibold text-red-800 hover:bg-red-100">
                <i class="fas fa-file-pdf"></i>
                PDF preview
            </a>
            <a href="{{ route('dashboard.class-attendance.course.export-json', $course) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                <i class="fas fa-file-code"></i>
                Download JSON
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900">{{ session('error') }}</div>
@endif

@if(isset($attendanceWeeks) && $attendanceWeeks->isNotEmpty())
<div class="mb-4 bg-white rounded-lg border border-gray-200 p-3">
    <p class="text-xs font-semibold text-gray-800">Teaching weeks</p>
    <p class="text-[11px] text-gray-500 mt-1">If your class did not meet for a week, mark it as cancelled. Students see this on their dashboard and the attendance PDF shows it for that week.</p>
    <ul class="mt-3 divide-y divide-gray-100 list-none p-0 m-0">
        @foreach($attendanceWeeks as $week)
        <li class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <span class="text-sm font-medium text-gray-900">Week {{ $week->week_number }}</span>
                @if($week->week_date)
                    <span class="text-xs text-gray-500 ml-2">{{ $week->week_date->format('M j, Y') }}</span>
                @endif
                @if($week->isCancelled())
                    <span class="ml-2 inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900">
                        Cancelled
                        @if($week->cancelled_by)
                            ({{ $week->cancelled_by }})
                        @endif
                    </span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($week->isCancelled())
                    <form action="{{ route('dashboard.class-attendance.week.uncancel', [$course, $week]) }}" method="post" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-primary hover:underline">Clear cancellation</button>
                    </form>
                @else
                    <form action="{{ route('dashboard.class-attendance.week.cancel', [$course, $week]) }}" method="post" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="text" name="note" placeholder="Optional note" maxlength="2000" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 min-w-[8rem] max-w-full">
                        <button type="submit" class="text-xs font-semibold rounded-lg bg-amber-700 text-white px-3 py-1.5 hover:bg-amber-800">Mark week cancelled</button>
                    </form>
                @endif
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

<div class="mb-4 bg-white rounded-lg border border-gray-200 p-3">
    <p class="text-xs font-semibold text-gray-800">Restore attendance (JSON)</p>
    <p class="text-[11px] text-gray-500 mt-1">Upload a backup downloaded from this page to recreate marks if they were lost.</p>
    <form action="{{ route('dashboard.class-attendance.course.import-json', $course) }}" method="post" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
        @csrf
        <input type="file" name="backup" accept=".json,application/json" required
            class="text-xs border border-gray-200 rounded-lg file:mr-2 file:py-1.5 file:px-2 file:text-xs file:rounded-md file:border-0 file:bg-gray-100">
        <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-primary/90">Upload JSON</button>
    </form>
</div>

<form method="GET" action="{{ route('dashboard.class-attendance.course', $course) }}" id="attendance-filters-form" class="mb-4 space-y-3">
    <div class="flex flex-wrap items-end gap-2">
        <div>
            <label for="date_from" class="block text-[10px] font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-primary/30">
        </div>
        <div>
            <label for="date_to" class="block text-[10px] font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-primary/30">
        </div>
        <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-primary/90">Apply</button>
        @if(request()->hasAny(['date_from', 'date_to', 'search']))
            <a href="{{ route('dashboard.class-attendance.course', $course) }}" class="text-xs text-gray-500 hover:text-gray-700 py-1.5">Clear filters</a>
        @endif
    </div>
    <div class="max-w-md">
        <label for="attendance-search" class="block text-[10px] font-medium text-gray-500 mb-1">Search index number</label>
        <div class="relative">
            <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]"></i>
            <input type="search" name="search" id="attendance-search" value="{{ request('search') }}"
                placeholder="Type to filter…"
                autocomplete="off"
                class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs focus:ring-2 focus:ring-primary/30 focus:border-primary/40">
        </div>
    </div>
</form>

@if(isset($dailyStats) && $dailyStats->isNotEmpty())
<div class="mb-4">
    <div class="flex items-end justify-between mb-2">
        <div>
            <p class="text-xs font-semibold text-gray-800 uppercase tracking-wide">Daily attendance</p>
            <p class="text-[11px] text-gray-500">
                Present vs absent per day{{ request()->hasAny(['date_from', 'date_to']) ? ' (filtered)' : '' }}.
                Absent = class size − unique students who marked.
            </p>
        </div>
        <span class="text-[11px] text-gray-400">{{ $dailyStats->count() }} day{{ $dailyStats->count() === 1 ? '' : 's' }}</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2.5">
        @foreach($dailyStats as $stat)
            @php
                $dateObj = \Carbon\Carbon::parse($stat['date']);
                $total = max(1, $stat['total']);
                $pct = (int) round(($stat['present'] / $total) * 100);
                $pct = min(100, max(0, $pct));
                if ($pct >= 75) {
                    $badge = 'bg-emerald-50 text-emerald-700';
                    $bar = 'bg-emerald-500';
                } elseif ($pct >= 50) {
                    $badge = 'bg-amber-50 text-amber-700';
                    $bar = 'bg-amber-500';
                } else {
                    $badge = 'bg-rose-50 text-rose-700';
                    $bar = 'bg-rose-500';
                }
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-3 hover:border-primary/40 hover:shadow-sm transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $dateObj->format('D') }}</p>
                        <p class="text-sm font-bold text-gray-900 leading-tight">{{ $dateObj->format('M j') }}</p>
                        <p class="text-[10px] text-gray-400">{{ $dateObj->format('Y') }}</p>
                    </div>
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg {{ $badge }} text-[11px] font-bold tabular-nums">{{ $pct }}%</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-1.5 text-center">
                    <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-1.5 py-1.5">
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-700">Present</p>
                        <p class="text-base font-bold text-emerald-800 tabular-nums leading-tight">{{ $stat['present'] }}</p>
                    </div>
                    <div class="rounded-lg bg-rose-50 border border-rose-100 px-1.5 py-1.5">
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-700">Absent</p>
                        <p class="text-base font-bold text-rose-800 tabular-nums leading-tight">{{ $stat['absent'] }}</p>
                    </div>
                </div>
                <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full {{ $bar }} rounded-full" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-1.5 text-[10px] text-gray-500 text-center">of {{ $stat['total'] }} student{{ $stat['total'] === 1 ? '' : 's' }}</p>
            </div>
        @endforeach
    </div>
</div>
@endif

@if($recentSessions->isNotEmpty())
<div class="mb-4 bg-white rounded-lg border border-gray-200 p-3">
    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Recent session lecturer tags</p>
    <div class="flex flex-wrap gap-2">
        @foreach($recentSessions as $sess)
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium {{ $sess->lecturer_status === 'absent' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                <i class="fas fa-user-tie"></i>
                {{ $sess->lecturer_status === 'absent' ? 'Absent' : 'Present' }}
                <span class="text-[10px] opacity-80">· {{ optional($sess->start_time ?? $sess->created_at)->format('M d H:i') }}</span>
            </span>
        @endforeach
    </div>
</div>
@endif

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[360px] text-xs">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Index number</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Time</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($attendances as $a)
                <tr class="hover:bg-gray-50/80">
                    <td class="px-3 py-2">
                        @if($a->student)
                            <span class="font-mono text-gray-900 font-medium">{{ $a->student->index_number }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $a->attendance_time->format('M d, Y H:i') }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-block px-1.5 py-0.5 bg-green-50 text-green-800 rounded text-[11px] font-medium">{{ $a->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-4 py-10 text-center text-gray-500">No attendance marks for this course in this date range</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($attendances->hasPages())
    <div class="px-3 py-2 border-t border-gray-100">
        {{ $attendances->links() }}
    </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('attendance-filters-form');
    const search = document.getElementById('attendance-search');
    if (!form || !search) return;
    let t;
    search.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () { form.requestSubmit(); }, 350);
    });
})();
</script>
@endpush
@endsection
