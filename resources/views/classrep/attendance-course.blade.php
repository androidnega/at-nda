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
                @php $classLabel = $repClassLabel ?? null; @endphp
                @if(!empty($classLabel) && $classLabel !== '—')
                    {{ $classLabel }}
                    <span class="text-gray-300">·</span>
                @elseif($course->schoolClass)
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
            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.online-week.create'))
                <form action="{{ route('dashboard.class-attendance.online-week.create', $course) }}" method="post" class="contents"
                      onsubmit="return confirm('Open a new online lecture week for this course?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-800">
                        <i class="fas fa-globe"></i>
                        Start online lecture
                    </button>
                </form>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.course.pdf'))
                <a href="{{ route('dashboard.class-attendance.course.pdf', $course) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50/80 px-3 py-2 text-xs font-semibold text-red-800 hover:bg-red-100">
                    <i class="fas fa-file-pdf"></i>
                    Semester PDF
                </a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.course.export-json'))
                <a href="{{ route('dashboard.class-attendance.course.export-json', $course) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                    <i class="fas fa-file-code"></i>
                    Semester JSON
                </a>
            @endif
        </div>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900">{{ session('error') }}</div>
@endif

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

@if(isset($weeklyAttendees) && $weeklyAttendees->isNotEmpty())
<div class="mb-4">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-2">
        <div>
            <p class="text-xs font-semibold text-gray-800 uppercase tracking-wide">Attendance by week</p>
            <p class="text-[11px] text-gray-500 max-w-2xl">
                Tap any week to open it — counts, attendees, and actions live inside. Use the toggle on the right to expand or collapse them all at once.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <span class="text-[11px] text-gray-400">{{ $weeklyAttendees->count() }} week{{ $weeklyAttendees->count() === 1 ? '' : 's' }}</span>
            <button type="button" data-week-toggle-all
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 hover:bg-gray-50 hover:border-primary/40 transition">
                <i class="fas fa-up-down-left-right text-[10px]"></i>
                <span data-week-toggle-label>Expand all</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-2.5 sm:gap-3" data-week-grid>
        @foreach($weeklyAttendees as $row)
            @php
                $week = $row['week'];
                $present = $row['present'];
                $presentCount = $row['present_count'];
                $absentCount = $row['absent_count'];
                $total = max(1, ($enrolledCount ?? ($presentCount + $absentCount)));
                $pct = (int) round(($presentCount / $total) * 100);
                $pct = min(100, max(0, $pct));
                if ($week->isCancelled()) {
                    $badge = 'bg-amber-100 text-amber-800 border-amber-200';
                    $bar = 'bg-amber-500';
                    $accent = 'amber';
                } elseif ($pct >= 75) {
                    $badge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                    $bar = 'bg-emerald-500';
                    $accent = 'emerald';
                } elseif ($pct >= 50) {
                    $badge = 'bg-amber-50 text-amber-700 border-amber-100';
                    $bar = 'bg-amber-500';
                    $accent = 'amber';
                } else {
                    $badge = 'bg-rose-50 text-rose-700 border-rose-100';
                    $bar = 'bg-rose-500';
                    $accent = 'rose';
                }
            @endphp

            {{-- Collapsed state stays small (just title, date, %, chevron + a 1.5px progress strip).
                 Counts, students list, manual-mark form, rename, etc. all live in the expanded
                 panel — nothing wasteful while the rep is scanning the grid. --}}
            <details class="group rounded-xl border border-gray-200 bg-white hover:border-primary/40 transition open:shadow-md open:border-primary/40 open:ring-1 open:ring-primary/10"
                     data-week-card data-week-id="{{ $week->id }}">
                <summary class="cursor-pointer list-none p-3 sm:p-3.5 select-none">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-bold text-gray-900 leading-none">Week {{ $week->week_number }}</p>
                                <span class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-bold tabular-nums {{ $badge }}">
                                    {{ $week->isCancelled() ? 'Cancelled' : $pct.'%' }}
                                </span>
                                @if($week->isOnline())
                                    <span class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-800"
                                          title="{{ $week->online_note }}">
                                        <i class="fas fa-globe text-[9px]"></i>
                                        Online{{ $week->online_platform ? ' · '.$week->online_platform : '' }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1 truncate">
                                {{ $week->week_date ? $week->week_date->format('M j, Y') : 'Date not set' }}
                                @unless($week->isCancelled())
                                    <span class="text-gray-300">·</span>
                                    <span class="text-emerald-700 font-semibold">{{ $presentCount }}</span>/<span class="text-gray-700">{{ $total }}</span>
                                @endunless
                            </p>
                        </div>
                        <i class="fas fa-chevron-down text-[11px] text-gray-400 group-open:rotate-180 group-open:text-primary transition shrink-0"></i>
                    </div>
                    @unless($week->isCancelled())
                        <div class="mt-2 h-1 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full {{ $bar }} rounded-full transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                    @endunless
                </summary>

                <div class="border-t border-gray-100 p-3 bg-gray-50/50 space-y-3">
                    {{-- The expanded panel opens with the headline numbers + actions. --}}
                    @unless($week->isCancelled())
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-700">Present</p>
                                <p class="text-base font-bold text-emerald-800 tabular-nums leading-tight">{{ $presentCount }}</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 border border-rose-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-700">Absent</p>
                                <p class="text-base font-bold text-rose-800 tabular-nums leading-tight">{{ $absentCount }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 border border-slate-100 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-600">Enrolled</p>
                                <p class="text-base font-bold text-slate-800 tabular-nums leading-tight">{{ $total }}</p>
                            </div>
                        </div>
                    @endunless

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
                        @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.week.uncancel'))
                            <form action="{{ route('dashboard.class-attendance.week.uncancel', [$course, $week]) }}" method="post">
                                @csrf
                                <button type="submit" class="text-[11px] font-semibold rounded-md border border-primary/40 bg-white px-2.5 py-1 text-primary hover:bg-primary/10">
                                    Clear cancellation
                                </button>
                            </form>
                        @endif
                    @else
                        @php $uniquePresent = $present->unique('student_id'); @endphp
                        {{-- Primary actions: short row of buttons. Heavy content (attendees list,
                             manual-mark form) opens in a modal so the card stays compact. --}}
                        <div class="flex flex-wrap gap-1.5">
                            @if($uniquePresent->isNotEmpty())
                                <button type="button"
                                        data-week-modal-open="attendees-{{ $week->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-emerald-800 hover:bg-emerald-50">
                                    <i class="fas fa-list-check text-[10px] text-emerald-600"></i>
                                    View attendees ({{ $uniquePresent->count() }})
                                </button>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[11px] font-medium text-gray-500">
                                    <i class="fas fa-user-slash text-[10px]"></i>
                                    No attendees yet
                                </span>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.manual-mark') && !empty($classmates ?? null))
                                <button type="button"
                                        data-week-modal-open="manual-{{ $week->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-700 text-white px-2.5 py-1.5 text-[11px] font-semibold hover:bg-indigo-800">
                                    <i class="fas fa-user-pen text-[10px]"></i>
                                    Manually mark a student
                                </button>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.roll-call') && !empty($classmates ?? null))
                                <button type="button"
                                        data-week-modal-open="rollcall-{{ $week->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 text-indigo-800 px-2.5 py-1.5 text-[11px] font-semibold hover:bg-indigo-100">
                                    <i class="fas fa-globe text-[10px]"></i>
                                    Online roll-call
                                </button>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.course.week.pdf'))
                                <a href="{{ route('dashboard.class-attendance.course.week.pdf', [$course, $week]) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-red-700 hover:bg-red-50">
                                    <i class="fas fa-file-pdf text-red-500 text-[10px]"></i>
                                    Week PDF
                                </a>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.week.rename'))
                                <button type="button"
                                        data-week-modal-open="rename-{{ $week->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                    <i class="fas fa-pen text-[10px]"></i>
                                    Edit label
                                </button>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.course.week.export-json'))
                                <a href="{{ route('dashboard.class-attendance.course.week.export-json', [$course, $week]) }}"
                                   class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                    <i class="fas fa-file-code text-slate-500 text-[10px]"></i>
                                    Download JSON
                                </a>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.course.week.import-json'))
                                <button type="button"
                                        data-week-modal-open="upload-{{ $week->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-primary/30 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-primary hover:bg-primary/10">
                                    <i class="fas fa-upload text-[10px]"></i>
                                    Upload backup
                                </button>
                            @endif

                            @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.week.cancel'))
                                <button type="button"
                                        data-week-modal-open="cancel-{{ $week->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-amber-800 hover:bg-amber-50 ml-auto">
                                    <i class="fas fa-ban text-[10px]"></i>
                                    Cancel week
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </details>

            {{-- Modals for this week. Hidden by default; opened by the buttons above.
                 Sit OUTSIDE the <details> so they don't disappear when the card closes. --}}
            @unless($week->isCancelled())
                @if(($uniquePresent ?? collect())->isNotEmpty())
                    <div data-week-modal="attendees-{{ $week->id }}"
                         class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
                         role="dialog" aria-modal="true" aria-labelledby="attendees-{{ $week->id }}-title">
                        <div class="relative w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200 max-h-[92vh] sm:max-h-[85vh] flex flex-col">
                            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Attendees</p>
                                    <h3 id="attendees-{{ $week->id }}-title" class="text-base font-bold text-gray-900 truncate">Week {{ $week->week_number }} — {{ $uniquePresent->count() }} present</h3>
                                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $week->week_date ? $week->week_date->format('l, M j, Y') : '' }}</p>
                                </div>
                                <button type="button" data-week-modal-close
                                        class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                            <div class="overflow-y-auto flex-1 p-2">
                                <ul class="divide-y divide-gray-100">
                                    @foreach($uniquePresent as $a)
                                        <li class="px-2.5 py-2 flex items-center justify-between gap-2 text-xs rounded-md hover:bg-gray-50">
                                            <span class="min-w-0 flex-1">
                                                <span class="font-mono font-semibold text-gray-900">{{ $a->student?->index_number ?? '—' }}</span>
                                                @if($a->student)
                                                    <span class="ml-1 text-gray-700">{{ trim(($a->student->last_name ?? '').' '.($a->student->first_name ?? '')) }}</span>
                                                @endif
                                                @if(method_exists($a, 'isManuallyMarked') && $a->isManuallyMarked())
                                                    <span class="ml-1 inline-flex items-center gap-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider"
                                                          title="{{ $a->manual_reason }}">
                                                        <i class="fas fa-user-pen text-[8px]"></i> Manual
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="text-gray-500 whitespace-nowrap shrink-0">{{ optional($a->attendance_time)->format('M d, H:i') }}</span>
                                            @if(\App\Models\SystemSetting::repsCanDeleteAttendance() && \Illuminate\Support\Facades\Route::has('dashboard.class-attendance.delete'))
                                                <form method="post" action="{{ route('dashboard.class-attendance.delete', $a) }}" class="shrink-0"
                                                      onsubmit="return promptAttendanceDeleteReason(this);">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="reason" value="">
                                                    <button type="submit" title="Delete this attendance row"
                                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-red-500/80 hover:bg-red-50 hover:text-red-700">
                                                        <i class="fas fa-xmark text-[10px]"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="flex justify-end gap-2 p-3 border-t border-gray-100 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[env(safe-area-inset-bottom,0)]">
                                <button type="button" data-week-modal-close
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.manual-mark') && !empty($classmates ?? null))
                    <div data-week-modal="manual-{{ $week->id }}"
                         class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
                         role="dialog" aria-modal="true" aria-labelledby="manual-{{ $week->id }}-title">
                        <div class="relative w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200 max-h-[92vh] sm:max-h-[85vh] flex flex-col">
                            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Manual mark</p>
                                    <h3 id="manual-{{ $week->id }}-title" class="text-base font-bold text-gray-900">Week {{ $week->week_number }}</h3>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Use this when a student couldn't mark in the app. The action is logged for audit.</p>
                                </div>
                                <button type="button" data-week-modal-close
                                        class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                            {{-- novalidate: browser-native validation tooltips on required fields render outside the modal's overflow area and get clipped, so the rep clicks Save and sees nothing. We do all validation in JS below with inline messages the rep can actually see. --}}
                            <form action="{{ route('dashboard.class-attendance.manual-mark', [$course, $week]) }}" method="post"
                                  class="manual-mark-form p-4 space-y-3 overflow-y-auto"
                                  data-week-id="{{ $week->id }}"
                                  novalidate>
                                @csrf
                                {{-- Searchable student picker — replaces the <select> that
                                     used to hold every classmate in one giant scroll list.
                                     Type any part of the index number or name and the
                                     suggestions narrow down instantly. The hidden
                                     student_id is set when a suggestion is clicked
                                     (or when Enter is pressed on a single match), so
                                     the existing controller validation is unchanged. --}}
                                <div class="relative">
                                    <label for="manual-search-{{ $week->id }}" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">
                                        Student <span class="text-gray-400 normal-case font-medium tracking-normal ml-1">(search by index or name)</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text"
                                               id="manual-search-{{ $week->id }}"
                                               data-manual-search="{{ $week->id }}"
                                               autocomplete="off"
                                               placeholder="e.g. UEB1101234 or Jane"
                                               class="w-full text-sm border border-gray-200 rounded-lg pl-9 pr-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 font-mono">
                                        <i class="fas fa-magnifying-glass text-gray-400 text-xs absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                    </div>
                                    {{-- NB: do NOT put `required` on this hidden input — browsers silently block submit on empty required hidden fields and the `submit` event never fires, so our JS validation + confirm() + spinner never runs and the button looks dead. The submit handler below enforces the pick. --}}
                                    <input type="hidden" name="student_id"
                                           id="manual-student-id-{{ $week->id }}"
                                           data-manual-student-id="{{ $week->id }}">
                                    <p id="manual-selected-{{ $week->id }}"
                                       data-manual-selected="{{ $week->id }}"
                                       class="hidden mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-md px-2 py-1">
                                        <i class="fas fa-check-circle text-[10px]"></i>
                                        <span data-manual-selected-text></span>
                                        <button type="button" data-manual-clear="{{ $week->id }}" class="ml-1 text-emerald-500 hover:text-emerald-800" aria-label="Clear selection">
                                            <i class="fas fa-xmark text-[10px]"></i>
                                        </button>
                                    </p>
                                    <p id="manual-no-match-{{ $week->id }}"
                                       data-manual-no-match="{{ $week->id }}"
                                       class="hidden mt-1.5 text-[11px] text-rose-600">No student matches that search.</p>
                                    <div id="manual-suggestions-{{ $week->id }}"
                                         data-manual-suggestions="{{ $week->id }}"
                                         class="hidden absolute z-20 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>
                                </div>

                                {{-- Roster JSON island for the typeahead (pre-computed in a php block to dodge Blade multi-line directive parsing quirks). --}}
                                @php
                                    $manualRoster = $classmates->map(fn ($cm) => [
                                        'id'    => (int) $cm->id,
                                        'index' => (string) ($cm->index_number ?? ''),
                                        'name'  => trim((string) ($cm->last_name ?? '').' '.(string) ($cm->first_name ?? '')),
                                    ])->values();
                                @endphp
                                <script type="application/json" data-manual-roster="{{ $week->id }}">{!! json_encode($manualRoster, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Status</label>
                                    <select name="status" required
                                            class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
                                        <option value="present">Present</option>
                                        <option value="late">Late</option>
                                        <option value="absent">Absent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Reason</label>
                                    <input type="text" name="reason" minlength="3" maxlength="500"
                                           data-manual-reason="{{ $week->id }}"
                                           placeholder="e.g. phone died, lecturer confirmed in class"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
                                    <p data-manual-reason-error="{{ $week->id }}" class="hidden mt-1.5 text-[11px] font-semibold text-rose-600">Reason is required (at least 3 characters).</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Required — saved with the audit log so this manual action is traceable.</p>
                                </div>
                                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 -mx-4 -mb-4 px-4 py-3 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[max(env(safe-area-inset-bottom,0px),12px)]">
                                    <button type="button" data-week-modal-close
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            data-manual-submit="{{ $week->id }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-800 disabled:opacity-60 disabled:cursor-not-allowed">
                                        <i class="fas fa-check text-[11px]" data-manual-submit-icon></i>
                                        <span data-manual-submit-label>Save manual mark</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.roll-call') && !empty($classmates ?? null))
                    @php $presentSet = $row['present_ids'] ?? collect(); @endphp
                    <div data-week-modal="rollcall-{{ $week->id }}"
                         class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
                         role="dialog" aria-modal="true" aria-labelledby="rollcall-{{ $week->id }}-title">
                        <div class="relative w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200 max-h-[92vh] sm:max-h-[85vh] flex flex-col">
                            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Online roll-call</p>
                                    <h3 id="rollcall-{{ $week->id }}-title" class="text-base font-bold text-gray-900">Week {{ $week->week_number }}</h3>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Tick everyone who joined the live meeting. Each row is stamped with your name + the reason for audit.</p>
                                </div>
                                <button type="button" data-week-modal-close
                                        class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>

                            <form action="{{ route('dashboard.class-attendance.roll-call', [$course, $week]) }}"
                                  method="post"
                                  class="p-4 space-y-3 overflow-y-auto"
                                  data-rollcall-form
                                  data-week-id="{{ $week->id }}">
                                @csrf

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <label class="block">
                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-600">Platform <span class="text-gray-400 normal-case">(optional)</span></span>
                                        <input type="text" name="platform" maxlength="60"
                                               value="{{ $week->online_platform }}"
                                               placeholder="Zoom, Meet, Teams…"
                                               class="mt-1 w-full text-[12px] border border-gray-200 rounded-md px-2 py-1.5">
                                    </label>
                                    <label class="block">
                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-600">Reason / note <span class="text-rose-600 normal-case">(required)</span></span>
                                        <input type="text" name="note" maxlength="500" required minlength="3"
                                               value="{{ $week->online_note ?: 'Online class — roll-call by rep' }}"
                                               placeholder="e.g. Zoom lecture on Jun 10"
                                               class="mt-1 w-full text-[12px] border border-gray-200 rounded-md px-2 py-1.5">
                                    </label>
                                </div>

                                <label class="inline-flex items-center gap-2 text-[11px] text-gray-700">
                                    <input type="checkbox" name="mark_online" value="1" checked
                                           class="rounded border-gray-300 text-indigo-700 focus:ring-indigo-500">
                                    Flag this week as Online (shows the badge to everyone)
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

                                <div class="rounded-lg border border-gray-200 bg-white max-h-72 overflow-y-auto divide-y divide-gray-100">
                                    @foreach($classmates as $cm)
                                        @php
                                            $sid = (int) $cm->id;
                                            $isPresent = $presentSet->has($sid);
                                            $current = $isPresent ? 'present' : 'absent';
                                            $displayName = trim(($cm->last_name ?? '').' '.($cm->first_name ?? ''));
                                            $searchKey = strtolower(($cm->index_number ?? '').' '.$displayName);
                                        @endphp
                                        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 text-[11px] hover:bg-gray-50"
                                             data-rollcall-row
                                             data-search="{{ $searchKey }}"
                                             data-default="{{ $current }}">
                                            <span class="min-w-0 truncate">
                                                <span class="font-mono font-semibold text-gray-900">{{ $cm->index_number }}</span>
                                                <span class="ml-1 text-gray-700">{{ $displayName }}</span>
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

                                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 -mx-4 -mb-4 px-4 py-3 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[max(env(safe-area-inset-bottom,0px),12px)]">
                                    <button type="button" data-week-modal-close
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-800">
                                        <i class="fas fa-floppy-disk text-[11px]"></i>
                                        Save roll-call
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.week.rename'))
                    <div data-week-modal="rename-{{ $week->id }}"
                         class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
                         role="dialog" aria-modal="true" aria-labelledby="rename-{{ $week->id }}-title">
                        <div class="relative w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200">
                            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-700">Edit week label</p>
                                    <h3 id="rename-{{ $week->id }}-title" class="text-base font-bold text-gray-900">Week {{ $week->week_number }}</h3>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Use this only if the week was numbered wrong (e.g. catching up after a holiday).</p>
                                </div>
                                <button type="button" data-week-modal-close
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                            <form action="{{ route('dashboard.class-attendance.week.rename', [$course, $week]) }}" method="post" class="p-4 space-y-3">
                                @csrf
                                <div>
                                    <label for="rename-week-{{ $week->id }}" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">New week number</label>
                                    <input type="number" name="week_number" id="rename-week-{{ $week->id }}" min="1" max="500" required value="{{ $week->week_number }}"
                                           class="w-full text-sm tabular-nums border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-200 focus:border-slate-400">
                                </div>
                                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 -mx-4 -mb-4 px-4 py-3 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[max(env(safe-area-inset-bottom,0px),12px)]">
                                    <button type="button" data-week-modal-close
                                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                                        <i class="fas fa-pen text-[11px]"></i> Save label
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.course.week.import-json'))
                    <div data-week-modal="upload-{{ $week->id }}"
                         class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
                         role="dialog" aria-modal="true" aria-labelledby="upload-{{ $week->id }}-title">
                        <div class="relative w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200">
                            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Restore week</p>
                                    <h3 id="upload-{{ $week->id }}-title" class="text-base font-bold text-gray-900">Week {{ $week->week_number }} — upload backup</h3>
                                </div>
                                <button type="button" data-week-modal-close
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                            <form action="{{ route('dashboard.class-attendance.course.week.import-json', [$course, $week]) }}" method="post" enctype="multipart/form-data" class="p-4 space-y-3">
                                @csrf
                                <input type="file" name="backup" accept=".json,application/json" required
                                       class="w-full text-sm border border-gray-200 rounded-lg file:mr-2 file:py-2 file:px-3 file:text-sm file:rounded file:border-0 file:bg-gray-100">
                                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 -mx-4 -mb-4 px-4 py-3 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[max(env(safe-area-inset-bottom,0px),12px)]">
                                    <button type="button" data-week-modal-close
                                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary/90">
                                        <i class="fas fa-upload text-[11px]"></i> Upload
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.week.cancel'))
                    <div data-week-modal="cancel-{{ $week->id }}"
                         class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
                         role="dialog" aria-modal="true" aria-labelledby="cancel-{{ $week->id }}-title">
                        <div class="relative w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200">
                            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-100">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Cancel week</p>
                                    <h3 id="cancel-{{ $week->id }}-title" class="text-base font-bold text-gray-900">Week {{ $week->week_number }}</h3>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Marks the week as cancelled. Existing attendance stays for audit; the % won't count against students.</p>
                                </div>
                                <button type="button" data-week-modal-close
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-100">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                            <form action="{{ route('dashboard.class-attendance.week.cancel', [$course, $week]) }}" method="post" class="p-4 space-y-3"
                                  onsubmit="return confirm('Cancel week {{ $week->week_number }}?');">
                                @csrf
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Note (optional)</label>
                                    <input type="text" name="note" placeholder="e.g. lecturer absent, public holiday" maxlength="2000"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-200 focus:border-amber-400">
                                </div>
                                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 -mx-4 -mb-4 px-4 py-3 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[max(env(safe-area-inset-bottom,0px),12px)]">
                                    <button type="button" data-week-modal-close
                                            class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Keep open</button>
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800">
                                        <i class="fas fa-ban text-[11px]"></i> Cancel week
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endunless
        @endforeach
    </div>
</div>
@else
<div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/50 px-4 py-10 text-center text-sm text-gray-500">
    No teaching weeks yet for this course.
</div>
@endif

@if($attendances->total() > 0 && (request()->filled('date_from') || request()->filled('date_to') || request()->filled('search')))
<div class="mt-4 bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
        <p class="text-xs font-semibold text-gray-700">Filtered marks ({{ $attendances->total() }})</p>
        <a href="{{ route('dashboard.class-attendance.course', $course) }}" class="text-[11px] text-primary hover:underline">Clear filters</a>
    </div>
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
                @foreach($attendances as $a)
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
                @endforeach
            </tbody>
        </table>
    </div>
    @if($attendances->hasPages())
    <div class="px-3 py-2 border-t border-gray-100">
        {{ $attendances->links() }}
    </div>
    @endif
</div>
@endif

@push('scripts')
<script>
(function () {
    const form = document.getElementById('attendance-filters-form');
    const search = document.getElementById('attendance-search');
    if (form && search) {
        let t;
        search.addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(function () { form.requestSubmit(); }, 350);
        });
    }

    // Expand-all / collapse-all toggle for the weekly grid.
    const toggleBtn = document.querySelector('[data-week-toggle-all]');
    const toggleLbl = document.querySelector('[data-week-toggle-label]');
    const grid = document.querySelector('[data-week-grid]');
    if (toggleBtn && toggleLbl && grid) {
        const allDetails = () => Array.from(grid.querySelectorAll(':scope > details'));
        const refreshLabel = () => {
            const items = allDetails();
            const openCount = items.filter(d => d.open).length;
            toggleLbl.textContent = openCount === items.length && items.length > 0 ? 'Collapse all' : 'Expand all';
        };
        toggleBtn.addEventListener('click', () => {
            const items = allDetails();
            const allOpen = items.length > 0 && items.every(d => d.open);
            items.forEach(d => { d.open = !allOpen; });
            refreshLabel();
        });
        // Keep the button label in sync when individual cards are toggled.
        grid.addEventListener('toggle', refreshLabel, true);
        refreshLabel();
    }

    // Per-week modals (attendees, manual mark, upload, cancel).
    // We attach handlers BOTH directly (so an eager click after page load
    // always works even if a stray ancestor swallowed propagation) AND
    // via document delegation as a fallback.
    function showModal(modal) {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.documentElement.dataset.weekModalOpen = '1';
        document.body.style.overflow = 'hidden';
        const focusable = modal.querySelector('input, select, textarea, button:not([data-week-modal-close])');
        if (focusable) {
            try { focusable.focus({ preventScroll: true }); } catch (_) { focusable.focus(); }
        }
    }
    function hideModal(modal) {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (!document.querySelector('[data-week-modal]:not(.hidden)')) {
            delete document.documentElement.dataset.weekModalOpen;
            document.body.style.overflow = '';
        }
    }
    function modalForCloser(btn) {
        // Walk up to the nearest element that has the data-week-modal
        // attribute (the modal root). Fall back to the explicit
        // [data-week-modal] selector to guard against future markup
        // changes that might add unrelated [data-*] attrs in between.
        let node = btn;
        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('data-week-modal')) return node;
            node = node.parentElement;
        }
        return null;
    }

    // Direct binding — fires even if some ancestor calls stopPropagation.
    document.querySelectorAll('[data-week-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            const id = btn.getAttribute('data-week-modal-open');
            const modal = document.querySelector('[data-week-modal="' + id + '"]');
            showModal(modal);
        });
    });
    document.querySelectorAll('[data-week-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            hideModal(modalForCloser(btn));
        });
    });
    // Click on the dim backdrop (the modal root, not its card) closes too.
    document.querySelectorAll('[data-week-modal]').forEach(function (modal) {
        modal.addEventListener('click', function (ev) {
            if (ev.target === modal) {
                hideModal(modal);
            }
        });
    });

    // Delegation fallback — ensures dynamically-injected close buttons
    // (and clicks that bubble past the direct listener) still work.
    document.addEventListener('click', function (ev) {
        const target = ev.target;
        if (!target || !target.closest) return;
        const closer = target.closest('[data-week-modal-close]');
        if (closer) {
            ev.preventDefault();
            ev.stopPropagation();
            hideModal(modalForCloser(closer));
            return;
        }
        const opener = target.closest('[data-week-modal-open]');
        if (opener) {
            ev.preventDefault();
            ev.stopPropagation();
            const id = opener.getAttribute('data-week-modal-open');
            showModal(document.querySelector('[data-week-modal="' + id + '"]'));
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        const open = document.querySelector('[data-week-modal]:not(.hidden)');
        if (open) hideModal(open);
    });
})();

