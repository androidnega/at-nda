{{--
    Attendance Map (rep / lecturer / admin).

    Single shared view backed by AttendanceMapController. The
    controller chooses the layout (classrep vs admin) and ships the
    JSON route URLs in $apiRoutes — everything else (filter UI,
    marker fetching, clustering, popups, summary cards) is rendered
    in the browser so the server is left to do what it does best:
    return small, lazy JSON payloads.

    Performance contract (owner spec, items 4–8 + 14):
      * Layout + summary + map container render INSTANTLY (no DB).
      * Markers load asynchronously via /markers, scoped to the
        current viewport (north/south/east/west bounds).
      * Marker payload is minimal: id / lat / lng / distance / colour /
        session — everything else is fetched lazily on click.
      * Cluster everything: Leaflet.MarkerCluster keeps rendering
        cheap even when thousands of pins fall inside the viewport.
--}}
@extends($layoutFile)

@section('title', $pageTitle)

@push('styles')
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <style>
        .amap-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px;
            box-shadow:0 4px 14px rgba(15,23,42,0.04); }
        .amap-stat-label { font-size:11px; color:#64748b; text-transform:uppercase;
            letter-spacing:0.06em; font-weight:600; }
        .amap-stat-value { font-size:22px; font-weight:700; color:#0f172a; line-height:1.1; }
        .amap-stat-value--sm { font-size:14px; font-weight:600; color:#0f172a; }

        #amap-canvas { width:100%; height:520px; border-radius:14px; z-index:0; background:#eef2f7; }
        @media (max-width: 640px) { #amap-canvas { height: 60vh; min-height: 360px; } }

        /* Coloured pin "dots" — DivIcons keep DOM cost flat compared
           to raster markers, and Marker Cluster handles the rest. */
        .amap-pin { width:14px; height:14px; border-radius:50%;
            border:2px solid #fff; box-shadow:0 1px 3px rgba(15,23,42,.45); }
        .amap-pin--in  { background:#16a34a; }
        .amap-pin--edge{ background:#eab308; }
        .amap-pin--out { background:#dc2626; }

        .amap-anchor { width:18px; height:18px; border-radius:50%;
            background:#0f172a; border:3px solid #fff; box-shadow:0 0 0 3px rgba(15,23,42,.35); }

        .amap-legend { display:flex; gap:10px; align-items:center;
            font-size:11px; color:#475569; }
        .amap-legend i { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:4px; }

        .amap-empty {
            display:none; position:absolute; inset:0;
            background:rgba(255,255,255,0.92);
            color:#64748b; font-size:13px; font-weight:500;
            align-items:center; justify-content:center; text-align:center; padding:24px;
            border-radius:14px;
        }
        .amap-empty.is-visible { display:flex; }

        .amap-banner {
            position:absolute; top:10px; left:50%; transform:translateX(-50%);
            background:rgba(255,255,255,0.96); border:1px solid #e2e8f0;
            border-radius:999px; padding:6px 14px; font-size:11.5px;
            color:#475569; box-shadow:0 4px 12px rgba(15,23,42,0.08);
            display:none; z-index:500;
        }
        .amap-banner.is-visible { display:inline-flex; align-items:center; gap:6px; }
        .amap-banner.is-error { color:#b91c1c; background:#fef2f2; border-color:#fecaca; }

        .amap-field label { display:block; font-size:11px; font-weight:600;
            color:#475569; text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px; }
        .amap-field select,
        .amap-field input[type="date"] {
            width:100%; padding:8px 10px; border:1px solid #cbd5e1;
            border-radius:8px; font-size:13px; background:#fff; color:#0f172a;
        }
        .amap-field select:disabled { background:#f1f5f9; color:#94a3b8; }

        .amap-skeleton {
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
            background-size: 200% 100%; animation: amapShine 1.4s ease-in-out infinite;
            border-radius: 8px; height: 26px; width: 100%;
        }
        @keyframes amapShine { 0%{background-position:200% 0;} 100%{background-position:-200% 0;} }
    </style>
@endpush

@section('content')
<div class="space-y-5">

    {{-- ── Page header ──────────────────────────────────────────── --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Attendance map</h1>
            <p class="text-[12px] text-slate-500 mt-1 max-w-2xl">
                Historical view of GPS-anchored attendance marks. Pick a session to see its anchor,
                radius, and per-student dots. Use the viewport to drill in — only the markers you
                can actually see are pulled from the server.
            </p>
        </div>
        <div class="amap-legend">
            <span><i style="background:#16a34a;"></i>Inside</span>
            <span><i style="background:#eab308;"></i>Edge (10 %)</span>
            <span><i style="background:#dc2626;"></i>Outside</span>
            <span><i style="background:#0f172a;"></i>Anchor</span>
        </div>
    </div>

    {{-- ── Filter bar ───────────────────────────────────────────── --}}
    <div class="amap-card p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="amap-field">
                <label for="amap-f-course">Course</label>
                <select id="amap-f-course"><option value="">All courses</option></select>
            </div>
            <div class="amap-field">
                <label for="amap-f-session">Session</label>
                <select id="amap-f-session"><option value="">All sessions</option></select>
            </div>
            <div class="amap-field">
                <label for="amap-f-student">Student</label>
                <select id="amap-f-student"><option value="">All students</option></select>
            </div>
            <div class="amap-field">
                <label for="amap-f-from">Date from</label>
                <input id="amap-f-from" type="date">
            </div>
            <div class="amap-field">
                <label for="amap-f-to">Date to</label>
                <input id="amap-f-to" type="date">
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 pt-3 border-t border-slate-100">
            <div class="flex flex-wrap gap-2 text-[11px]">
                <button type="button"
                        class="px-2.5 py-1 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                        data-amap-mode="">All modes</button>
                <button type="button"
                        class="px-2.5 py-1 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                        data-amap-mode="location">Location</button>
                <button type="button"
                        class="px-2.5 py-1 rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                        data-amap-mode="hybrid">Hybrid</button>
            </div>
            <button type="button" id="amap-clear"
                    class="text-[12px] font-semibold text-rose-600 hover:text-rose-700">
                Reset filters
            </button>
        </div>
    </div>

    {{-- ── Summary strip (populated by /summary when a session is picked) ── --}}
    <div id="amap-summary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="amap-card p-4">
            <div class="amap-stat-label">Present</div>
            <div class="amap-stat-value" data-amap-stat="present">—</div>
        </div>
        <div class="amap-card p-4">
            <div class="amap-stat-label">Radius</div>
            <div class="amap-stat-value" data-amap-stat="radius">—</div>
        </div>
        <div class="amap-card p-4">
            <div class="amap-stat-label">Average distance</div>
            <div class="amap-stat-value" data-amap-stat="avg">—</div>
        </div>
        <div class="amap-card p-4">
            <div class="amap-stat-label">Closest</div>
            <div class="amap-stat-value--sm" data-amap-stat="closest">—</div>
        </div>
        <div class="amap-card p-4">
            <div class="amap-stat-label">Farthest</div>
            <div class="amap-stat-value--sm" data-amap-stat="farthest">—</div>
        </div>
    </div>

    {{-- ── Map container + overlay states ───────────────────────── --}}
    <div class="amap-card p-3 relative">
        <div id="amap-canvas" aria-label="Attendance map"></div>
        <div class="amap-empty" id="amap-empty">
            No attendance marks match the current filters or viewport.
            Try zooming out or removing a filter.
        </div>
        <div class="amap-banner" id="amap-banner" role="status"></div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin="anonymous" defer></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" defer></script>
    <script>
        // ──────────────────────────────────────────────────────────
        // Attendance Map — pure client-side controller
        // ──────────────────────────────────────────────────────────
        // Everything below runs in the browser. The server is *only*
        // ever asked for small JSON (markers, summary, details,
        // filters). Per the owner spec we never load all markers and
        // never recompute distance on the client.
        // ──────────────────────────────────────────────────────────
        (function () {
            const ROUTES = @json($apiRoutes);
            const AUDIENCE = @json($audience);
            const VIEWPORT_DEBOUNCE_MS = 350;

            // Wait for the deferred Leaflet bundle before doing anything.
            function whenReady(fn) {
                if (window.L && window.L.markerClusterGroup) { fn(); return; }
                setTimeout(() => whenReady(fn), 50);
            }

            whenReady(() => {
                const map = L.map('amap-canvas', {
                    zoomControl: true,
                    preferCanvas: true,        // smoother panning at 500+ pins
                    worldCopyJump: true,
                });
                // Default view: centred globally until first marker batch
                // arrives or a session is selected.
                map.setView([0, 0], 2);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                    crossOrigin: true,
                }).addTo(map);

                const cluster = L.markerClusterGroup({
                    chunkedLoading: true,
                    chunkInterval: 80,
                    spiderfyOnMaxZoom: true,
                    showCoverageOnHover: false,
                    disableClusteringAtZoom: 18,
                    maxClusterRadius: 60,
                });
                map.addLayer(cluster);

                // Layer for anchor + radius (one session at a time).
                let sessionOverlay = null;

                // State of the user-driven filters.
                const filters = { course_id: '', session_id: '', student_id: '', mode: '', date_from: '', date_to: '' };

                // ────────────────────────────────────────────────────
                // Marker fetch + render
                // ────────────────────────────────────────────────────
                let viewportTimer = null;
                let activeRequest = null;

                function colorClass(c) {
                    if (c === 'edge') return 'amap-pin--edge';
                    if (c === 'out') return 'amap-pin--out';
                    return 'amap-pin--in';
                }

                function buildIcon(c) {
                    return L.divIcon({
                        className: 'amap-pin ' + colorClass(c),
                        iconSize: [14, 14],
                        iconAnchor: [7, 7],
                    });
                }

                function setBanner(msg, isError = false) {
                    const el = document.getElementById('amap-banner');
                    if (!msg) { el.classList.remove('is-visible', 'is-error'); el.textContent = ''; return; }
                    el.textContent = msg;
                    el.classList.add('is-visible');
                    el.classList.toggle('is-error', !!isError);
                }
                function setEmpty(show) {
                    document.getElementById('amap-empty').classList.toggle('is-visible', !!show);
                }

                function currentBounds() {
                    const b = map.getBounds();
                    // Leaflet at low zooms (or after panning across the
                    // antimeridian) cheerfully hands back bounds OUTSIDE
                    // the [-90,90] / [-180,180] world frame. Clamp here
                    // so the server isn't asked to validate impossible
                    // coordinates — the database has no rows beyond the
                    // world frame anyway.
                    const clampLat = (v) => Math.max(-90, Math.min(90, v));
                    const clampLng = (v) => Math.max(-180, Math.min(180, v));
                    return {
                        north: clampLat(b.getNorth()),
                        south: clampLat(b.getSouth()),
                        east: clampLng(b.getEast()),
                        west: clampLng(b.getWest()),
                    };
                }

                function queryString(params) {
                    const usp = new URLSearchParams();
                    Object.entries(params).forEach(([k, v]) => {
                        if (v !== '' && v !== null && v !== undefined) usp.append(k, v);
                    });
                    return usp.toString();
                }

                async function fetchMarkers() {
                    if (activeRequest && activeRequest.abort) activeRequest.abort();
                    const ctrl = new AbortController();
                    activeRequest = ctrl;

                    const params = { ...filters, ...currentBounds() };
                    setBanner('Loading markers…');

                    try {
                        const resp = await fetch(ROUTES.markers + '?' + queryString(params), {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            signal: ctrl.signal,
                        });
                        if (!resp.ok) {
                            // Surface the server message when one is available
                            // (the controller logs the stack to laravel.log and
                            // sends back a small JSON body for the UI).
                            let detail = 'HTTP ' + resp.status;
                            try {
                                const body = await resp.json();
                                if (body && body.message) detail += ' — ' + body.message;
                            } catch (_) { /* ignore */ }
                            throw new Error(detail);
                        }
                        const data = await resp.json();
                        renderMarkers(data.points || []);
                        if (data.capped) {
                            setBanner('Showing the most recent ' + data.limit + ' marks — zoom in for the rest.');
                        } else {
                            setBanner('');
                        }
                    } catch (e) {
                        if (e.name === 'AbortError') return;
                        setBanner('Could not load markers (' + (e.message || 'network error') + ').', true);
                    }
                }

                function renderMarkers(points) {
                    cluster.clearLayers();
                    if (!points.length) { setEmpty(true); return; }
                    setEmpty(false);

                    const layers = [];
                    points.forEach((p) => {
                        if (typeof p.la !== 'number' || typeof p.lo !== 'number') return;
                        const m = L.marker([p.la, p.lo], { icon: buildIcon(p.c) });
                        m._amapId = p.id;
                        m.on('click', () => openDetail(m, p));
                        layers.push(m);
                    });
                    cluster.addLayers(layers);
                }

                async function openDetail(marker, point) {
                    const url = ROUTES.details_pattern.replace('__ATTENDANCE__', String(point.id));
                    marker.bindPopup('<div style="min-width:200px;">Loading…</div>').openPopup();
                    try {
                        const resp = await fetch(url, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);
                        const d = await resp.json();
                        const when = d.marked_at ? new Date(d.marked_at).toLocaleString() : '—';
                        const distance = (d.distance === null || d.distance === undefined) ? '—' : (d.distance + ' m');
                        const html =
                            '<div style="min-width:200px;">' +
                            '  <div style="font-weight:700;color:#0f172a;">' + escapeHtml(d.student_name) + '</div>' +
                            '  <div style="color:#475569;font-size:11px;">' + escapeHtml(d.index_number) + '</div>' +
                            '  <div style="margin-top:6px;font-size:11.5px;color:#0f172a;">' +
                            '    <div><b>Course:</b> ' + escapeHtml(d.course && d.course.name || '—') + '</div>' +
                            '    <div><b>Time:</b> ' + escapeHtml(when) + '</div>' +
                            '    <div><b>Distance:</b> ' + escapeHtml(distance) + '</div>' +
                            '    <div><b>Mode:</b> ' + escapeHtml(d.mode || '—') + '</div>' +
                            '  </div>' +
                            '</div>';
                        marker.setPopupContent(html);
                    } catch (e) {
                        marker.setPopupContent('<div style="color:#b91c1c;">Could not load marker.</div>');
                    }
                }

                function escapeHtml(v) {
                    return String(v == null ? '' : v).replace(/[&<>"']/g, (c) =>
                        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
                }

                // ────────────────────────────────────────────────────
                // Summary panel
                // ────────────────────────────────────────────────────
                async function refreshSummary(sessionId) {
                    const cards = document.querySelectorAll('#amap-summary [data-amap-stat]');
                    cards.forEach((el) => { el.textContent = '—'; });
                    drawSessionOverlay(null);
                    if (!sessionId) return;

                    const url = ROUTES.summary_pattern.replace('__SESSION__', String(sessionId));
                    try {
                        const resp = await fetch(url, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!resp.ok) return;
                        const s = await resp.json();
                        const t = s.totals || {};
                        const setStat = (name, value) => {
                            const el = document.querySelector('#amap-summary [data-amap-stat="' + name + '"]');
                            if (el) el.textContent = value;
                        };
                        setStat('present', t.present_count != null ? t.present_count : '—');
                        setStat('radius', (s.session && s.session.radius_m) ? (s.session.radius_m + ' m') : '—');
                        setStat('avg', t.average_distance != null ? (t.average_distance + ' m') : '—');
                        const closestLbl = s.closest_student
                            ? s.closest_student.name + ' · ' + (s.closest_student.distance != null ? (s.closest_student.distance + ' m') : '—')
                            : '—';
                        const farthestLbl = s.farthest_student
                            ? s.farthest_student.name + ' · ' + (s.farthest_student.distance != null ? (s.farthest_student.distance + ' m') : '—')
                            : '—';
                        setStat('closest', closestLbl);
                        setStat('farthest', farthestLbl);

                        drawSessionOverlay(s.session);
                    } catch (e) {
                        // non-fatal — markers can still render
                    }
                }

                function drawSessionOverlay(session) {
                    if (sessionOverlay) { map.removeLayer(sessionOverlay); sessionOverlay = null; }
                    if (!session || !session.anchor || session.anchor.lat == null || session.anchor.lng == null) return;

                    const group = L.layerGroup();
                    const center = [session.anchor.lat, session.anchor.lng];
                    L.marker(center, {
                        icon: L.divIcon({ className: 'amap-anchor', iconSize: [18, 18], iconAnchor: [9, 9] }),
                        interactive: false,
                    }).addTo(group);
                    if (session.radius_m && session.radius_m > 0) {
                        L.circle(center, {
                            radius: session.radius_m,
                            color: '#0f172a', weight: 1.5,
                            fillColor: '#0f172a', fillOpacity: 0.06,
                            interactive: false,
                        }).addTo(group);
                    }
                    sessionOverlay = group;
                    map.addLayer(group);

                    // Recentre if the anchor is outside the current viewport.
                    if (!map.getBounds().contains(center)) {
                        map.setView(center, Math.max(map.getZoom(), 15));
                    }
                }

                // ────────────────────────────────────────────────────
                // Filters
                // ────────────────────────────────────────────────────
                async function loadFilterOptions() {
                    try {
                        const resp = await fetch(ROUTES.filters, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!resp.ok) return;
                        const data = await resp.json();
                        populateSelect('amap-f-course', data.courses, 'All courses');
                        populateSelect('amap-f-session', data.sessions, 'All sessions');
                        populateSelect('amap-f-student', data.students, 'All students');
                    } catch (e) {
                        // dropdowns simply stay empty
                    }
                }

                function populateSelect(id, items, placeholder) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.innerHTML = '';
                    const opt = document.createElement('option');
                    opt.value = ''; opt.textContent = placeholder;
                    el.appendChild(opt);
                    (items || []).forEach((it) => {
                        const o = document.createElement('option');
                        o.value = String(it.id);
                        o.textContent = it.label;
                        el.appendChild(o);
                    });
                }

                function bindFilters() {
                    document.getElementById('amap-f-course').addEventListener('change', (e) => {
                        filters.course_id = e.target.value;
                        scheduleFetch();
                    });
                    document.getElementById('amap-f-session').addEventListener('change', (e) => {
                        filters.session_id = e.target.value;
                        refreshSummary(filters.session_id);
                        scheduleFetch();
                    });
                    document.getElementById('amap-f-student').addEventListener('change', (e) => {
                        filters.student_id = e.target.value;
                        scheduleFetch();
                    });
                    document.getElementById('amap-f-from').addEventListener('change', (e) => {
                        filters.date_from = e.target.value;
                        scheduleFetch();
                    });
                    document.getElementById('amap-f-to').addEventListener('change', (e) => {
                        filters.date_to = e.target.value;
                        scheduleFetch();
                    });
                    document.querySelectorAll('[data-amap-mode]').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            filters.mode = btn.getAttribute('data-amap-mode') || '';
                            document.querySelectorAll('[data-amap-mode]').forEach((b) =>
                                b.classList.toggle('ring-2', b === btn));
                            scheduleFetch();
                        });
                    });
                    document.getElementById('amap-clear').addEventListener('click', () => {
                        ['amap-f-course', 'amap-f-session', 'amap-f-student'].forEach((id) => {
                            const el = document.getElementById(id); if (el) el.value = '';
                        });
                        document.getElementById('amap-f-from').value = '';
                        document.getElementById('amap-f-to').value = '';
                        document.querySelectorAll('[data-amap-mode]').forEach((b) => b.classList.remove('ring-2'));
                        Object.keys(filters).forEach((k) => filters[k] = '');
                        refreshSummary('');
                        scheduleFetch();
                    });
                }

                // ────────────────────────────────────────────────────
                // Wiring
                // ────────────────────────────────────────────────────
                function scheduleFetch() {
                    if (viewportTimer) clearTimeout(viewportTimer);
                    viewportTimer = setTimeout(fetchMarkers, VIEWPORT_DEBOUNCE_MS);
                }

                map.on('moveend zoomend', scheduleFetch);

                loadFilterOptions();
                bindFilters();
                // Kick off an initial fetch with no filters; bounds are world-
                // scale, so the response simply returns the most recent
                // MAX_MARKERS marks — perfect for orienting the user.
                fetchMarkers();
            });
        })();
    </script>
@endpush
