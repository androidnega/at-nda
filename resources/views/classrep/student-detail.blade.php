@extends('layouts.classrep')

@section('title', $student->index_number . ' - Student')

@section('content')
<div class="mb-4">
    <a href="{{ route('dashboard.students.index') }}" class="text-gray-500 hover:text-gray-700 text-xs mb-1.5 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left text-[10px]"></i> Students
    </a>
    <div class="flex items-start gap-3 mt-1">
        @if($student->profileImageUrl())
        <img src="{{ $student->profileImageUrl() }}" alt="" class="h-11 w-11 rounded-full object-cover border border-gray-200 flex-shrink-0">
        @else
        <span class="h-11 w-11 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold flex-shrink-0">{{ $student->avatarInitials() }}</span>
        @endif
        <div class="min-w-0">
            <h1 class="text-lg sm:text-xl font-bold text-gray-900 leading-snug">
                @if($student->getDisplayName() !== '')
                    {{ $student->getDisplayName() }}
                @else
                    <span class="font-mono text-base">{{ $student->index_number }}</span>
                @endif
            </h1>
            <p class="text-gray-500 text-xs mt-0.5 flex items-center gap-1.5 flex-wrap">
                @if($student->getDisplayName() !== '')
                    <span class="font-mono">{{ $student->index_number }}</span>
                @endif
                @if($isRepStudent)
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700">Rep</span>
                @endif
                @if($student->getDisplayName() !== '')
                    <span class="text-gray-400">·</span>
                @endif
                <span>{{ $student->getProgramLabel() }}</span>
            </p>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 p-2.5 bg-green-50 text-green-800 rounded-lg border border-green-100 flex items-center gap-2 text-xs">
        <i class="fas fa-check-circle text-green-600 text-sm"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 p-2.5 bg-red-50 text-red-800 rounded-lg border border-red-100 flex items-center gap-2 text-xs">
        <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
        {{ session('error') }}
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4">
    <div class="bg-white rounded-lg border border-gray-200 p-3 flex items-center gap-2.5">
        <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-book text-xs"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Class courses</p>
            <p class="text-lg font-bold text-gray-800 tabular-nums leading-tight">{{ $coursesCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-3 flex items-center gap-2.5">
        <span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0">
            <i class="fas fa-clipboard-check text-xs"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Attendance marks</p>
            <p class="text-lg font-bold text-gray-800 tabular-nums leading-tight">{{ $attendanceRecordsCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-3 flex items-center gap-2.5">
        <span class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center text-violet-600 flex-shrink-0">
            <i class="fas fa-chart-line text-xs"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Courses w/ marks</p>
            <p class="text-lg font-bold text-gray-800 tabular-nums leading-tight">{{ $coursesWithMarks }}</p>
        </div>
    </div>
</div>

@if($scheduledWeekRows > 0)
<p class="text-[11px] text-gray-500 mb-4 -mt-2">
    <i class="fas fa-info-circle text-gray-400 mr-0.5"></i>
    Across class courses there are <span class="font-medium text-gray-600 tabular-nums">{{ $scheduledWeekRows }}</span> scheduled week row{{ $scheduledWeekRows === 1 ? '' : 's' }} in the system (not the same as attendance marks).
</p>
@endif

<div class="grid gap-4 lg:grid-cols-2 mb-4">
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-3 py-2 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Contact &amp; account</h2>
        </div>
        <dl class="divide-y divide-gray-100 text-xs">
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Phone</dt>
                <dd class="text-gray-900 text-right">{{ $student->phone_number ? $student->phone_number : '—' }}</dd>
            </div>
            @if(filled($student->bound_ip))
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Bound IP</dt>
                <dd class="text-gray-900 text-right font-mono text-[11px]">{{ $student->bound_ip }}</dd>
            </div>
            @endif
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Password</dt>
                <dd class="text-gray-900 text-right">{{ $hasPassword ? 'Set' : 'Not set' }}</dd>
            </div>
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Last updated</dt>
                <dd class="text-gray-900 text-right tabular-nums">{{ $student->updated_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-3 py-2 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800">Class &amp; programme</h2>
        </div>
        <dl class="divide-y divide-gray-100 text-xs">
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Faculty</dt>
                <dd class="text-gray-900 text-right">{{ $student->schoolClass?->faculty?->name ?? $student->department?->faculty?->name ?? '—' }}</dd>
            </div>
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Department</dt>
                <dd class="text-gray-900 text-right">{{ $student->schoolClass?->department?->name ?? $student->department?->name ?? '—' }}</dd>
            </div>
            <div class="px-3 py-2.5 flex justify-between gap-3">
                <dt class="text-gray-500 shrink-0">Level</dt>
                <dd class="text-gray-900 text-right">{{ $student->schoolClass?->level !== null ? 'Level ' . $student->schoolClass->level : '—' }}</dd>
            </div>
        </dl>
    </div>
</div>

@if(count($missingProfileFields) > 0)
<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2.5 text-xs text-amber-900">
    <p class="font-semibold text-amber-950 mb-1"><i class="fas fa-user-edit mr-1"></i> Profile incomplete</p>
    <p class="text-amber-800/90">Missing: {{ collect($missingProfileFields)->map(fn ($f) => str_replace('_', ' ', $f))->join(', ') }}</p>
</div>
@endif

@if($repAssignments)
<div class="mb-4 bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-3 py-2 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Rep roles</h2>
        <p class="text-[11px] text-gray-500 mt-0.5">This student’s representative assignments.</p>
    </div>
    <div class="p-3 space-y-3 text-xs">
        @if($repAssignments['classReps']->isNotEmpty())
        <div>
            <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mb-1.5">Class rep</p>
            <ul class="space-y-1.5">
                @foreach($repAssignments['classReps'] as $cr)
                <li class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span class="font-medium text-gray-800">{{ $cr->schoolClass?->name ?? 'Class #' . $cr->class_id }}</span>
                    <span class="text-gray-400">·</span>
                    <span class="text-gray-600">{{ $cr->role === \App\Models\ClassRep::ROLE_ASSIST ? 'Assist' : 'Main rep' }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endif

<div class="mb-4 bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-3 py-2 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
        <div>
            <h2 class="text-sm font-semibold text-gray-800">Attendance by course</h2>
            <p class="text-[11px] text-gray-500 mt-0.5">Marks recorded for this student in each class course.</p>
        </div>
        <a href="{{ route('dashboard.class-attendance.index') }}" class="text-[11px] font-medium text-primary shrink-0">Open attendance hub</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/80 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-3 py-2">Course</th>
                    <th class="px-3 py-2 text-right tabular-nums">Marks</th>
                    <th class="px-3 py-2 w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($attendanceByCourse as $row)
                <tr class="hover:bg-gray-50/80">
                    <td class="px-3 py-2.5">
                        <span class="font-medium text-gray-900">{{ $row['course']->course_name }}</span>
                        @if($row['course']->course_code)
                            <span class="text-gray-500">({{ $row['course']->course_code }})</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-medium text-gray-800">{{ $row['count'] }}</td>
                    <td class="px-3 py-2.5 text-right">
                        <a href="{{ route('dashboard.class-attendance.course', $row['course']) }}" class="text-primary font-medium hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-3 py-8 text-center text-gray-500">No courses for this class yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mb-4 bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-3 py-2 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Recent attendance</h2>
        <p class="text-[11px] text-gray-500 mt-0.5">Latest marks (newest first).</p>
    </div>
    <ul class="divide-y divide-gray-100">
        @forelse($recentAttendances as $att)
        @php
            $device = $att->deviceLabel();
            $ip = trim((string) ($att->device_ip ?? ''));
            $hasGeo = !is_null($att->lat) && !is_null($att->lng);
            $fp = (string) ($att->device_fingerprint ?? '');
            $fpShort = $fp !== '' ? substr($fp, 0, 10) : '';
            $clientMeta = is_array($att->client_meta) ? $att->client_meta : [];
            $mapUrl = $hasGeo
                ? 'https://www.google.com/maps?q='.urlencode(number_format((float) $att->lat, 6).','.number_format((float) $att->lng, 6))
                : null;
        @endphp
        <li class="px-3 py-2.5 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                <div class="min-w-0">
                    <span class="font-medium text-gray-900">{{ $att->course?->course_name ?? 'Course' }}</span>
                    @if($att->attendanceWeek)
                        <span class="text-gray-500"> · Week {{ $att->attendanceWeek->week_number }}</span>
                        @if($att->attendanceWeek->week_date)
                            <span class="text-gray-400">({{ $att->attendanceWeek->week_date->format('M j, Y') }})</span>
                        @endif
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-500 shrink-0">
                    <span class="tabular-nums">{{ $att->attendance_time?->format('M j, Y g:i A') ?? '—' }}</span>
                    <span class="inline-flex items-center rounded px-1.5 py-0.5 font-medium bg-gray-100 text-gray-700">{{ ucfirst((string) ($att->status ?? 'present')) }}</span>
                </div>
            </div>
            @if($device !== '—' || $ip !== '' || $hasGeo || $fpShort !== '' || !empty($clientMeta))
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10.5px] text-gray-500">
                @if($device !== '—')
                <span class="inline-flex items-center gap-1" title="{{ $att->user_agent }}">
                    <i class="fas fa-mobile-screen text-gray-400 text-[10px]"></i>{{ $device }}
                </span>
                @endif
                @if($ip !== '')
                <span class="inline-flex items-center gap-1 font-mono">
                    <i class="fas fa-network-wired text-gray-400 text-[10px]"></i>{{ $ip }}
                </span>
                @endif
                @if($hasGeo)
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-mono text-blue-700 hover:text-blue-900" title="Open in Google Maps">
                    <i class="fas fa-location-dot text-blue-500/80 text-[10px]"></i>{{ number_format((float) $att->lat, 5) }}, {{ number_format((float) $att->lng, 5) }}
                </a>
                @endif
                @if($fpShort !== '')
                <span class="inline-flex items-center gap-1 font-mono text-amber-700/90" title="Device fingerprint (first 10 chars of a 1-year persistent cookie). Survives Wi-Fi changes and private windows. Same code on two students = same physical device.">
                    <i class="fas fa-fingerprint text-amber-500/90 text-[10px]"></i>{{ $fpShort }}
                </span>
                @endif
                @if(!empty($clientMeta['platform']))
                <span class="inline-flex items-center gap-1" title="Browser-reported OS / device platform">
                    <i class="fas fa-microchip text-gray-400 text-[10px]"></i>{{ $clientMeta['platform'] }}
                </span>
                @endif
                @if(!empty($clientMeta['screen']))
                <span class="inline-flex items-center gap-1 font-mono" title="Screen resolution at mark-time">
                    <i class="fas fa-display text-gray-400 text-[10px]"></i>{{ $clientMeta['screen'] }}
                </span>
                @endif
                @if(!empty($clientMeta['tz']))
                <span class="inline-flex items-center gap-1" title="Browser-reported time zone">
                    <i class="fas fa-clock text-gray-400 text-[10px]"></i>{{ $clientMeta['tz'] }}
                </span>
                @endif
                @if(!empty($clientMeta['lang']))
                <span class="inline-flex items-center gap-1 uppercase tracking-wider" title="Browser language">
                    <i class="fas fa-language text-gray-400 text-[10px]"></i>{{ $clientMeta['lang'] }}
                </span>
                @endif
            </div>
            @endif
        </li>
        @empty
        <li class="px-3 py-8 text-center text-gray-500">No attendance marks yet.</li>
        @endforelse
    </ul>
</div>

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-3 py-2 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Reset password</h2>
        <p class="text-[11px] text-gray-500 mt-0.5">Generate a new password for this student. Copy and share it with them.</p>
    </div>
    <form method="POST" action="{{ route('dashboard.students.reset-password', $student) }}" class="p-3">
        @csrf
        <button type="submit" class="inline-flex items-center gap-1.5 bg-primary text-white px-3 py-2 rounded-lg text-xs font-medium hover:bg-primary/90" onclick="return confirm('Generate a new password? The current password will be replaced.')">
            <i class="fas fa-key text-[10px]"></i> Generate password
        </button>
    </form>
</div>
@endsection
