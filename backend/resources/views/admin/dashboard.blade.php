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

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-primary">{{ $greeting }}!</h1>
    <p class="text-gray-500 text-sm mt-1">Admin</p>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

{{-- KPI Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-box text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Today's Attendance</p>
            <p class="text-2xl font-bold text-gray-800">{{ $attendanceToday }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center text-sky-600 flex-shrink-0">
            <i class="fas fa-boxes-stacked text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Total Attendance</p>
            <p class="text-2xl font-bold text-gray-800">{{ $totalAttendances }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 flex-shrink-0">
            <i class="fas fa-calendar-check text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Courses</p>
            <p class="text-2xl font-bold text-gray-800">{{ $totalCourses }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4 col-span-2 lg:col-span-1">
        <span class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
            <i class="fas fa-users text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Students</p>
            <p class="text-2xl font-bold text-gray-800">{{ $totalStudents }}</p>
        </div>
    </div>
</div>

{{-- Stats row + Chart --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 sm:mb-8">
    {{-- Attendance trend chart --}}
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5 sm:p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Attendance Trend (Last 7 Days)</h3>
        @php $maxCount = $last7Days->max('count') ?: 1; @endphp
        <div class="flex items-end gap-2 sm:gap-4 h-40">
            @foreach($last7Days as $day)
            <div class="flex-1 flex flex-col items-center gap-2">
                <div class="w-full flex flex-col justify-end h-28">
                    <div
                        class="w-full rounded-t-lg bg-primary/20 hover:bg-primary/30 transition-colors min-h-[4px]"
                        style="height: {{ max(4, ($day['count'] / $maxCount) * 100) }}%"
                        title="{{ $day['count'] }} attendance"
                    ></div>
                </div>
                <span class="text-xs text-gray-500 font-medium">{{ $day['label'] }}</span>
                <span class="text-xs font-semibold text-gray-700">{{ $day['count'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Top students --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sm:p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Top Students</h3>
        @forelse($topStudents as $student)
        <div class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
            <span class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm">
                {{ $student->first_name && $student->last_name ? strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) : strtoupper(substr($student->index_number ?? '', 0, 2)) }}
            </span>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-800 truncate">
                    @if($student->getDisplayName() !== '')
                        {{ $student->getDisplayName() }}
                    @else
                        <span class="font-mono text-sm">{{ $student->index_number }}</span>
                    @endif
                </p>
                <p class="text-sm text-gray-500">{{ $student->attendances_count }} attendance{{ $student->attendances_count !== 1 ? 's' : '' }}</p>
            </div>
        </div>
        @empty
        <p class="text-gray-500 text-sm py-4">No attendance data yet</p>
        @endforelse
    </div>
</div>

{{-- Stats cards row --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-4 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center text-violet-600">
            <i class="fas fa-chart-pie"></i>
        </span>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $totalAttendances }}</p>
            <p class="text-xs text-gray-500">Total</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
            <i class="fas fa-hourglass-half"></i>
        </span>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $attendanceToday }}</p>
            <p class="text-xs text-gray-500">Today</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
            <i class="fas fa-check"></i>
        </span>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $totalStudents }}</p>
            <p class="text-xs text-gray-500">Students</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-lg bg-sky-100 flex items-center justify-center text-sky-600">
            <i class="fas fa-book"></i>
        </span>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $totalCourses }}</p>
            <p class="text-xs text-gray-500">Courses</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 col-span-2 sm:col-span-1">
        <span class="w-10 h-10 rounded-lg bg-rose-100 flex items-center justify-center text-rose-600">
            <i class="fas fa-user-graduate"></i>
        </span>
        <div>
            <p class="text-2xl font-bold text-gray-800">{{ $attendanceByCourse->sum('attendances_count') }}</p>
            <p class="text-xs text-gray-500">By Course</p>
        </div>
    </div>
</div>

{{-- Attendance by course + Recent --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Attendance by course --}}
    <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-100 p-5 sm:p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Attendance by Course</h3>
        <div class="space-y-3">
            @forelse($attendanceByCourse as $course)
            <div class="flex items-center justify-between">
                <span class="text-gray-700 truncate pr-2">{{ $course->course_name }}</span>
                <span class="font-semibold text-primary flex-shrink-0">{{ $course->attendances_count }}</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                @php $courseMax = $attendanceByCourse->max('attendances_count') ?: 1; @endphp
                <div class="h-full bg-primary/30 rounded-full" style="width: {{ ($course->attendances_count / $courseMax) * 100 }}%"></div>
            </div>
            @empty
            <p class="text-gray-500 text-sm">No data yet</p>
            @endforelse
        </div>
    </div>

    {{-- Recent attendance --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <span class="font-semibold text-gray-800">Recent Attendance</span>
            @if(session()->has('admin_id'))
            <a href="{{ route('dashboard.attendances') }}" class="text-primary hover:text-primary/80 text-sm font-medium inline-flex items-center gap-1">
                View all <i class="fas fa-arrow-right text-xs"></i>
            </a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[320px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">Course</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($attendances as $a)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3">
                            @if($a->student->getDisplayName() !== '')
                                <span class="font-medium text-gray-800">{{ $a->student->getDisplayName() }}</span>
                                <span class="text-gray-500 text-sm block sm:inline sm:ml-1">({{ $a->student->index_number }})</span>
                            @else
                                <span class="font-mono font-medium text-gray-800">{{ $a->student->index_number }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell text-gray-600">{{ $a->course->course_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            <i class="fas fa-clock text-gray-400 mr-1 hidden sm:inline"></i>
                            {{ $a->attendance_time->format('M d, H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                            No attendance records yet
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $attendances->links() }}
        </div>
    </div>
</div>
@endsection
