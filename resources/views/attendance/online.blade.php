@extends('layouts.app')

@section('title', 'Online attendance — ' . $course->course_name)

@section('content')
{{--
    Student-facing online attendance page.

    The whole UX collapses to ONE action: type a rolling 4-digit code your
    rep shares in the meeting chat. No QR upload, no checkpoints, no
    second verification. PART 6 of the spec.

    The submit also POSTs a "client" object containing a FingerprintJS
    visitor id plus a few window.navigator hints. The server uses it for
    NON-BLOCKING risk scoring only (PART 7 / PART 8 / PART 10–12) —
    attendance is still recorded even if the client refuses to load JS or
    sends nothing at all.
--}}
<div class="max-w-md mx-auto px-4 py-6">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-indigo-50/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Online attendance</p>
            <h1 class="text-lg font-bold text-gray-900 mt-0.5">{{ $course->course_name }}</h1>
            <p class="text-[12px] text-gray-600 mt-0.5">
                {{ $course->course_code ? $course->course_code . ' · ' : '' }}{{ $student->index_number }}
            </p>
            @if($session->meeting_platform)
                @php
                    $platformLabel = match($session->meeting_platform) {
                        'zoom'        => 'Zoom',
                        'google_meet' => 'Google Meet',
                        'teams'       => 'Microsoft Teams',
                        'custom'      => 'Online meeting',
                        default       => 'Online meeting',
                    };
                @endphp
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-white px-2 py-0.5 text-[10.5px] font-semibold text-indigo-800">
                    <i class="fas fa-video text-[10px]"></i>
                    {{ $platformLabel }}
                </div>
                @if($session->meeting_link)
                    <a href="{{ $session->meeting_link }}" target="_blank" rel="noopener" class="ml-1 inline-flex items-center gap-1 text-[10.5px] font-semibold text-indigo-700 hover:underline">
                        <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                        Join meeting
                    </a>
                @endif
            @endif
            <div class="mt-2 flex items-center gap-2 text-[11px] text-indigo-700">
                <i class="fas fa-clock"></i>
                <span>Session ends in <span class="font-mono font-bold tabular-nums" data-online-countdown>--:--</span></span>
            </div>
        </div>

        <div id="online-feedback" class="hidden m-4 mb-0 rounded-lg border px-3 py-2 text-[12px]" role="status" aria-live="polite"></div>

        @if($alreadyMarked)
            <div class="p-4 text-center space-y-2">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-700">
                    <i class="fas fa-check text-xl"></i>
                </div>
                <p class="text-sm font-semibold text-gray-900">You're already marked present.</p>
                <p class="text-[11px] text-gray-500">Nothing more to do — close this tab when you're done with the lecture.</p>
            </div>
        @else
            <form id="online-code-form" class="p-4 space-y-3">
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                    <i class="fas fa-keyboard text-indigo-600 text-[13px]"></i>
                    Enter the attendance code
                </h2>
                <p class="text-[11px] text-gray-500 -mt-1">
                    Your rep is sharing a {{ $codeLength }}-digit code in the meeting chat. The code rotates every couple of minutes, so type whichever one is currently active.
                </p>
                <input type="text"
                       id="online-code-input"
                       name="code"
                       required
                       maxlength="{{ $codeLength }}"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       autocomplete="one-time-code"
                       placeholder="{{ str_repeat('•', $codeLength) }}"
                       class="w-full text-2xl text-center font-mono tracking-[0.5em] border border-gray-200 rounded-lg px-3 py-4 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
                <button type="submit"
                        id="online-code-submit"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-indigo-800 disabled:opacity-60 disabled:cursor-not-allowed">
                    <i class="fas fa-check"></i>
                    <span data-submit-label>Submit code</span>
                </button>
            </form>
        @endif

        <div class="p-3 border-t border-gray-100 bg-gray-50/60 text-center">
            <a href="{{ route('dashboard.dashboard') }}" class="text-[12px] text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left text-[10px]"></i> Back to dashboard
            </a>
        </div>
    </div>
</div>

{{--
    FingerprintJS v4 (open source) computes a stable visitor id without
    sending any data off-device. The library is BSD-licensed and small
    enough to load from the maintainer's CDN; failure to load is
    handled gracefully (the form falls back to submitting without a
    fingerprint, attendance still goes through). PART 8.
--}}
<script>
    window.fpPromise = import('https://openfpcdn.io/fingerprintjs/v4')
        .then(FingerprintJS => FingerprintJS.load())
        .catch(() => null);
