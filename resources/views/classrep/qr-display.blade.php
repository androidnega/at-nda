@extends('layouts.classrep')

@section('title', 'Session QR — ' . $session->course->course_name)

@push('head')
    <meta name="session-live-id" content="{{ $session->id }}">
@endpush

@section('content')
@php
    $mode = $session->mode ?? 'location';
    $modeLabel = match ($mode) {
        'qr' => 'QR only',
        'hybrid' => 'Hybrid',
        'location' => 'Location',
        default => strtoupper($mode),
    };
    $modeHint = match ($mode) {
        'qr' => 'Students scan this code with the app or web — no QR rotation.',
        'hybrid' => 'Students confirm location, then scan this code.',
        'location' => 'Students must be in range, then mark on the web.',
        default => 'Share this screen with the class.',
    };
@endphp
<div class="min-h-[calc(100vh-6rem)] px-4 py-6 sm:py-8">
    <div class="max-w-6xl mx-auto w-full">
        {{-- Two sections: QR (left) | Session info (right); stacks on small screens --}}
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8 lg:gap-10 xl:gap-14">
            {{-- Left: QR --}}
            <div class="w-full lg:w-[min(22rem,42%)] xl:w-[min(24rem,40%)] shrink-0 flex flex-col items-center lg:items-stretch">
                <p class="lg:hidden text-[11px] font-semibold uppercase tracking-[0.2em] text-primary/80 text-center mb-3">Live session · QR</p>
                <div class="relative w-full max-w-sm lg:max-w-none mx-auto">
                    <div class="relative rounded-xl bg-white p-5 sm:p-7 border border-slate-200">
                        <div class="flex justify-center">
                            <div class="rounded-lg bg-white p-2.5 sm:p-3 border border-slate-200">
                                <img id="qr-rotating-image" src="{{ $qrUrl }}" alt="Session QR code" width="288" height="288" class="w-full max-w-[min(16rem,70vw)] sm:max-w-[18rem] aspect-square object-contain select-none mx-auto" draggable="false">
                            </div>
                        </div>
                        <p class="mt-3 text-center text-[10px] uppercase tracking-wider text-emerald-700/70 font-semibold">
                            <i class="fas fa-arrows-rotate text-emerald-600/70 mr-1"></i>
                            Rotates every <span id="qr-rotate-window">{{ (int) ($qrRotateSeconds ?? 8) }}</span>s — each student sees a different code
                        </p>
                        @if($session->session_code)
                        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-center">
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Session code (manual entry)</span>
                            <span class="font-mono text-lg font-bold tracking-tight text-emerald-900">{{ $session->session_code }}</span>
                            <p class="mt-1.5 text-[10px] text-slate-500 leading-snug">
                                This code is fixed for the whole session — read it out for any student who can&rsquo;t scan.
                                Only the QR rotates (anti-screenshot).
                            </p>
                        </div>
                        @endif
                        <p class="mt-5 text-center text-xs text-slate-400 leading-relaxed">
                            Scan from another device when possible · <span class="text-slate-600 font-medium">a-tenda</span> app or web check-in
                        </p>
                        <div class="mt-5 flex justify-center">
                            <a href="{{ route('dashboard.live-sessions.qr-download', $session) }}"
                               class="inline-flex items-center gap-2 rounded-lg bg-slate-900 text-white px-5 py-3 text-sm font-semibold hover:bg-slate-800">
                                <i class="fas fa-download" aria-hidden="true"></i>
                                Download PNG (print)
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: title, badges, stats, actions (all in vertical rows of content) --}}
            <div class="flex-1 min-w-0 flex flex-col gap-6 sm:gap-8">
                <header class="text-center lg:text-left">
                    <p class="hidden lg:block text-[11px] font-semibold uppercase tracking-[0.2em] text-primary/80 mb-2">Live session</p>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight leading-tight">{{ $session->course->course_name }}</h1>
                    @if($session->course->course_code)
                        <p class="mt-2 text-sm text-slate-500 font-mono">{{ $session->course->course_code }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center justify-center lg:justify-start gap-2">
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary ring-1 ring-primary/20">{{ $modeLabel }}</span>
                        @if(optional($session->attendanceWeek)->week_number)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Week {{ $session->attendanceWeek->week_number }}</span>
                        @endif
                    </div>
                    <p class="mt-4 text-sm text-slate-500 max-w-xl lg:mx-0 mx-auto leading-relaxed">{{ $modeHint }}</p>
                </header>

                {{-- One row: two stat tiles --}}
                <div class="grid grid-cols-2 gap-3 min-w-0 max-w-xl lg:max-w-none">
                    <div class="rounded-xl bg-emerald-50/80 border border-emerald-200 px-3 py-4 sm:px-5 sm:py-5 text-center min-w-0">
                        <p class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-emerald-800/70 leading-tight">Students checked in</p>
                        <p class="mt-1 text-3xl sm:text-4xl font-bold tabular-nums text-emerald-950 tracking-tight" id="qr-scanned-count">{{ (int) ($scannedCount ?? 0) }}</p>
                        <p class="text-[11px] sm:text-xs text-emerald-700/80 mt-0.5">students</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-4 sm:px-5 sm:py-5 flex flex-col justify-center text-center min-w-0">
                        <p class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wider text-slate-500 leading-tight">Session ends</p>
                        <p class="mt-1 text-base sm:text-lg font-semibold text-slate-800 tabular-nums leading-tight">{{ $session->expires_at?->format('M j') ?? '—' }}</p>
                        <p class="text-xs sm:text-sm text-slate-500">{{ $session->expires_at?->format('g:i A') ?? 'No expiry set' }}</p>
                    </div>
                </div>

                {{-- One row: note + back --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-1 max-w-xl lg:max-w-none">
                    <p class="text-xs text-slate-400 text-center lg:text-left order-2 sm:order-1">
                        Present count updates live (WebSocket). Ensure Reverb is running.
                    </p>
                    <div class="flex justify-center lg:justify-end order-1 sm:order-2">
                        <a href="{{ route('dashboard.session') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 whitespace-nowrap">
                            <span class="text-slate-400" aria-hidden="true">←</span>
                            Back to session
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function() {
    var statsUrl = @json(route('dashboard.live-sessions.qr-stats', $session));
    var payloadUrl = @json(route('dashboard.live-sessions.qr-payload', $session));
    var el = document.getElementById('qr-scanned-count');
    var qrImg = document.getElementById('qr-rotating-image');
    var rotateWindowEl = document.getElementById('qr-rotate-window');
    var rotateSeconds = parseInt({{ (int) ($qrRotateSeconds ?? 8) }}, 10);
    if (!rotateSeconds || rotateSeconds < 5) rotateSeconds = 8;

    function refreshStats() {
        if (!el) return;
        fetch(statsUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (typeof d.scanned_count === 'number') {
                    el.textContent = String(d.scanned_count);
                }
            })
            .catch(function() {});
    }
    setInterval(refreshStats, 15000);

    // Rotate the QR image every (TTL - 2)s so each student gets a fresh
    // signed token. Screenshots stop validating once the previous token
    // expires.
    function refreshQr() {
        if (!qrImg) return;
        fetch(payloadUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(function(r) { if (!r.ok) throw new Error('http_'+r.status); return r.json(); })
            .then(function(d) {
                if (d && typeof d.image_url === 'string') {
                    qrImg.src = d.image_url;
                }
                if (rotateWindowEl && typeof d.rotates_in_seconds === 'number') {
                    rotateWindowEl.textContent = String(d.rotates_in_seconds);
                }
            })
            .catch(function() { /* keep last image on failure */ });
    }
    var iv = Math.max(5, rotateSeconds) * 1000;
    setInterval(refreshQr, iv);
})();
</script>
@endpush
@endsection
