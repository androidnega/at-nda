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
                                @if($week->isOnline())
                                    <span class="ml-1 inline-flex items-center gap-1 rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-900" title="{{ $week->online_note }}">
                                        <i class="fas fa-globe text-[9px]"></i>
                                        Online{{ $week->online_platform ? ' · '.$week->online_platform : '' }}
                                    </span>
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

                        @if(\Illuminate\Support\Facades\Route::has('lecturer.courses.week.roll-call') && !empty($enrolledStudents) && $enrolledStudents->isNotEmpty())
                            @php $presentSet = $row['present_ids'] ?? collect(); @endphp
                            <details class="group/rc rounded-lg border border-indigo-100 bg-indigo-50/40 open:bg-white"
                                     data-rollcall-card data-week-id="{{ $week->id }}">
                                <summary class="cursor-pointer list-none px-3 py-2 flex items-center justify-between gap-2 select-none">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-indigo-900">
                                        <i class="fas fa-globe text-[10px]"></i>
                                        Online roll-call
                                    </span>
                                    <span class="text-[10px] text-indigo-700 group-open/rc:hidden">
                                        Mark everyone who joined Zoom / Meet / Teams
                                    </span>
                                    <i class="fas fa-chevron-down text-[10px] text-indigo-700 group-open/rc:rotate-180 transition"></i>
                                </summary>

                                <form action="{{ route('lecturer.courses.week.roll-call', [$course, $week]) }}"
                                      method="post"
                                      class="p-3 space-y-3"
                                      data-rollcall-form>
                                    @csrf

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <label class="block">
                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-600">Platform <span class="text-gray-400 normal-case">(optional)</span></span>
                                            <input type="text" name="platform" maxlength="60"
                                                   value="{{ $week->online_platform }}"
                                                   placeholder="Zoom, Google Meet, Teams…"
                                                   class="mt-1 w-full text-[12px] border border-gray-200 rounded-md px-2 py-1.5">
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-600">Reason / note <span class="text-rose-600 normal-case">(required)</span></span>
                                            <input type="text" name="note" maxlength="500" required minlength="3"
                                                   value="{{ $week->online_note ?: 'Online class — roll-call by lecturer' }}"
                                                   placeholder="e.g. Zoom lecture on Jun 10"
                                                   class="mt-1 w-full text-[12px] border border-gray-200 rounded-md px-2 py-1.5">
                                        </label>
                                    </div>

                                    <label class="inline-flex items-center gap-2 text-[11px] text-gray-700">
                                        <input type="checkbox" name="mark_online" value="1" checked
                                               class="rounded border-gray-300 text-indigo-700 focus:ring-indigo-500">
                                        Flag this week as Online (shows the badge on every dashboard)
                                    </label>

                                    <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-gray-100">
                                        <button type="button" data-rollcall-bulk="present"
                                                class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-white px-2 py-1 text-[10px] font-semibold text-emerald-800 hover:bg-emerald-50">
                                            <i class="fas fa-circle-check text-[9px]"></i> All present
                                        </button>
                                        <button type="button" data-rollcall-bulk="absent"
                                                class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-white px-2 py-1 text-[10px] font-semibold text-rose-800 hover:bg-rose-50">
                                            <i class="fas fa-circle-xmark text-[9px]"></i> All absent
                                        </button>
                                        <button type="button" data-rollcall-bulk="reset"
                                                class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-semibold text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-rotate-left text-[9px]"></i> Reset
                                        </button>
                                        <span class="ml-auto text-[10px] text-gray-500 tabular-nums" data-rollcall-summary>—</span>
                                    </div>

                                    <div class="relative">
                                        <i class="fas fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                        <input type="search" data-rollcall-search placeholder="Filter students…" autocomplete="off"
                                               class="w-full pl-7 pr-3 py-1.5 text-[12px] border border-gray-200 rounded-md focus:ring-1 focus:ring-indigo-300 focus:border-indigo-400">
                                    </div>

                                    <div class="rounded-lg border border-gray-200 bg-white max-h-72 overflow-y-auto divide-y divide-gray-100"
                                         data-rollcall-list>
                                        @foreach($enrolledStudents as $s)
                                            @php
                                                $sid = (int) $s->id;
                                                $isPresent = $presentSet->has($sid);
                                                $current = $isPresent ? 'present' : 'absent';
                                            @endphp
                                            <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 text-[11px] hover:bg-gray-50"
                                                 data-rollcall-row
                                                 data-search="{{ strtolower(($s->index_number ?? '').' '.$s->getDisplayName()) }}"
                                                 data-default="{{ $current }}">
                                                <span class="min-w-0 truncate">
                                                    <span class="font-mono font-semibold text-gray-900">{{ $s->index_number }}</span>
                                                    <span class="ml-1 text-gray-700">{{ $s->getDisplayName() }}</span>
                                                </span>
                                                <span class="inline-flex shrink-0 rounded-md border border-gray-200 overflow-hidden text-[10px] font-semibold">
                                                    <label class="px-2 py-0.5 cursor-pointer {{ $current === 'present' ? 'bg-emerald-600 text-white' : 'bg-white text-emerald-800 hover:bg-emerald-50' }}">
                                                        <input type="radio" name="marks[{{ $sid }}]" value="present" class="sr-only" data-rollcall-radio
                                                               {{ $current === 'present' ? 'checked' : '' }}>
                                                        P
                                                    </label>
                                                    <label class="px-2 py-0.5 cursor-pointer border-l border-gray-200 {{ $current === 'late' ? 'bg-amber-500 text-white' : 'bg-white text-amber-800 hover:bg-amber-50' }}">
                                                        <input type="radio" name="marks[{{ $sid }}]" value="late" class="sr-only" data-rollcall-radio
                                                               {{ $current === 'late' ? 'checked' : '' }}>
                                                        L
                                                    </label>
                                                    <label class="px-2 py-0.5 cursor-pointer border-l border-gray-200 {{ $current === 'absent' ? 'bg-rose-600 text-white' : 'bg-white text-rose-800 hover:bg-rose-50' }}">
                                                        <input type="radio" name="marks[{{ $sid }}]" value="absent" class="sr-only" data-rollcall-radio
                                                               {{ $current === 'absent' ? 'checked' : '' }}>
                                                        A
                                                    </label>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-700 text-white px-3 py-1.5 text-[11px] font-semibold hover:bg-indigo-800">
                                            <i class="fas fa-floppy-disk text-[10px]"></i>
                                            Save roll-call
                                        </button>
                                    </div>
                                </form>
                            </details>
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

    // ────────────────────────────────────────────────────────────
    // Online roll-call: per-week bulk attendance form.
    //
    // Each row is a label wrapping a P / L / A pill group; the
    // *checked* radio gets the coloured pill style and the others
    // fall back to plain. Because Blade renders the initial classes
    // statically we re-apply them on every change so the UI tracks
    // the actual radio state instead of the page-load state.
    // ────────────────────────────────────────────────────────────
    document.querySelectorAll('[data-rollcall-card]').forEach(function (card) {
        var form = card.querySelector('[data-rollcall-form]');
        if (!form) return;

        var rows = Array.from(form.querySelectorAll('[data-rollcall-row]'));
        var search = form.querySelector('[data-rollcall-search]');
        var summary = form.querySelector('[data-rollcall-summary]');

        var pillStyles = {
            present: {
                on:  'bg-emerald-600 text-white',
                off: 'bg-white text-emerald-800 hover:bg-emerald-50',
            },
            late: {
                on:  'bg-amber-500 text-white',
                off: 'bg-white text-amber-800 hover:bg-amber-50',
            },
            absent: {
                on:  'bg-rose-600 text-white',
                off: 'bg-white text-rose-800 hover:bg-rose-50',
            },
        };

        function repaintRow(row) {
            row.querySelectorAll('[data-rollcall-radio]').forEach(function (radio) {
                var pill = radio.closest('label');
                if (!pill) return;
                var status = radio.value;
                var styles = pillStyles[status];
                if (!styles) return;
                styles.on.split(' ').concat(styles.off.split(' ')).forEach(function (cls) {
                    if (cls) pill.classList.remove(cls);
                });
                (radio.checked ? styles.on : styles.off).split(' ').forEach(function (cls) {
                    if (cls) pill.classList.add(cls);
                });
            });
        }

        function refreshSummary() {
            var counts = { present: 0, late: 0, absent: 0 };
            rows.forEach(function (row) {
                var checked = row.querySelector('[data-rollcall-radio]:checked');
                if (!checked) return;
                counts[checked.value] = (counts[checked.value] || 0) + 1;
            });
            if (summary) {
                summary.textContent =
                    'Present ' + counts.present +
                    ' · Late ' + counts.late +
                    ' · Absent ' + counts.absent;
            }
        }

        function setAll(value) {
            rows.forEach(function (row) {
                if (row.style.display === 'none') return; // respect active filter
                var radio = row.querySelector('[data-rollcall-radio][value="' + value + '"]');
                if (!radio) return;
                radio.checked = true;
                repaintRow(row);
            });
            refreshSummary();
        }

        function reset() {
            rows.forEach(function (row) {
                var def = row.dataset.default || 'absent';
                var radio = row.querySelector('[data-rollcall-radio][value="' + def + '"]');
                if (radio) {
                    radio.checked = true;
                    repaintRow(row);
                }
            });
            refreshSummary();
        }

        rows.forEach(function (row) {
            repaintRow(row);
            row.querySelectorAll('[data-rollcall-radio]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    repaintRow(row);
                    refreshSummary();
                });
            });
        });

        form.querySelectorAll('[data-rollcall-bulk]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var which = btn.dataset.rollcallBulk;
                if (which === 'reset') return reset();
                if (which === 'present' || which === 'absent' || which === 'late') {
                    return setAll(which);
                }
            });
        });

        if (search) {
            search.addEventListener('input', function () {
                var q = (search.value || '').toLowerCase().trim();
                rows.forEach(function (row) {
                    var match = q === '' || (row.dataset.search || '').indexOf(q) !== -1;
                    row.style.display = match ? '' : 'none';
                });
            });
            search.addEventListener('click', function (e) { e.stopPropagation(); });
        }

        refreshSummary();
    });
})();
</script>
@endpush
@endsection
