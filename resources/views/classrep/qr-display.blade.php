@extends('layouts.classrep')

@section('title', 'Session QR — ' . $session->course->course_name)

@push('head')
    <meta name="session-live-id" content="{{ $session->id }}">
@endpush

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<style>
    #session-fence-map {
        width: 100%;
        height: 100%;
        border-radius: 14px;
        z-index: 0;
    }
    #session-fence-map .leaflet-control-attribution {
        font-size: 10px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(6px);
    }
    .fence-pin-anchor {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #0f766e;
        border: 3px solid #ffffff;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.25), 0 6px 14px rgba(15, 118, 110, 0.35);
        animation: fencePinPulse 2s ease-in-out infinite;
    }
    @keyframes fencePinPulse {
        0%, 100% { box-shadow: 0 0 0 3px rgba(15,118,110,0.25), 0 6px 14px rgba(15,118,110,0.35); }
        50% { box-shadow: 0 0 0 10px rgba(15,118,110,0.10), 0 6px 14px rgba(15,118,110,0.35); }
    }
</style>
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

    // Geofence figures — only meaningful for location / hybrid sessions
    // and only when the venue actually has coordinates. The "nominal"
    // radius is what the rep configured; the "allowed" radius is what
    // the server actually checks (nominal + GPS slop) so the screen
    // matches what the backend will accept.
    $hasGeofence = in_array($mode, ['location', 'hybrid'], true)
        && $session->location_lat !== null
        && $session->location_lng !== null;
    $nominalRadiusM = $hasGeofence ? (int) $session->effectiveAttendanceRangeMeters($session->course ?? null) : 0;
    $allowedRadiusM = $hasGeofence ? (int) $session->allowedGeofenceRadiusMeters($session->course ?? null) : 0;
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
                            Rotates every <span id="qr-rotate-window">{{ (int) ($rotatingCodeWindow ?? 8) }}</span>s — each student sees a different code
                        </p>

                        {{-- Rotating manual-entry code: same window as the QR.
                             Reps read this out for students who can't scan.
                             Server validates against this window + 2 previous
                             (≈24s of validity) so the student isn't punished
                             if it rotated while they were typing. --}}
                        <div class="mt-4 rounded-xl border-2 border-emerald-300 bg-emerald-50/80 px-4 py-3 text-center">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-emerald-800/80 mb-1">Session code (rotates with QR)</span>
                            <span id="qr-rotating-code" class="font-mono text-2xl sm:text-3xl font-extrabold tracking-[0.18em] text-emerald-900 select-all">{{ $rotatingCode ?? '------' }}</span>
                            <p class="mt-1.5 text-[10px] text-emerald-700/80 leading-snug">
                                Read this out for any student who can&rsquo;t scan. Both the QR <em>and</em> this code rotate together — leaked codes stop working in seconds.
                            </p>
                        </div>

                        @if($session->session_code)
                        <p class="mt-2 text-center text-[10px] text-slate-400">
                            Backup (static): <span class="font-mono text-slate-600">{{ $session->session_code }}</span>
                        </p>
                        @endif
                        <p class="mt-5 text-center text-xs text-slate-400 leading-relaxed">
                            Scan from another device when possible · <span class="text-slate-600 font-medium">{{ config('app.name', 'at-enda') }}</span> app or web check-in
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

                @if($hasGeofence)
                    {{-- ───────── Live geofence preview ─────────
                         Shows the venue centre + the radius the backend
                         enforces. Two concentric circles:
                           • Solid teal  → nominal radius (what the rep set)
                           • Faint teal  → allowed radius (nominal + GPS
                             slop the server tolerates so honest students
                             aren't rejected for a 5-metre GPS jitter).
                         Backend already rejects out-of-range submissions
                         (see AttendanceController::*) so this is purely
                         for shared awareness during the session. --}}
                    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden max-w-xl lg:max-w-none">
                        <div class="px-4 sm:px-5 py-3 border-b border-slate-100 bg-gradient-to-r from-teal-50 via-white to-emerald-50/40 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
                                    <i class="fas fa-location-crosshairs text-sm"></i>
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Attendance zone</h2>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Marks outside this radius are rejected automatically</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider">
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                                    <span class="inline-block w-2 h-2 rounded-full bg-teal-600"></span> Nominal · {{ $nominalRadiusM }}m
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-50 text-slate-600 ring-1 ring-slate-100">
                                    <span class="inline-block w-2 h-2 rounded-full bg-slate-400"></span> GPS buffer · {{ $allowedRadiusM }}m
                                </span>
                            </div>
                        </div>
                        <div class="relative h-[260px] sm:h-[300px] bg-slate-50">
                            <div id="session-fence-map"></div>
                        </div>
                        <div class="px-4 sm:px-5 py-2.5 text-[11px] text-slate-500 border-t border-slate-100">
                            <i class="fas fa-circle-info mr-1 text-slate-400"></i>
                            Centre: <span class="font-mono text-slate-700">{{ number_format((float) $session->location_lat, 5) }}, {{ number_format((float) $session->location_lng, 5) }}</span>
                            · The server enforces the GPS-buffer radius so honest students with mild GPS drift still mark.
                        </div>
                    </div>
                @endif
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
    var rotatingCodeEl = document.getElementById('qr-rotating-code');
    var rotateWindowEl = document.getElementById('qr-rotate-window');
    // Poll cadence. Defaulted server-side from
    // SecureQrToken::ROTATION_WINDOW_SECONDS / 2 so the screen catches a
    // new rotation within one window. Was 18s before — felt frozen.
    var pollSeconds = parseInt({{ (int) ($qrRotateSeconds ?? 4) }}, 10);
    if (!pollSeconds || pollSeconds < 2) pollSeconds = 4;

    function refreshStats() {
        if (!el) return;
        fetch(statsUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (typeof d.scanned_count === 'number') {
                    var prev = parseInt(el.textContent || '0', 10) || 0;
                    el.textContent = String(d.scanned_count);
                    // A scan just landed — refresh the QR + manual code
                    // immediately so the next student can't reuse what
                    // the previous student just submitted. Cheap: one
                    // extra payload poll, server is already optimised.
                    if (d.scanned_count > prev) {
                        refreshQr();
                    }
                }
            })
            .catch(function() {});
    }
    // Faster stats poll → faster "rotate on scan" reactivity.
    setInterval(refreshStats, 4000);

    // Refresh the QR image + rotating manual-entry code on every poll.
    // Each server call returns a freshly signed token AND the current
    // rotating short code, so the visible code matches whatever the
    // server will accept right now.
    function refreshQr() {
        fetch(payloadUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(function(r) { if (!r.ok) throw new Error('http_'+r.status); return r.json(); })
            .then(function(d) {
                if (d && typeof d.image_url === 'string' && qrImg) {
                    qrImg.src = d.image_url;
                }
                if (rotatingCodeEl && typeof d.rotating_code === 'string') {
                    rotatingCodeEl.textContent = d.rotating_code;
                }
                if (rotateWindowEl && typeof d.rotating_code_window_seconds === 'number') {
                    rotateWindowEl.textContent = String(d.rotating_code_window_seconds);
                }
            })
            .catch(function() { /* keep last image on failure */ });
    }
    setInterval(refreshQr, Math.max(2, pollSeconds) * 1000);
})();
</script>

