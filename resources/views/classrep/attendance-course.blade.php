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
            <details class="group rounded-xl border border-gray-200 bg-white hover:border-primary/40 transition open:shadow-md open:border-primary/40 open:ring-1 open:ring-primary/10">
                <summary class="cursor-pointer list-none p-3 sm:p-3.5 select-none">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-bold text-gray-900 leading-none">Week {{ $week->week_number }}</p>
                                <span class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-bold tabular-nums {{ $badge }}">
                                    {{ $week->isCancelled() ? 'Cancelled' : $pct.'%' }}
                                </span>
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
                            <form action="{{ route('dashboard.class-attendance.manual-mark', [$course, $week]) }}" method="post"
                                  class="manual-mark-form p-4 space-y-3 overflow-y-auto"
                                  data-week-id="{{ $week->id }}">
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
                                    <input type="text" name="reason" required minlength="3" maxlength="500"
                                           placeholder="e.g. phone died, lecturer confirmed in class"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
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

    // Form submit: guard against the "I clicked Save but nothing happened"
    // confusion by (1) blocking submission when no student is selected
    // and (2) disabling the button + showing a spinner the moment it's
    // pressed so the rep sees their click registered.
    document.addEventListener('submit', function (ev) {
        var form = ev.target.closest && ev.target.closest('.manual-mark-form');
        if (!form) return;
        var weekId = form.getAttribute('data-week-id');
        var hidden = $('[data-manual-student-id="' + weekId + '"]');
        var search = $('[data-manual-search="' + weekId + '"]');
        if (!hidden || !hidden.value) {
            ev.preventDefault();
            ev.stopPropagation();
            // Focus the search box and flash it red so it's obvious why
            // nothing happened.
            if (search) {
                search.focus();
                search.classList.add('ring-2', 'ring-rose-400');
                setTimeout(function () {
                    search.classList.remove('ring-2', 'ring-rose-400');
                }, 1600);
            }
            var noMatch = $('[data-manual-no-match="' + weekId + '"]');
            if (noMatch) {
                noMatch.textContent = 'Pick a student from the suggestions first.';
                noMatch.classList.remove('hidden');
            }
            return;
        }
        // Ask for confirmation now (replaces the form-level onsubmit so we
        // can keep the spinner behaviour atomic with the user's decision).
        var ok = window.confirm('Mark this student manually? This is logged for audit.');
        if (!ok) {
            ev.preventDefault();
            ev.stopPropagation();
            return;
        }
        // Disable submit + show spinner. Re-enables itself after 8s in
        // case the request hangs (so the form is never permanently dead).
        var btn = $('[data-manual-submit="' + weekId + '"]', form);
        if (btn) {
            btn.disabled = true;
            var icon = btn.querySelector('[data-manual-submit-icon]');
            var label = btn.querySelector('[data-manual-submit-label]');
            if (icon) icon.className = 'fas fa-spinner fa-spin text-[11px]';
            if (label) label.textContent = 'Saving…';
            setTimeout(function () {
                if (!btn.disabled) return;
                btn.disabled = false;
                if (icon) icon.className = 'fas fa-check text-[11px]';
                if (label) label.textContent = 'Save manual mark';
            }, 8000);
        }
    }, true);
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
</script>
@endpush
@endsection
