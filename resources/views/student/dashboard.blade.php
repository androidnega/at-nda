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
    <div class="mb-4 p-3 sm:p-4 bg-amber-50 text-amber-900 rounded-xl text-sm border border-amber-100">{{ session('success') }}</div>
@endif
@if (session('info'))
    <div class="mb-4 p-3 sm:p-4 bg-slate-100 text-slate-900 rounded-xl text-sm border border-slate-200">{{ session('info') }}</div>
@endif

@if($liveAttendanceSessions->isNotEmpty())
    <div class="max-w-lg mx-auto w-full space-y-5 md:max-w-none">
        <div class="text-center md:text-left pt-safe">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">Live sessions</p>
            <p class="text-xs text-slate-500 mt-1">Each open session is listed separately — mark each one before it closes.</p>
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
                <li class="rounded-2xl {{ $isVioletTheme ? 'bg-indigo-50/90 border-indigo-200' : ($isMidnightTheme ? 'bg-slate-800 border-slate-700' : 'bg-white border-slate-200') }} p-4 sm:p-5 border flex flex-col gap-4 touch-manipulation">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-10 h-10 rounded-xl {{ $isVioletTheme ? 'bg-indigo-600' : 'bg-amber-600' }} text-white flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-base" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold {{ $isMidnightTheme ? 'text-slate-100' : 'text-slate-900' }} text-[15px] leading-snug">{{ $course->course_name }}</p>
                            @if($course->course_code)
                                <p class="text-xs {{ $isMidnightTheme ? 'text-slate-400' : 'text-slate-500' }} font-mono mt-1">{{ $course->course_code }}</p>
                            @endif
                            <p class="text-[11px] {{ $isMidnightTheme ? 'text-slate-300' : 'text-slate-600' }} mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>Week {{ $session->attendanceWeek?->week_number ?? '—' }}</span>
                                <span class="text-slate-300" aria-hidden="true">·</span>
                                <span class="inline-flex items-center gap-1 rounded-lg {{ $isMidnightTheme ? 'bg-slate-700 text-slate-100' : 'bg-slate-100 text-slate-700' }} px-2 py-0.5 text-[11px] font-medium">{{ $modeLabel }}</span>
                                @if($session->expires_at)
                                    <span class="text-slate-400">·</span>
                                    <span class="{{ $isMidnightTheme ? 'text-cyan-300' : 'text-amber-900' }} font-medium">Closes {{ $session->expires_at->timezone(config('app.timezone'))->format('g:i A') }}</span>
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

