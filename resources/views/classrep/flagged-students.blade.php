@extends('layouts.classrep')

@section('title', 'At-risk students')

@section('content')
<div class="w-full min-w-0 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-600">Attendance alert</p>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mt-1">At-risk students</h1>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl leading-relaxed">
                Students who missed <strong>{{ $threshold ?? 3 }} or more classes in a row</strong> without marking present — counted from the most recent ended session backward, per course.
            </p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-800 shrink-0 tabular-nums">
            <i class="fas fa-triangle-exclamation text-rose-500"></i>
            {{ count($flaggedStudents) }} flagged
        </div>
    </div>

    @if(empty($flaggedStudents))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 px-6 py-12 text-center">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 mb-3">
                <i class="fas fa-check text-xl"></i>
            </span>
            <p class="text-base font-semibold text-emerald-900">No students flagged right now</p>
            <p class="text-sm text-emerald-800/80 mt-1">Everyone in your classes is below the {{ $threshold ?? 3 }}-miss streak threshold.</p>
            <a href="{{ route('dashboard.dashboard') }}" class="inline-flex items-center gap-2 mt-5 text-sm font-semibold text-emerald-700 hover:underline">
                <i class="fas fa-arrow-left text-xs"></i> Back to dashboard
            </a>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
            <div class="px-4 sm:px-5 py-3 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between gap-3">
                <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Sorted by longest streak</p>
                <a href="{{ route('dashboard.students.index') }}" class="text-xs font-semibold text-primary hover:underline">All students</a>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($flaggedStudents as $row)
                    <a href="{{ route('dashboard.students.show', $row['student_id']) }}"
                       class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 px-4 sm:px-5 py-4 hover:bg-rose-50/40 transition">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700 font-bold text-sm tabular-nums">
                                {{ (int) $row['consecutive_missed'] }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $row['name'] ?? 'Student' }}</p>
                                <p class="text-xs font-mono text-slate-500 mt-0.5">{{ $row['index_number'] ?? '—' }}</p>
                            </div>
                        </div>
                        <div class="sm:text-right shrink-0 pl-14 sm:pl-0">
                            <p class="text-xs font-semibold text-rose-700">
                                {{ (int) $row['consecutive_missed'] }} missed in a row
                            </p>
                            @if(!empty($row['course_name']))
                                <p class="text-[11px] text-slate-500 mt-0.5">Worst streak · {{ $row['course_name'] }}</p>
                            @endif
                        </div>
                        <span class="hidden sm:inline-flex text-slate-300"><i class="fas fa-chevron-right text-xs"></i></span>
                    </a>
                @endforeach
            </div>
        </div>

        <p class="text-[11px] text-slate-400 leading-relaxed max-w-3xl">
            Streaks are recalculated from ended sessions only. Cancelled weeks and present marks reset the count. Data refreshes every few minutes.
        </p>
    @endif
</div>
@endsection
