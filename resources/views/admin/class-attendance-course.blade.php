@extends('layouts.admin')

@section('title', $schoolClass->name . ' — ' . $course->course_name)

@section('content')
@php
    $pdfUrl = route('dashboard.pdf.export', $course).'?class_id='.$schoolClass->id;
@endphp
<div class="mb-6">
    <a href="{{ route('dashboard.classes.show', $schoolClass) }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> {{ $schoolClass->name }}
    </a>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-primary">{{ $course->course_name }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $schoolClass->name }}
                @if($course->course_code)
                    <span class="text-gray-300">·</span> {{ $course->course_code }}
                @endif
                <span class="text-gray-300">·</span> {{ $enrolledCount }} student{{ $enrolledCount === 1 ? '' : 's' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-red-200 bg-red-50 text-sm font-medium text-red-800 hover:bg-red-100">
                <i class="fas fa-file-pdf"></i> Semester PDF
            </a>
        </div>
    </div>
</div>

@if($attendanceWeeks->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 mb-3">
            <i class="fas fa-calendar text-lg"></i>
        </span>
        <p class="text-sm font-semibold text-gray-800">No attendance weeks yet</p>
        <p class="text-xs text-gray-500 mt-1">Weeks appear once a session is opened for this course in {{ $schoolClass->name }}.</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" id="admin-class-weeks-grid">
        @foreach($weeklyAttendees as $row)
            @php
                $week = $row['week'];
                $present = $row['present'];
                $absent = $row['absent'];
                $presentCount = $row['present_count'];
                $absentCount = $row['absent_count'];
                $total = max(1, $enrolledCount);
                $pct = $week->isCancelled() ? 0 : (int) round(($presentCount / $total) * 100);
                $pct = min(100, max(0, $pct));
                if ($week->isCancelled()) {
                    $tone = 'bg-amber-50 text-amber-700';
                    $bar = 'bg-amber-500';
                } elseif ($pct >= 75) {
                    $tone = 'bg-emerald-50 text-emerald-700';
                    $bar = 'bg-emerald-500';
                } elseif ($pct >= 50) {
                    $tone = 'bg-amber-50 text-amber-700';
                    $bar = 'bg-amber-500';
                } else {
                    $tone = 'bg-rose-50 text-rose-700';
                    $bar = 'bg-rose-500';
                }
            @endphp
            <details class="group rounded-xl border border-gray-200 bg-white hover:border-primary/40 transition open:shadow-sm"
                     data-week-card data-week-id="{{ $week->id }}">
                <summary class="cursor-pointer list-none p-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900">Week {{ $week->week_number }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                {{ $week->week_date ? $week->week_date->format('M j, Y') : 'Date not set' }}
                                @if($week->isCancelled())
                                    <span class="ml-1 inline-flex items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">Cancelled</span>
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center justify-center px-2 h-7 rounded-lg {{ $tone }} text-[11px] font-bold tabular-nums">
                            {{ $week->isCancelled() ? 'Cancelled' : $pct.'%' }}
                        </span>
                    </div>

                    @unless($week->isCancelled())
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-700">Present</p>
                                <p class="text-base font-bold text-emerald-800 tabular-nums leading-tight">{{ $presentCount }}</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 border border-rose-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-700">Absent</p>
                                <p class="text-base font-bold text-rose-800 tabular-nums leading-tight">{{ $absentCount }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 border border-slate-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-600">Class</p>
                                <p class="text-base font-bold text-slate-800 tabular-nums leading-tight">{{ $enrolledCount }}</p>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full {{ $bar }} rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    @endunless
                </summary>

                <div class="border-t border-gray-100 p-3 bg-gray-50/50 space-y-3">
                    @if($week->isCancelled())
                        <p class="text-xs text-amber-900">
                            This week was marked cancelled
                            @if($week->cancelled_by)
                                by <strong>{{ $week->cancelled_by }}</strong>
                            @endif.
                            @if($week->cancellation_note)
                                <br><span class="text-amber-800/80">"{{ $week->cancellation_note }}"</span>
                            @endif
                        </p>
                    @else
                        <div class="relative">
                            <i class="fas fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input type="search" data-week-search placeholder="Search by name or index…" autocomplete="off"
                                   class="w-full pl-7 pr-3 py-1.5 text-[12px] border border-gray-200 rounded-md focus:ring-1 focus:ring-primary/30 focus:border-primary/50">
                        </div>

                        <div class="space-y-3" data-week-lists>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 mb-1">Present ({{ $presentCount }})</p>
                                @if($present->isEmpty())
                                    <p class="text-[11px] text-gray-400 italic">No students marked present.</p>
                                @else
                                    <ul class="divide-y divide-gray-100 bg-white rounded-lg border border-emerald-100 max-h-56 overflow-y-auto">
                                        @foreach($present as $row2)
                                            @php $s = $row2['student']; @endphp
                                            <li class="px-2.5 py-1.5 flex items-center justify-between gap-2 text-[11px]"
                                                data-student-row
                                                data-search="{{ strtolower(($s->index_number ?? '').' '.$s->getDisplayName()) }}">
                                                <span class="min-w-0 truncate">
                                                    <span class="font-mono font-semibold text-gray-900">{{ $s->index_number }}</span>
                                                    <span class="ml-1 text-gray-700">{{ $s->getDisplayName() }}</span>
                                                </span>
                                                <span class="shrink-0 text-gray-400 tabular-nums">{{ optional($row2['time'])->format('M d, H:i') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700 mb-1">Absent ({{ $absentCount }})</p>
                                @if($absent->isEmpty())
                                    <p class="text-[11px] text-gray-400 italic">Everyone was present.</p>
                                @else
                                    <ul class="divide-y divide-gray-100 bg-white rounded-lg border border-rose-100 max-h-56 overflow-y-auto">
                                        @foreach($absent as $s)
                                            <li class="px-2.5 py-1.5 text-[11px]"
                                                data-student-row
                                                data-search="{{ strtolower(($s->index_number ?? '').' '.$s->getDisplayName()) }}">
                                                <span class="font-mono font-semibold text-gray-900">{{ $s->index_number }}</span>
                                                <span class="ml-1 text-gray-600">{{ $s->getDisplayName() }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
@endif

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-week-card]').forEach(function (card) {
        var input = card.querySelector('[data-week-search]');
        if (!input) return;
        var rows = card.querySelectorAll('[data-student-row]');
        input.addEventListener('input', function () {
            var q = (input.value || '').toLowerCase().trim();
            rows.forEach(function (r) {
                var match = q === '' || (r.dataset.search || '').indexOf(q) !== -1;
                r.style.display = match ? '' : 'none';
            });
        });
        input.addEventListener('click', function (e) { e.stopPropagation(); });
        input.addEventListener('focus', function (e) { e.stopPropagation(); });
    });
})();
</script>
@endpush
@endsection
