@extends('layouts.courserep')

@section('title', 'Open session')

@section('content')
@php
    $fieldBase = 'w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelBase = 'block text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5';
@endphp

<div class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div>
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
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    @if($coursesWithOpen->isNotEmpty())
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white">
            <div class="border-b border-slate-100 bg-slate-50/90 px-5 py-4 sm:px-6 sm:py-5">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-primary border border-slate-200">
                        <i class="fas fa-sliders text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Session setup</h2>
                        <p class="text-sm text-slate-500 mt-1 leading-relaxed">Main rep only. On the scheduled day, the session ends at your timetable end time at the latest.</p>
                    </div>
                </div>
            </div>
            <form action="{{ route('dashboard.live-sessions.store') }}" method="POST" id="open-session-form" class="p-5 sm:p-6 space-y-6">
                @csrf
                <div class="space-y-1.5">
                    <label for="course_id" class="{{ $labelBase }}">Course</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-book text-xs"></i></span>
                        <select name="course_id" id="course_id" required class="{{ $fieldBase }} appearance-none pl-10 pr-10 cursor-pointer">
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
                                {{ $c->course_name }}{{ $c->course_code ? ' (' . $c->course_code . ')' : '' }} — {{ $c->getScheduleLabel() }}
                            </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="mode" class="{{ $labelBase }}">Attendance mode</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-layer-group text-xs"></i></span>
                            <select name="mode" id="session-mode" class="{{ $fieldBase }} appearance-none pl-10 pr-10 cursor-pointer">
                                <option value="location" {{ old('mode', 'location') === 'location' ? 'selected' : '' }}>Location (GPS)</option>
                                <option value="qr" {{ old('mode') === 'qr' ? 'selected' : '' }}>QR code</option>
                                <option value="hybrid" {{ old('mode') === 'hybrid' ? 'selected' : '' }}>Hybrid (GPS + QR)</option>
                                <option value="wifi" {{ old('mode') === 'wifi' ? 'selected' : '' }}>Wi‑Fi (same network)</option>
                            </select>
                            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="duration_minutes" class="{{ $labelBase }}">Duration</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-clock text-xs"></i></span>
                            <input type="number" name="duration_minutes" id="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="5" max="480" required inputmode="numeric"
                                class="{{ $fieldBase }} pl-10 tabular-nums">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Minutes · 5–480</p>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label for="lecturer_status" class="{{ $labelBase }}">Lecturer status for this class</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-user-tie text-xs"></i></span>
                        <select name="lecturer_status" id="lecturer_status" class="{{ $fieldBase }} appearance-none pl-10 pr-10 cursor-pointer" required>
                            <option value="present" {{ old('lecturer_status', 'present') === 'present' ? 'selected' : '' }}>Lecturer present</option>
                            <option value="absent" {{ old('lecturer_status') === 'absent' ? 'selected' : '' }}>Lecturer absent</option>
                        </select>
                        <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-chevron-down text-[10px]"></i></span>
                    </div>
                    <p class="text-[11px] text-slate-400">Saved on this attendance session and shown in attendance list/PDF.</p>
                </div>
                <div class="rounded-2xl border border-primary/20 bg-primary/[0.04] p-4 sm:p-5" id="session-location-section">
                    <div class="flex items-start gap-3 mb-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-primary ring-1 ring-primary/15">
                            <i class="fas fa-location-dot text-sm"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Session anchor</p>
                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Used for <strong class="font-semibold text-slate-600">location</strong> and <strong class="font-semibold text-slate-600">hybrid</strong>. Pick GPS, saved course coordinates, or type lat/lng from Maps.</p>
                        </div>
                    </div>

                    <div id="location-status" class="text-sm text-slate-600 mb-3 min-h-[1.25rem] rounded-xl bg-white/60 border border-slate-100 px-3 py-2" role="status" aria-live="polite">Choose a course, then set a location using one of the options below.</div>
                    <p id="location-error" class="hidden text-sm text-red-700 mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2" role="alert"></p>

                    <div class="flex flex-wrap gap-2 mb-4">
                        <button type="button" id="get-location-btn" class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary/25 bg-white px-4 py-2.5 text-sm font-semibold text-primary hover:bg-primary/5">
                            <i class="fas fa-location-crosshairs"></i> Use device GPS
                        </button>
                        <button type="button" id="use-course-location-btn" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none disabled:hover:bg-white" disabled title="Select a course that has saved coordinates">
                            <i class="fas fa-map-pin"></i> Use course location
                        </button>
                    </div>

                    <div id="location-display" class="hidden text-xs text-slate-700 font-mono rounded-xl border border-slate-200 bg-white px-3 py-2.5 mb-4"></div>

                    <div class="rounded-xl border border-slate-200/90 bg-white/70 p-4 space-y-3">
                        <p class="text-xs font-semibold text-slate-700">Manual coordinates</p>
                        <p class="text-[11px] text-slate-500 leading-relaxed">In Google Maps, right‑click → first value is latitude, second is longitude.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="manual_lat" class="text-[11px] font-medium text-slate-500">Latitude (−90 to 90)</label>
                                <input type="text" inputmode="decimal" id="manual_lat" placeholder="e.g. 5.6037" autocomplete="off"
                                    class="{{ $fieldBase }} font-mono text-sm">
                            </div>
                            <div class="space-y-1">
                                <label for="manual_lng" class="text-[11px] font-medium text-slate-500">Longitude (−180 to 180)</label>
                                <input type="text" inputmode="decimal" id="manual_lng" placeholder="e.g. −0.1870" autocomplete="off"
                                    class="{{ $fieldBase }} font-mono text-sm">
                            </div>
                        </div>
                        <button type="button" id="use-manual-btn" class="text-sm font-semibold text-primary hover:text-primary/80">Apply coordinates</button>
                    </div>

                    <div class="mt-4 space-y-1.5">
                        <label for="attendance_range_m" class="{{ $labelBase }}">Radius (meters)</label>
                        <input type="number" name="attendance_range_m" id="attendance_range_m" value="{{ old('attendance_range_m', 100) }}" min="10" max="500"
                            class="{{ $fieldBase }} tabular-nums">
                        <p class="text-[11px] text-slate-400">Students must be within this distance of the anchor.</p>
                    </div>
                    <input type="hidden" name="location_lat" id="location_lat" value="{{ old('location_lat') }}">
                    <input type="hidden" name="location_lng" id="location_lng" value="{{ old('location_lng') }}">
                </div>
                <div class="hidden rounded-2xl border border-sky-200/80 bg-sky-50/50 p-4 sm:p-5" id="session-wifi-section" aria-hidden="true">
                    <div class="flex items-start gap-3 mb-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 ring-1 ring-sky-200/80">
                            <i class="fas fa-wifi text-sm"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Wi‑Fi network</p>
                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Students must join this exact SSID (as shown in Wi‑Fi settings).</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="allowed_wifi_ssid" class="{{ $labelBase }}">Expected SSID</label>
                        <input type="text" name="allowed_wifi_ssid" id="allowed_wifi_ssid" value="{{ old('allowed_wifi_ssid') }}" maxlength="128" placeholder="e.g. NDA-Campus"
                            class="{{ $fieldBase }}">
                    </div>
                </div>
                <div class="pt-1 flex flex-col sm:flex-row sm:items-center gap-3 border-t border-slate-100 pt-6">
                    <button type="submit" id="open-session-btn" class="inline-flex w-full sm:w-auto min-w-[200px] items-center justify-center gap-2 rounded-xl bg-primary px-8 py-3.5 text-sm font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
                        <i class="fas fa-play"></i> Open session
                    </button>
                    <p class="text-xs text-slate-400 text-center sm:text-left">You can adjust mode and anchor before students mark.</p>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="lg:col-span-2"></div>
    @endif
    <div class="lg:sticky lg:top-6 self-start">
        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white">
            <div class="border-b border-slate-100 bg-slate-50/90 px-4 py-3.5 sm:px-5">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-500/30"></span>
                    <h2 class="text-sm font-bold text-slate-900 tracking-tight">Live attendance</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Countdown until the session window closes.</p>
            </div>
            <div class="p-4 space-y-4 max-h-[min(70vh,32rem)] overflow-y-auto">
                @php $hasActive = false; @endphp
                @foreach($courses as $item)
                    @php $course = $item->course; $activeSession = $course->activeSession(); @endphp
                    @if($activeSession)
                        @php $hasActive = true; $expiresIso = $activeSession->expires_at?->toIso8601String(); @endphp
                        <div
                            class="rounded-xl border border-slate-200/90 bg-slate-50/40 p-4"
                            data-session-countdown
                            data-expires="{{ $expiresIso }}"
                        >
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900 text-sm leading-snug line-clamp-2">{{ $course->course_name }}</p>
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
                            <div class="rounded-xl bg-slate-900 p-3.5 mb-3 text-center ring-1 ring-slate-800">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500 mb-1" data-countdown-label>Time remaining</p>
                                <p class="text-2xl sm:text-3xl font-mono font-bold tabular-nums tracking-tight text-white" data-countdown-display>—:—</p>
                                @if($activeSession->expires_at)
                                    <p class="text-[10px] text-slate-500 mt-2">Until {{ $activeSession->expires_at->format('g:i A') }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if(in_array($activeSession->mode, ['qr', 'hybrid']))
                                    <a href="{{ route('dashboard.live-sessions.qr', $activeSession) }}" target="_blank" class="flex-1 min-w-[4rem] inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-semibold bg-primary text-white hover:bg-primary/90">
                                        <i class="fas fa-qrcode text-[10px]"></i> QR
                                    </a>
                                @endif
                                <a href="{{ route('web.attendance.form', $course) }}" target="_blank" class="flex-1 min-w-[4rem] inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-semibold border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">
                                    <i class="fas fa-clipboard-check text-[10px]"></i> Form
                                </a>
                                @if($item->canOpenSession)
                                <form action="{{ route('dashboard.live-sessions.close', $activeSession) }}" method="POST" class="flex-1 min-w-full sm:min-w-0 sm:flex-none" onsubmit="return confirm('Close this session?');">
                                    @csrf
                                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-xl text-[11px] font-semibold border border-rose-200/90 bg-white text-rose-600 hover:bg-rose-50">
                                        <i class="fas fa-stop text-[10px]"></i> Close
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
                @if(!$hasActive)
                    <div class="text-center py-10 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                            <i class="fas fa-hourglass-start text-lg"></i>
                        </div>
                        <p class="text-sm font-semibold text-slate-700">No session open</p>
                        <p class="text-xs text-slate-500 mt-1.5">Use the form to start one when you&rsquo;re ready.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($coursesWithOpen->isNotEmpty())
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

    /** High accuracy first; on POSITION_UNAVAILABLE or TIMEOUT, retry with network/Wi‑Fi (helps macOS Safari / CoreLocation kCLErrorLocationUnknown). */
    var geoOptionsHigh = { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 };
    var geoOptionsLow = { enableHighAccuracy: false, timeout: 30000, maximumAge: 120000 };

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

    function geoErrorMessage(err) {
        if (!err || err.code === undefined) return 'Could not read your location.';
        switch (err.code) {
            case 1:
                return 'Location permission is blocked. Click the lock or site icon in the address bar, allow location for this site, then try again — or use course location / manual coordinates.';
            case 2:
                return 'Your device could not determine a position (common indoors, on some Macs, or when Wi‑Fi location is unavailable — Safari may report CoreLocation “location unknown”). Use “Use course location” or paste coordinates from Google Maps.';
            case 3:
                return 'Location request timed out. Try again, or use course location / manual coordinates.';
            default:
                return 'Could not read your location. Use course location or manual coordinates below.';
        }
    }

    /** One detailed message in the red box; status line stays a short hint (no duplicate paragraphs). */
    function reportGeoFailure(err) {
        if (locationStatus) {
            locationStatus.textContent = 'GPS did not return a fix. Use course location or manual coordinates below.';
        }
        showLocationError(geoErrorMessage(err));
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

    getLocationBtn?.addEventListener('click', function() {
        clearLocationError();
        if (!navigator.geolocation) {
            if (locationStatus) locationStatus.textContent = 'Geolocation is not available in this browser.';
            showLocationError('This browser does not support geolocation. Use course location or manual coordinates.');
            return;
        }
        if (locationStatus) locationStatus.textContent = 'Requesting precise location…';
        getLocationBtn.disabled = true;

        function finishSuccess(pos) {
            setLocation(pos.coords.latitude, pos.coords.longitude, 'Location captured (accuracy ~' + Math.round(pos.coords.accuracy || 0) + ' m).');
            getLocationBtn.disabled = false;
        }

        navigator.geolocation.getCurrentPosition(
            finishSuccess,
            function(errFirst) {
                var retry = (errFirst.code === 2 || errFirst.code === 3);
                if (retry) {
                    if (locationStatus) {
                        locationStatus.textContent = 'Trying approximate location (works better on some Macs / indoors)…';
                    }
                    navigator.geolocation.getCurrentPosition(
                        finishSuccess,
                        function(errSecond) {
                            reportGeoFailure(errSecond);
                            getLocationBtn.disabled = false;
                        },
                        geoOptionsLow
                    );
                    return;
                }
                if (errFirst.code === 1) {
                    if (locationStatus) locationStatus.textContent = 'Location permission needed, or use the options below.';
                    showLocationError(geoErrorMessage(errFirst));
                } else {
                    reportGeoFailure(errFirst);
                }
                getLocationBtn.disabled = false;
            },
            geoOptionsHigh
        );
    });

    useManualBtn?.addEventListener('click', function() {
        clearLocationError();
        var lat = parseCoordInput(manualLat && manualLat.value);
        var lng = parseCoordInput(manualLng && manualLng.value);
        if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            showLocationError('Enter valid latitude (−90 to 90) and longitude (−180 to 180).');
            return;
        }
        setLocation(lat, lng, 'Manual coordinates applied.');
    });

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
            var lat = parseFloat(latHidden.value);
            var lng = parseFloat(lngHidden.value);
            if (!latHidden.value || !lngHidden.value || isNaN(lat) || isNaN(lng)) {
                e.preventDefault();
                showLocationError('Set a location first: use device GPS, use course location (if available), or apply manual coordinates.');
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