// ────────────────────────────────────────────────────────────────
// Manual-mark searchable student picker.
// One delegated handler set works across every per-week modal so we
// don't have to bind N×M listeners. The roster for each week is
// embedded as a <script type="application/json"> island under the
// form (one per modal) — keeps the search 100% client-side and
// avoids an extra XHR every time the rep types.
// ────────────────────────────────────────────────────────────────
(function () {
    var rosterCache = {};

    function getRoster(weekId) {
        if (rosterCache[weekId]) return rosterCache[weekId];
        var node = document.querySelector('script[data-manual-roster="' + weekId + '"]');
        if (!node) return [];
        try {
            rosterCache[weekId] = JSON.parse(node.textContent || '[]');
        } catch (e) {
            rosterCache[weekId] = [];
        }
        return rosterCache[weekId];
    }
    function $ (sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setSelected(weekId, item) {
        var hidden = $('[data-manual-student-id="' + weekId + '"]');
        var search = $('[data-manual-search="' + weekId + '"]');
        var pill = $('[data-manual-selected="' + weekId + '"]');
        var pillText = pill && pill.querySelector('[data-manual-selected-text]');
        var sugg = $('[data-manual-suggestions="' + weekId + '"]');
        var noMatch = $('[data-manual-no-match="' + weekId + '"]');
        if (!hidden) return;
        hidden.value = String(item.id);
        if (search) search.value = item.index;
        if (pillText) pillText.textContent = item.index + (item.name ? ' — ' + item.name : '');
        if (pill) pill.classList.remove('hidden');
        if (sugg) { sugg.classList.add('hidden'); sugg.innerHTML = ''; }
        if (noMatch) noMatch.classList.add('hidden');
    }
    function clearSelected(weekId, opts) {
        opts = opts || {};
        var hidden = $('[data-manual-student-id="' + weekId + '"]');
        var search = $('[data-manual-search="' + weekId + '"]');
        var pill = $('[data-manual-selected="' + weekId + '"]');
        if (hidden) hidden.value = '';
        if (pill) pill.classList.add('hidden');
        if (!opts.keepSearch && search) search.value = '';
    }

    function renderSuggestions(weekId, query) {
        var sugg = $('[data-manual-suggestions="' + weekId + '"]');
        var noMatch = $('[data-manual-no-match="' + weekId + '"]');
        if (!sugg) return;
        var q = (query || '').trim().toLowerCase();
        if (!q) {
            sugg.classList.add('hidden');
            sugg.innerHTML = '';
            if (noMatch) noMatch.classList.add('hidden');
            return;
        }
        var roster = getRoster(weekId);
        var matches = roster.filter(function (r) {
            return (r.index || '').toLowerCase().indexOf(q) !== -1
                || (r.name || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 10);

        if (matches.length === 0) {
            sugg.classList.add('hidden');
            sugg.innerHTML = '';
            if (noMatch) noMatch.classList.remove('hidden');
            return;
        }
        if (noMatch) noMatch.classList.add('hidden');

        sugg.innerHTML = matches.map(function (m) {
            var idxSafe = escapeHtml(m.index);
            var nameSafe = escapeHtml(m.name);
            return '<button type="button" '
                + 'data-manual-pick="' + weekId + '" '
                + 'data-pick-id="' + m.id + '" '
                + 'data-pick-index="' + idxSafe + '" '
                + 'data-pick-name="' + nameSafe + '" '
                + 'class="w-full text-left px-3 py-2 text-sm border-b border-slate-100 last:border-b-0 hover:bg-indigo-50 flex items-center gap-2">'
                + '<span class="font-mono font-bold text-slate-900">' + idxSafe + '</span>'
                + (nameSafe ? '<span class="text-slate-600 text-xs truncate">' + nameSafe + '</span>' : '')
                + '</button>';
        }).join('');
        sugg.classList.remove('hidden');
    }

    // Input → re-render the suggestion list and clear stale selection.
    document.addEventListener('input', function (ev) {
        var input = ev.target.closest && ev.target.closest('[data-manual-search]');
        if (!input) return;
        var weekId = input.getAttribute('data-manual-search');
        // Any edit invalidates the previous pick.
        clearSelected(weekId, { keepSearch: true });
        renderSuggestions(weekId, input.value);
    });

    // Click on a suggestion row → commit the pick.
    document.addEventListener('click', function (ev) {
        var pick = ev.target.closest && ev.target.closest('[data-manual-pick]');
        if (!pick) return;
        ev.preventDefault();
        ev.stopPropagation();
        var weekId = pick.getAttribute('data-manual-pick');
        setSelected(weekId, {
            id: parseInt(pick.getAttribute('data-pick-id'), 10),
            index: pick.getAttribute('data-pick-index') || '',
            name: pick.getAttribute('data-pick-name') || '',
        });
    });

    // The little ✕ on the green pill clears the pick.
    document.addEventListener('click', function (ev) {
        var clr = ev.target.closest && ev.target.closest('[data-manual-clear]');
        if (!clr) return;
        ev.preventDefault();
        ev.stopPropagation();
        clearSelected(clr.getAttribute('data-manual-clear'));
    });

    // Enter on the search box selects the top match (saves the rep a click).
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var input = ev.target.closest && ev.target.closest('[data-manual-search]');
        if (!input) return;
        var weekId = input.getAttribute('data-manual-search');
        var sugg = $('[data-manual-suggestions="' + weekId + '"]');
        if (!sugg || sugg.classList.contains('hidden')) return;
        var first = sugg.querySelector('[data-manual-pick]');
        if (first) {
            ev.preventDefault();
            first.click();
        }
    });

    // Click outside a modal's search/suggestions hides the dropdown.
    document.addEventListener('click', function (ev) {
        document.querySelectorAll('[data-manual-suggestions]').forEach(function (sugg) {
            var weekId = sugg.getAttribute('data-manual-suggestions');
            var input = $('[data-manual-search="' + weekId + '"]');
            if (sugg.contains(ev.target) || (input && input.contains(ev.target))) return;
            sugg.classList.add('hidden');
        });
    });

    // Show / hide the search-box red flash + "pick a student" message.
    function flagMissingStudent(weekId, missing) {
        var search = $('[data-manual-search="' + weekId + '"]');
        var noMatch = $('[data-manual-no-match="' + weekId + '"]');
        if (missing) {
            if (search) {
                search.focus();
                search.classList.add('ring-2', 'ring-rose-400');
                setTimeout(function () {
                    search.classList.remove('ring-2', 'ring-rose-400');
                }, 1600);
            }
            if (noMatch) {
                noMatch.textContent = 'Pick a student from the suggestions first.';
                noMatch.classList.remove('hidden');
            }
        }
    }

    function flagMissingReason(weekId, missing, reasonEl) {
        var err = $('[data-manual-reason-error="' + weekId + '"]');
        if (missing) {
            if (err) err.classList.remove('hidden');
            if (reasonEl) {
                reasonEl.classList.add('ring-2', 'ring-rose-400', 'border-rose-300');
                reasonEl.focus();
                setTimeout(function () {
                    reasonEl.classList.remove('ring-2', 'ring-rose-400', 'border-rose-300');
                }, 2200);
            }
        } else {
            if (err) err.classList.add('hidden');
            if (reasonEl) reasonEl.classList.remove('ring-2', 'ring-rose-400', 'border-rose-300');
        }
    }

    // Clear reason error as soon as the rep starts typing.
    document.addEventListener('input', function (ev) {
        var r = ev.target.closest && ev.target.closest('[data-manual-reason]');
        if (!r) return;
        var weekId = r.getAttribute('data-manual-reason');
        if ((r.value || '').trim().length >= 3) {
            flagMissingReason(weekId, false, r);
        }
    });

    // Returns true if the form is OK to submit; otherwise shows the
    // appropriate inline feedback and returns false.
    function validateManualForm(form) {
        var weekId = form.getAttribute('data-week-id');
        var hidden = $('[data-manual-student-id="' + weekId + '"]');
        var reason = $('[data-manual-reason="' + weekId + '"]');
        var hasStudent = !!(hidden && hidden.value);
        var reasonText = reason ? (reason.value || '').trim() : '';
        var hasReason = reasonText.length >= 3;
        // Flag both — but focus the student picker first if missing.
        flagMissingStudent(weekId, !hasStudent);
        flagMissingReason(weekId, !hasReason, reason);
        if (!hasStudent) return false;
        if (!hasReason) return false;
        return true;
    }

    // Swap the button to the spinner / saving state.
    function setSubmitBusy(form, busy) {
        var weekId = form.getAttribute('data-week-id');
        var btn = $('[data-manual-submit="' + weekId + '"]', form);
        if (!btn) return;
        var icon = btn.querySelector('[data-manual-submit-icon]');
        var label = btn.querySelector('[data-manual-submit-label]');
        if (busy) {
            btn.disabled = true;
            if (icon) icon.className = 'fas fa-spinner fa-spin text-[11px]';
            if (label) label.textContent = 'Saving…';
            setTimeout(function () {
                if (!btn.disabled) return;
                btn.disabled = false;
                if (icon) icon.className = 'fas fa-check text-[11px]';
                if (label) label.textContent = 'Save manual mark';
            }, 8000);
        } else {
            btn.disabled = false;
            if (icon) icon.className = 'fas fa-check text-[11px]';
            if (label) label.textContent = 'Save manual mark';
        }
    }

    // Single click-driven flow: validate → confirm → busy → submit.
    //
    // Why not rely on the native click→submit pipeline?
    // We confirmed via [MANUAL-MARK] logs that the click reaches us
    // and validation passes, but the browser's submit event never
    // fires (something — likely a click handler attached elsewhere
    // in the modal's ancestor chain or a stopPropagation higher up
    // — is eating the event). Instead of fighting it, we ALWAYS
    // preventDefault on the click and explicitly drive the submit
    // ourselves with form.requestSubmit() (or form.submit() as a
    // fallback for very old browsers). This makes the path one
    // straight line that's impossible to silently break.
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('[data-manual-submit]');
        if (!btn) return;
        var form = btn.closest('.manual-mark-form');
        if (!form) return;

        // Always take over the click so no other handler can
        // re-cancel it later.
        ev.preventDefault();
        ev.stopPropagation();

        var weekId = form.getAttribute('data-week-id');
        var hidden = $('[data-manual-student-id="' + weekId + '"]');
        var reason = $('[data-manual-reason="' + weekId + '"]');
        console.info('[MANUAL-MARK] save.click', {
            week_id: weekId,
            student_id: hidden ? hidden.value : null,
            status: (form.querySelector('select[name="status"]') || {}).value,
            reason_len: reason ? (reason.value || '').trim().length : 0,
            btn_disabled: btn.disabled,
            form_action: form.getAttribute('action'),
        });

        if (btn.disabled) {
            console.warn('[MANUAL-MARK] save.blocked: button disabled (already in flight)');
            return;
        }
        if (!validateManualForm(form)) {
            console.warn('[MANUAL-MARK] save.blocked: client validation failed');
            return;
        }
        if (!window.confirm('Mark this student manually? This is logged for audit.')) {
            console.info('[MANUAL-MARK] save.cancelled by rep');
            return;
        }

        setSubmitBusy(form, true);
        console.info('[MANUAL-MARK] save.submitting →', form.getAttribute('action'));
        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } catch (err) {
            console.error('[MANUAL-MARK] save.submit_threw', err);
            // Fall back to a raw submit if requestSubmit somehow throws.
            try { form.submit(); } catch (_) {}
        }
    });

    // Keep this listener so we can see in the console whether the
    // submit event actually fired (helps debug environments where
    // form.requestSubmit() is silently no-op'd by a parent handler).
    document.addEventListener('submit', function (ev) {
        var form = ev.target.closest && ev.target.closest('.manual-mark-form');
        if (!form) return;
        console.info('[MANUAL-MARK] submit.event_fired', {
            week_id: form.getAttribute('data-week-id'),
            default_prevented: ev.defaultPrevented,
        });
    }, true);

    // ────────────────────────────────────────────────────────────
    // Generic modal-form submit driver — same root cause as the
    // manual-mark fix above. The page has layered <details> +
    // modal-backdrop click handlers that swallow the native
    // click→submit pipeline for any submit button inside a modal,
    // so Cancel Week, Rename Week, Import JSON, and friends all
    // looked dead too. We take over the click and drive the form
    // submission ourselves with form.requestSubmit() (which still
    // fires the form's submit event, so inline `onsubmit="return
    // confirm(...)"` attributes keep working as expected).
    // ────────────────────────────────────────────────────────────
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('[data-week-modal] button[type="submit"]');
        if (!btn) return;
        // The manual-mark Save button has its own dedicated handler
        // above with extra validation + audit confirm — skip here so
        // we don't double-submit.
        if (btn.hasAttribute('data-manual-submit')) return;
        var form = btn.closest('form');
        if (!form) return;
        if (btn.disabled) {
            ev.preventDefault();
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        console.info('[MODAL-FORM] submit.driving →', form.getAttribute('action'));
        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(btn);
            } else {
                form.submit();
            }
        } catch (err) {
            console.error('[MODAL-FORM] submit.threw', err);
            try { form.submit(); } catch (_) {}
        }
    });
})();

