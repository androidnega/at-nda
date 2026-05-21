@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $maxCount = max($last7Days->max('count') ?: 1, 1);
@endphp

<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-md">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input
                    type="text"
                    placeholder="Search framework..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-slate-300 focus:outline-none"
                />
            </div>
            <div class="flex items-center gap-2 self-end lg:self-auto">
                <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                    Monthly <i class="fas fa-chevron-down ml-1 text-[10px]"></i>
                </button>
                <button class="rounded-xl bg-sky-500 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-600">
                    <i class="fas fa-download mr-1"></i> Download Info
                </button>
            </div>
        </div>
    </div>

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
                    Operational snapshot for attendance, courses, and students.
                </p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @php
                $detailTiles = [
                    ['label' => 'Today', 'value' => $attendanceToday, 'caption' => 'Total attendance', 'icon' => 'fa-calendar-day', 'tone' => 'bg-sky-100 text-sky-700'],
                    ['label' => 'Overall', 'value' => $totalAttendances, 'caption' => 'Attendance records', 'icon' => 'fa-clipboard-check', 'tone' => 'bg-emerald-100 text-emerald-700'],
                    ['label' => 'Courses', 'value' => $totalCourses, 'caption' => 'Active courses', 'icon' => 'fa-book-open', 'tone' => 'bg-amber-100 text-amber-700'],
                    ['label' => 'Students', 'value' => $totalStudents, 'caption' => 'Registered students', 'icon' => 'fa-user-graduate', 'tone' => 'bg-rose-100 text-rose-700'],
                ];
            @endphp
            @foreach($detailTiles as $tile)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 flex items-start gap-3">
                    <span class="w-10 h-10 rounded-lg {{ $tile['tone'] }} flex items-center justify-center shrink-0">
                        <i class="fas {{ $tile['icon'] }} text-base"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $tile['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-800 tabular-nums leading-tight">{{ number_format((int) $tile['value']) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $tile['caption'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                        <i class="fas fa-calendar-week"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-500 inline-flex items-center gap-1.5">Class Days</h3>
                        <p class="mt-0.5 text-xs text-slate-400 inline-flex items-center gap-1">
                            <i class="far fa-calendar text-[10px]"></i> Classes days for monthly
                        </p>
                        <p class="mt-3 text-4xl font-semibold text-slate-700">{{ $last7Days->sum('count') }} <span class="text-2xl">Days</span></p>
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
                        <i class="far fa-clock text-[10px]"></i> This period
                    </span>
                </div>
                <p class="mb-4 text-5xl font-semibold text-slate-700">{{ $totalStudents > 0 ? round(($attendanceToday / $totalStudents) * 100) : 0 }}%</p>
                <div class="space-y-2">
                    @foreach($last7Days as $day)
                        <div class="flex items-center gap-3">
                            <span class="w-9 text-xs font-medium text-slate-500">{{ $day['label'] }}</span>
                            <div class="h-2 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ ($day['count'] / $maxCount) * 100 }}%"></div>
                            </div>
                            <span class="w-8 text-right text-xs font-semibold text-slate-600">{{ $day['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="xl:col-span-3 space-y-6">
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
                            ['icon' => 'fa-clipboard-check', 'tone' => 'sky', 'label' => 'Attendance', 'value' => $totalAttendances],
                            ['icon' => 'fa-calendar-day', 'tone' => 'lime', 'label' => 'Today', 'value' => $attendanceToday],
                            ['icon' => 'fa-book-open', 'tone' => 'amber', 'label' => 'Courses', 'value' => $totalCourses],
                            ['icon' => 'fa-user-graduate', 'tone' => 'rose', 'label' => 'Students', 'value' => $totalStudents],
                        ];
                        $toneMap = [
                            'sky' => ['bg' => 'bg-sky-100', 'badge' => 'bg-sky-200/70', 'text' => 'text-sky-700'],
                            'lime' => ['bg' => 'bg-lime-100', 'badge' => 'bg-lime-200/70', 'text' => 'text-lime-700'],
                            'amber' => ['bg' => 'bg-amber-100', 'badge' => 'bg-amber-200/70', 'text' => 'text-amber-700'],
                            'rose' => ['bg' => 'bg-rose-100', 'badge' => 'bg-rose-200/70', 'text' => 'text-rose-700'],
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

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-2xl font-semibold text-slate-700 inline-flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                            <i class="fas fa-trophy text-sm"></i>
                        </span>
                        Top Attendance Students
                    </h3>
                    <button class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-500">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[580px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                <th class="py-3 pr-3">No.</th>
                                <th class="py-3 pr-3">Name</th>
                                <th class="py-3 pr-3">ID</th>
                                <th class="py-3">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($topStudents as $i => $student)
                                @php
                                    $maxTop = max($topStudents->max('attendances_count') ?: 1, 1);
                                    $progress = round(($student->attendances_count / $maxTop) * 100);
                                @endphp
                                <tr>
                                    <td class="py-3 pr-3 text-sm font-semibold text-slate-500">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="py-3 pr-3 text-sm font-medium text-slate-700">
                                        {{ $student->getDisplayName() !== '' ? $student->getDisplayName() : $student->index_number }}
                                    </td>
                                    <td class="py-3 pr-3 text-sm text-slate-500">{{ $student->index_number }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 w-full max-w-[180px] rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full bg-sky-500" style="width: {{ $progress }}%"></div>
                                            </div>
                                            <span class="text-sm font-semibold text-slate-600">{{ $progress }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-sm text-slate-500">No attendance data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
