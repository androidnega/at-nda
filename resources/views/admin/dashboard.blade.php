@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $maxCount = max($last7Days->max('count') ?: 1, 1);
    $period = $period ?? 'month';
    $periodLabel = $periodLabel ?? 'This month';
    $modeTone = [
        'location' => ['bg' => 'bg-sky-500',     'soft' => 'bg-sky-100',     'text' => 'text-sky-700'],
        'qr'       => ['bg' => 'bg-indigo-500',  'soft' => 'bg-indigo-100',  'text' => 'text-indigo-700'],
        'hybrid'   => ['bg' => 'bg-amber-500',   'soft' => 'bg-amber-100',   'text' => 'text-amber-700'],
        'wifi'     => ['bg' => 'bg-teal-500',    'soft' => 'bg-teal-100',    'text' => 'text-teal-700'],
    ];
@endphp

<div class="space-y-6" data-admin-dashboard>
    {{-- ───── Toolbar: search + period + download ───── --}}
    <div class="rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-md">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input
                    type="text"
                    id="admin-dash-search"
                    placeholder="Search top students by name or ID…"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-slate-300 focus:outline-none"
                />
            </div>
            <div class="flex items-center gap-2 self-end lg:self-auto">
                {{-- Period switcher — submits as ?period=… to keep the URL
                     shareable. Each option re-runs the controller so
                     every tile reflects the chosen window. --}}
                <div class="relative" data-period-dropdown>
                    <button type="button" id="period-toggle" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">
                        {{ $periodLabel }} <i class="fas fa-chevron-down ml-1 text-[10px]"></i>
                    </button>
                    <div id="period-menu" class="hidden absolute right-0 top-full mt-1.5 w-44 rounded-xl bg-white border border-slate-200 shadow-lg py-1 z-30">
                        @foreach(['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'all' => 'All-time'] as $key => $label)
                            <a href="{{ url()->current() }}?period={{ $key }}"
                               class="block px-3 py-2 text-xs font-medium {{ $period === $key ? 'bg-sky-50 text-sky-700' : 'text-slate-700 hover:bg-slate-50' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('dashboard.attendance.export', ['period' => $period]) }}"
                   class="rounded-xl bg-sky-500 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-600">
                    <i class="fas fa-download mr-1"></i> Download CSV
                </a>
            </div>
        </div>
    </div>

    {{-- ───── Headline tiles ───── --}}
    <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
        <div class="mb-4 flex items-start gap-3">
            <span class="hidden sm:flex w-11 h-11 rounded-xl bg-sky-100 text-sky-700 items-center justify-center shrink-0 ring-1 ring-sky-200/60">
                <i class="fas fa-chart-pie text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-800 inline-flex items-center gap-2">
                    Attendance Details
                </h1>
                <p class="text-sm text-slate-500 mt-1 inline-flex items-center gap-1.5">
                    <i class="fas fa-circle-info text-[11px] text-slate-400"></i>
                    {{ $periodLabel }} · Operational snapshot for attendance, courses, and students.
                </p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
            @php
                $detailTiles = [
                    ['label' => 'Today',         'value' => $attendanceToday,    'caption' => 'Marks recorded today',   'icon' => 'fa-calendar-day',     'tone' => 'bg-sky-100 text-sky-700'],
                    ['label' => $periodLabel,    'value' => $periodAttendances,  'caption' => 'Marks in this window',   'icon' => 'fa-clock',            'tone' => 'bg-indigo-100 text-indigo-700'],
                    ['label' => 'Live sessions', 'value' => $liveSessionsCount,  'caption' => 'Open right now',         'icon' => 'fa-broadcast-tower',  'tone' => 'bg-rose-100 text-rose-700'],
                    ['label' => 'Courses',       'value' => $totalCourses,       'caption' => 'Active courses',         'icon' => 'fa-book-open',        'tone' => 'bg-amber-100 text-amber-700'],
                    ['label' => 'Students',      'value' => $totalStudents,      'caption' => 'Registered students',    'icon' => 'fa-user-graduate',    'tone' => 'bg-emerald-100 text-emerald-700'],
                    ['label' => 'App downloads', 'value' => $appDownloadCount ?? 0, 'caption' => 'Android APK installs', 'icon' => 'fa-android',          'tone' => 'bg-teal-100 text-teal-700', 'fab' => true],
                ];
            @endphp
            @foreach($detailTiles as $tile)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 flex items-start gap-3">
                    <span class="w-10 h-10 rounded-lg {{ $tile['tone'] }} flex items-center justify-center shrink-0">
                        <i class="{{ !empty($tile['fab']) ? 'fab' : 'fas' }} {{ $tile['icon'] }} text-base"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 truncate">{{ $tile['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-800 tabular-nums leading-tight">{{ number_format((int) $tile['value']) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 truncate">{{ $tile['caption'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        {{-- ───── Left column ───── --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                        <i class="fas fa-calendar-week"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-500 inline-flex items-center gap-1.5">Active days (last 7)</h3>
                        <p class="mt-0.5 text-xs text-slate-400 inline-flex items-center gap-1">
                            <i class="far fa-calendar text-[10px]"></i> Days with at least one attendance mark
                        </p>
                        <p class="mt-3 text-4xl font-semibold text-slate-700 tabular-nums">{{ $last7Days->filter(fn($d) => $d['count'] > 0)->count() }} <span class="text-2xl text-slate-400">/ 7</span></p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-slate-700 inline-flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                            <i class="fas fa-chart-line text-sm"></i>
                        </span>
                        Attendance Rate
                    </h3>
                    <span class="rounded-md bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-600 inline-flex items-center gap-1">
                        <i class="far fa-clock text-[10px]"></i> {{ $periodLabel }}
                    </span>
                </div>
                <p class="mb-4 text-5xl font-semibold text-slate-700 tabular-nums">{{ $attendanceRate }}%</p>
                <p class="text-xs text-slate-500 mb-3">Last 7 days · daily marks</p>
                <div class="space-y-2">
                    @foreach($last7Days as $day)
                        <div class="flex items-center gap-3">
                            <span class="w-9 text-xs font-medium text-slate-500">{{ $day['label'] }}</span>
                            <div class="h-2 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-sky-500 transition-all" style="width: {{ ($day['count'] / $maxCount) * 100 }}%"></div>
                            </div>
                            <span class="w-8 text-right text-xs font-semibold text-slate-600 tabular-nums">{{ $day['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Capture-mode breakdown — stacked bar + legend. --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-500 mb-2 inline-flex items-center gap-2">
                    <span class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                        <i class="fas fa-shuffle text-sm"></i>
                    </span>
                    By capture mode
                </h3>
                <p class="text-xs text-slate-400 mb-3">{{ $periodLabel }} · share of marks per method</p>
                <div class="flex h-3 w-full rounded-full overflow-hidden bg-slate-100">
                    @foreach($modeBreakdown as $m)
                        @if($m['count'] > 0)
                            @php $tone = $modeTone[$m['mode']] ?? $modeTone['location']; @endphp
                            <div class="h-full {{ $tone['bg'] }}" style="width: {{ ($m['count'] / $totalModeCount) * 100 }}%"
                                title="{{ $m['label'] }} · {{ $m['count'] }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    @foreach($modeBreakdown as $m)
                        @php $tone = $modeTone[$m['mode']] ?? $modeTone['location']; @endphp
                        <div class="flex items-center justify-between rounded-lg {{ $tone['soft'] }} px-2.5 py-1.5">
                            <span class="inline-flex items-center gap-1.5 {{ $tone['text'] }} font-semibold">
                                <span class="w-2 h-2 rounded-full {{ $tone['bg'] }}"></span> {{ $m['label'] }}
                            </span>
                            <span class="tabular-nums {{ $tone['text'] }} font-bold">{{ $m['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Faculty breakdown (only when data is wired). --}}
            @if($facultyBreakdown->isNotEmpty())
                @php $facMax = max($facultyBreakdown->max('count') ?: 1, 1); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-500 mb-3 inline-flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center">
                            <i class="fas fa-university text-sm"></i>
                        </span>
                        Students by faculty (top 5)
                    </h3>
                    <div class="space-y-2">
                        @foreach($facultyBreakdown as $f)
                            <div>
                                <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                                    <span class="truncate">{{ $f['name'] }}</span>
                                    <span class="font-semibold text-slate-700 tabular-nums">{{ $f['count'] }}</span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-rose-400" style="width: {{ ($f['count'] / $facMax) * 100 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ───── Right column ───── --}}
        <div class="xl:col-span-3 space-y-6">
            {{-- Summary tiles (kept for quick at-a-glance reference). --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-2xl font-semibold text-slate-700 mb-4 inline-flex items-center gap-2">
                    <span class="w-9 h-9 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center">
                        <i class="fas fa-layer-group text-sm"></i>
                    </span>
                    Summary
                </h3>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @php
                        $summaryTiles = [
                            ['icon' => 'fa-clipboard-check', 'tone' => 'sky',   'label' => 'All-time',  'value' => $totalAttendances],
                            ['icon' => 'fa-calendar-day',    'tone' => 'lime',  'label' => 'Today',     'value' => $attendanceToday],
                            ['icon' => 'fa-book-open',       'tone' => 'amber', 'label' => 'Courses',   'value' => $totalCourses],
                            ['icon' => 'fa-user-graduate',   'tone' => 'rose',  'label' => 'Students',  'value' => $totalStudents],
                        ];
                        $toneMap = [
                            'sky'   => ['bg' => 'bg-sky-100',   'badge' => 'bg-sky-200/70',   'text' => 'text-sky-700'],
                            'lime'  => ['bg' => 'bg-lime-100',  'badge' => 'bg-lime-200/70',  'text' => 'text-lime-700'],
                            'amber' => ['bg' => 'bg-amber-100', 'badge' => 'bg-amber-200/70', 'text' => 'text-amber-700'],
                            'rose'  => ['bg' => 'bg-rose-100',  'badge' => 'bg-rose-200/70', 'text' => 'text-rose-700'],
                        ];
                    @endphp
                    @foreach($summaryTiles as $tile)
                        @php $t = $toneMap[$tile['tone']]; @endphp
                        <div class="rounded-2xl {{ $t['bg'] }} p-4 text-center relative overflow-hidden">
                            <span class="absolute top-2.5 right-2.5 w-8 h-8 rounded-lg {{ $t['badge'] }} {{ $t['text'] }} flex items-center justify-center">
                                <i class="fas {{ $tile['icon'] }} text-xs"></i>
                            </span>
                            <p class="text-2xl font-bold {{ $t['text'] }} tabular-nums">{{ number_format((int) $tile['value']) }}</p>
                            <p class="mt-2 text-xs font-semibold {{ $t['text'] }}">{{ $tile['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Top courses by attendance — was fetched but never rendered before. --}}
            @if($attendanceByCourse->isNotEmpty())
                @php $maxCourse = max($attendanceByCourse->max('attendances_count') ?: 1, 1); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-2xl font-semibold text-slate-700 inline-flex items-center gap-2">
                            <span class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                                <i class="fas fa-book-open text-sm"></i>
                            </span>
                            Top Courses
                        </h3>
                        <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
                    </div>
                    <div class="space-y-3">
                        @foreach($attendanceByCourse as $course)
                            @php $pct = round(($course->attendances_count / $maxCourse) * 100); @endphp
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-700 truncate">
                                        {{ $course->course_name }}
                                        @if($course->course_code)
                                            <span class="text-xs text-slate-400 font-mono ml-1">{{ $course->course_code }}</span>
                                        @endif
                                    </span>
                                    <span class="font-semibold text-slate-700 tabular-nums">{{ $course->attendances_count }}</span>
                                </div>
                                <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Top students table — filterable by the toolbar search. --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-2xl font-semibold text-slate-700 inline-flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                            <i class="fas fa-trophy text-sm"></i>
                        </span>
                        Top Attendance Students
                    </h3>
                    <span class="text-xs text-slate-400">{{ $periodLabel }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[580px]" id="top-students-table">
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                <th class="py-3 pr-3">No.</th>
                                <th class="py-3 pr-3">Name</th>
                                <th class="py-3 pr-3">ID</th>
                                <th class="py-3">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($topStudents as $i => $stu)
                                @php
                                    $maxTop = max($topStudents->max('attendances_count') ?: 1, 1);
                                    $progress = round(($stu->attendances_count / $maxTop) * 100);
                                    $display = $stu->getDisplayName() !== '' ? $stu->getDisplayName() : $stu->index_number;
                                @endphp
                                <tr data-name="{{ strtolower($display.' '.$stu->index_number) }}">
                                    <td class="py-3 pr-3 text-sm font-semibold text-slate-500">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="py-3 pr-3 text-sm font-medium text-slate-700">{{ $display }}</td>
                                    <td class="py-3 pr-3 text-sm text-slate-500 font-mono">{{ $stu->index_number }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 w-full max-w-[180px] rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full bg-sky-500" style="width: {{ $progress }}%"></div>
                                            </div>
                                            <span class="text-sm font-semibold text-slate-600 tabular-nums">{{ $stu->attendances_count }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-sm text-slate-500">No attendance data for this window.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Live activity feed — recent marks + audit log side by side. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3 inline-flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center">
                            <i class="fas fa-stream text-sm"></i>
                        </span>
                        Recent attendance
                    </h3>
                    <ul class="space-y-2.5">
                        @forelse($recentActivity as $row)
                            @php
                                $name = trim(($row->student?->first_name ?? '').' '.($row->student?->last_name ?? ''));
                                if ($name === '') $name = $row->student?->index_number ?? '—';
                            @endphp
                            <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5">
                                <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <i class="fas fa-arrow-down-left text-xs"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $name }}</p>
                                    <p class="text-xs text-slate-500 truncate">
                                        {{ $row->course?->course_name ?? '—' }}
                                        @if($row->course?->course_code)<span class="font-mono ml-1">· {{ $row->course->course_code }}</span>@endif
                                    </p>
                                </div>
                                <span class="text-[11px] text-slate-400 shrink-0 tabular-nums">{{ optional($row->attendance_time)->diffForHumans() ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500 text-center py-6">No marks yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3 inline-flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center">
                            <i class="fas fa-clock-rotate-left text-sm"></i>
                        </span>
                        Audit trail
                    </h3>
                    <ul class="space-y-2.5">
                        @forelse($recentAudit as $log)
                            <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5">
                                <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                                    <i class="fas fa-shield-halved text-xs"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $log->action }}</p>
                                    <p class="text-xs text-slate-500 truncate">
                                        {{ $log->actor_name ?? '—' }}
                                        @if($log->actor_role)<span class="px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide bg-slate-100 text-slate-600 rounded-md ml-1">{{ $log->actor_role }}</span>@endif
                                    </p>
                                </div>
                                <span class="text-[11px] text-slate-400 shrink-0 tabular-nums">{{ optional($log->created_at)->diffForHumans() ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500 text-center py-6">No audit events yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Toggle the period dropdown.
    var ptoggle = document.getElementById('period-toggle');
    var pmenu = document.getElementById('period-menu');
    if (ptoggle && pmenu) {
        ptoggle.addEventListener('click', function (e) {
            e.stopPropagation();
            pmenu.classList.toggle('hidden');
        });
        document.addEventListener('click', function () { pmenu.classList.add('hidden'); });
    }

    // Filter the top-students table client-side as the operator types.
    // No server round-trip — top-N is small so this stays snappy.
    var search = document.getElementById('admin-dash-search');
    var tbody = document.querySelector('#top-students-table tbody');
    if (search && tbody) {
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            tbody.querySelectorAll('tr').forEach(function (row) {
                if (!row.dataset.name) return;
                row.style.display = !q || row.dataset.name.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
})();
</script>
@endsection