@if($hasGeofence)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script>
(function () {
    var el = document.getElementById('session-fence-map');
    if (!el || typeof L === 'undefined') return;

    var lat = {{ (float) $session->location_lat }};
    var lng = {{ (float) $session->location_lng }};
    var nominalRadius = {{ $nominalRadiusM }};
    var allowedRadius = {{ $allowedRadiusM }};

    var map = L.map(el, {
        zoomControl: true,
        scrollWheelZoom: false,
        attributionControl: true,
    }).setView([lat, lng], 18);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
        maxZoom: 19,
        subdomains: 'abcd',
    }).addTo(map);

    // Outer (server-tolerated) circle drawn first so the inner one sits
    // on top — gives a clean two-tone zone preview.
    L.circle([lat, lng], {
        radius: allowedRadius,
        color: '#94a3b8',
        weight: 1,
        opacity: 0.55,
        dashArray: '4 6',
        fillColor: '#94a3b8',
        fillOpacity: 0.06,
    }).addTo(map);

    L.circle([lat, lng], {
        radius: nominalRadius,
        color: '#0f766e',
        weight: 2,
        opacity: 0.9,
        fillColor: '#14b8a6',
        fillOpacity: 0.12,
    }).addTo(map);

    L.marker([lat, lng], {
        icon: L.divIcon({
            html: '<div class="fence-pin-anchor"></div>',
            className: '',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        }),
    }).bindTooltip('Session venue', { permanent: false, direction: 'top', offset: [0, -8] }).addTo(map);

    // Fit so both circles are comfortably visible.
    var bounds = L.latLng(lat, lng).toBounds(allowedRadius * 2.4);
    map.fitBounds(bounds);
})();
</script>
@endif
@endpush
@endsection