<div class="space-y-5 sm:space-y-6">
    @php
        $lastName = trim((string) $student->last_name);
        $displayName = $lastName !== ''
            ? \Illuminate\Support\Str::title($lastName)
            : $student->index_number;
        $greeting = collect(['Hello', 'Yo'])->random();
        $greetingPunct = $greeting === 'Yo' ? '!' : '';
    @endphp
    <div class="rounded-2xl {{ $isVioletTheme ? 'bg-gradient-to-br from-indigo-700 to-indigo-900 border-indigo-800' : ($isMidnightTheme ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-200') }} border p-5 sm:p-6">
        <p class="{{ $isVioletTheme ? 'text-indigo-100' : ($isMidnightTheme ? 'text-cyan-300' : 'text-amber-700') }} text-xs font-semibold uppercase tracking-wider">Student</p>
        <h1 class="text-xl sm:text-2xl font-bold mt-1 {{ ($isVioletTheme || $isMidnightTheme) ? 'text-white' : 'text-slate-900' }} truncate">{{ $greeting }} {{ $displayName }}{{ $greetingPunct }}</h1>
        <p class="{{ $isVioletTheme ? 'text-indigo-100/90' : ($isMidnightTheme ? 'text-slate-300' : 'text-slate-600') }} text-sm mt-1 font-mono">{{ $student->index_number }}</p>
        @if($student->department?->name)
            <p class="{{ $isVioletTheme ? 'text-indigo-100' : ($isMidnightTheme ? 'text-slate-300' : 'text-slate-600') }} text-sm mt-2 flex items-center gap-2">
                <i class="fas fa-building-columns {{ $isVioletTheme ? 'text-indigo-200' : ($isMidnightTheme ? 'text-cyan-300' : 'text-amber-600') }}"></i>
                {{ $student->department->name }}
            </p>
        @endif
    </div>

    @if($isVioletTheme)
    <div class="rounded-2xl bg-white border border-indigo-100 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-900">Calendar view</h2>
            <a href="{{ route('student.attendance.history') }}" class="text-sm text-indigo-700 font-semibold hover:underline">Open history</a>
        </div>
        <p class="text-sm text-slate-600">This theme mirrors the mobile violet calendar layout for timetable-focused usage.</p>
    </div>
    @endif

    {{-- Quick stats — Today / This week first, since they're the most
         actionable for a student. "Present" is the lifetime total of
         non-cancelled marks; "Courses" is how many distinct class
         courses the student has been marked present in so far. --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center text-sky-700"><i class="fas fa-sun text-sm"></i></span>
                Today
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $todayCount }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">marks · {{ now()->format('D, M j') }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-700"><i class="fas fa-calendar-week text-sm"></i></span>
                This week
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $weekCount }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">since {{ now()->startOfWeek()->format('D, M j') }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700"><i class="fas fa-check-double text-sm"></i></span>
                Present
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $totalPresent }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">total marks</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-700"><i class="fas fa-book text-sm"></i></span>
                Courses
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">
                {{ $coursesAttended }}@if(($totalCoursesEnrolled ?? 0) > 0)<span class="text-base text-slate-400 font-medium"> / {{ $totalCoursesEnrolled }}</span>@endif
            </p>
            <p class="text-[11px] text-slate-500 mt-0.5">attended at least once</p>
        </div>
    </div>

    {{-- Today's schedule — straight from the per-class timetable.
         Each row shows the slot's time, lecturer, venue, and whether
         the student has already been marked present today. --}}
    @if(($todaysClasses ?? collect())->isNotEmpty())
    <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-calendar-day text-slate-400"></i>
                    Today’s classes
                </h2>
                <p class="text-[11px] text-slate-500 mt-0.5">{{ now()->format('l, F j') }} · {{ $todaysClasses->count() }} {{ $todaysClasses->count() === 1 ? 'slot' : 'slots' }}</p>
            </div>
            <a href="{{ route('student.attendance.history') }}" class="text-[11px] font-semibold text-amber-700 hover:underline">History</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach($todaysClasses as $slot)
                @php $course = $slot['course']; @endphp
                <li class="px-4 py-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-900 text-sm">
                            {{ $course->course_name }}
                            @if($course->course_code)
                                <span class="text-slate-400 font-normal">· {{ $course->course_code }}</span>
                            @endif
                        </p>
                        <p class="text-[11px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            @if($slot['start'])
                                <span class="inline-flex items-center gap-1"><i class="far fa-clock text-slate-400 text-[10px]"></i>{{ $slot['start'] }}@if($slot['end']) – {{ $slot['end'] }}@endif</span>
                            @endif
                            @if(!empty($slot['lecturer']))
                                <span class="inline-flex items-center gap-1"><i class="fas fa-chalkboard-teacher text-slate-400 text-[10px]"></i>{{ $slot['lecturer'] }}</span>
                            @endif
                            @if(!empty($slot['venue']))
                                <span class="inline-flex items-center gap-1"><i class="fas fa-map-marker-alt text-slate-400 text-[10px]"></i>{{ $slot['venue'] }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wide {{ $slot['marked'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $slot['marked'] ? 'Marked' : 'Pending' }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

</div>

@endif

@if(isset($cancelledWeeks) && $cancelledWeeks->isNotEmpty())
<div class="max-w-lg mx-auto w-full mt-5 md:max-w-none">
    <div class="rounded-2xl border border-amber-200 bg-amber-50/90 p-4 sm:p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Cancelled week(s)</p>
        <p class="text-xs text-amber-800/80 mt-1">Your class rep or lecturer marked these teaching weeks as cancelled — no attendance was expected.</p>
        <ul class="mt-3 space-y-2 text-sm text-amber-950 list-none p-0 m-0">
            @foreach($cancelledWeeks as $cw)
            <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-amber-200/60 pb-2 last:border-0 last:pb-0">
                <span class="font-semibold">{{ $cw->course?->course_name ?? 'Course' }}</span>
                @if($cw->course?->course_code)
                    <span class="font-mono text-xs text-amber-800/90">{{ $cw->course->course_code }}</span>
                @endif
                <span class="text-amber-900">· Week {{ $cw->week_number }}</span>
                @if($cw->week_date)
                    <span class="text-xs text-amber-800">· {{ $cw->week_date->format('M j, Y') }}</span>
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
        const cls = isError ? 'bg-rose-100 text-rose-800 border-rose-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200';
        const wrap = document.createElement('div');
        wrap.className = 'fixed bottom-5 left-1/2 -translate-x-1/2 z-[80] px-4 py-2 rounded-lg border text-sm font-medium shadow ' + cls;
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

