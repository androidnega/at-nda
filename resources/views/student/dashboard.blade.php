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
                    $mode = $session->mode ?? 'location';
                    $modeLabel = match ($mode) {
                        'qr' => 'QR code',
                        'hybrid' => 'QR + venue',
                        'wifi' => 'Wi‑Fi',
                        default => 'Venue',
                    };
                @endphp
                <li class="rounded-2xl {{ $isVioletTheme ? 'bg-indigo-50/90 border-indigo-200' : 'bg-white border-slate-200' }} p-4 sm:p-5 border flex flex-col gap-4 touch-manipulation">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-10 h-10 rounded-xl {{ $isVioletTheme ? 'bg-indigo-600' : 'bg-amber-600' }} text-white flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-base" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900 text-[15px] leading-snug">{{ $course->course_name }}</p>
                            @if($course->course_code)
                                <p class="text-xs text-slate-500 font-mono mt-1">{{ $course->course_code }}</p>
                            @endif
                            <p class="text-[11px] text-slate-600 mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>Week {{ $session->attendanceWeek?->week_number ?? '—' }}</span>
                                <span class="text-slate-300" aria-hidden="true">·</span>
                                <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">{{ $modeLabel }}</span>
                                @if($session->expires_at)
                                    <span class="text-slate-400">·</span>
                                    <span class="text-amber-900 font-medium">Closes {{ $session->expires_at->timezone(config('app.timezone'))->format('g:i A') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="w-full pt-0.5">
                        @if($isCheckMode)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button type="button"
                                        class="attendance-run-btn inline-flex w-full items-center justify-center gap-2 rounded-xl text-white px-4 py-3.5 text-sm font-semibold transition-colors {{ $checkedIn ? 'bg-emerald-400 cursor-not-allowed' : ($isVioletTheme ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-emerald-600 hover:bg-emerald-700') }}"
                                        data-action="checkin"
                                        data-course-id="{{ $course->id }}"
                                        data-session-id="{{ $session->id }}"
                                        {{ $checkedIn ? 'disabled' : '' }}>
                                    <i class="fas fa-hand-pointer"></i>
                                    {{ $checkedIn ? 'Checked in' : 'Check-in' }}
                                </button>
                                <button type="button"
                                        class="attendance-run-btn inline-flex w-full items-center justify-center gap-2 rounded-xl text-white px-4 py-3.5 text-sm font-semibold transition-colors {{ $checkedOut ? 'bg-rose-300 cursor-not-allowed' : (($session->checkout_enabled || !$checkedIn) ? ($isVioletTheme ? 'bg-indigo-500 hover:bg-indigo-600' : 'bg-rose-500 hover:bg-rose-600') : 'bg-rose-300 cursor-not-allowed') }}"
                                        data-action="checkout"
                                        data-course-id="{{ $course->id }}"
                                        data-session-id="{{ $session->id }}"
                                        {{ ($checkedOut || !($session->checkout_enabled && $checkedIn)) ? 'disabled' : '' }}>
                                    <i class="fas fa-hand-pointer"></i>
                                    {{ $checkedOut ? 'Checked out' : 'Check-out' }}
                                </button>
                            </div>
                            @if($checkedIn && !$checkedOut)
                                <p class="mt-2 text-xs text-slate-600 attendance-countdown"
                                   data-end-at="{{ optional($session->expected_end_time)->toIso8601String() }}"
                                   data-session-id="{{ $session->id }}">
                                    Waiting for checkout...
                                </p>
                            @endif
                        @else
                            <a href="{{ route('web.attendance.form', $course) }}"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl {{ $isVioletTheme ? 'bg-indigo-700 hover:bg-indigo-800' : 'bg-amber-700 hover:bg-amber-800' }} text-white px-4 py-3.5 text-sm font-semibold transition-colors">
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
    <div class="rounded-2xl {{ $isVioletTheme ? 'bg-gradient-to-br from-indigo-700 to-indigo-900 border-indigo-800' : 'bg-slate-100 border-slate-200' }} border p-5 sm:p-6">
        <p class="{{ $isVioletTheme ? 'text-indigo-100' : 'text-amber-700' }} text-xs font-semibold uppercase tracking-wider">Student</p>
        <h1 class="text-xl sm:text-2xl font-bold mt-1 {{ $isVioletTheme ? 'text-white' : 'text-slate-900' }} truncate">{{ $greeting }} {{ $displayName }}{{ $greetingPunct }}</h1>
        <p class="{{ $isVioletTheme ? 'text-indigo-100/90' : 'text-slate-600' }} text-sm mt-1 font-mono">{{ $student->index_number }}</p>
        @if($student->department?->name)
            <p class="{{ $isVioletTheme ? 'text-indigo-100' : 'text-slate-600' }} text-sm mt-2 flex items-center gap-2">
                <i class="fas fa-building-columns {{ $isVioletTheme ? 'text-indigo-200' : 'text-amber-600' }}"></i>
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

    <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-amber-700"><i class="fas fa-check-double text-sm"></i></span>
                Present
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $totalPresent }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5">
            <div class="flex items-center gap-2 text-slate-500 text-xs font-medium uppercase tracking-wide mb-1">
                <span class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center text-rose-700"><i class="fas fa-calendar-week text-sm"></i></span>
                Weeks
            </div>
            <p class="text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums">{{ $totalWeeks }}</p>
        </div>
    </div>

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

    document.querySelectorAll('.attendance-countdown').forEach((el) => {
        const endAt = el.dataset.endAt ? new Date(el.dataset.endAt) : null;
        if (!endAt || Number.isNaN(endAt.getTime())) return;
        const tick = () => {
            const diff = endAt.getTime() - Date.now();
            el.textContent = formatRemaining(diff);
            if (diff <= 0) {
                const sid = el.dataset.sessionId || '';
                const checkoutBtn = document.querySelector(`.attendance-run-btn[data-action="checkout"][data-session-id="${sid}"]`);
                if (checkoutBtn && checkoutBtn.hasAttribute('disabled')) {
                    checkoutBtn.removeAttribute('disabled');
                    checkoutBtn.classList.remove('bg-rose-300', 'cursor-not-allowed');
                    checkoutBtn.classList.add('bg-rose-500', 'hover:bg-rose-600');
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

