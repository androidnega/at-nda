@extends('layouts.classrep')

@section('title', 'Attendance map')

@push('styles')
{{-- Leaflet (OpenStreetMap) — no API key required. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<style>
    /* ──────────────── Map chrome ────────────────
       Per-student pins are CSS-only DivIcons so we can animate them
       on hover without a marker plugin. Tints by capture mode. */
    .rep-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04); overflow: hidden; }
    .rep-card__head { padding: 0.85rem 1.1rem; border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap; }
    .rep-card__head h2 { font-size: 0.95rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em; }
    .rep-card__head p { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
    .rep-card__icon { width: 36px; height: 36px; border-radius: 0.75rem;
        display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; }
    .rep-card__icon--rose { background: #ffe4e6; color: #be123c; }

    #rep-attendance-map { width: 100%; height: 100%; border-radius: 0; z-index: 0; }
    #rep-attendance-map .leaflet-control-attribution { font-size: 10px;
        background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(6px); }

    .rep-pin {
        width: 22px; height: 22px; border-radius: 50%; background: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.18), 0 6px 14px rgba(2,132,199,0.35);
        border: 2px solid #ffffff; cursor: pointer; position: relative;
        transition: transform 220ms cubic-bezier(.2,.8,.2,1), box-shadow 220ms cubic-bezier(.2,.8,.2,1);
    }
    .rep-pin::after {
        content: ""; position: absolute; inset: -10px; border-radius: 50%;
        background: radial-gradient(circle, rgba(14,165,233,0.25) 0%, rgba(14,165,233,0) 70%);
        opacity: 0; transition: opacity 220ms ease; pointer-events: none;
    }
    .rep-pin:hover { transform: scale(1.45);
        box-shadow: 0 0 0 5px rgba(14,165,233,0.25), 0 10px 22px rgba(2,132,199,0.45); z-index: 800; }
    .rep-pin:hover::after { opacity: 1; }

    .rep-pin--qr     { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18), 0 6px 14px rgba(79,70,229,0.35); }
    .rep-pin--hybrid { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.18), 0 6px 14px rgba(217,119,6,0.35); }
    .rep-pin--wifi   { background: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,0.18), 0 6px 14px rgba(13,148,136,0.35); }
    .rep-pin--qr:hover::after     { background: radial-gradient(circle, rgba(99,102,241,0.28) 0%, rgba(99,102,241,0) 70%); }
    .rep-pin--hybrid:hover::after { background: radial-gradient(circle, rgba(245,158,11,0.30) 0%, rgba(245,158,11,0) 70%); }
    .rep-pin--wifi:hover::after   { background: radial-gradient(circle, rgba(20,184,166,0.28) 0%, rgba(20,184,166,0) 70%); }

    @keyframes repPinPulse {
        0% { box-shadow: 0 0 0 3px rgba(14,165,233,0.5), 0 6px 14px rgba(2,132,199,0.35); }
        70% { box-shadow: 0 0 0 14px rgba(14,165,233,0), 0 6px 14px rgba(2,132,199,0.35); }
        100% { box-shadow: 0 0 0 3px rgba(14,165,233,0.18), 0 6px 14px rgba(2,132,199,0.35); }
    }
    .rep-pin--fresh { animation: repPinPulse 1.8s ease-out infinite; }

    .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18); }
    .leaflet-popup-content { margin: 10px 14px; font-family: Inter, system-ui, sans-serif;
        font-size: 12.5px; line-height: 1.45; color: #1e293b; }
    .leaflet-popup-content b { color: #0f172a; }
    .leaflet-popup-content .pop-meta { margin-top: 4px; color: #64748b; font-size: 11px; }
    .leaflet-popup-content .pop-chip {
        display: inline-flex; align-items: center; gap: 4px; margin-top: 6px;
        padding: 2px 8px; border-radius: 999px; background: #f1f5f9; color: #475569;
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
    }

    .map-filter-chip { cursor: pointer; user-select: none;
        transition: background 160ms ease, color 160ms ease, transform 160ms ease; }
    .map-filter-chip:hover { transform: translateY(-1px); }
    .map-filter-chip[aria-pressed="true"] { background: #0f172a; color: #ffffff; }
</style>
@endpush

@section('content')
@php
    $mapPoints = collect($attendanceMapPoints ?? []);
    $uniqueCourses = $mapPoints->pluck('course')->filter()->unique()->values();
    $modeCounts = $mapPoints->groupBy('mode')->map->count();
@endphp

<div class="w-full min-w-0 space-y-5">
    {{-- Header: title + time-window selector --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight inline-flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                    <i class="fas fa-map-location-dot text-base"></i>
                </span>
                Attendance map
            </h1>
            <p class="text-slate-500 text-sm mt-1.5">Every student mark on a map, last {{ $days }} days · {{ $mapPoints->count() }} pin{{ $mapPoints->count() === 1 ? '' : 's' }}.</p>
        </div>
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 self-start sm:self-end">
            @foreach($allowedWindows as $w)
                <a href="{{ route('dashboard.attendance-map', ['days' => $w]) }}"
                   class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors {{ $w === $days ? 'bg-primary text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ $w }}d
                </a>
            @endforeach
        </div>
    </div>

    {{-- Mode legend chips (always show — zero counts are useful info) --}}
    <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-sky-50 text-sky-700">
            <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span> Location · {{ $modeCounts->get('location', 0) }}
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700">
            <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span> QR · {{ $modeCounts->get('qr', 0) }}
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700">
            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span> Hybrid · {{ $modeCounts->get('hybrid', 0) }}
        </span>
        @if($modeCounts->get('wifi', 0) > 0)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-teal-50 text-teal-700">
                <span class="inline-block w-2 h-2 rounded-full bg-teal-500"></span> Wi-Fi · {{ $modeCounts->get('wifi', 0) }}
            </span>
        @endif
        <span class="text-slate-400 ml-1">·</span>
        <span class="inline-flex items-center gap-1.5 text-slate-500">
            <span class="inline-block w-2.5 h-2.5 rounded-full ring-2 ring-white" style="background:#0ea5e9; box-shadow:0 0 0 2px rgba(14,165,233,0.5);"></span>
            Pulsing pins = marked in the last 30 min
        </span>
    </div>

    {{-- Main map card with course filter --}}
    <div class="rep-card">
        @if($uniqueCourses->isNotEmpty())
            <div class="px-4 sm:px-5 py-3 border-b border-slate-100 bg-slate-50/40">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 shrink-0">Filter by course</span>
                    <button type="button" class="map-filter-chip px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" data-course-filter="" aria-pressed="true">All ({{ $mapPoints->count() }})</button>
                    @foreach($uniqueCourses as $c)
                        @php $n = $mapPoints->where('course', $c)->count(); @endphp
                        <button type="button" class="map-filter-chip px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" data-course-filter="{{ $c }}" aria-pressed="false">
                            {{ \Illuminate\Support\Str::limit($c, 30) }} · {{ $n }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if($mapPoints->isEmpty())
            <div class="py-20 text-center">
                <span class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <i class="fas fa-map-pin text-2xl"></i>
                </span>
                <p class="text-base font-semibold text-slate-700">No location data in the last {{ $days }} days</p>
                <p class="text-sm text-slate-500 mt-1.5 max-w-md mx-auto">When students mark from a <strong>Location</strong> or <strong>Hybrid</strong> session, their pins will appear here. Try widening the window above, or open a Location/Hybrid session to start collecting data.</p>
                <a href="{{ route('dashboard.session') }}" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90">
                    <i class="fas fa-play-circle"></i> Open a session
                </a>
            </div>
        @else
            <div class="relative h-[70vh] min-h-[480px] bg-slate-50">
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

    var pinLayer = L.layerGroup().addTo(map);
    var bounds = L.latLngBounds([]);
    var markers = [];
    var freshCutoff = Date.now() - 30 * 60 * 1000;

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
