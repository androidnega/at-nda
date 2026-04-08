@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $greeting = match (true) {
        now()->hour < 12 => 'Good Morning',
        now()->hour < 17 => 'Good Afternoon',
        default => 'Good Evening',
    };
@endphp

<div class="mb-6 rounded-3xl border border-slate-200 bg-white/90 px-6 py-5 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">{{ $greeting }}, Admin</h1>
            <p class="text-slate-500 text-sm mt-1">Operational overview across attendance, classes, and students.</p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
            <i class="fas fa-calendar-days text-slate-400"></i>
            {{ now()->format('l, d M Y') }}
        </div>
    </div>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6 sm:mb-8">
    @php
        $cards = [
            ['label' => "Today's Attendance", 'value' => $attendanceToday, 'icon' => 'fa-calendar-check', 'tone' => 'from-blue-500 to-blue-600'],
            ['label' => 'Total Attendance', 'value' => $totalAttendances, 'icon' => 'fa-chart-line', 'tone' => 'from-violet-500 to-indigo-600'],
            ['label' => 'Courses', 'value' => $totalCourses, 'icon' => 'fa-book-open', 'tone' => 'from-amber-500 to-orange-500'],
            ['label' => 'Students', 'value' => $totalStudents, 'icon' => 'fa-users', 'tone' => 'from-emerald-500 to-teal-600'],
        ];
    @endphp
    @foreach($cards as $card)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br {{ $card['tone'] }} text-white">
                    <i class="fas {{ $card['icon'] }} text-sm"></i>
                </span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight text-slate-900">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 sm:mb-8">
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="font-semibold text-slate-800">Attendance Trend (Last 7 Days)</h3>
            <span class="text-xs font-semibold rounded-full bg-slate-100 px-3 py-1 text-slate-500">Weekly</span>
        </div>
        @php $maxCount = $last7Days->max('count') ?: 1; @endphp
        <div class="flex items-end gap-2 sm:gap-4 h-40">
            @foreach($last7Days as $day)
            <div class="flex-1 flex flex-col items-center gap-2">
                <div class="w-full flex flex-col justify-end h-28">
                    <div
                        class="w-full rounded-t-lg bg-gradient-to-t from-primary/50 to-primary/25 hover:from-primary/60 hover:to-primary/35 transition-colors min-h-[4px]"
                        style="height: {{ max(4, ($day['count'] / $maxCount) * 100) }}%"
                        title="{{ $day['count'] }} attendance"
                    ></div>
                </div>
                <span class="text-xs text-slate-500 font-medium">{{ $day['label'] }}</span>
                <span class="text-xs font-semibold text-slate-700">{{ $day['count'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Top Students</h3>
        @forelse($topStudents as $student)
        <div class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
            <span class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm">
                {{ $student->first_name && $student->last_name ? strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) : strtoupper(substr($student->index_number ?? '', 0, 2)) }}
            </span>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-slate-800 truncate">
                    @if($student->getDisplayName() !== '')
                        {{ $student->getDisplayName() }}
                    @else
                        <span class="font-mono text-sm">{{ $student->index_number }}</span>
                    @endif
                </p>
                <p class="text-sm text-slate-500">{{ $student->attendances_count }} attendance{{ $student->attendances_count !== 1 ? 's' : '' }}</p>
            </div>
        </div>
        @empty
        <p class="text-slate-500 text-sm py-4">No attendance data yet</p>
        @endforelse
    </div>
</div>

{{-- Attendance by course + Recent --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Attendance by course --}}
    <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Attendance by Course</h3>
        <div class="space-y-3">
            @forelse($attendanceByCourse as $course)
            <div class="flex items-center justify-between">
                <span class="text-slate-700 truncate pr-2">{{ $course->course_name }}</span>
                <span class="font-semibold text-primary flex-shrink-0">{{ $course->attendances_count }}</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                @php $courseMax = $attendanceByCourse->max('attendances_count') ?: 1; @endphp
                <div class="h-full bg-primary/30 rounded-full" style="width: {{ ($course->attendances_count / $courseMax) * 100 }}%"></div>
            </div>
            @empty
            <p class="text-slate-500 text-sm">No data yet</p>
            @endforelse
        </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <span class="font-semibold text-slate-800">Recent Attendance</span>
            @if(session()->has('admin_id'))
            <a href="{{ route('dashboard.attendances') }}" class="text-primary hover:text-primary/80 text-sm font-medium inline-flex items-center gap-1">
                View all <i class="fas fa-arrow-right text-xs"></i>
            </a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[320px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider hidden sm:table-cell">Course</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attendances as $a)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-4 py-3">
                            @if($a->student->getDisplayName() !== '')
                                <span class="font-medium text-slate-800">{{ $a->student->getDisplayName() }}</span>
                                <span class="text-slate-500 text-sm block sm:inline sm:ml-1">({{ $a->student->index_number }})</span>
                            @else
                                <span class="font-mono font-medium text-slate-800">{{ $a->student->index_number }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell text-slate-600">{{ $a->course->course_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">
                            <i class="fas fa-clock text-slate-400 mr-1 hidden sm:inline"></i>
                            {{ $a->attendance_time->format('M d, H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-slate-500">
                            <i class="fas fa-inbox text-3xl text-slate-300 mb-2 block"></i>
                            No attendance records yet
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $attendances->links() }}
        </div>
    </div>
</div>
@endsection
