@extends('layouts.classrep')

@section('title', 'Dashboard')

@push('styles')
{{-- Leaflet (OpenStreetMap) loaded from CDN — no API key required. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<style>
    /* ──────────────── Attendance map ────────────────
       The map sits inside a softly bevelled card. Each per-student pin
       is a custom DivIcon (CSS-only) so we can animate `scale`, `ring`
       and `shadow` on hover without depending on a marker library. */
    #rep-attendance-map {
        width: 100%;
        height: 100%;
        border-radius: 14px;
        z-index: 0;
    }
    #rep-attendance-map .leaflet-control-attribution {
        font-size: 10px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(6px);
    }
    .rep-pin {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.18),
                    0 6px 14px rgba(2, 132, 199, 0.35);
        border: 2px solid #ffffff;
        cursor: pointer;
        position: relative;
        transition: transform 220ms cubic-bezier(.2,.8,.2,1),
                    box-shadow 220ms cubic-bezier(.2,.8,.2,1);
    }
    .rep-pin::after {
        content: "";
        position: absolute;
        inset: -10px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(14,165,233,0.25) 0%, rgba(14,165,233,0) 70%);
        opacity: 0;
        transition: opacity 220ms ease;
        pointer-events: none;
    }
    .rep-pin:hover {
        transform: scale(1.45);
        box-shadow: 0 0 0 5px rgba(14, 165, 233, 0.25),
                    0 10px 22px rgba(2, 132, 199, 0.45);
        z-index: 800;
    }
    .rep-pin:hover::after { opacity: 1; }

    /* Per-mode pin tint so a quick glance reveals which capture method
       a row used (QR vs hybrid vs raw location). */
    .rep-pin--qr { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18), 0 6px 14px rgba(79,70,229,0.35); }
    .rep-pin--hybrid { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.18), 0 6px 14px rgba(217,119,6,0.35); }
    .rep-pin--wifi { background: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,0.18), 0 6px 14px rgba(13,148,136,0.35); }
    .rep-pin--qr:hover::after { background: radial-gradient(circle, rgba(99,102,241,0.28) 0%, rgba(99,102,241,0) 70%); }
    .rep-pin--hybrid:hover::after { background: radial-gradient(circle, rgba(245,158,11,0.30) 0%, rgba(245,158,11,0) 70%); }
    .rep-pin--wifi:hover::after { background: radial-gradient(circle, rgba(20,184,166,0.28) 0%, rgba(20,184,166,0) 70%); }

    /* Subtle pulse on the latest mark so the rep can spot it. */
    @keyframes repPinPulse {
        0% { box-shadow: 0 0 0 3px rgba(14,165,233,0.5), 0 6px 14px rgba(2,132,199,0.35); }
        70% { box-shadow: 0 0 0 14px rgba(14,165,233,0), 0 6px 14px rgba(2,132,199,0.35); }
        100% { box-shadow: 0 0 0 3px rgba(14,165,233,0.18), 0 6px 14px rgba(2,132,199,0.35); }
    }
    .rep-pin--fresh { animation: repPinPulse 1.8s ease-out infinite; }

    .leaflet-popup-content-wrapper {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
    }
    .leaflet-popup-content {
        margin: 10px 14px;
        font-family: Inter, system-ui, sans-serif;
        font-size: 12.5px;
        line-height: 1.45;
        color: #1e293b;
    }
    .leaflet-popup-content b { color: #0f172a; }
    .leaflet-popup-content .pop-meta {
        margin-top: 4px;
        color: #64748b;
        font-size: 11px;
    }
    .leaflet-popup-content .pop-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .map-filter-chip {
        cursor: pointer;
        user-select: none;
        transition: background 160ms ease, color 160ms ease, transform 160ms ease;
    }
    .map-filter-chip:hover { transform: translateY(-1px); }
    .map-filter-chip[aria-pressed="true"] { background: #0f172a; color: #ffffff; }
</style>
@endpush

@section('content')
<div class="w-full min-w-0 space-y-6">
    {{-- Hero: photo + flat tint overlay --}}
    <div class="relative overflow-hidden rounded-2xl text-white">
        <div
            class="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('https://thumbs.dreamstime.com/b/people-working-coworking-space-using-computers-mobile-devices-individuals-engage-tasks-their-desks-426022434.jpg');"
            aria-hidden="true"
        ></div>
        {{-- Darken photo for contrast --}}
        <div class="absolute inset-0 bg-black/45" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-teal-900/65"></div>
        <div class="relative z-10 px-5 py-6 sm:px-8 sm:py-8">
            <p class="text-teal-100/90 text-xs font-semibold uppercase tracking-widest">Dashboard</p>
            @php
                $repGreetingName = trim((string) ($student->last_name ?? ''));
                if ($repGreetingName === '') {
                    $repGreetingName = (string) ($student->index_number ?? 'there');
                }
            @endphp
            <h1 class="text-2xl sm:text-3xl font-bold mt-1 tracking-tight">Hello, {{ $repGreetingName }}</h1>
            <p class="text-teal-100/80 text-sm mt-2 max-w-xl">{{ now()->format('l, F j, Y') }} · Stay on top of sessions and attendance</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('dashboard.session') }}" class="inline-flex items-center gap-2 rounded-xl bg-white text-teal-800 px-4 py-2.5 text-sm font-semibold hover:bg-teal-50">
                    <i class="fas fa-play-circle"></i> Open session
                </a>
                <a href="{{ route('dashboard.class-attendance.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 border border-white/25 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/15">
                    <i class="fas fa-clipboard-list"></i> Attendance
                </a>
            </div>
        </div>
    </div>

    {{-- Stat grid: flat tinted cards, each a distinct palette (no gradients) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
        <div class="rounded-xl border border-[#c5d4e0] bg-[#edf3f8] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#3d5a6e]">Students</span>
                <span class="w-9 h-9 rounded-xl bg-[#d4e4ef] flex items-center justify-center text-[#1f4558]"><i class="fas fa-users text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#1a3344] tabular-nums">{{ $studentsCount }}</p>
        </div>
        <div class="rounded-xl border border-[#e0cfc0] bg-[#f7f0ea] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#6b4a38]">Courses</span>
                <span class="w-9 h-9 rounded-xl bg-[#ead9cc] flex items-center justify-center text-[#5c3d2e]"><i class="fas fa-book text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#3d281c] tabular-nums">{{ $coursesCount }}</p>
        </div>
        <div class="rounded-2xl border border-[#c4d2be] bg-[#eef4ec] p-4 shadow-sm shadow-[#c4d2be]/25">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#3f5a42]">7 days</span>
                <span class="w-9 h-9 rounded-xl bg-[#dce8d8] flex items-center justify-center text-[#2d4a32]"><i class="fas fa-chart-line text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#243828] tabular-nums">{{ $weekAttendanceMarks }}</p>
            <p class="text-[10px] text-[#4d6350] mt-1 font-medium">Marks recorded</p>
        </div>
        <div class="rounded-xl border border-[#d4c8de] bg-[#f4eef8] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#5c476f]">Today</span>
                <span class="w-9 h-9 rounded-xl bg-[#e5daf0] flex items-center justify-center text-[#4a3560]"><i class="fas fa-sun text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#352847] tabular-nums">{{ $todayAttendanceMarks }}</p>
            <p class="text-[10px] text-[#6b5578] mt-1 font-medium">Today&rsquo;s marks</p>
        </div>
        <div class="rounded-xl border border-[#c8c6d8] bg-[#ecebf4] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#45425c]">All-time</span>
                <span class="w-9 h-9 rounded-xl bg-[#dad8ea] flex items-center justify-center text-[#38354f]"><i class="fas fa-clipboard-check text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#252238] tabular-nums">{{ $totalAttendanceMarks }}</p>
            <p class="text-[10px] text-[#5a5670] mt-1 font-medium">Total marks</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-[#c5d4e0] bg-[#f5f9fc] overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-[#c5d4e0] flex flex-wrap items-center justify-between gap-2 bg-[#edf3f8]">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#d4e4ef] text-[#1f4558]">
                        <i class="fas fa-calendar-day text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-[#1a3344] tracking-tight">Today&rsquo;s schedule</h2>
                    </div>
                </div>
                <span class="shrink-0 inline-flex items-center rounded-lg border border-[#b8ccdb] bg-white/90 px-3 py-1.5 text-xs font-semibold tabular-nums text-[#2d4a5c]">{{ now()->format('M j, Y') }}</span>
            </div>
            <div class="p-3 sm:p-4 space-y-2.5">
                @forelse($todayCourses as $c)
                    <div class="flex items-stretch gap-3 rounded-xl border border-[#dce7ee] bg-white p-3 sm:p-3.5">
                        <span class="hidden sm:flex w-1 shrink-0 rounded-full bg-[#8eb4c8]" aria-hidden="true"></span>
                        <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div class="min-w-0 flex gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#eef5fa] text-[#2d5a6e] ring-1 ring-[#dce7ee]">
                                    <i class="fas fa-book-open text-[11px]"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-[#142a38] leading-snug truncate">
                                        {{ $c->course_name }}
                                        @if(!empty($c->course_code))
                                            <span class="ml-1 text-[11px] font-mono text-[#5a6f7c]">{{ $c->course_code }}</span>
                                        @endif
                                    </p>
                                    @if(!empty($c->schedule_label))
                                        <p class="text-[12px] text-[#5a6f7c] mt-1 leading-relaxed">
                                            <span class="text-[#3d5a6e] font-medium">{{ $c->schedule_label }}</span>
                                        </p>
                                    @endif
                                </div>
                            </div>
                            @if(!empty($c->has_active_session))
                                <span class="shrink-0 self-start inline-flex items-center gap-1.5 rounded-lg border border-[#b5d9c4] bg-[#ecf6f0] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#1f5c36]">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#2d8f4e]" aria-hidden="true"></span>
                                    Live
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#c5d4e0] bg-[#fafcfd] px-4 py-10 text-center">
                        <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#e8f0f6] text-[#6b8fa3]">
                            <i class="fas fa-mug-hot text-lg"></i>
                        </span>
                        <p class="text-sm font-medium text-[#3d5a6e]">Nothing on your timetable today</p>
                        <p class="text-xs text-[#7a919c] mt-1.5 max-w-xs mx-auto">When you add a course to <a href="{{ route('dashboard.timetable.manage') }}" class="text-[#2d5a6e] underline">your timetable</a> for {{ now()->format('l') }}, it&rsquo;ll show up here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Shortcuts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <a href="{{ route('dashboard.students.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-user-friends text-primary w-5 text-center"></i> Students
                </a>
                <a href="{{ route('dashboard.timetable') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-calendar-alt text-primary w-5 text-center"></i> Timetable
                </a>
                <a href="{{ route('dashboard.my-class') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-layer-group text-primary w-5 text-center"></i> My class
                </a>
                <a href="{{ route('dashboard.class-attendance.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-clipboard-list text-primary w-5 text-center"></i> Attendance
                </a>
            </div>
        </div>
    </div>

    {{-- ───────── Attendance map (Leaflet / OpenStreetMap) ─────────
         Every per-student mark from the last 14 days that includes
         coordinates is dropped onto the map as a coloured pin. Course
         anchors (when configured on the course) are shown as faint
         circles so the rep can see who marked inside vs. outside the
         configured radius at a glance. Tints by mode:
           • sky      → location-only
           • indigo   → QR only
           • amber    → hybrid (QR + GPS)
           • teal     → wifi (same-network)
         Hover any pin for a soft scale + halo, click for the popup. --}}
    @php
        $mapPoints = collect($attendanceMapPoints ?? []);
        $anchors = collect($courseAnchors ?? []);
        $uniqueCourses = $mapPoints->pluck('course')->filter()->unique()->values();
        $modeCounts = $mapPoints->groupBy('mode')->map->count();
    @endphp
    <div class="rounded-2xl border border-slate-200/80 bg-white overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-slate-100 bg-gradient-to-r from-sky-50/70 via-white to-emerald-50/40">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                    <i class="fas fa-map-location-dot text-sm"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Attendance map</h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Where students marked from over the last 14 days · {{ $mapPoints->count() }} pin{{ $mapPoints->count() === 1 ? '' : 's' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider">
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                    <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span> Location · {{ $modeCounts->get('location', 0) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span> QR · {{ $modeCounts->get('qr', 0) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-100">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span> Hybrid · {{ $modeCounts->get('hybrid', 0) }}
                </span>
                @if($modeCounts->get('wifi', 0) > 0)
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                        <span class="inline-block w-2 h-2 rounded-full bg-teal-500"></span> Wi-Fi · {{ $modeCounts->get('wifi', 0) }}
                    </span>
                @endif
            </div>
        </div>

        @if($uniqueCourses->isNotEmpty())
            <div class="px-4 sm:px-5 py-3 border-b border-slate-100 bg-white/60">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 shrink-0">Course filter</span>
                    <button type="button" class="map-filter-chip px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" data-course-filter="" aria-pressed="true">All ({{ $mapPoints->count() }})</button>
                    @foreach($uniqueCourses as $c)
                        @php $n = $mapPoints->where('course', $c)->count(); @endphp
                        <button type="button" class="map-filter-chip px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" data-course-filter="{{ $c }}" aria-pressed="false">
                            {{ \Illuminate\Support\Str::limit($c, 26) }} · {{ $n }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if($mapPoints->isEmpty())
            <div class="px-6 py-16 text-center">
                <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <i class="fas fa-map-pin text-xl"></i>
                </span>
                <p class="text-sm font-medium text-slate-700">No location data yet</p>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">When students mark attendance from a location / hybrid session, their pins will appear here.</p>
            </div>
        @else
            <div class="relative h-[360px] sm:h-[440px] lg:h-[520px] bg-slate-50">
                <div id="rep-attendance-map"></div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if(!empty($attendanceMapPoints) && count($attendanceMapPoints) > 0)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script>
(function () {
    var mapEl = document.getElementById('rep-attendance-map');
    if (!mapEl || typeof L === 'undefined') return;

    var POINTS  = @json($attendanceMapPoints);
    var ANCHORS = @json($courseAnchors);

    // Build the Leaflet map with a friendly tile layer. CartoDB Positron
    // works without API keys and reads cleanly against our coloured pins.
    var map = L.map(mapEl, {
        zoomControl: true,
        scrollWheelZoom: true,
        attributionControl: true,
    }).setView([0, 0], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
        maxZoom: 19,
        subdomains: 'abcd',
    }).addTo(map);

    // Helper for a custom DivIcon pin so we can CSS-animate it on hover.
    function pinIcon(mode, isFresh) {
        var cls = 'rep-pin rep-pin--' + (mode || 'location');
        if (isFresh) cls += ' rep-pin--fresh';
        return L.divIcon({
            html: '<div class="' + cls + '"></div>',
            className: '',
            iconSize: [22, 22],
            iconAnchor: [11, 11],
            popupAnchor: [0, -10],
        });
    }

    // Tint course anchor circles by the same per-mode palette so the
    // visual mapping stays consistent throughout the panel.
    var anchorLayer = L.layerGroup().addTo(map);
    ANCHORS.forEach(function (a) {
        if (typeof a.lat !== 'number' || typeof a.lng !== 'number') return;
        L.circle([a.lat, a.lng], {
            radius: a.radius_m || 75,
            color: '#0284c7',
            weight: 1.2,
            opacity: 0.5,
            fillColor: '#0ea5e9',
            fillOpacity: 0.07,
        }).bindTooltip(a.name + (a.code ? ' · ' + a.code : ''), { sticky: true }).addTo(anchorLayer);
    });

    // Add per-student pins. We track each marker so the course filter
    // chips can hide/show without rebuilding the layer.
    var pinLayer = L.layerGroup().addTo(map);
    var bounds = L.latLngBounds([]);
    var markers = [];
    var freshCutoff = Date.now() - 30 * 60 * 1000; // 30 min ago

    POINTS.forEach(function (p) {
        if (!isFinite(p.lat) || !isFinite(p.lng)) return;
        var ts = p.time_iso ? Date.parse(p.time_iso) : 0;
        var isFresh = ts && ts >= freshCutoff;

        var marker = L.marker([p.lat, p.lng], { icon: pinIcon(p.mode, isFresh) });

        var modeLabel = ({
            qr: 'QR scan',
            hybrid: 'Hybrid (QR + GPS)',
            wifi: 'Wi-Fi',
            location: 'Location'
        })[p.mode] || 'Location';

        var html =
            '<b>' + escapeHtml(p.student) + '</b>' +
            (p.index ? ' <span class="text-xs text-slate-500 font-mono">· ' + escapeHtml(p.index) + '</span>' : '') +
            '<div class="pop-meta">' + escapeHtml(p.course) +
            (p.course_code ? ' <span class="font-mono">(' + escapeHtml(p.course_code) + ')</span>' : '') + '</div>' +
            (p.time ? '<div class="pop-meta">' + escapeHtml(p.time) + '</div>' : '') +
            '<span class="pop-chip"><i class="fas fa-location-dot"></i>' + escapeHtml(modeLabel) + '</span>';

        marker.bindPopup(html);
        marker._courseName = p.course;
        marker.addTo(pinLayer);
        markers.push(marker);
        bounds.extend([p.lat, p.lng]);
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
    } else {
        map.setView([0, 0], 2);
    }

    // Course-filter chips — toggle marker visibility client-side.
    document.querySelectorAll('.map-filter-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var name = chip.dataset.courseFilter || '';
            document.querySelectorAll('.map-filter-chip').forEach(function (c) {
                c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
            });
            var visible = L.latLngBounds([]);
            markers.forEach(function (m) {
                var match = !name || m._courseName === name;
                if (match) {
                    m.addTo(pinLayer);
                    visible.extend(m.getLatLng());
                } else {
                    pinLayer.removeLayer(m);
                }
            });
            if (visible.isValid()) {
                map.fitBounds(visible, { padding: [40, 40], maxZoom: 17 });
            }
        });
    });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
})();
</script>
@endif
@endpush
