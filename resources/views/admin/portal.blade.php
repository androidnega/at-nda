@extends('layouts.admin')

@section('title', 'Open Portal')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">Open Portal</h1>
    <p class="text-gray-600 text-sm mt-1">Select a course and open an attendance session</p>
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-xl">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">{{ session('error') }}</div>
@endif

@if($courses->isEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-500">
        No courses yet. <a href="{{ route('dashboard.courses.create') }}" class="text-blue-600 hover:underline">Create a course</a> first.
    </div>
@else
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Open Session Form --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Open Session</h2>
                <p class="text-sm text-gray-500 mt-0.5">Select course, set time and range</p>
            </div>
            <form action="{{ route('dashboard.sessions.store') }}" method="POST" class="p-5 space-y-4">
                @csrf
                <div>
                    <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                    <select name="course_id" id="course_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                        <option value="">Select course...</option>
                        @foreach($courses as $course)
                        <option value="{{ $course->id }}"
                            data-default-lat="{{ $course->location_lat !== null ? e($course->location_lat) : '' }}"
                            data-default-lng="{{ $course->location_lng !== null ? e($course->location_lng) : '' }}"
                            data-default-range="{{ $course->attendance_range_m !== null ? e($course->attendance_range_m) : '' }}"
                            {{ old('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->course_name }}{{ $course->course_code ? ' (' . $course->course_code . ')' : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="mode" class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
                        <select name="mode" id="session-mode" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                            <option value="location">Location</option>
                            <option value="qr">QR Code</option>
                            <option value="hybrid">Hybrid (Both)</option>
                        </select>
                    </div>
                    <div>
                        <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-2">Duration (min)</label>
                        <input type="number" name="duration_minutes" id="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="5" max="480" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div id="location-fields" class="space-y-4 p-4 bg-gray-50 rounded-xl">
                    <p id="location-fields-help" class="text-sm text-gray-600">Students must be within this distance to mark attendance. Your location is used automatically when you open the session.</p>
                    <div class="max-w-xs">
                        <label for="attendance_range_m" class="block text-sm font-medium text-gray-700 mb-2">Range</label>
                        <input type="number" name="attendance_range_m" id="attendance_range_m" value="{{ old('attendance_range_m', 100) }}" min="10" max="500" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500" placeholder="e.g. 100">
                        <p class="text-xs text-gray-500 mt-1">Distance in meters (e.g. 100 = about a classroom)</p>
                    </div>
                    <input type="hidden" name="location_lat" id="location_lat">
                    <input type="hidden" name="location_lng" id="location_lng">
                </div>

                <button type="submit" id="open-session-btn" class="w-full sm:w-auto bg-green-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-green-700">Open Session</button>
            </form>
        </div>
    </div>

    {{-- Active Sessions --}}
    <div class="lg:sticky lg:top-6 self-start">
        <div class="rounded-2xl overflow-hidden border border-blue-100/90 bg-gradient-to-b from-white via-sky-50/30 to-white shadow-xl shadow-sky-200/25 ring-1 ring-sky-100/50">
            <div class="px-5 py-4 border-b border-sky-100/80 bg-gradient-to-r from-sky-500/5 to-indigo-500/5">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    <h2 class="font-semibold text-gray-900 tracking-tight">Live attendance</h2>
                </div>
                <p class="text-xs text-gray-500 mt-1">Countdown until session closes</p>
            </div>
            <div class="p-4 space-y-4 max-h-[min(70vh,32rem)] overflow-y-auto">
                @php $hasActive = false; @endphp
                @foreach($courses as $course)
                    @php $activeSession = $course->activeSession(); @endphp
                    @if($activeSession)
                        @php $hasActive = true; $expiresIso = $activeSession->expires_at?->toIso8601String(); @endphp
                        <div
                            class="group rounded-xl border border-white/80 bg-white/95 backdrop-blur-sm p-4 shadow-sm hover:shadow-md transition-all duration-300"
                            data-session-countdown
                            data-expires="{{ $expiresIso }}"
                        >
                            <div class="mb-3">
                                <p class="font-semibold text-gray-900 leading-snug line-clamp-2">{{ $course->course_name }}</p>
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-semibold uppercase tracking-wide bg-slate-100 text-slate-600">
                                        Week {{ $activeSession->attendanceWeek?->week_number ?? '—' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-semibold uppercase tracking-wide bg-blue-100 text-blue-700">
                                        {{ $activeSession->mode }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4 mb-4 text-center ring-1 ring-white/10">
                                <p class="text-[10px] font-medium uppercase tracking-widest text-slate-400 mb-1" data-countdown-label>Time remaining</p>
                                <p class="text-3xl sm:text-4xl font-mono font-bold tabular-nums tracking-tight text-white" data-countdown-display>—:—</p>
                                @if($activeSession->expires_at)
                                    <p class="text-[10px] text-slate-500 mt-2">Until {{ $activeSession->expires_at->format('g:i A') }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if(in_array($activeSession->mode, ['qr', 'hybrid']))
                                    <a href="{{ route('dashboard.sessions.qr', $activeSession) }}" target="_blank" class="flex-1 min-w-[4rem] inline-flex justify-center items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition">
                                        <i class="fas fa-qrcode"></i> QR
                                    </a>
                                @endif
                                <a href="{{ route('web.attendance.form', $course) }}" target="_blank" class="flex-1 min-w-[4rem] inline-flex justify-center items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-800 hover:bg-slate-200 transition">
                                    <i class="fas fa-clipboard-check"></i> Form
                                </a>
                                <form action="{{ route('dashboard.sessions.close', $activeSession) }}" method="POST" class="flex-1 min-w-full sm:min-w-0 sm:flex-none" onsubmit="return confirm('Close this session?');">
                                    @csrf
                                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold border border-rose-200 text-rose-600 hover:bg-rose-50 transition">
                                        <i class="fas fa-stop"></i> Close
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endforeach
                @if(!$hasActive)
                    <div class="text-center py-10 px-4 rounded-xl border border-dashed border-sky-200/80 bg-sky-50/40">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-500 mb-3">
                            <i class="fas fa-hourglass-start text-lg"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-700">No session open</p>
                        <p class="text-xs text-gray-500 mt-1">Open one from the left when ready</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var form = document.querySelector('form[action*="sessions.store"]');
    var mode = document.getElementById('session-mode');
    var loc = document.getElementById('location-fields');
    var btn = document.getElementById('open-session-btn');
    var help = document.getElementById('location-fields-help');
    if (!mode || !loc || !form) return;

    function needsAnchor() {
        var m = mode.value;
        return m === 'location' || m === 'hybrid';
    }

    function toggle() {
        var need = needsAnchor();
        loc.style.display = need ? 'block' : 'none';
        loc.setAttribute('aria-hidden', need ? 'false' : 'true');
        var rangeEl = document.getElementById('attendance_range_m');
        if (rangeEl) {
            if (need) rangeEl.setAttribute('required', 'required');
            else rangeEl.removeAttribute('required');
        }
        if (help) {
            help.textContent = need
                ? 'Students must be within this distance to mark attendance. Your location is used automatically when you open the session.'
                : 'QR-only mode does not use GPS. Switch to Location or Hybrid to set an anchor and range.';
        }
        if (!need) {
            var la = document.getElementById('location_lat');
            var ln = document.getElementById('location_lng');
            if (la) la.value = '';
            if (ln) ln.value = '';
        }
    }
    mode.addEventListener('change', toggle);
    toggle();

    form.addEventListener('submit', function(e) {
        if (!needsAnchor()) return;
        var latEl = document.getElementById('location_lat');
        var lngEl = document.getElementById('location_lng');
        var rangeEl = document.getElementById('attendance_range_m');
        if (latEl && latEl.value && lngEl && lngEl.value) return;
        var courseSel = document.getElementById('course_id');
        var opt = courseSel && courseSel.options[courseSel.selectedIndex];
        var dla = opt && opt.getAttribute('data-default-lat');
        var dln = opt && opt.getAttribute('data-default-lng');
        var dr = opt && opt.getAttribute('data-default-range');
        if (dla && dln && latEl && lngEl) {
            latEl.value = String(dla);
            lngEl.value = String(dln);
            if (rangeEl && dr !== null && dr !== '' && !isNaN(parseInt(dr, 10))) {
                rangeEl.value = String(parseInt(dr, 10));
            }
            return;
        }
        if (latEl && latEl.value) return;
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Getting your location...';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('location_lat').value = pos.coords.latitude;
                document.getElementById('location_lng').value = pos.coords.longitude;
                form.submit();
            },
            function() {
                btn.disabled = false;
                btn.textContent = 'Open Session';
                alert('Could not get your location. Set a default location on the course (Courses → edit) or allow location access.');
            }
        );
    });
})();
</script>
@endif
@include('partials.session-countdown-script')
@endsection
