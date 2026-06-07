@extends('layouts.student')

@section('title', 'Dashboard')

@section('body_class', 'select-none overscroll-y-auto dashboard-fixed')

@section('breadcrumb')
    @if($liveAttendanceSessions->isNotEmpty())
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm">
            <span class="inline-flex items-center gap-2 text-amber-700 font-semibold">
                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span></span>
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

@push('styles')
<style>
    .dashboard-fixed {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .dashboard-fixed::-webkit-scrollbar {
        width: 0;
        height: 0;
    }
    .no-scrollbar {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .no-scrollbar::-webkit-scrollbar {
        width: 0;
        height: 0;
    }
    .attendance-cta {
        width: 100%;
        min-height: 54px;
        border-radius: 14px;
        border: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.95rem;
        font-weight: 700;
        color: #fff;
        transition: transform .14s ease, filter .14s ease, box-shadow .14s ease;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.14);
    }
    .attendance-cta:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
    }
    .attendance-cta:disabled {
        opacity: .56;
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
    }
    .attendance-cta--checkin {
        background: linear-gradient(180deg, #16984a 0%, #10863f 100%);
    }
    .attendance-cta--checkout {
        background: linear-gradient(180deg, #e05e5a 0%, #c94844 100%);
    }
    .attendance-cta--waiting {
        background: linear-gradient(180deg, #7c8798 0%, #646f7f 100%);
    }
    .attendance-cta--done {
        background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    }
    @media (max-width: 430px) {
        .attendance-cta {
            min-height: 50px;
            font-size: 0.9rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    function blockCopy(e) {
        e.preventDefault();
    }
    document.addEventListener('copy', blockCopy);
    document.addEventListener('cut', blockCopy);
    document.addEventListener('dragstart', blockCopy);
    document.addEventListener('selectstart', blockCopy);
    document.body.style.userSelect = 'none';
    document.body.style.webkitUserSelect = 'none';
})();
</script>
@endpush

@section('content')
@php
    $isVioletTheme = ($studentDashboardTheme ?? 'classic') === 'violet_calendar';
    $isMidnightTheme = ($studentDashboardTheme ?? 'classic') === 'midnight_control';
@endphp
@if (session('success'))
    <div class="mb-4 p-3 sm:p-4 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 rounded-xl text-sm border border-amber-100 dark:border-amber-900/60">{{ session('success') }}</div>
@endif
@if (session('info'))
    <div class="mb-4 p-3 sm:p-4 bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-xl text-sm border border-slate-200 dark:border-slate-700">{{ session('info') }}</div>
@endif

@if($liveAttendanceSessions->isNotEmpty())
    <div class="max-w-lg mx-auto w-full space-y-5 md:max-w-none">
        <div class="text-center md:text-left pt-safe">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Live sessions</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Each open session is listed separately — mark each one before it closes.</p>
        </div>

        <div class="max-h-[min(60vh,28rem)] overflow-y-auto no-scrollbar">
        <ul class="space-y-5 list-none p-0 m-0">
            @foreach($liveAttendanceSessions as $row)
                @php
                    $session = $row['session'];
                    $course = $row['course'];
                    $myAttendance = $row['my_attendance'] ?? null;
                    $attendanceMode = (string) ($session->attendance_mode ?? 'instant');
                    $isCheckMode = $attendanceMode === 'checkin_checkout';
                    $checkedIn = $myAttendance && ! empty($myAttendance->check_in_time);
                    $checkedOut = $myAttendance && ! empty($myAttendance->check_out_time);
                    $singleAction = 'checkin';
                    $singleLabel = 'Check in';
                    $singleIcon = 'fa-hand-pointer';
                    $singleDisabled = false;
                    $singleVariant = 'checkin';
                    if ($checkedOut) {
                        $singleAction = 'done';
                        $singleLabel = 'Checked out';
                        $singleIcon = 'fa-circle-check';
                        $singleDisabled = true;
                        $singleVariant = 'done';
                    } elseif ($checkedIn) {
                        if ($session->checkout_enabled) {
                            $singleAction = 'checkout';
                            $singleLabel = 'Check out';
                            $singleIcon = 'fa-arrow-right-from-bracket';
                            $singleVariant = 'checkout';
                        } else {
                            $singleAction = 'checkout_wait';
                            $singleLabel = 'Waiting for checkout';
                            $singleIcon = 'fa-hourglass-half';
                            $singleDisabled = true;
                            $singleVariant = 'waiting';
                        }
                    }
                    $mode = $session->mode ?? 'location';
                    $modeLabel = match ($mode) {
                        'qr' => 'QR code',
                        'hybrid' => 'QR + venue',
                        'wifi' => 'Wi‑Fi',
                        default => 'Venue',
                    };
                @endphp
                <li class="rounded-2xl {{ $isVioletTheme ? 'bg-indigo-50/90 border-indigo-200 dark:bg-indigo-950/40 dark:border-indigo-900' : ($isMidnightTheme ? 'bg-slate-800 border-slate-700' : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-700') }} p-4 sm:p-5 border flex flex-col gap-4 touch-manipulation">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-10 h-10 rounded-xl {{ $isVioletTheme ? 'bg-indigo-600' : 'bg-amber-600' }} text-white flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-base" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold {{ $isMidnightTheme ? 'text-slate-100' : 'text-slate-900 dark:text-slate-100' }} text-[15px] leading-snug">{{ $course->course_name }}</p>
                            @if($course->course_code)
                                <p class="text-xs {{ $isMidnightTheme ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400' }} font-mono mt-1">{{ $course->course_code }}</p>
                            @endif
                            <p class="text-[11px] {{ $isMidnightTheme ? 'text-slate-300' : 'text-slate-600 dark:text-slate-300' }} mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>Week {{ $session->attendanceWeek?->week_number ?? '—' }}</span>
                                <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
                                <span class="inline-flex items-center gap-1 rounded-lg {{ $isMidnightTheme ? 'bg-slate-700 text-slate-100' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }} px-2 py-0.5 text-[11px] font-medium">{{ $modeLabel }}</span>
                                @if($session->expires_at)
                                    <span class="text-slate-400 dark:text-slate-500">·</span>
                                    <span class="{{ $isMidnightTheme ? 'text-cyan-300' : 'text-amber-900 dark:text-amber-300' }} font-medium">Closes {{ $session->expires_at->timezone(config('app.timezone'))->format('g:i A') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="w-full pt-0.5">
                        @if($isCheckMode)
                            <button type="button"
                                    class="attendance-run-btn attendance-cta attendance-cta--{{ $singleVariant }}"
                                    data-action="{{ $singleAction }}"
                                    data-course-id="{{ $course->id }}"
                                    data-session-id="{{ $session->id }}"
                                    {{ $singleDisabled ? 'disabled' : '' }}>
                                <i class="fas {{ $singleIcon }}"></i>
                                <span>{{ $singleLabel }}</span>
                            </button>
                            @if($checkedIn && !$checkedOut)
                                <p class="mt-2 text-xs text-slate-600 attendance-countdown"
                                   data-end-at="{{ optional($session->expected_end_time)->toIso8601String() }}"
                                   data-session-id="{{ $session->id }}">
                                    Waiting for checkout...
                                </p>
                            @endif
                        @else
                            <a href="{{ route('web.attendance.form', $course) }}"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl {{ $isVioletTheme ? 'bg-indigo-700 hover:bg-indigo-800' : ($isMidnightTheme ? 'bg-cyan-500 hover:bg-cyan-400 text-slate-900' : 'bg-amber-700 hover:bg-amber-800 text-white') }} px-4 py-3.5 text-sm font-semibold transition-colors">
                                <i class="fas fa-arrow-right-to-bracket"></i>
                                Mark attendance
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        </div>
    </div>

@else

@php
    // Display-name preference: surname (title-cased) if we have one,
    // otherwise the index number so the header is never blank.
    $lastName = trim((string) $student->last_name);
    $displayName = $lastName !== ''
        ? \Illuminate\Support\Str::title($lastName)
        : $student->index_number;
    $initials = strtoupper(substr(trim($student->first_name ?? $displayName), 0, 1) . substr($lastName, 0, 1));
    if ($initials === '' || strlen($initials) < 2) {
        $initials = strtoupper(substr($student->index_number, 0, 2));
    }
    // Card accent colours rotate per course so the carousel doesn't
    // turn into a wall of identical white cards. Borrowed from
    // Tailwind palettes that we already use elsewhere.
    $cardPalette = [
        ['from' => 'from-sky-50',     'ring' => 'ring-sky-200',     'accent' => 'text-sky-700',     'bar' => 'bg-sky-500'],
        ['from' => 'from-emerald-50', 'ring' => 'ring-emerald-200', 'accent' => 'text-emerald-700', 'bar' => 'bg-emerald-500'],
        ['from' => 'from-amber-50',   'ring' => 'ring-amber-200',   'accent' => 'text-amber-700',   'bar' => 'bg-amber-500'],
        ['from' => 'from-rose-50',    'ring' => 'ring-rose-200',    'accent' => 'text-rose-700',    'bar' => 'bg-rose-500'],
        ['from' => 'from-indigo-50',  'ring' => 'ring-indigo-200',  'accent' => 'text-indigo-700',  'bar' => 'bg-indigo-500'],
    ];
@endphp

<div class="max-w-md mx-auto w-full lg:max-w-3xl space-y-5 sm:space-y-6 pb-24 lg:pb-6">

    {{-- ─── HEADER · avatar + greeting + bell ───────────────────────── --}}
    <div class="flex items-center justify-between gap-3 pt-1">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 w-11 h-11 rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center text-sm font-bold ring-2 ring-white dark:ring-slate-900 shadow-sm select-none">
                {{ $initials }}
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">Welcome back,</p>
                <p class="text-base font-bold text-slate-900 dark:text-slate-100 leading-tight truncate">{{ $displayName }}</p>
            </div>
        </div>
        <a href="{{ route('student.attendance.history') }}" aria-label="View attendance history"
           class="relative shrink-0 w-11 h-11 rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
            <i class="fas fa-bell text-base"></i>
            @if(($todayCount ?? 0) > 0)
                <span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-900"></span>
            @endif
        </a>
    </div>

    {{-- ─── SECTION TITLE · "Courses" ────────────────────────────────── --}}
    <div class="flex items-end justify-between gap-3 px-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">Courses</h1>
        @if($student->department?->name)
            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[55%] text-right">{{ $student->department->name }}</p>
        @endif
    </div>

    {{-- ─── COURSE CARDS · horizontal swipe (mobile) / grid (desktop) ──
         Snap-scroll on touch devices, grid on desktops. Each card
         shows attendance % (the "balance"), the course code (the
         "account number"), and lecturer / venue (the "valid thru"). --}}
    @if(($courseSummaries ?? collect())->isNotEmpty())
        <div class="-mx-1">
            <div class="flex lg:grid lg:grid-cols-2 gap-3 overflow-x-auto lg:overflow-visible snap-x snap-mandatory no-scrollbar px-1 pb-2">
                @foreach($courseSummaries as $i => $cs)
                    @php $p = $cardPalette[$i % count($cardPalette)]; @endphp
                    <div class="snap-start shrink-0 w-[85%] sm:w-[60%] lg:w-auto rounded-2xl bg-gradient-to-br {{ $p['from'] }} to-white dark:from-slate-800 dark:to-slate-900 border border-slate-200 dark:border-slate-700 ring-1 {{ $p['ring'] }} dark:ring-slate-700 p-4 sm:p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="shrink-0 w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center {{ $p['accent'] }} dark:text-slate-200">
                                    <i class="fas fa-book-open text-sm"></i>
                                </span>
                                <p class="font-semibold text-slate-900 dark:text-slate-100 text-sm leading-tight line-clamp-2 break-words">{{ $cs['name'] }}</p>
                            </div>
                            @if($cs['code'])
                                <span class="shrink-0 text-[10px] font-mono font-semibold text-slate-500 dark:text-slate-400 uppercase whitespace-nowrap">{{ $cs['code'] }}</span>
                            @endif
                        </div>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Your attendance</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-slate-100 tabular-nums leading-none">{{ $cs['pct'] }}<span class="text-xl text-slate-400 dark:text-slate-500">%</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">{{ $cs['present'] }} / {{ $cs['weeks'] }} wks</p>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full {{ $p['bar'] }} rounded-full transition-all" style="width: {{ max(2, $cs['pct']) }}%"></div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-slate-200/70 dark:border-slate-700/70 grid grid-cols-2 gap-2 text-[11px]">
                            <div>
                                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wide text-[9px] font-semibold">Lecturer</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium truncate">{{ $cs['lecturer'] ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wide text-[9px] font-semibold">Venue</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium truncate">{{ $cs['venue'] ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-5 text-center text-sm text-slate-500 dark:text-slate-400">
            No courses linked to your class yet. Ask your rep to enrol you.
        </div>
    @endif

    {{-- ─── ACTION PILLS · Mark / Timetable / + ──────────────────────── --}}
    <div class="flex items-center gap-3 px-1">
        <a href="{{ route('student.attendance.web') }}"
           class="flex-1 inline-flex items-center justify-center gap-2 rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100 hover:border-slate-300 dark:hover:border-slate-600 shadow-sm">
            <i class="fas fa-arrow-down-to-bracket text-slate-500 dark:text-slate-400"></i> Mark
        </a>
        <a href="{{ route('dashboard.timetable') }}"
           class="flex-1 inline-flex items-center justify-center gap-2 rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100 hover:border-slate-300 dark:hover:border-slate-600 shadow-sm">
            <i class="fas fa-calendar-alt text-slate-500 dark:text-slate-400"></i> Timetable
        </a>
        <a href="{{ route('dashboard.materials.index') }}" aria-label="Course materials"
           class="shrink-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 shadow-sm">
            <i class="fas fa-plus text-base"></i>
        </a>
    </div>

    {{-- ─── QUICK STATS · denser strip ──────────────────────────────── --}}
    <div class="grid grid-cols-4 gap-2 sm:gap-3 px-1">
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-3 text-center">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Today</p>
            <p class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 tabular-nums mt-0.5">{{ $todayCount }}</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-3 text-center">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Week</p>
            <p class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 tabular-nums mt-0.5">{{ $weekCount }}</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-3 text-center">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Present</p>
            <p class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 tabular-nums mt-0.5">{{ $totalPresent }}</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-3 text-center">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">Courses</p>
            <p class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 tabular-nums mt-0.5">
                {{ $coursesAttended }}@if(($totalCoursesEnrolled ?? 0) > 0)<span class="text-xs text-slate-400 dark:text-slate-500 font-medium">/{{ $totalCoursesEnrolled }}</span>@endif
            </p>
        </div>
    </div>

    {{-- ─── ACTIVITY · recent attendance marks (like "Transaction") ──── --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-4 py-3 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">Activity</h2>
            <a href="{{ route('student.attendance.history') }}" class="text-xs font-semibold text-sky-700 dark:text-sky-400 hover:underline">View all</a>
        </div>
        @if(($recentActivity ?? collect())->isNotEmpty())
            @php
                $tz = config('app.timezone');
                $grouped = $recentActivity->groupBy(function ($r) use ($tz) {
                    $t = $r['time']?->timezone($tz);
                    if (!$t) return 'EARLIER';
                    if ($t->isToday()) return 'TODAY';
                    if ($t->isYesterday()) return 'YESTERDAY';
                    return strtoupper($t->format('D, M j'));
                });
            @endphp
            @foreach($grouped as $label => $rows)
                <p class="px-4 py-1.5 text-[10px] uppercase tracking-wider font-bold text-slate-400 dark:text-slate-500 bg-slate-50/60 dark:bg-slate-800/40">{{ $label }}</p>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($rows as $r)
                        @php
                            $st = $r['status'];
                            [$icnBg, $icnFg, $icn, $amount, $amountClass] = match ($st) {
                                'present'    => ['bg-emerald-100 dark:bg-emerald-900/40', 'text-emerald-700 dark:text-emerald-300', 'fa-arrow-down-left',  'Present',  'text-emerald-600 dark:text-emerald-400'],
                                'late'       => ['bg-amber-100 dark:bg-amber-900/40',     'text-amber-700 dark:text-amber-300',     'fa-clock',            'Late',     'text-amber-600 dark:text-amber-400'],
                                'absent'     => ['bg-rose-100 dark:bg-rose-900/40',       'text-rose-700 dark:text-rose-300',       'fa-xmark',            'Absent',   'text-rose-600 dark:text-rose-400'],
                                'pending'    => ['bg-slate-100 dark:bg-slate-800',        'text-slate-600 dark:text-slate-300',     'fa-hourglass-half',   'Pending',  'text-slate-500 dark:text-slate-400'],
                                default      => ['bg-slate-100 dark:bg-slate-800',        'text-slate-600 dark:text-slate-300',     'fa-circle',           ucfirst($st), 'text-slate-500 dark:text-slate-400'],
                            };
                        @endphp
                        <li class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="shrink-0 w-10 h-10 rounded-full {{ $icnBg }} {{ $icnFg }} flex items-center justify-center">
                                    <i class="fas {{ $icn }} text-sm"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $r['course_name'] }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        @if($r['course_code'])<span class="font-mono">{{ $r['course_code'] }}</span> · @endif
                                        {{ $r['time']?->timezone($tz)?->format('g:i A') }}
                                    </p>
                                </div>
                            </div>
                            <span class="shrink-0 text-sm font-bold tabular-nums {{ $amountClass }}">{{ $amount }}</span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @else
            <p class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No attendance marks yet.</p>
        @endif
    </div>

    {{-- ─── TODAY'S SCHEDULE (only when there are slots) ───────────── --}}
    @if(($todaysClasses ?? collect())->isNotEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-4 py-3 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">Today’s classes</h2>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ now()->format('l, F j') }} · {{ $todaysClasses->count() }} {{ $todaysClasses->count() === 1 ? 'slot' : 'slots' }}</p>
            </div>
        </div>
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($todaysClasses as $slot)
                @php $course = $slot['course']; @endphp
                <li class="px-4 py-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900 dark:text-slate-100 text-sm">
                            {{ $course->course_name }}
                            @if($course->course_code)
                                <span class="text-slate-400 dark:text-slate-500 font-normal">· {{ $course->course_code }}</span>
                            @endif
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            @if($slot['start'])
                                <span class="inline-flex items-center gap-1"><i class="far fa-clock text-slate-400 dark:text-slate-500 text-[10px]"></i>{{ $slot['start'] }}@if($slot['end']) – {{ $slot['end'] }}@endif</span>
                            @endif
                            @if(!empty($slot['lecturer']))
                                <span class="inline-flex items-center gap-1"><i class="fas fa-chalkboard-teacher text-slate-400 dark:text-slate-500 text-[10px]"></i>{{ $slot['lecturer'] }}</span>
                            @endif
                            @if(!empty($slot['venue']))
                                <span class="inline-flex items-center gap-1"><i class="fas fa-map-marker-alt text-slate-400 dark:text-slate-500 text-[10px]"></i>{{ $slot['venue'] }}</span>
                            @endif
                        </p>
                    </div>
                    @php
                        $slotStatus = $slot['status'] ?? ($slot['marked'] ? 'marked' : 'pending');
                        $slotLabel  = $slot['status_label'] ?? ($slot['marked'] ? 'Marked' : 'Pending');
                        $slotClass  = match ($slotStatus) {
                            'marked'   => 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800',
                            'live'     => 'bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 ring-1 ring-amber-200 dark:ring-amber-800 animate-pulse',
                            'upcoming' => 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 ring-1 ring-sky-200 dark:ring-sky-800',
                            'missed'   => 'bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800',
                            default    => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700',
                        };
                    @endphp
                    <span class="shrink-0 px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wide {{ $slotClass }}">
                        {{ $slotLabel }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

</div>

{{-- Bottom-nav is provided by layouts/student.blade.php so every
     student page gets the same floating mobile dock. The old
     "Today's classes" block that used to sit here is now part of
     the new banking-app column above (with status-aware badges and
     dark-mode variants). --}}

@endif

@if(isset($cancelledWeeks) && $cancelledWeeks->isNotEmpty())
<div class="max-w-lg mx-auto w-full mt-5 md:max-w-none">
    <div class="rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50/90 dark:bg-amber-950/30 p-4 sm:p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">Cancelled week(s)</p>
        <p class="text-xs text-amber-800/80 dark:text-amber-200/80 mt-1">Your class rep or lecturer marked these teaching weeks as cancelled — no attendance was expected.</p>
        <ul class="mt-3 space-y-2 text-sm text-amber-950 dark:text-amber-100 list-none p-0 m-0">
            @foreach($cancelledWeeks as $cw)
            <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-amber-200/60 dark:border-amber-900/40 pb-2 last:border-0 last:pb-0">
                <span class="font-semibold">{{ $cw->course?->course_name ?? 'Course' }}</span>
                @if($cw->course?->course_code)
                    <span class="font-mono text-xs text-amber-800/90 dark:text-amber-200/80">{{ $cw->course->course_code }}</span>
                @endif
                <span class="text-amber-900 dark:text-amber-100">· Week {{ $cw->week_number }}</span>
                @if($cw->week_date)
                    <span class="text-xs text-amber-800 dark:text-amber-200">· {{ $cw->week_date->format('M j, Y') }}</span>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function toast(msg, isError) {
        const cls = isError
            ? 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950 dark:text-rose-200 dark:border-rose-900'
            : 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-900';
        const wrap = document.createElement('div');
        // Toast sits above the mobile bottom-nav so it stays visible.
        wrap.className = 'fixed bottom-24 lg:bottom-5 left-1/2 -translate-x-1/2 z-[80] px-4 py-2 rounded-lg border text-sm font-medium shadow ' + cls;
        wrap.textContent = msg;
        document.body.appendChild(wrap);
        setTimeout(() => wrap.remove(), 2600);
    }

    function formatRemaining(ms) {
        if (ms <= 0) return 'Checkout enabled now';
        const s = Math.floor(ms / 1000);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `Checkout opens in ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        return `Checkout opens in ${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    }
    function setActionButtonState(btn, state) {
        if (!btn || !state) return;
        btn.classList.remove('attendance-cta--checkin', 'attendance-cta--checkout', 'attendance-cta--waiting', 'attendance-cta--done');
        btn.classList.add(`attendance-cta--${state.variant}`);
        btn.dataset.action = state.action;
        btn.disabled = !!state.disabled;
        btn.innerHTML = `<i class="fas ${state.icon}"></i><span>${state.label}</span>`;
    }

    document.querySelectorAll('.attendance-countdown').forEach((el) => {
        const endAt = el.dataset.endAt ? new Date(el.dataset.endAt) : null;
        if (!endAt || Number.isNaN(endAt.getTime())) return;
        const tick = () => {
            const diff = endAt.getTime() - Date.now();
            el.textContent = formatRemaining(diff);
            if (diff <= 0) {
                const sid = el.dataset.sessionId || '';
                const actionBtn = document.querySelector(`.attendance-run-btn[data-session-id="${sid}"]`);
                if (actionBtn && actionBtn.dataset.action === 'checkout_wait') {
                    setActionButtonState(actionBtn, {
                        variant: 'checkout',
                        action: 'checkout',
                        icon: 'fa-arrow-right-from-bracket',
                        label: 'Check out',
                        disabled: false,
                    });
                }
            }
        };
        tick();
        setInterval(tick, 1000);
    });

    async function withLocation() {
        return await new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Location is not supported on this browser.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude }),
                () => reject(new Error('Allow location access and try again.')),
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    }

    async function submitCheckRun(btn) {
        const action = btn.dataset.action || '';
        if (action === 'done' || action === 'checkout_wait') return;
        const courseId = Number(btn.dataset.courseId || 0);
        const sessionId = Number(btn.dataset.sessionId || 0);
        if (!courseId || !sessionId) return;
        const indexMeta = document.querySelector('meta[name="student-index-number"]');
        const indexNumber = indexMeta ? indexMeta.getAttribute('content') : '';
        if (!indexNumber) {
            toast('Missing student session. Sign in again.', true);
            return;
        }
        btn.disabled = true;
        try {
            setActionButtonState(btn, {
                variant: 'waiting',
                action,
                icon: 'fa-spinner fa-spin',
                label: action === 'checkout' ? 'Checking out...' : 'Checking in...',
                disabled: true,
            });
            const loc = await withLocation();
            const payload = {
                index_number: indexNumber,
                course_id: courseId,
                session_id: sessionId,
                latitude: loc.latitude,
                longitude: loc.longitude,
            };
            const res = await fetch('{{ route('web.attendance.mark') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success !== true) {
                throw new Error(data.message || 'Attendance action failed.');
            }
            toast(data.message || 'Saved.', false);
            setTimeout(() => window.location.reload(), 800);
        } catch (e) {
            toast((e && e.message) ? e.message : 'Could not submit attendance.', true);
            btn.disabled = false;
            window.location.reload();
        }
    }

    document.querySelectorAll('.attendance-run-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            submitCheckRun(btn);
        });
    });
})();
</script>
@endpush