</script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const expiresAtIso = @json(optional($expiresAt)->toIso8601String());
    const successUrl = @json(route('web.attendance.success', ['course' => $course->id]));
    const codeUrl = @json(route('web.attendance.online.code', ['course' => $course->id]));

    const feedback = document.getElementById('online-feedback');

    function notify(kind, message) {
        if (!feedback) return;
        feedback.className = 'm-4 mb-0 rounded-lg border px-3 py-2 text-[12px]';
        const tone = kind === 'error'
            ? ' border-rose-200 bg-rose-50 text-rose-800'
            : kind === 'success'
                ? ' border-emerald-200 bg-emerald-50 text-emerald-800'
                : ' border-indigo-200 bg-indigo-50 text-indigo-800';
        feedback.className += tone;
        feedback.textContent = message;
        feedback.classList.remove('hidden');
    }

    /* Session-end countdown — flips to "ended" when the deadline passes.
       The backend rejects late submissions anyway; this is just a hint. */
    const countdownEl = document.querySelector('[data-online-countdown]');
    if (expiresAtIso && countdownEl) {
        const deadline = new Date(expiresAtIso).getTime();
        function tick() {
            const ms = deadline - Date.now();
            if (ms <= 0) {
                countdownEl.textContent = 'ended';
                countdownEl.classList.add('text-rose-700');
                notify('error', 'This online session has ended. Ask your rep to reopen it if you still need to mark.');
                return;
            }
            const total = Math.floor(ms / 1000);
            const m = String(Math.floor(total / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            countdownEl.textContent = m + ':' + s;
            setTimeout(tick, 1000);
        }
        tick();
    }

    /* Per-device telemetry assembled at submit time. FingerprintJS is
       the only piece we treat as authoritative — everything else is
       pulled from window.navigator and may be missing on stripped
       browsers (still fine, the server scores defensively). */
    async function collectClientInfo() {
        let fingerprintHash = null;
        try {
            const fp = await window.fpPromise;
            if (fp) {
                const r = await fp.get();
                fingerprintHash = r && r.visitorId ? String(r.visitorId).slice(0, 64) : null;
            }
        } catch (_) {
            fingerprintHash = null;
        }

        const ua = navigator.userAgent || '';
        const browser = (function () {
            if (ua.indexOf('Edg/') !== -1) return 'Edge';
            if (ua.indexOf('Chrome/') !== -1) return 'Chrome';
            if (ua.indexOf('Safari/') !== -1 && ua.indexOf('Chrome/') === -1) return 'Safari';
            if (ua.indexOf('Firefox/') !== -1) return 'Firefox';
            return 'Unknown';
        })();
        const os = (function () {
            if (/Windows/.test(ua)) return 'Windows';
            if (/Android/.test(ua)) return 'Android';
            if (/iPhone|iPad|iPod/.test(ua)) return 'iOS';
            if (/Macintosh|Mac OS X/.test(ua)) return 'macOS';
            if (/Linux/.test(ua)) return 'Linux';
            return 'Unknown';
        })();

        return {
            fingerprint_hash:  fingerprintHash,
            platform:          navigator.platform || null,
            browser:           browser,
            operating_system:  os,
            screen_resolution: (window.screen && window.screen.width && window.screen.height)
                ? (window.screen.width + 'x' + window.screen.height)
                : null,
            timezone: (Intl && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone : null,
            language: (navigator.languages && navigator.languages[0]) || navigator.language || null,
            device_memory: typeof navigator.deviceMemory === 'number' ? navigator.deviceMemory : null,
            cpu_cores: typeof navigator.hardwareConcurrency === 'number' ? navigator.hardwareConcurrency : null,
            touch_support: 'ontouchstart' in window || (navigator.maxTouchPoints || 0) > 0,
        };
    }

    async function postJson(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        });
        const data = await r.json().catch(() => ({}));
        return { ok: r.ok, status: r.status, data };
    }

    /* Submit handler — collects telemetry, posts code + client block,
       and either redirects on success or shows an inline error. */
    const codeForm = document.getElementById('online-code-form');
    if (codeForm) {
        codeForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const codeInput = document.getElementById('online-code-input');
            const submitBtn = document.getElementById('online-code-submit');
            const submitLabel = submitBtn ? submitBtn.querySelector('[data-submit-label]') : null;

            const code = (codeInput.value || '').trim();
            if (code.length === 0) {
                notify('error', 'Enter the current attendance code.');
                return;
            }

            if (submitBtn) submitBtn.disabled = true;
            if (submitLabel) submitLabel.textContent = 'Submitting…';

            try {
                const client = await collectClientInfo();
                const r = await postJson(codeUrl, { code: code, client: client });
                if (r.ok && r.data && r.data.ok) {
                    notify('success', r.data.message || 'Marked present.');
                    setTimeout(() => { window.location.href = r.data.redirect || successUrl; }, 600);
                } else {
                    notify('error', (r.data && r.data.message) || 'Could not submit. Check the code and try again.');
                    if (submitBtn) submitBtn.disabled = false;
                    if (submitLabel) submitLabel.textContent = 'Submit code';
                }
            } catch (err) {
                notify('error', 'Network error — please try again.');
                if (submitBtn) submitBtn.disabled = false;
                if (submitLabel) submitLabel.textContent = 'Submit code';
            }
        });
    }
})();
</script>
@endsection
