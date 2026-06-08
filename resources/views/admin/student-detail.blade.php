@extends('layouts.admin')

@section('title', $student->index_number . ' - Student')

@section('content')
<div class="mb-6">
    <a href="{{ request()->headers->get('referer') ?: route('dashboard.students.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <div class="flex flex-col sm:flex-row sm:items-start gap-4 mt-2">
        @if($student->profileImageUrl())
        <img src="{{ $student->profileImageUrl() }}" alt="" class="h-20 w-20 rounded-full object-cover border border-gray-200 flex-shrink-0">
        @else
        <span class="h-20 w-20 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xl font-semibold flex-shrink-0">{{ $student->avatarInitials() }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl sm:text-3xl font-bold text-primary">
                @if($student->getDisplayName() !== '')
                    {{ $student->getDisplayName() }}
                @else
                    <span class="font-mono text-xl sm:text-2xl">{{ $student->index_number }}</span>
                @endif
            </h1>
            <p class="text-gray-500 text-sm mt-1 flex items-center gap-2 flex-wrap">
                @if($student->getDisplayName() !== '')
                    <span class="font-mono text-gray-800">{{ $student->index_number }}</span>
                @endif
                @if($student->isRep())
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Rep</span>
                @endif
                <span class="text-gray-400">·</span>
                <span>{{ $student->getProgramLabel() }}</span>
            </p>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-6 p-4 bg-red-50 text-red-800 rounded-xl border border-red-100 flex items-center gap-2">
        <i class="fas fa-exclamation-circle text-red-600"></i>
        {{ session('error') }}
    </div>
@endif

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-book text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Courses (class)</p>
            <p class="text-2xl font-bold text-gray-800">{{ $coursesCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-700 flex-shrink-0">
            <i class="fas fa-check text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Present marks</p>
            <p class="text-2xl font-bold text-gray-800">{{ $presentCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-700 flex-shrink-0">
            <i class="fas fa-times text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Absent (est.)</p>
            <p class="text-2xl font-bold text-gray-800">{{ $absentCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center text-sky-700 flex-shrink-0">
            <i class="fas fa-layer-group text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Class</p>
            <p class="text-lg font-bold text-gray-800 truncate">{{ $student->schoolClass?->name ?? '—' }}</p>
        </div>
    </div>
</div>

{{-- Identity & account --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Profile &amp; account</h2>
        <p class="text-sm text-gray-500 mt-0.5">All stored fields for this student.</p>
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-0 sm:divide-x sm:divide-gray-100">
        <div class="p-5 space-y-4 sm:border-b border-gray-100">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Index number</dt>
                <dd class="mt-1 font-mono text-sm text-gray-900">{{ $student->index_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">First name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->first_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Middle name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->middle_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Last name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->last_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->phone_number ?? '—' }}</dd>
            </div>
        </div>
        <div class="p-5 space-y-4 border-t sm:border-t-0 border-gray-100">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Program (from index)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->getProgramLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Department (onboarding)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->department?->name ?? '—' }}</dd>
            </div>
            @if($student->department?->faculty)
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Faculty (onboarding)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->department->faculty->name }}</dd>
            </div>
            @endif
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Bound IP</dt>
                <dd class="mt-1 font-mono text-sm text-gray-900">{{ $student->bound_ip ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Push notifications</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->deviceToken ? 'Device token registered' : 'Not registered' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Record</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <span class="block">Created {{ $student->created_at?->format('Y-m-d H:i') ?? '—' }}</span>
                    <span class="block text-gray-600">Updated {{ $student->updated_at?->format('Y-m-d H:i') ?? '—' }}</span>
                </dd>
            </div>
        </div>
    </dl>
</div>

{{-- Class enrollment --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Class enrollment</h2>
    </div>
    <div class="p-5">
        @if($student->schoolClass)
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Class name</dt>
                    <dd class="mt-1 font-medium text-gray-900">{{ $student->schoolClass->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Level</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->level ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Faculty</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->faculty?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Department</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->department?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Semester</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->semester?->display_label ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Week rows (class courses)</dt>
                    <dd class="mt-1 tabular-nums text-gray-900">{{ $totalWeeks }}</dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-gray-600">No class assigned.</p>
        @endif
    </div>
</div>

@if($student->classReps->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Class rep assignments</h2>
        <p class="text-sm text-gray-500 mt-0.5">Rep roles per class.</p>
    </div>
    <ul class="divide-y divide-gray-100">
        @foreach($student->classReps as $cr)
        <li class="px-5 py-3 flex items-center justify-between gap-3">
            <span class="font-medium">{{ $cr->schoolClass?->name ?? '—' }}</span>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-xs {{ $cr->isMainRep() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">{{ $cr->isMainRep() ? 'Rep' : 'Assist' }}</span>
                <form action="{{ route('dashboard.students.remove-rep', $student) }}" method="POST" class="inline" onsubmit="return confirm('Remove rep assignment?')">
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $cr->class_id }}">
                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Remove</button>
                </form>
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

{{-- Audit trail for this student: every login, mark, manual mark, fraud
     flag, deletion, etc. Only admins land on this page so we can show
     IPs / device fingerprints without privacy concerns. Click any row
     for the full detail modal. --}}
@if(($auditAvailable ?? false) && isset($studentLogs))
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-shield-halved text-primary/80"></i>
                Security &amp; activity log
            </h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Latest {{ $studentLogs->count() }} events for this student — logins, marks, manual marks, fraud flags and deletions. Tap any row for the full detail.
            </p>
        </div>
        @if($studentLogs->isNotEmpty() && \Illuminate\Support\Facades\Route::has('dashboard.audit-logs.index'))
            <a href="{{ route('dashboard.audit-logs.index', ['search' => $student->index_number]) }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline">
                Open in full audit log <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
            </a>
        @endif
    </div>
    <div class="p-3 sm:p-4">
        @include('_partials.audit-log-table', ['logs' => $studentLogs, 'available' => true, 'actions' => $auditActions ?? []])
    </div>
</div>
@endif

@if(isset($recentAttendance) && $recentAttendance->isNotEmpty())
@php
    /*
     * Build a JS-friendly payload for the detail modal.
     *
     * We do this in PHP so the page ships everything needed to render
     * the per-attendance detail (including the embedded Leaflet pin)
     * without an extra AJAX call per row. Each entry is keyed by the
     * attendance ID and contains the audit fields the admin needs:
     * coordinates, device/IP/fingerprint/user-agent, client meta,
     * manual-mark provenance, and the session's capture mode.
     */
    $attendanceDetailPayload = $recentAttendance->mapWithKeys(function ($a) {
        $hasGeo = ! is_null($a->lat) && ! is_null($a->lng);
        $mode = $a->attendanceSession?->capture_mode
            ?? $a->attendanceSession?->mode
            ?? null;
        $manualBy = null;
        if ($a->isManuallyMarked() && $a->markedManuallyBy) {
            $by = $a->markedManuallyBy;
            $manualBy = trim($by->first_name.' '.$by->last_name)
                .' ('.$by->index_number.', rep)';
        } elseif ($a->isManuallyMarkedByLecturer() && $a->markedManuallyByLecturer) {
            $lec = $a->markedManuallyByLecturer;
            $manualBy = ($lec->name ?: $lec->email).' (lecturer)';
        }

        return [(int) $a->id => [
            'id' => (int) $a->id,
            'course' => $a->course?->course_name,
            'course_code' => $a->course?->course_code,
            'week' => $a->attendanceWeek?->week_number,
            'status' => (string) ($a->status ?? ''),
            'time' => $a->attendance_time?->format('Y-m-d H:i:s'),
            'check_in' => $a->check_in_time?->format('Y-m-d H:i:s'),
            'check_out' => $a->check_out_time?->format('Y-m-d H:i:s'),
            'time_spent_seconds' => $a->time_spent_seconds ? (int) $a->time_spent_seconds : null,
            'lat' => $hasGeo ? (float) $a->lat : null,
            'lng' => $hasGeo ? (float) $a->lng : null,
            'device' => method_exists($a, 'deviceLabel') ? $a->deviceLabel() : null,
            'ip' => $a->device_ip ?: null,
            'fingerprint' => $a->device_fingerprint ?: null,
            'user_agent' => $a->user_agent ?: null,
            'device_id' => $a->device_id ?: null,
            'qr_code' => $a->qr_code ?: null,
            'client_meta' => is_array($a->client_meta) ? $a->client_meta : null,
            'manual_by' => $manualBy,
            'manual_reason' => $a->manual_reason ?: null,
            'manual_at' => $a->marked_manually_at?->format('Y-m-d H:i:s'),
            'capture_mode' => $mode,
        ]];
    });

    // For the overview map: only rows that actually carry coordinates.
    $attendanceMapPoints = $attendanceDetailPayload
        ->filter(fn ($r) => $r['lat'] !== null && $r['lng'] !== null)
        ->values();
@endphp

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="font-semibold text-gray-900">Recent attendance</h2>
            <p class="text-sm text-gray-500 mt-0.5">Latest {{ $recentAttendance->count() }} marks. Tap any row for full audit detail (coordinates, IP, device, fingerprint).</p>
        </div>
        @if($attendanceMapPoints->isNotEmpty())
            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full border border-emerald-100">
                <i class="fas fa-location-dot text-[10px]"></i> {{ $attendanceMapPoints->count() }} geo-tagged
            </span>
        @endif
    </div>

    @if($attendanceMapPoints->isNotEmpty())
        {{-- Overview map: pin every geo-tagged attendance from this list.
             Clicking a pin opens the same detail modal as clicking a row,
             so the admin can move fluidly between spatial + tabular views. --}}
        <div id="student-attendance-overview-map"
             class="h-64 sm:h-72 bg-slate-100 border-b border-gray-200"
             aria-label="Map of recent attendance locations"></div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Course</th>
                    <th class="px-4 py-3">Week</th>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Location</th>
                    <th class="px-4 py-3">Device / IP</th>
                    <th class="px-4 py-3 text-right">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentAttendance as $a)
                @php
                    $hasGeo = !is_null($a->lat) && !is_null($a->lng);
                    $device = method_exists($a, 'deviceLabel') ? $a->deviceLabel() : '—';
                    $ip = trim((string) ($a->device_ip ?? ''));
                    $fp = (string) ($a->device_fingerprint ?? '');
                    $fpShort = $fp !== '' ? substr($fp, 0, 10) : '';
                    $clientMeta = is_array($a->client_meta) ? $a->client_meta : [];
                    $mapUrl = $hasGeo
                        ? 'https://www.google.com/maps?q='.urlencode(number_format((float) $a->lat, 6).','.number_format((float) $a->lng, 6))
                        : null;
                @endphp
                <tr class="hover:bg-gray-50/80 cursor-pointer" data-att-row="{{ $a->id }}">
                    <td class="px-4 py-3 text-gray-900">{{ $a->course?->course_name ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums text-gray-700">W{{ $a->attendanceWeek?->week_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $a->attendance_time?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="text-gray-800">{{ $a->status ?? '—' }}</span></td>
                    <td class="px-4 py-3 text-xs">
                        @if($hasGeo)
                            <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-mono text-blue-700 hover:text-blue-900"
                               onclick="event.stopPropagation();">
                                <i class="fas fa-location-dot text-blue-500/80 text-[10px]"></i>
                                {{ number_format((float) $a->lat, 5) }}, {{ number_format((float) $a->lng, 5) }}
                            </a>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <div class="flex flex-col gap-0.5">
                            @if($device !== '—')
                                <span class="text-gray-700" title="{{ $a->user_agent }}"><i class="fas fa-mobile-screen text-gray-400 text-[10px] mr-1"></i>{{ $device }}</span>
                            @endif
                            @if(filled($ip))
                                <span class="font-mono text-gray-500"><i class="fas fa-network-wired text-gray-400 text-[10px] mr-1"></i>{{ $ip }}</span>
                            @endif
                            @if($fpShort !== '')
                                <span class="font-mono text-amber-700/90" title="Persistent device fingerprint (first 10 chars). Same code on two students = one physical device.">
                                    <i class="fas fa-fingerprint text-amber-500/90 text-[10px] mr-1"></i>{{ $fpShort }}
                                </span>
                            @endif
                            @if(!empty($clientMeta['platform']) || !empty($clientMeta['screen']) || !empty($clientMeta['tz']))
                                <span class="text-gray-500" title="Browser-reported signals at mark-time">
                                    <i class="fas fa-microchip text-gray-400 text-[10px] mr-1"></i>
                                    @if(!empty($clientMeta['platform'])){{ $clientMeta['platform'] }}@endif
                                    @if(!empty($clientMeta['screen'])) · {{ $clientMeta['screen'] }}@endif
                                    @if(!empty($clientMeta['tz'])) · {{ $clientMeta['tz'] }}@endif
                                </span>
                            @endif
                            @if($device === '—' && !filled($ip) && $fpShort === '')
                                <span class="text-gray-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" data-att-open="{{ $a->id }}"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary/80 px-2 py-1 rounded hover:bg-primary/5">
                            <i class="fas fa-magnifying-glass text-[10px]"></i> View
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ─────────────── Per-attendance detail modal ───────────────
     Singleton modal: rendered once and populated from the JS payload
     when a row's "View" button is clicked. Contains the embedded
     Leaflet pin + every audit field the admin might need to
     investigate a disputed mark.                                  --}}
<div id="att-detail-modal" class="hidden fixed inset-0 z-[70] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-3 sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="att-detail-title">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
        <div class="flex items-start justify-between gap-3 p-4 sm:p-5 border-b border-gray-100">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500" data-att-status>Attendance detail</p>
                <h3 id="att-detail-title" class="text-base sm:text-lg font-bold text-gray-900 truncate" data-att-course>—</h3>
                <p class="text-xs text-gray-500 mt-0.5" data-att-when>—</p>
            </div>
            <button type="button" data-att-close
                    class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-100">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <div id="att-detail-map" class="h-56 bg-slate-100 border-b border-gray-200 hidden"></div>
            <div data-att-nogeo class="px-5 py-4 text-xs text-gray-500 border-b border-gray-100 hidden bg-amber-50/40">
                <i class="fas fa-circle-info text-amber-600 mr-1"></i>
                No coordinates were captured for this attendance — likely a QR-only or manual mark.
            </div>

            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4 text-sm">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Latitude</p>
                    <p class="font-mono text-gray-900" data-att-lat>—</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Longitude</p>
                    <p class="font-mono text-gray-900" data-att-lng>—</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Open in maps</p>
                    <p class="text-xs"><a href="#" target="_blank" rel="noopener"
                                          class="text-blue-700 hover:text-blue-900 break-all" data-att-mapurl>—</a></p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">IP address</p>
                    <p class="font-mono text-gray-900" data-att-ip>—</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Device</p>
                    <p class="text-gray-900" data-att-device>—</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Device fingerprint</p>
                    <p class="font-mono text-amber-700 break-all text-xs" data-att-fingerprint>—</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">User agent</p>
                    <p class="text-gray-700 text-xs break-all" data-att-ua>—</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Capture mode</p>
                    <p class="text-gray-900" data-att-mode>—</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">QR token</p>
                    <p class="font-mono text-gray-700 text-xs break-all" data-att-qr>—</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Check-in</p>
                    <p class="text-gray-900" data-att-checkin>—</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Check-out</p>
                    <p class="text-gray-900" data-att-checkout>—</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Client signals (browser-reported)</p>
                    <div class="mt-1 text-xs text-gray-700 grid grid-cols-2 gap-x-3 gap-y-1" data-att-clientmeta>
                        <span class="text-gray-400 col-span-2">—</span>
                    </div>
                </div>
                <div class="sm:col-span-2 hidden" data-att-manual-wrap>
                    <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-800">Manually marked</p>
                        <p class="text-sm text-amber-900 mt-1" data-att-manual-by>—</p>
                        <p class="text-xs text-amber-800 mt-1" data-att-manual-when>—</p>
                        <p class="text-xs text-amber-900 italic mt-1" data-att-manual-reason></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
<style>
    /* Re-use the project-wide pin language from classrep/attendance-map.
       Tinted variants per capture mode so the overview map reads at a
       glance — qr=indigo, hybrid=amber, wifi=teal, default=sky. */
    .att-pin { width: 18px; height: 18px; border-radius: 50%; background: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.18), 0 4px 10px rgba(2,132,199,0.35);
        border: 2px solid #ffffff; cursor: pointer;
        transition: transform 200ms cubic-bezier(.2,.8,.2,1); }
    .att-pin:hover { transform: scale(1.35); }
    .att-pin--qr     { background: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18), 0 4px 10px rgba(79,70,229,0.35); }
    .att-pin--hybrid { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.18), 0 4px 10px rgba(217,119,6,0.35); }
    .att-pin--wifi   { background: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,0.18), 0 4px 10px rgba(13,148,136,0.35); }
    #student-attendance-overview-map .leaflet-control-attribution,
    #att-detail-map .leaflet-control-attribution { font-size: 10px; background: rgba(255,255,255,0.7); }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<script>
(function () {
    'use strict';

    // Single in-page registry of attendance audit payloads, keyed by ID.
    // Built server-side so the modal can render without an extra request.
    const ATTENDANCE_DATA = @json($attendanceDetailPayload);
    const MAP_POINTS = @json($attendanceMapPoints);

    if (typeof L === 'undefined') return;

    const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const tileAttr = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

    function pinClassFor(mode) {
        if (!mode) return '';
        const m = String(mode).toLowerCase();
        if (m.indexOf('hybrid') === 0) return ' att-pin--hybrid';
        if (m.indexOf('qr') === 0) return ' att-pin--qr';
        if (m.indexOf('wifi') === 0) return ' att-pin--wifi';
        return '';
    }

    function fmtTime(iso) {
        if (!iso) return '—';
        return iso.replace('T', ' ');
    }

    // ───── Overview map ─────
    let overviewMap = null;
    const overviewEl = document.getElementById('student-attendance-overview-map');
    if (overviewEl && MAP_POINTS.length > 0) {
        overviewMap = L.map(overviewEl, {
            zoomControl: true,
            scrollWheelZoom: false,
            tap: true,
        });
        L.tileLayer(tileUrl, { attribution: tileAttr, maxZoom: 19 }).addTo(overviewMap);

        const bounds = [];
        MAP_POINTS.forEach(function (p) {
            const icon = L.divIcon({
                className: '',
                html: '<div class="att-pin' + pinClassFor(p.capture_mode) + '"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9],
            });
            const marker = L.marker([p.lat, p.lng], { icon: icon }).addTo(overviewMap);
            const popupHtml = '<b>' + (p.course || 'Attendance') + '</b>'
                + '<div class="pop-meta">' + fmtTime(p.time) + '</div>'
                + '<div class="pop-meta">W' + (p.week || '—') + ' &middot; ' + (p.status || '—') + '</div>'
                + '<a href="#" data-att-popup-open="' + p.id + '"'
                + ' style="display:inline-block;margin-top:6px;font-weight:600;color:#0369a1;">Full detail →</a>';
            marker.bindPopup(popupHtml);
            bounds.push([p.lat, p.lng]);
        });

        if (bounds.length === 1) {
            overviewMap.setView(bounds[0], 16);
        } else {
            overviewMap.fitBounds(bounds, { padding: [28, 28] });
        }

        // Click-through from popup "Full detail →" link to the modal.
        overviewMap.on('popupopen', function (e) {
            const link = e.popup.getElement().querySelector('[data-att-popup-open]');
            if (!link) return;
            link.addEventListener('click', function (ev) {
                ev.preventDefault();
                const id = parseInt(link.getAttribute('data-att-popup-open'), 10);
                openDetail(id);
            });
        });
    }

    // ───── Detail modal ─────
    const modal = document.getElementById('att-detail-modal');
    if (!modal) return;
    const closeBtns = modal.querySelectorAll('[data-att-close]');
    const mapEl = modal.querySelector('#att-detail-map');
    const noGeo = modal.querySelector('[data-att-nogeo]');
    let detailMap = null;
    let detailMarker = null;

    function fillText(selector, value) {
        const el = modal.querySelector(selector);
        if (!el) return;
        el.textContent = (value === null || value === undefined || value === '') ? '—' : String(value);
    }

    function openDetail(id) {
        const data = ATTENDANCE_DATA[id];
        if (!data) return;

        const courseLine = data.course
            ? (data.course + (data.course_code ? ' · ' + data.course_code : ''))
            : '—';
        fillText('[data-att-course]', courseLine);

        const status = data.status ? data.status.toUpperCase() : 'ATTENDANCE';
        fillText('[data-att-status]', 'Week ' + (data.week || '—') + ' · ' + status);

        fillText('[data-att-when]', data.time ? fmtTime(data.time) : '—');
        fillText('[data-att-lat]', data.lat !== null ? data.lat.toFixed(7) : null);
        fillText('[data-att-lng]', data.lng !== null ? data.lng.toFixed(7) : null);

        const mapLink = modal.querySelector('[data-att-mapurl]');
        if (data.lat !== null && data.lng !== null) {
            const url = 'https://www.google.com/maps?q=' + data.lat.toFixed(6) + ',' + data.lng.toFixed(6);
            mapLink.href = url;
            mapLink.textContent = url;
        } else {
            mapLink.href = '#';
            mapLink.textContent = '—';
        }

        fillText('[data-att-ip]', data.ip);
        fillText('[data-att-device]', data.device);
        fillText('[data-att-fingerprint]', data.fingerprint);
        fillText('[data-att-ua]', data.user_agent);
        fillText('[data-att-mode]', data.capture_mode);
        fillText('[data-att-qr]', data.qr_code);
        fillText('[data-att-checkin]', data.check_in ? fmtTime(data.check_in) : null);
        fillText('[data-att-checkout]', data.check_out ? fmtTime(data.check_out) : null);

        const cmEl = modal.querySelector('[data-att-clientmeta]');
        cmEl.innerHTML = '';
        if (data.client_meta && Object.keys(data.client_meta).length > 0) {
            Object.keys(data.client_meta).forEach(function (k) {
                const dt = document.createElement('span');
                dt.className = 'text-gray-500 uppercase tracking-wide text-[10px] font-semibold';
                dt.textContent = k;
                const dd = document.createElement('span');
                dd.className = 'text-gray-800 break-all';
                dd.textContent = String(data.client_meta[k]);
                cmEl.appendChild(dt);
                cmEl.appendChild(dd);
            });
        } else {
            const empty = document.createElement('span');
            empty.className = 'text-gray-400 col-span-2';
            empty.textContent = '—';
            cmEl.appendChild(empty);
        }

        // Manual mark provenance.
        const manualWrap = modal.querySelector('[data-att-manual-wrap]');
        if (data.manual_by) {
            manualWrap.classList.remove('hidden');
            fillText('[data-att-manual-by]', data.manual_by);
            fillText('[data-att-manual-when]', data.manual_at ? ('at ' + fmtTime(data.manual_at)) : null);
            const reason = modal.querySelector('[data-att-manual-reason]');
            reason.textContent = data.manual_reason ? ('“' + data.manual_reason + '”') : '';
        } else {
            manualWrap.classList.add('hidden');
        }

        // Embedded map (or graceful "no coords" notice).
        if (data.lat !== null && data.lng !== null) {
            noGeo.classList.add('hidden');
            mapEl.classList.remove('hidden');
        } else {
            noGeo.classList.remove('hidden');
            mapEl.classList.add('hidden');
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        // Initialise / re-centre the embedded map. Defer to next frame
        // so Leaflet measures the now-visible container (it returns
        // 0×0 if we init while still display:none).
        if (data.lat !== null && data.lng !== null) {
            requestAnimationFrame(function () {
                if (!detailMap) {
                    detailMap = L.map(mapEl, {
                        zoomControl: true,
                        scrollWheelZoom: false,
                        tap: true,
                    });
                    L.tileLayer(tileUrl, { attribution: tileAttr, maxZoom: 19 }).addTo(detailMap);
                }
                const icon = L.divIcon({
                    className: '',
                    html: '<div class="att-pin' + pinClassFor(data.capture_mode) + '"></div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                });
                if (detailMarker) detailMap.removeLayer(detailMarker);
                detailMarker = L.marker([data.lat, data.lng], { icon: icon }).addTo(detailMap);
                detailMap.setView([data.lat, data.lng], 17);
                // Leaflet sometimes needs a kick when its container
                // becomes visible mid-init.
                setTimeout(function () { detailMap.invalidateSize(); }, 60);
            });
        }
    }

    function closeDetail() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    closeBtns.forEach(function (btn) { btn.addEventListener('click', closeDetail); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeDetail();
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeDetail(); });

    // Bind row + button triggers.
    document.querySelectorAll('[data-att-open]').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            const id = parseInt(btn.getAttribute('data-att-open'), 10);
            openDetail(id);
        });
    });
    document.querySelectorAll('[data-att-row]').forEach(function (row) {
        row.addEventListener('click', function () {
            const id = parseInt(row.getAttribute('data-att-row'), 10);
            openDetail(id);
        });
    });
})();
</script>
@endpush
@endif

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Assign as class rep</h2>
        <p class="text-sm text-gray-500 mt-0.5">Rep role applies only to this student’s own class.</p>
    </div>
    <div class="px-5 pt-5">
        <div class="rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm text-gray-700">
            <p class="font-semibold text-gray-900 mb-2 flex items-center gap-1.5"><i class="fas fa-circle-info text-primary"></i> Role privileges</p>
            <ul class="space-y-2">
                <li class="flex items-start gap-2">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold mt-0.5">REP</span>
                    <span><strong>Class Rep</strong> — full control: builds the per-class timetable, opens and closes attendance sessions, uploads course outlines/materials, manages the class roster, edits cancelled weeks. Attendance is auto-marked when a session opens.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-sky-100 text-sky-800 text-[10px] font-bold mt-0.5">ASST</span>
                    <span><strong>Assistant Rep</strong> — read/support access: views attendance and rosters for the class, can help moderate live sessions and download exports, but <em>cannot</em> open/close sessions or change the timetable. Attendance is also auto-marked when a session opens, so they don’t need to mark themselves in.</span>
                </li>
            </ul>
        </div>
    </div>
    <form method="POST" action="{{ route('dashboard.students.assign-rep', $student) }}" class="p-5 flex flex-wrap gap-4 items-end">
        @csrf
        <div class="min-w-[200px] flex-1">
            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select id="class_id" name="class_id" required class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary">
                <option value="">Select class...</option>
                @forelse($repAssignableClasses ?? [] as $c)
                <option value="{{ $c->id }}" selected>{{ $c->name }}</option>
                @empty
                <option value="" disabled>Student has no class — assign a class first</option>
                @endforelse
            </select>
        </div>
        <div class="min-w-[140px]">
            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select id="role" name="role" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20">
                <option value="rep">Class Rep — full control</option>
                <option value="assist">Assistant Rep — read & support</option>
            </select>
        </div>
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-primary/90">Assign</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Reset password</h2>
        <p class="text-sm text-gray-500 mt-0.5">Generate a new password for this student. Copy and share it with them.</p>
    </div>
    <form method="POST" action="{{ route('dashboard.students.reset-password', $student) }}" class="p-5">
        @csrf
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-primary/90" onclick="return confirm('Generate a new password? The current password will be replaced.')">
            <i class="fas fa-key mr-1"></i> Generate password
        </button>
    </form>
</div>

@if(session()->has('admin_id'))
<div class="rounded-xl border border-red-200 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-red-100 bg-red-50">
        <h2 class="font-semibold text-red-950">Remove student</h2>
        <p class="text-sm text-red-900/90 mt-0.5">Permanently deletes this student, rep assignments, attendance marks, and device registration. This cannot be undone.</p>
    </div>
    <div class="p-5">
        <form method="POST" action="{{ route('dashboard.students.destroy', $student) }}" onsubmit="return confirm('Permanently delete this student ({{ e($student->index_number) }})? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border-2 border-red-600 bg-white text-red-700 px-5 py-2.5 text-sm font-semibold hover:bg-red-50">
                <i class="fas fa-user-minus"></i> Remove student
            </button>
        </form>
    </div>
</div>
@endif
@endsection
