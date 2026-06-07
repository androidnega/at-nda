@extends('layouts.classrep')

@section('title', 'Open session')

@section('content')
@php
    $fieldBase = 'w-full rounded-md border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none';
    $labelBase = 'block text-xs font-medium text-slate-600 mb-1';
@endphp

<style>
    @keyframes floatInUp {
        0% { opacity: 0; transform: translateY(8px) scale(0.99); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    .float-in-up {
        animation: floatInUp 420ms cubic-bezier(.2,.8,.2,1) both;
    }

    .rep-glass {
        background: linear-gradient(160deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #e5edf8;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    }
</style>

<div class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div class="order-2 sm:order-1">
        <h1 class="text-2xl sm:text-[1.65rem] font-bold text-slate-900 tracking-tight">Open attendance session</h1>
        <p class="text-slate-500 text-sm mt-1 max-w-xl leading-relaxed">Start a session so students can mark attendance. <span class="text-slate-600">Location</span> and <span class="text-slate-600">hybrid</span> need a map anchor; <span class="text-slate-600">QR</span> does not use GPS.</p>
    </div>
    <div class="hidden sm:flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20">
        <i class="fas fa-broadcast-tower text-lg"></i>
    </div>
</div>

@if (session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-emerald-900">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600"><i class="fas fa-check text-sm"></i></span>
        <p class="text-sm font-medium leading-snug pt-1">{{ session('success') }}</p>
    </div>
@endif
@if (session('error'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-red-900">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600"><i class="fas fa-circle-exclamation text-sm"></i></span>
        <p class="text-sm font-medium leading-snug pt-1">{{ session('error') }}</p>
    </div>
@endif

@php $coursesWithOpen = $courses->filter(fn($c) => $c->canOpenSession); @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @if($coursesWithOpen->isNotEmpty())
    <div>
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="px-4 py-3 border-b border-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Open session</h2>
                <p class="text-xs text-slate-500 mt-0.5">Main rep only. Session closes at timetable end time.</p>
            </div>
            <form action="{{ route('dashboard.live-sessions.store') }}" method="POST" id="open-session-form" class="p-4 space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label for="course_id" class="{{ $labelBase }}">Course</label>
                    <div class="relative">
                        <select name="course_id" id="course_id" required class="{{ $fieldBase }} appearance-none pr-8 cursor-pointer">
                            <option value="">Choose a course…</option>
                            @foreach($coursesWithOpen as $item)
                            @php $c = $item->course; @endphp
                            <option
                                value="{{ $c->id }}"
                                data-default-lat="{{ $c->location_lat !== null ? e($c->location_lat) : '' }}"
                                data-default-lng="{{ $c->location_lng !== null ? e($c->location_lng) : '' }}"
                                data-default-range="{{ $c->attendance_range_m !== null ? e($c->attendance_range_m) : '' }}"
                                {{ old('course_id') == $c->id ? 'selected' : '' }}
                            >
                                {{ $c->course_name }}{{ $c->course_code ? ' (' . $c->course_code . ')' : '' }}
                                {{ $c->schoolClass?->faculty?->university?->name ? ' — ' . $c->schoolClass?->faculty?->university?->name : '' }}
                                — {{ $c->getScheduleLabel() }}
                            </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="mode" class="{{ $labelBase }}">Attendance mode</label>
                        <div class="relative">
                            @php
                                $forcedMode = $attendanceMode === 'checkin_checkout'
                                    ? 'location'
                                    : ($instantModeType === 'wifi' ? 'wifi' : ($instantModeType === 'location' ? 'location' : 'hybrid'));
                                $forcedModeLabel = $forcedMode === 'location'
                                    ? 'Location (GPS)'
                                    : ($forcedMode === 'wifi' ? 'Wi‑Fi (same network)' : 'Hybrid (GPS + QR)');
                            @endphp
                            <select name="mode" id="session-mode" class="{{ $fieldBase }} appearance-none pr-8 cursor-pointer" disabled>
                                <option value="{{ $forcedMode }}" selected>{{ $forcedModeLabel }}</option>
                            </select>
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                        </div>
                        <input type="hidden" name="mode" value="{{ $forcedMode }}">
                        <p class="text-[11px] text-slate-400 mt-1">
                            @if($attendanceMode === 'checkin_checkout')
                                Check-in / Check-out is active: this is location-only.
                            @else
                                Instant mode is locked by admin settings.
                            @endif
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <label for="duration_minutes" class="{{ $labelBase }}">Duration</label>
                        <div class="relative">
                            <input type="number" name="duration_minutes" id="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="5" max="480" required inputmode="numeric"
                                class="{{ $fieldBase }} tabular-nums">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Minutes (5–480)</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label for="week_number" class="{{ $labelBase }}">Week number <span class="text-slate-400 font-normal">(optional)</span></label>
                        <div class="relative">
                            <input type="number" name="week_number" id="week_number" value="{{ old('week_number') }}" min="1" max="500" inputmode="numeric" placeholder="Auto"
                                class="{{ $fieldBase }} tabular-nums">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Blank = auto-number. Pin to a specific week if needed.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label for="venue_id" class="{{ $labelBase }}">Venue <span class="text-slate-400 font-normal">(optional)</span></label>
                        <div class="relative">
                            <select name="venue_id" id="venue_id" class="{{ $fieldBase }} appearance-none pr-8 cursor-pointer">
                                <option value="">Use timetable default</option>
                                @foreach(($venues ?? collect()) as $v)
                                    <option value="{{ $v->id }}" {{ (string) old('venue_id') === (string) $v->id ? 'selected' : '' }}>
                                        {{ $v->name }}@if(!empty($v->code)) — {{ $v->code }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Overrides the day's default venue for this session only.</p>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label for="lecturer_status" class="{{ $labelBase }}">Lecturer status</label>
                    <div class="relative">
                        <select name="lecturer_status" id="lecturer_status" class="{{ $fieldBase }} appearance-none pr-8 cursor-pointer" required>
                            <option value="present" {{ old('lecturer_status', 'present') === 'present' ? 'selected' : '' }}>Lecturer present</option>
                            <option value="absent" {{ old('lecturer_status') === 'absent' ? 'selected' : '' }}>Lecturer absent</option>
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                    </div>
                    <p class="text-[11px] text-slate-400">Shown on attendance list and PDF.</p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3 space-y-3" id="session-location-section">
                    <p class="text-sm font-semibold text-slate-800">Location (for GPS/Hybrid)</p>
                    <p class="text-xs text-slate-500">Choose GPS, course location, or enter coordinates.</p>

                    <div id="location-status" class="text-xs text-slate-600 min-h-[1.1rem]" role="status" aria-live="polite">Choose a course, then set a location.</div>
                    <p id="location-error" class="hidden text-xs text-red-700" role="alert"></p>

                    {{-- GPS diagnostics expander — hidden until the
                         cascade has actually run. Surfaces the exact
                         reason GPS failed so the rep can either fix
                         it themselves or report something concrete.
                         "Copy diagnostics" puts the JSON on the
                         clipboard so a support chat can read it. --}}
                    <details id="gps-diag-wrap" class="hidden text-[11px] text-slate-600 bg-slate-50 border border-slate-200 rounded-md px-2.5 py-1.5">
                        <summary class="cursor-pointer font-semibold text-slate-700 select-none flex items-center justify-between gap-2">
                            <span><i class="fas fa-stethoscope text-slate-500 mr-1"></i> GPS diagnostics</span>
                            <button type="button" id="gps-diag-copy" class="text-[10px] uppercase tracking-wider font-bold text-primary hover:underline">Copy</button>
                        </summary>
                        <pre id="gps-diag-body" class="mt-1.5 whitespace-pre-wrap font-mono text-[10.5px] leading-snug text-slate-700"></pre>
                    </details>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="get-location-btn" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Use device GPS
                        </button>
                        <button type="button" id="use-course-location-btn" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none" disabled title="Select a course that has saved coordinates">
                            Use course location
                        </button>
                    </div>

                    <div id="location-display" class="hidden text-xs text-slate-700 font-mono rounded-md border border-slate-200 bg-white px-2.5 py-2"></div>

                    <div id="manual-coords-wrap" class="rounded-md p-1 -m-1 transition-shadow">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <label for="manual_lat" class="text-[11px] font-medium text-slate-500">Latitude</label>
                                <input type="text" inputmode="decimal" id="manual_lat" placeholder="e.g. 5.6037" autocomplete="off"
                                    class="{{ $fieldBase }} font-mono text-sm">
                            </div>
                            <div class="space-y-1">
                                <label for="manual_lng" class="text-[11px] font-medium text-slate-500">Longitude</label>
                                <input type="text" inputmode="decimal" id="manual_lng" placeholder="e.g. −0.1870" autocomplete="off"
                                    class="{{ $fieldBase }} font-mono text-sm">
                            </div>
                        </div>
                        <button type="button" id="use-manual-btn" class="mt-1 text-xs font-semibold text-primary hover:text-primary/80">Apply coordinates</button>
                    </div>

                    <div class="space-y-1.5">
                        <label for="attendance_range_m" class="{{ $labelBase }}">Radius (meters)</label>
                        <input type="number" name="attendance_range_m" id="attendance_range_m" value="{{ old('attendance_range_m', 100) }}" min="10" max="500"
                            class="{{ $fieldBase }} tabular-nums">
                        <p class="text-[11px] text-slate-400">Students must be within this distance.</p>
                    </div>
                    <input type="hidden" name="location_lat" id="location_lat" value="{{ old('location_lat') }}">
                    <input type="hidden" name="location_lng" id="location_lng" value="{{ old('location_lng') }}">

                    {{-- Live geofence preview. Only visible once the rep
                         has picked a location (device GPS, course default,
                         or manual coords). Reacts to every radius / coord
                         change so the rep can see exactly where students
                         will need to stand. --}}
                    <div id="fence-preview-wrap" class="hidden">
                        <div class="mt-2 rounded-md border border-slate-200 bg-slate-50/40 overflow-hidden">
                            <div class="px-3 py-2 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 bg-white">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 inline-flex items-center gap-1.5">
                                    <i class="fas fa-location-crosshairs text-teal-600 text-xs"></i>
                                    Attendance zone preview
                                </p>
                                <p id="fence-preview-meta" class="text-[10px] font-mono text-slate-500"></p>
                            </div>
                            <div class="relative h-[220px] sm:h-[260px]">
                                <div id="fence-preview-map" style="position:absolute;inset:0;border-radius:0;"></div>
                            </div>
                            <p class="px-3 py-2 text-[10px] text-slate-500 border-t border-slate-100">
                                Solid teal = your radius · faint outer ring = the GPS buffer the server tolerates. Marks outside the buffer are rejected.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="hidden border border-slate-200 rounded-lg p-3 space-y-2" id="session-wifi-section" aria-hidden="true">
                    <p class="text-sm font-semibold text-slate-800">Wi‑Fi SSID</p>
                    <label for="allowed_wifi_ssid" class="{{ $labelBase }}">Expected network</label>
                    <input type="text" name="allowed_wifi_ssid" id="allowed_wifi_ssid" value="{{ old('allowed_wifi_ssid') }}" maxlength="128" placeholder="e.g. NDA-Campus"
                        class="{{ $fieldBase }}">
                </div>
                <div class="pt-1 flex flex-col sm:flex-row sm:items-center gap-3 border-t border-slate-200 pt-4">
                    <button type="submit" id="open-session-btn" class="inline-flex w-full sm:w-auto min-w-[200px] items-center justify-center gap-2 rounded-md bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary/90">
                        Open session
                    </button>
                    <p class="text-xs text-slate-400 text-center sm:text-left">You can adjust mode and location before students mark.</p>
                </div>
            </form>
        </div>
    </div>
    @else
    <div></div>
    @endif
    <div class="order-1 sm:order-2 lg:sticky lg:top-6 self-start">
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-500/30"></span>
                    <h2 class="text-sm font-semibold text-slate-900">Live attendance</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">Countdown until session closes.</p>
            </div>
            <div class="p-3 space-y-3 max-h-[min(70vh,32rem)] overflow-y-auto">
                @php $hasActive = false; @endphp
                @foreach($courses as $item)
                    @php $course = $item->course; $activeSession = $item->active_session ?? null; @endphp
                    @if($activeSession)
                        @php $hasActive = true; $expiresIso = $activeSession->expires_at?->toIso8601String(); @endphp
                        <div
                            class="rounded-md border border-slate-200 p-3 float-in-up"
                            data-session-countdown
                            data-expires="{{ $expiresIso }}"
                            @if(isset($loop))
                                style="animation-delay: {{ $loop->index * 0.06 }}s"
                            @endif
                        >
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900 text-sm leading-snug line-clamp-2">{{ $course->course_name }}</p>
                                    @php
                                        $uniName = $course->schoolClass?->faculty?->university?->name;
                                    @endphp
                                    @if($uniName)
                                        <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ $uniName }}</p>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                            Week {{ $activeSession->attendanceWeek?->week_number ?? '—' }}
                                        </span>
                                        <span class="inline-flex items-center rounded-md border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">
                                            {{ $activeSession->mode }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-md border border-slate-200 p-3 mb-3 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500 mb-1" data-countdown-label>Time remaining</p>
                                <p class="text-2xl sm:text-3xl font-mono font-bold tabular-nums tracking-tight text-slate-900" data-countdown-display>—:—</p>
                                @if($activeSession->expires_at)
                                    <p class="text-[10px] text-slate-500 mt-2">Until {{ $activeSession->expires_at->format('g:i A') }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if(in_array($activeSession->mode, ['qr', 'hybrid']))
                                    <a href="{{ route('dashboard.live-sessions.qr', $activeSession) }}" target="_blank" class="flex-1 min-w-[4rem] inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-md text-[11px] font-semibold bg-primary text-white hover:bg-primary/90">
                                        <i class="fas fa-qrcode text-[10px]"></i> QR
                                    </a>
                                @endif
                                <a href="{{ route('web.attendance.form', $course) }}" target="_blank" class="flex-1 min-w-[4rem] inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-md text-[11px] font-semibold border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">
                                    <i class="fas fa-clipboard-check text-[10px]"></i> Form
                                </a>
                                @if($item->canOpenSession)
                                {{-- Extend: bumps expires_at / end_time by 15 minutes (server clamps to a sane max).
                                     Repeat the click for longer extensions; an audit log line is written each time. --}}
                                <form action="{{ route('dashboard.live-sessions.extend', $activeSession) }}" method="POST" class="flex-1 min-w-[5rem] sm:flex-none">
                                    @csrf
                                    <input type="hidden" name="minutes" value="15">
                                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-md text-[11px] font-semibold border border-amber-200 bg-white text-amber-700 hover:bg-amber-50">
                                        <i class="fas fa-clock text-[10px]"></i> +15 min
                                    </button>
                                </form>
                                <form action="{{ route('dashboard.live-sessions.close', $activeSession) }}" method="POST" class="flex-1 min-w-full sm:min-w-0 sm:flex-none" onsubmit="return confirm('Close this session?');">
                                    @csrf
                                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-md text-[11px] font-semibold border border-rose-200/90 bg-white text-rose-600 hover:bg-rose-50">
                                        <i class="fas fa-stop text-[10px]"></i> Close
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
                @if(!$hasActive)
                    <div class="text-center py-8 px-3 rounded-md border border-dashed border-slate-200">
                        <p class="text-sm font-semibold text-slate-700">No session open</p>
                        <p class="text-xs text-slate-500 mt-1">Use the form to start one.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($coursesWithOpen->isNotEmpty())
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<style>
    #fence-preview-map { width: 100%; height: 100%; }
    #fence-preview-map .leaflet-control-attribution { font-size: 9px; }
    .fence-pin-anchor-mini { width: 16px; height: 16px; border-radius: 50%; background: #0f766e; border: 2.5px solid #ffffff;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.22), 0 4px 10px rgba(15, 118, 110, 0.35); }
</style>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script>
// Geofence preview: a small Leaflet map that shows the venue anchor +
// the configured radius. Decoupled from the form's existing GPS logic
// by polling the hidden inputs every ~700ms — cheap and keeps this
// block independent of the rest of the script.
(function () {
    var GPS_BUFFER_M = {{ (int) config('app.geofence_gps_buffer_m', 50) }};
    var MIN_CHECK_M  = {{ (int) config('app.min_geofence_check_m', 150) }};

    var wrap = document.getElementById('fence-preview-wrap');
    var mapEl = document.getElementById('fence-preview-map');
    var meta = document.getElementById('fence-preview-meta');
    if (!wrap || !mapEl) return;

    var latHidden = document.getElementById('location_lat');
    var lngHidden = document.getElementById('location_lng');
    var radiusInput = document.getElementById('attendance_range_m');
    if (!latHidden || !lngHidden) return;

    var map = null;
    var anchorMarker = null;
    var innerCircle = null;
    var outerCircle = null;
    var lastLat = null;
    var lastLng = null;
    var lastRadius = null;

    function ensureMap(lat, lng) {
        if (map) return;
        wrap.classList.remove('hidden');
        map = L.map(mapEl, {
            zoomControl: true,
            scrollWheelZoom: false,
            attributionControl: true,
        }).setView([lat, lng], 18);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            maxZoom: 19,
            subdomains: 'abcd',
        }).addTo(map);
        anchorMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                html: '<div class="fence-pin-anchor-mini"></div>',
                className: '',
                iconSize: [16, 16],
                iconAnchor: [8, 8],
            }),
        }).addTo(map);
    }

    function refresh() {
        var lat = parseFloat(latHidden.value);
        var lng = parseFloat(lngHidden.value);
        var nominal = Math.max(10, parseInt(radiusInput && radiusInput.value, 10) || 100);
        if (!isFinite(lat) || !isFinite(lng) || lat === 0 && lng === 0) return;

        var allowed = Math.max(nominal, MIN_CHECK_M) + GPS_BUFFER_M;

        if (lat === lastLat && lng === lastLng && nominal === lastRadius) return;
        lastLat = lat; lastLng = lng; lastRadius = nominal;

        ensureMap(lat, lng);
        anchorMarker.setLatLng([lat, lng]);
        if (outerCircle) outerCircle.remove();
        if (innerCircle) innerCircle.remove();
        outerCircle = L.circle([lat, lng], {
            radius: allowed,
            color: '#94a3b8', weight: 1, opacity: 0.55, dashArray: '4 6',
            fillColor: '#94a3b8', fillOpacity: 0.05,
        }).addTo(map);
        innerCircle = L.circle([lat, lng], {
            radius: nominal,
            color: '#0f766e', weight: 2, opacity: 0.9,
            fillColor: '#14b8a6', fillOpacity: 0.12,
        }).addTo(map);
        if (meta) {
            meta.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5)
                + ' · ' + nominal + 'm (buffer: ' + allowed + 'm)';
        }
        var bounds = L.latLng(lat, lng).toBounds(allowed * 2.4);
        map.fitBounds(bounds);
        // Defer to next frame so Leaflet sizes correctly after the
        // hidden wrapper becomes visible for the first time.
        setTimeout(function () { map.invalidateSize(); }, 60);
    }

    setInterval(refresh, 700);
    radiusInput && radiusInput.addEventListener('input', refresh);
    refresh();
})();
</script>
<script>
(function() {
    var form = document.getElementById('open-session-form');
    var btn = document.getElementById('open-session-btn');
    var modeSelect = document.getElementById('session-mode');
    var locationSection = document.getElementById('session-location-section');
    var rangeInput = document.getElementById('attendance_range_m');
    var courseSelect = document.getElementById('course_id');
    var locationStatus = document.getElementById('location-status');
    var locationError = document.getElementById('location-error');
    var locationDisplay = document.getElementById('location-display');
    var getLocationBtn = document.getElementById('get-location-btn');
    var useCourseBtn = document.getElementById('use-course-location-btn');
    var manualLat = document.getElementById('manual_lat');
    var manualLng = document.getElementById('manual_lng');
    var useManualBtn = document.getElementById('use-manual-btn');
    var latHidden = document.getElementById('location_lat');
    var lngHidden = document.getElementById('location_lng');
    var btnDefaultHtml = btn ? btn.innerHTML : '';

    // ---- Robust GPS strategy --------------------------------------
    // The bare browser geolocation API is brittle (macOS Safari often
    // takes the full 15s timeout before returning kCLErrorLocationUnknown
    // even outdoors), so we wrap it in a cascade:
    //   1. localStorage cache from a recent rep session (instant)
    //   2. High-accuracy GPS, short 7s timeout
    //   3. Low-accuracy network/Wi-Fi, 12s timeout
    //   4. Course's saved coordinates (silent)
    //   5. Stale cache (better than nothing, marked as such)
    //   6. Calm manual-entry prompt (no scary CoreLocation jargon)
    // Cache key is per-origin; we never round/share across reps.
    var GEO_CACHE_KEY = 'atnda_rep_gps_v1';
    var GEO_CACHE_FRESH_MS = 30 * 60 * 1000; // 30 min ok without re-fix
    var GEO_CACHE_STALE_MS = 4 * 60 * 60 * 1000; // 4h still usable as last resort

    var geoOptionsHigh = { enableHighAccuracy: true, timeout: 7000, maximumAge: 30000 };
    var geoOptionsLow = { enableHighAccuracy: false, timeout: 12000, maximumAge: 5 * 60 * 1000 };

    // ---- GPS diagnostics ------------------------------------------
    // Every cascade step pushes a step record into `gpsDiagSteps` so
    // the rep can open the "GPS diagnostics" expander to see exactly
    // what happened. We also POST a summary to the server so the
    // operator can `tail -F storage/logs/laravel-*.log | grep GPS-DEBUG`
    // and read the same data on the backend.
    var GPS_DIAG_URL = @json(route('dashboard.diag.gps'));
    var GPS_DIAG_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var gpsDiagSteps = [];
    var gpsDiagWrap = document.getElementById('gps-diag-wrap');
    var gpsDiagBody = document.getElementById('gps-diag-body');
    var gpsDiagCopyBtn = document.getElementById('gps-diag-copy');

    function gpsCodeName(code) {
        return ({ 1: 'PERMISSION_DENIED', 2: 'POSITION_UNAVAILABLE', 3: 'TIMEOUT' })[code] || 'UNKNOWN';
    }

    function gpsDiagPush(entry) {
        // Stamp with elapsed ms since the cascade started so the rep
        // can see which step was slow.
        entry.t = new Date().toISOString().substring(11, 19);
        gpsDiagSteps.push(entry);
        // Log to console too so DevTools shows them inline.
        try { console.info('[GPS-DEBUG]', entry); } catch (e) {}
        renderGpsDiag();
    }

    function renderGpsDiag() {
        if (!gpsDiagWrap || !gpsDiagBody) return;
        gpsDiagWrap.classList.remove('hidden');
        var summary = {
            secure: !!window.isSecureContext,
            has_api: !!(navigator.geolocation),
            permissions_api: !!(navigator.permissions && navigator.permissions.query),
            ua: (navigator.userAgent || '').substring(0, 120),
            steps: gpsDiagSteps,
        };
        gpsDiagBody.textContent = JSON.stringify(summary, null, 2);
    }

    gpsDiagCopyBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        if (!gpsDiagBody || !navigator.clipboard) return;
        navigator.clipboard.writeText(gpsDiagBody.textContent).then(function() {
            gpsDiagCopyBtn.textContent = 'Copied';
            setTimeout(function() { gpsDiagCopyBtn.textContent = 'Copy'; }, 1600);
        }).catch(function() {});
    });

    /** Fire-and-forget telemetry beacon. Never throws, never blocks. */
    function gpsDiagSend(event, extra) {
        try {
            var payload = Object.assign({
                event: event,
                secure: !!window.isSecureContext,
                has_api: !!(navigator.geolocation),
                ua_short: (navigator.userAgent || '').substring(0, 120),
            }, extra || {});
            // Some hosts strip credentials on sendBeacon — use fetch
            // with keepalive so the rep can leave the page after.
            fetch(GPS_DIAG_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': GPS_DIAG_CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(function() {});
        } catch (e) { /* swallow — telemetry must never block */ }
    }

    function readCachedFix(allowStale) {
        try {
            var raw = localStorage.getItem(GEO_CACHE_KEY);
            if (!raw) return null;
            var d = JSON.parse(raw);
            if (!d || typeof d.lat !== 'number' || typeof d.lng !== 'number' || !d.ts) return null;
            var age = Date.now() - d.ts;
            if (age <= GEO_CACHE_FRESH_MS) return d;
            if (allowStale && age <= GEO_CACHE_STALE_MS) { d.stale = true; return d; }
            return null;
        } catch (e) { return null; }
    }
    function writeCachedFix(lat, lng, accuracy) {
        try {
            localStorage.setItem(GEO_CACHE_KEY, JSON.stringify({
                lat: lat, lng: lng, accuracy: accuracy || 0, ts: Date.now()
            }));
        } catch (e) { /* private mode / quota — fine, just no caching */ }
    }
    function minutesAgo(ts) {
        return Math.max(1, Math.round((Date.now() - ts) / 60000));
    }
    function getPositionPromise(opts, label) {
        var started = Date.now();
        return new Promise(function(resolve, reject) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                gpsDiagPush({
                    step: label || 'getCurrentPosition',
                    status: 'ok',
                    duration_ms: Date.now() - started,
                    accuracy: Math.round(pos.coords.accuracy || 0),
                });
                resolve(pos);
            }, function(err) {
                gpsDiagPush({
                    step: label || 'getCurrentPosition',
                    status: 'error',
                    code: err ? err.code : null,
                    code_name: err ? gpsCodeName(err.code) : 'UNKNOWN',
                    message: err && err.message ? err.message : null,
                    duration_ms: Date.now() - started,
                });
                reject(err);
            }, opts);
        });
    }
    function safePermissionState() {
        if (!navigator.permissions || !navigator.permissions.query) return Promise.resolve(null);
        try {
            return navigator.permissions.query({ name: 'geolocation' })
                .then(function(s) { return s.state; })
                .catch(function() { return null; });
        } catch (e) { return Promise.resolve(null); }
    }

    function clearLocationError() {
        if (locationError) {
            locationError.textContent = '';
            locationError.classList.add('hidden');
        }
    }

    function showLocationError(msg) {
        if (locationError) {
            locationError.textContent = msg;
            locationError.classList.remove('hidden');
        }
    }

    function parseCoordInput(raw) {
        if (raw == null || String(raw).trim() === '') return NaN;
        var s = String(raw).trim().replace(/,/g, '.').replace(/\u2212/g, '-');
        return parseFloat(s);
    }

    function setLocation(lat, lng, message) {
        latHidden.value = String(lat);
        lngHidden.value = String(lng);
        if (manualLat) manualLat.value = parseFloat(lat).toFixed(7);
        if (manualLng) manualLng.value = parseFloat(lng).toFixed(7);
        if (locationDisplay) {
            locationDisplay.textContent = 'Anchor: ' + parseFloat(lat).toFixed(6) + ', ' + parseFloat(lng).toFixed(6);
            locationDisplay.classList.remove('hidden');
        }
        if (locationStatus) locationStatus.textContent = message || 'Location saved. You can open the session.';
        clearLocationError();
    }

    function updateCourseLocationButton() {
        if (!useCourseBtn || !courseSelect) return;
        var opt = courseSelect.options[courseSelect.selectedIndex];
        var la = opt ? opt.getAttribute('data-default-lat') : '';
        var ln = opt ? opt.getAttribute('data-default-lng') : '';
        var ok = la !== null && la !== '' && ln !== null && ln !== '' && !isNaN(parseFloat(la)) && !isNaN(parseFloat(ln));
        useCourseBtn.disabled = !ok;
        useCourseBtn.title = ok ? 'Use coordinates saved on this course' : 'This course has no saved latitude/longitude in the system';
    }

    function tryFallbackToCourseLocation() {
        if (!courseSelect) return false;
        var opt = courseSelect.options[courseSelect.selectedIndex];
        if (!opt || !courseSelect.value) return false;
        var la = opt.getAttribute('data-default-lat');
        var ln = opt.getAttribute('data-default-lng');
        var r = opt.getAttribute('data-default-range');
        var lat = parseFloat(la);
        var lng = parseFloat(ln);
        if (isNaN(lat) || isNaN(lng)) return false;
        if (rangeInput && r !== null && r !== '' && !isNaN(parseInt(r, 10))) {
            rangeInput.value = String(parseInt(r, 10));
        }
        setLocation(lat, lng, 'GPS unavailable — using saved course location instead.');
        return true;
    }

    function geoErrorMessage(err) {
        // Only the permission-denied case actually needs the user to do
        // something. Every other code is just "we couldn't get a fix" —
        // for those we silently fall back, no error needed.
        if (err && err.code === 1) {
            return 'Location is blocked for this site. Click the lock icon in the address bar, allow location, then try again. Or pick a course with saved coordinates / paste coordinates below.';
        }
        return null; // signal: nothing to surface, the cascade handled it
    }

    /** Detect the rep's OS from the UA string. Coarse — only used to
     *  pick which "enable Location Services" instructions to show. */
    function detectPlatform() {
        var ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) return 'ios';
        if (/Android/i.test(ua)) return 'android';
        if (/Mac OS X/i.test(ua)) return 'mac';
        if (/Windows/i.test(ua)) return 'windows';
        if (/Linux/i.test(ua)) return 'linux';
        return 'other';
    }

    /** Build a human, copy-pasteable explanation of the *last* GPS
     *  failure recorded in `gpsDiagSteps`. Used when the cascade
     *  ends with no usable fix so the rep sees a real reason.
     *
     *  Key signal: when POSITION_UNAVAILABLE comes back in under
     *  200ms, the OS itself refused — the geolocation chip never
     *  even tried. That has very specific fixes (toggle Location
     *  Services / killall locationd on macOS, etc.) so we show
     *  platform-specific guidance instead of the generic message. */
    function lastGpsFailureReason() {
        if (!window.isSecureContext) {
            return 'This page is loaded over HTTP. Browsers only allow GPS on HTTPS — open the dashboard at https://' + location.host + ' and try again.';
        }
        var lastErr = null;
        for (var i = gpsDiagSteps.length - 1; i >= 0; i--) {
            if (gpsDiagSteps[i].status === 'error' && gpsDiagSteps[i].code != null) { lastErr = gpsDiagSteps[i]; break; }
        }
        if (!lastErr) {
            return 'GPS could not return a fix. Use the course location (if available) or paste lat/long from Google Maps below.';
        }
        if (lastErr.code === 1) {
            return 'Permission denied. Allow location for this site in your browser settings, then try again.';
        }
        if (lastErr.code === 3) {
            return 'GPS timed out. Move closer to a window or try outdoors. You can also pick a course with saved coordinates, or paste lat/long from Google Maps below.';
        }
        if (lastErr.code === 2) {
            // <200ms = OS refused before talking to the GPS chip.
            // ≥200ms = chip tried but had no signal (indoors etc.).
            var instant = (lastErr.duration_ms || 0) < 200;
            var plat = detectPlatform();
            if (instant && plat === 'mac') {
                return 'macOS is blocking GPS for your browser. Open  → System Settings → Privacy & Security → Location Services, turn it ON, then make sure your browser is ticked in the list. Quit and reopen the browser. If it still fails, open Terminal and run "sudo killall locationd", then refresh this page. In the meantime, pick a course with saved coordinates or paste lat/lng from Google Maps below.';
            }
            if (instant && plat === 'windows') {
                return 'Windows is blocking GPS for your browser. Open Settings → Privacy & security → Location, turn Location services ON, then allow your browser below. In the meantime, pick a course with saved coordinates or paste lat/lng from Google Maps below.';
            }
            if (instant && plat === 'linux') {
                return 'Your Linux desktop is not providing a location fix (Geoclue may not be running). Use the course location or paste lat/lng from Google Maps below to open the session.';
            }
            if (instant && (plat === 'ios' || plat === 'android')) {
                return 'Your device is blocking GPS for the browser. Open device Settings → Privacy → Location and allow the browser, then reload. In the meantime, paste lat/lng from Google Maps below.';
            }
            if (instant) {
                return 'Your device refused to provide a location fix at the operating-system level. Enable Location Services in your OS settings and make sure your browser can use it, then try again — or paste lat/lng from Google Maps below.';
            }
            return 'GPS could not get a position. Try moving near a window, toggle Location Services off/on, or use the course location / paste lat/lng below.';
        }
        return 'GPS could not return a fix. Use the course location (if available) or paste lat/long from Google Maps below.';
    }

    /** Soft hint shown when every auto-fallback (GPS, network, course
     *  coords, stale cache) has been exhausted. Now includes the
     *  actual reason, briefly highlights the manual-entry inputs
     *  so the rep notices them, and auto-focuses the latitude
     *  field so the next action is one click/tab away. */
    function showManualEntryHint() {
        if (locationStatus) {
            locationStatus.textContent = 'Pick a course with saved coordinates, or paste lat/long from Google Maps below.';
        }
        showLocationError(lastGpsFailureReason());

        // Pulse the manual-entry block + autofocus the lat input,
        // but only if the rep hasn't already typed something there
        // (we don't want to steal focus from someone mid-edit).
        var manualWrap = document.getElementById('manual-coords-wrap')
            || (manualLat ? manualLat.closest('.grid, .flex, .space-y-2, div') : null);
        if (manualWrap) {
            manualWrap.classList.add('ring-2', 'ring-amber-300', 'ring-offset-1', 'rounded-md', 'transition-shadow');
            setTimeout(function () {
                manualWrap.classList.remove('ring-2', 'ring-amber-300', 'ring-offset-1', 'ring-offset-1');
            }, 2600);
        }
        if (manualLat && document.activeElement !== manualLat && document.activeElement !== manualLng
            && (!manualLat.value || String(manualLat.value).trim() === '')) {
            try {
                manualLat.focus({ preventScroll: false });
                manualLat.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) { /* older browsers */ }
        }
    }

    /** Try the last-known GPS fix from this browser even if it's old. */
    function tryStaleCache() {
        var d = readCachedFix(true);
        if (!d) return false;
        setLocation(d.lat, d.lng,
            'Using your last GPS fix from ' + minutesAgo(d.ts) + ' min ago — confirm before opening the session.');
        return true;
    }

    courseSelect?.addEventListener('change', function() {
        updateCourseLocationButton();
        if (locationStatus && !latHidden.value) {
            locationStatus.textContent = 'Choose how to set the anchor point (GPS, course location, or manual).';
        }
    });

    useCourseBtn?.addEventListener('click', function() {
        clearLocationError();
        var opt = courseSelect && courseSelect.options[courseSelect.selectedIndex];
        if (!opt || !courseSelect.value) {
            showLocationError('Select a course first.');
            return;
        }
        var la = opt.getAttribute('data-default-lat');
        var ln = opt.getAttribute('data-default-lng');
        var r = opt.getAttribute('data-default-range');
        var lat = parseFloat(la);
        var lng = parseFloat(ln);
        if (isNaN(lat) || isNaN(lng)) {
            showLocationError('This course has no saved coordinates. Ask an admin to set latitude/longitude on the course, or use GPS / manual entry.');
            return;
        }
        if (rangeInput && r !== null && r !== '' && !isNaN(parseInt(r, 10))) {
            rangeInput.value = String(parseInt(r, 10));
        }
        setLocation(lat, lng, 'Using course location from the system.');
    });

    /**
     * Full cascade. Always resolves — never leaves the rep stuck.
     * On the unlikely path where *everything* fails, surfaces a calm
     * manual-entry hint instead of the old CoreLocation jargon.
     *
     *  opts.silent = true → no UI status updates while running (used
     *                       by the page-load pre-warm so we don't
     *                       hijack the status row before the rep
     *                       even clicks anything).
     */
    async function acquireLocationCascade(opts) {
        opts = opts || {};
        gpsDiagSteps = [];                      // fresh log per attempt
        gpsDiagPush({ step: 'cascade.start', silent: !!opts.silent });

        // 0a. No geolocation API at all
        if (!navigator.geolocation) {
            gpsDiagPush({ step: 'cascade.abort', reason: 'no_geolocation_api' });
            if (!opts.silent) {
                showLocationError('This browser does not support geolocation. Pick a course with saved coordinates or paste lat/long below.');
                gpsDiagSend('cascade.no_api', { duration_ms: 0 });
            }
            return false;
        }

        // 0b. Insecure context — Chrome/Firefox/Safari refuse GPS on
        // bare HTTP. Surface this immediately instead of waiting for
        // a confusing PERMISSION_DENIED with no prompt.
        if (typeof window.isSecureContext !== 'undefined' && !window.isSecureContext) {
            gpsDiagPush({ step: 'cascade.abort', reason: 'insecure_context' });
            if (!opts.silent) {
                showLocationError('This page is loaded over HTTP. Browsers only allow GPS on HTTPS — open the dashboard at https://' + location.host + ' and try again.');
                gpsDiagSend('cascade.insecure_context', {});
            }
            return false;
        }

        clearLocationError();
        var t0 = Date.now();

        // 1. Fresh cache
        var cached = readCachedFix(false);
        if (cached) {
            gpsDiagPush({ step: 'cache.fresh.hit', age_min: minutesAgo(cached.ts) });
            setLocation(cached.lat, cached.lng,
                'Used cached GPS fix from ' + minutesAgo(cached.ts) + ' min ago (~' + Math.round(cached.accuracy || 0) + 'm).');
            // Refresh in the background so the next click is fresher
            (async function() {
                try {
                    var p = await getPositionPromise(geoOptionsLow, 'bg_refresh');
                    writeCachedFix(p.coords.latitude, p.coords.longitude, p.coords.accuracy);
                } catch (e) { /* ignore — cache stays */ }
            })();
            return true;
        }
        gpsDiagPush({ step: 'cache.fresh.miss' });

        // 2. Skip GPS entirely if user previously denied permission
        var perm = await safePermissionState();
        gpsDiagPush({ step: 'permissions.query', permission: perm || 'unknown' });
        if (perm === 'denied') {
            if (tryFallbackToCourseLocation()) {
                gpsDiagSend('cascade.fallback_course_after_denied', { permission: perm, duration_ms: Date.now() - t0 });
                return true;
            }
            if (tryStaleCache()) {
                gpsDiagSend('cascade.fallback_stale_after_denied', { permission: perm, duration_ms: Date.now() - t0 });
                return true;
            }
            if (!opts.silent) {
                showLocationError(geoErrorMessage({ code: 1 }));
                showManualEntryHint();
                gpsDiagSend('cascade.failed_permission_denied', { permission: perm, duration_ms: Date.now() - t0 });
            }
            return false;
        }

        // 3. High-accuracy GPS
        if (!opts.silent && locationStatus) locationStatus.textContent = 'Getting your location…';
        var highStartedAt = Date.now();
        var skipLowAccuracy = false;
        try {
            var p1 = await getPositionPromise(geoOptionsHigh, 'high_accuracy');
            writeCachedFix(p1.coords.latitude, p1.coords.longitude, p1.coords.accuracy);
            setLocation(p1.coords.latitude, p1.coords.longitude,
                'GPS fix (~' + Math.round(p1.coords.accuracy || 0) + 'm accuracy).');
            gpsDiagSend('cascade.ok_high_accuracy', {
                permission: perm,
                accuracy: Math.round(p1.coords.accuracy || 0),
                duration_ms: Date.now() - t0,
            });
            return true;
        } catch (e1) {
            if (e1 && e1.code === 1) {
                // Hard deny — same path as step 2.
                if (tryFallbackToCourseLocation()) {
                    gpsDiagSend('cascade.fallback_course_after_denied', { permission: perm, duration_ms: Date.now() - t0 });
                    return true;
                }
                if (tryStaleCache()) {
                    gpsDiagSend('cascade.fallback_stale_after_denied', { permission: perm, duration_ms: Date.now() - t0 });
                    return true;
                }
                if (!opts.silent) {
                    showLocationError(geoErrorMessage(e1));
                    gpsDiagSend('cascade.failed_permission_denied', { permission: perm, duration_ms: Date.now() - t0 });
                }
                return false;
            }
            // POSITION_UNAVAILABLE returning in <200ms means the OS
            // itself blocked us (macOS Location Services off, Windows
            // Location off, etc.). The low-accuracy retry will hit
            // the exact same wall — skip the 12s wait and let the
            // rep see the real fix-it message immediately.
            if (e1 && e1.code === 2 && (Date.now() - highStartedAt) < 200) {
                skipLowAccuracy = true;
                gpsDiagPush({ step: 'cascade.skip_low_accuracy', reason: 'instant_position_unavailable' });
            }
            // Otherwise (POSITION_UNAVAILABLE / TIMEOUT) — quietly try lower accuracy
        }

        if (!skipLowAccuracy) {
            if (!opts.silent && locationStatus) locationStatus.textContent = 'Trying Wi-Fi / network location…';
            try {
                var p2 = await getPositionPromise(geoOptionsLow, 'low_accuracy');
                writeCachedFix(p2.coords.latitude, p2.coords.longitude, p2.coords.accuracy);
                setLocation(p2.coords.latitude, p2.coords.longitude,
                    'Network location (~' + Math.round(p2.coords.accuracy || 0) + 'm). Tap GPS again outdoors for a tighter fix.');
                gpsDiagSend('cascade.ok_low_accuracy', {
                    permission: perm,
                    accuracy: Math.round(p2.coords.accuracy || 0),
                    duration_ms: Date.now() - t0,
                });
                return true;
            } catch (e2) { /* fall through silently */ }
        }

        // 4. Course saved coords (silent)
        if (tryFallbackToCourseLocation()) {
            gpsDiagSend('cascade.fallback_course', { permission: perm, duration_ms: Date.now() - t0 });
            return true;
        }

        // 5. Stale cache as a last resort (clearly labelled)
        if (tryStaleCache()) {
            gpsDiagSend('cascade.fallback_stale', { permission: perm, duration_ms: Date.now() - t0 });
            return true;
        }

        // 6. Manual entry hint with the real reason surfaced
        if (!opts.silent) {
            showManualEntryHint();
            // Send the last known failure code/message so the operator
            // can match this with the rep's complaint.
            var lastErr = null;
            for (var i = gpsDiagSteps.length - 1; i >= 0; i--) {
                if (gpsDiagSteps[i].status === 'error') { lastErr = gpsDiagSteps[i]; break; }
            }
            gpsDiagSend('cascade.failed_exhausted', {
                permission: perm,
                code: lastErr ? lastErr.code : null,
                message: lastErr ? lastErr.code_name + (lastErr.message ? (' — ' + lastErr.message) : '') : 'no_error_code',
                duration_ms: Date.now() - t0,
            });
        }
        return false;
    }

    getLocationBtn?.addEventListener('click', async function() {
        getLocationBtn.disabled = true;
        var prevHtml = getLocationBtn.innerHTML;
        getLocationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating…';
        try {
            await acquireLocationCascade({});
        } finally {
            getLocationBtn.disabled = false;
            getLocationBtn.innerHTML = prevHtml;
        }
    });

    // Pre-warm GPS on page load when the user already granted location
    // for this site. The cascade runs silently; if it succeeds, the
    // rep clicks "Use device GPS" and gets an instant cached fix
    // instead of a fresh 7-second wait.
    (async function preWarm() {
        if (!navigator.geolocation) return;
        var perm = await safePermissionState();
        if (perm !== 'granted') return;
        // Wait a beat so the UI mounts first.
        setTimeout(function() { acquireLocationCascade({ silent: true }); }, 400);
    })();

    /**
     * Validate + apply whatever's in the visible manual_lat / manual_lng
     * inputs into the hidden location_lat / location_lng inputs.
     * Returns true on success, false if values are missing/invalid.
     * Used by both the "Apply coordinates" button and the form-submit
     * guard (so reps who just type and hit "Open session" still work).
     */
    function applyManualCoordsIfAny(opts) {
        opts = opts || {};
        var lat = parseCoordInput(manualLat && manualLat.value);
        var lng = parseCoordInput(manualLng && manualLng.value);
        if (isNaN(lat) && isNaN(lng)) return false; // nothing typed
        if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            if (!opts.silent) showLocationError('Enter valid latitude (−90 to 90) and longitude (−180 to 180).');
            return false;
        }
        setLocation(lat, lng, opts.message || 'Manual coordinates applied.');
        return true;
    }

    useManualBtn?.addEventListener('click', function() {
        clearLocationError();
        applyManualCoordsIfAny();
    });

    // Live-sync the manual inputs into the hidden form fields the
    // moment both values are valid — so the rep can type lat/long
    // and click "Open session" without first remembering to click
    // "Apply coordinates". (We don't show a status message here to
    // avoid flicker while they're still typing.)
    function liveSyncManual() {
        var lat = parseCoordInput(manualLat && manualLat.value);
        var lng = parseCoordInput(manualLng && manualLng.value);
        if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return;
        latHidden.value = String(lat);
        lngHidden.value = String(lng);
        if (locationDisplay) {
            locationDisplay.textContent = 'Anchor: ' + lat.toFixed(6) + ', ' + lng.toFixed(6);
            locationDisplay.classList.remove('hidden');
        }
        clearLocationError();
    }
    manualLat?.addEventListener('input', liveSyncManual);
    manualLng?.addEventListener('input', liveSyncManual);
    manualLat?.addEventListener('blur', function() { applyManualCoordsIfAny({ silent: true }); });
    manualLng?.addEventListener('blur', function() { applyManualCoordsIfAny({ silent: true }); });

    if (latHidden && latHidden.value && lngHidden && lngHidden.value) {
        setLocation(parseFloat(latHidden.value), parseFloat(lngHidden.value), 'Location restored from last attempt.');
    }
    updateCourseLocationButton();

    var wifiSection = document.getElementById('session-wifi-section');
    var wifiSsidInput = document.getElementById('allowed_wifi_ssid');

    function syncModeUi() {
        var mode = modeSelect && modeSelect.value;
        var needLoc = mode === 'location' || mode === 'hybrid';
        var needWifi = mode === 'wifi';
        if (locationSection) {
            locationSection.classList.toggle('hidden', !needLoc);
            locationSection.setAttribute('aria-hidden', needLoc ? 'false' : 'true');
        }
        if (wifiSection) {
            wifiSection.classList.toggle('hidden', !needWifi);
            wifiSection.setAttribute('aria-hidden', needWifi ? 'false' : 'true');
        }
        if (rangeInput) {
            if (needLoc) rangeInput.setAttribute('required', 'required');
            else rangeInput.removeAttribute('required');
        }
        if (wifiSsidInput) {
            if (needWifi) wifiSsidInput.setAttribute('required', 'required');
            else wifiSsidInput.removeAttribute('required');
        }
        if (!needLoc && latHidden && lngHidden) {
            latHidden.value = '';
            lngHidden.value = '';
        }
    }
    if (modeSelect) {
        modeSelect.addEventListener('change', syncModeUi);
        syncModeUi();
    }

    if (form && btn) {
        form.addEventListener('submit', function(e) {
            clearLocationError();
            var mode = modeSelect && modeSelect.value;
            var needLoc = mode === 'location' || mode === 'hybrid';
            var needWifi = mode === 'wifi';
            if (needWifi) {
                var ssid = wifiSsidInput && wifiSsidInput.value ? wifiSsidInput.value.trim() : '';
                if (!ssid) {
                    e.preventDefault();
                    showLocationError('Enter the expected Wi‑Fi network name (SSID) for students.');
                    if (wifiSection) wifiSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
                return;
            }
            if (!needLoc) return;
            // If the rep typed lat/long into the visible manual inputs
            // but never clicked "Apply coordinates", auto-apply now
            // before the validator runs. Saves them an extra click.
            if (!latHidden.value || !lngHidden.value) {
                applyManualCoordsIfAny({ silent: true });
            }
            var lat = parseFloat(latHidden.value);
            var lng = parseFloat(lngHidden.value);
            if (!latHidden.value || !lngHidden.value || isNaN(lat) || isNaN(lng)) {
                e.preventDefault();
                // If they DID type something but it's invalid, be specific.
                var typedLat = manualLat && manualLat.value && String(manualLat.value).trim() !== '';
                var typedLng = manualLng && manualLng.value && String(manualLng.value).trim() !== '';
                if (typedLat || typedLng) {
                    showLocationError('Latitude must be −90 to 90 and longitude −180 to 180. Check the coordinates and try again.');
                } else {
                    showLocationError('Set a location first: use device GPS, use course location (if available), or paste coordinates from Google Maps below.');
                }
                var section = document.getElementById('session-location-section');
                if (section) section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                e.preventDefault();
                showLocationError('Coordinates are out of range.');
                return;
            }
        });
    }
})();
</script>
@endif
@include('partials.session-countdown-script')

@endsection
