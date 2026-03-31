@extends('layouts.student')

@section('title', 'Attendance History')

@section('breadcrumb')
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm text-slate-500">
        <a href="{{ route('dashboard.dashboard') }}" class="hover:text-amber-700 transition-colors">Dashboard</a>
        <span class="text-slate-300">/</span>
        <span class="font-semibold text-slate-800 truncate">Attendance history</span>
    </nav>
@endsection

@section('content')
<div class="space-y-5 sm:space-y-6">
    @php
        $lastName = trim((string) $student->last_name);
        $displayName = $lastName !== ''
            ? \Illuminate\Support\Str::title($lastName)
            : $student->index_number;
    @endphp
    <div class="rounded-2xl bg-slate-100 border border-slate-200 p-5 sm:p-6">
        <p class="text-amber-700 text-xs font-semibold uppercase tracking-wider">Attendance overview</p>
        <h1 class="text-xl sm:text-2xl font-bold mt-1 text-slate-900 truncate">{{ $displayName }}</h1>
        <p class="text-slate-600 text-sm mt-1 font-mono">{{ $student->index_number }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Sessions</p>
            <p class="text-2xl font-bold text-slate-900 mt-1 tabular-nums">{{ $totalSessions }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Present</p>
            <p class="text-2xl font-bold text-amber-700 mt-1 tabular-nums">{{ $presentCount }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Absent</p>
            <p class="text-2xl font-bold text-rose-700 mt-1 tabular-nums">{{ $absentCount }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Rate</p>
            <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ number_format($attendanceRate, 1) }}%</p>
        </div>
    </div>

    @if($trend->isNotEmpty())
    <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
        <h2 class="font-semibold text-slate-800 mb-4 text-sm sm:text-base">Attendance trend</h2>
        <div class="space-y-3">
            @foreach($trend as $t)
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                        <span>{{ $t['label'] }}</span>
                        <span class="font-semibold text-slate-700">{{ $t['present'] }}/{{ $t['total'] }} present</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500" style="width: {{ $t['rate'] }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($courseStats->isNotEmpty())
    <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
        <h2 class="font-semibold text-slate-800 mb-4 text-sm sm:text-base">By course</h2>
        <div class="space-y-3">
            @foreach($courseStats as $c)
                <div class="rounded-xl border border-slate-100 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 text-sm truncate">{{ $c['course_name'] }}</p>
                            @if($c['course_code'])
                                <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ $c['course_code'] }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold text-slate-700">{{ $c['rate'] }}%</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ $c['present'] }} present · {{ $c['absent'] }} absent</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800 text-sm sm:text-base">Attendance records</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($history as $row)
                @php
                    $course = $row['course'];
                    $time = $row['time'];
                @endphp
                <div class="p-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 text-sm">{{ $course?->course_name ?? 'Unknown course' }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Week {{ $row['week'] ?? '—' }} · {{ $time ? $time->format('M d, Y H:i') : '—' }}
                        </p>
                    </div>
                    @if($row['is_present'])
                        <span class="shrink-0 px-2 py-1 bg-amber-50 text-amber-800 rounded-lg text-xs font-semibold">Present</span>
                    @else
                        <span class="shrink-0 px-2 py-1 bg-rose-50 text-rose-700 rounded-lg text-xs font-semibold">Absent</span>
                    @endif
                </div>
            @empty
                <div class="p-10 text-center text-slate-500 text-sm">No attendance sessions found yet for your class.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