// Prompt the rep for a deletion reason and stash it into the hidden
// `reason` field before submitting. Returns false to cancel if blank.
window.promptAttendanceDeleteReason = function (formEl) {
    const reason = window.prompt('Why are you deleting this attendance record?\nThis is logged for audit and cannot be undone.', '');
    if (reason === null) return false;
    const trimmed = reason.trim();
    if (trimmed.length < 3) {
        alert('Please provide a reason (at least 3 characters).');
        return false;
    }
    const hidden = formEl.querySelector('input[name="reason"]');
    if (hidden) hidden.value = trimmed;
    return true;
};

// After "Start online lecture" the controller bounces back here
// with ?focus_week=<id>. Expand that week card AND auto-open its
// roll-call modal so the rep lands straight on the mark sheet.
(function focusWeekFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var focusId = params.get('focus_week');
    if (!focusId) return;
    var card = document.querySelector('[data-week-card][data-week-id="' + focusId + '"]');
    if (card) {
        card.open = true;
        setTimeout(function () {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
    }
    // Trigger the roll-call modal opener (matches the existing
    // data-week-modal-open click handler used by the action row).
    var opener = document.querySelector('[data-week-modal-open="rollcall-' + focusId + '"]');
    if (opener) {
        setTimeout(function () { opener.click(); }, 150);
    }
})();

// ────────────────────────────────────────────────────────────
// Online roll-call (rep): per-week bulk attendance form. Same
// behaviour as the lecturer version — keeps the pill styling in
// sync with the (hidden, sr-only) radio inputs, drives the
// All-present / All-absent / Reset bulk buttons, and filters
// the visible rows via the search box.
// ────────────────────────────────────────────────────────────
document.querySelectorAll('[data-rollcall-form]').forEach(function (form) {
    const rows = Array.from(form.querySelectorAll('[data-rollcall-row]'));
    const search = form.querySelector('[data-rollcall-search]');
    const summary = form.querySelector('[data-rollcall-summary]');

    const pillStyles = {
        present: { on: 'bg-emerald-600 text-white', off: 'bg-white text-emerald-800 hover:bg-emerald-50' },
        late:    { on: 'bg-amber-500 text-white',   off: 'bg-white text-amber-800 hover:bg-amber-50' },
        absent:  { on: 'bg-rose-600 text-white',    off: 'bg-white text-rose-800 hover:bg-rose-50' },
    };

    function repaintRow(row) {
        row.querySelectorAll('[data-rollcall-radio]').forEach(function (radio) {
            const pill = radio.closest('label');
            if (!pill) return;
            const styles = pillStyles[radio.value];
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
        const counts = { present: 0, late: 0, absent: 0 };
        rows.forEach(function (row) {
            const checked = row.querySelector('[data-rollcall-radio]:checked');
            if (!checked) return;
            counts[checked.value] = (counts[checked.value] || 0) + 1;
        });
        if (summary) {
            summary.textContent = 'Present ' + counts.present + ' · Late ' + counts.late + ' · Absent ' + counts.absent;
        }
    }

    function setAll(value) {
        rows.forEach(function (row) {
            if (row.style.display === 'none') return;
            const radio = row.querySelector('[data-rollcall-radio][value="' + value + '"]');
            if (!radio) return;
            radio.checked = true;
            repaintRow(row);
        });
        refreshSummary();
    }

    function reset() {
        rows.forEach(function (row) {
            const def = row.dataset.default || 'absent';
            const radio = row.querySelector('[data-rollcall-radio][value="' + def + '"]');
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
            const which = btn.dataset.rollcallBulk;
            if (which === 'reset') return reset();
            if (which === 'present' || which === 'absent' || which === 'late') return setAll(which);
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            const q = (search.value || '').toLowerCase().trim();
            rows.forEach(function (row) {
                const match = q === '' || (row.dataset.search || '').indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
            });
        });
    }

    refreshSummary();
});
</script>
@endpush
@endsection
