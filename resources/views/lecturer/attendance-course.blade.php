@extends('layouts.admin')

@section('title', $course->course_name . ' — Attendance')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.teaching.attendance.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> All courses
    </a>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-primary">{{ $course->course_name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $course->assignedClassesLabel() ?: '—' }} · {{ $enrolledCount }} student{{ $enrolledCount === 1 ? '' : 's' }} enrolled</p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('dashboard.teaching.attendance.course.pdf', $course) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-red-200 bg-red-50 text-sm font-medium text-red-800 hover:bg-red-100">
                <i class="fas fa-file-pdf"></i> Semester PDF
            </a>
            <a href="{{ route('dashboard.teaching.attendance.course.export-json', $course) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-code"></i> JSON
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 p-3 bg-emerald-50 text-emerald-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 p-3 bg-red-50 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
@endif

@if($attendanceWeeks->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 mb-3">
            <i class="fas fa-calendar text-lg"></i>
        </span>
        <p class="text-sm font-semibold text-gray-800">No teaching weeks yet</p>
        <p class="text-xs text-gray-500 mt-1">Weeks appear here once a session is opened for this course.</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" id="lec-weeks-grid">
        @foreach($weeklyAttendees as $row)
            @php
                $week = $row['week'];
                $present = $row['present'];
                $absent = $row['absent'];
                $presentCount = $row['present_count'];
                $absentCount = $row['absent_count'];
                $total = max(1, $presentCount + $absentCount);
                $pct = (int) round(($presentCount / $total) * 100);
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
            <details class="group rounded-xl border border-gray-200 bg-white hover:border-primary/40 transition open:shadow-sm" data-week-card>
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
                        <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-700">Present</p>
                                <p class="text-base font-bold text-emerald-800 tabular-nums leading-tight">{{ $presentCount }}</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 border border-rose-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-700">Absent</p>
                                <p class="text-base font-bold text-rose-800 tabular-nums leading-tight">{{ $absentCount }}</p>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full {{ $bar }} rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    @endunless

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span class="text-[11px] text-gray-500 inline-flex items-center gap-1">
                            <i class="fas fa-chevron-down text-[10px] opacity-70 group-open:rotate-180 transition"></i>
                            Open week
                        </span>
                    </div>
                </summary>

                <div class="border-t border-gray-100 p-3 bg-gray-50/50 space-y-3">
                    @if($week->isCancelled())
                        <p class="text-xs text-amber-900">
                            This week was marked cancelled
                            @if($week->cancelled_by)
                                by <strong>{{ $week->cancelled_by }}</strong>
                            @endif
                            .
                            @if($week->cancellation_note)
                                <br><span class="text-amber-800/80">"{{ $week->cancellation_note }}"</span>
                            @endif
                        </p>
                        @if(\Illuminate\Support\Facades\Route::has('lecturer.courses.week.uncancel'))
                        <form action="{{ route('lecturer.courses.week.uncancel', [$course, $week]) }}" method="post">
                            @csrf
                            <button type="submit" class="text-[11px] font-semibold rounded-md border border-primary/40 bg-white px-2.5 py-1 text-primary hover:bg-primary/10">
                                Clear cancellation
                            </button>
                        </form>
                        @endif
                    @else
                        <div class="relative">
                            <i class="fas fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                            <input type="search" data-week-search placeholder="Search by name or index…" autocomplete="off"
                                   class="w-full pl-7 pr-3 py-1.5 text-[12px] border border-gray-200 rounded-md focus:ring-1 focus:ring-primary/30 focus:border-primary/50">
                        </div>

                        <div class="space-y-3" data-week-lists>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 mb-1 flex items-center gap-1">
                                    <i class="fas fa-circle-check text-[9px]"></i> Present
                                    <span class="ml-auto text-emerald-900/70 tabular-nums" data-count-present>{{ $presentCount }}</span>
                                </p>
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
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700 mb-1 flex items-center gap-1">
                                    <i class="fas fa-circle-xmark text-[9px]"></i> Absent
                                    <span class="ml-auto text-rose-900/70 tabular-nums" data-count-absent>{{ $absentCount }}</span>
                                </p>
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

                        @if(\Illuminate\Support\Facades\Route::has('lecturer.courses.week.cancel'))
                        <form action="{{ route('lecturer.courses.week.cancel', [$course, $week]) }}" method="post"
                              class="flex flex-wrap items-center gap-1 pt-2 border-t border-gray-100">
                            @csrf
                            <input type="text" name="note" placeholder="Optional note" maxlength="2000"
                                   class="text-[11px] border border-gray-200 rounded-md px-2 py-1 flex-1 min-w-[120px]">
                            <button type="submit" class="inline-flex items-center gap-1 rounded-md bg-amber-700 text-white px-2 py-1 text-[11px] font-semibold hover:bg-amber-800">
                                Cancel week
                            </button>
                        </form>
                        @endif
                    @endif
                </div>
            </details>
        @endforeach
    </div>
@endif

@push('scripts')
<script>
(function () {
    // Live filter inside each week card.
    document.querySelectorAll('[data-week-card]').forEach(function (card) {
        var input = card.querySelector('[data-week-search]');
        if (!input) return;
        var rows = card.querySelectorAll('[data-week-lists] [data-student-row]');
        var presentList = card.querySelector('[data-count-present]');
        var absentList = card.querySelector('[data-count-absent]');
        var basePresent = presentList ? parseInt(presentList.textContent, 10) || 0 : 0;
        var baseAbsent = absentList ? parseInt(absentList.textContent, 10) || 0 : 0;

        function apply() {
            var q = (input.value || '').toLowerCase().trim();
            var visiblePresent = 0;
            var visibleAbsent = 0;
            rows.forEach(function (r) {
                var match = q === '' || (r.dataset.search || '').indexOf(q) !== -1;
                r.style.display = match ? '' : 'none';
                if (!match) return;
                if (r.closest('ul')?.previousElementSibling?.textContent?.toLowerCase().indexOf('present') === 0 ||
                    r.closest('ul')?.classList.contains('border-emerald-100')) {
                    visiblePresent += 1;
                } else {
                    visibleAbsent += 1;
                }
            });
            if (presentList) presentList.textContent = q === '' ? basePresent : visiblePresent;
            if (absentList) absentList.textContent = q === '' ? baseAbsent : visibleAbsent;
        }
        input.addEventListener('input', apply);
        input.addEventListener('click', function (e) { e.stopPropagation(); });
        input.addEventListener('focus', function (e) { e.stopPropagation(); });
    });
})();
</script>
@endpush
@endsection
