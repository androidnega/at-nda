@extends('layouts.admin')

@section('title', 'Course attendance')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.dashboard') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Dashboard
    </a>
    <h1 class="text-2xl font-bold text-primary">Course attendance</h1>
    <p class="text-sm text-gray-500 mt-1">View marks, export PDF/JSON, and restore backups for your courses</p>
</div>

<div class="space-y-3">
    @forelse($courses as $course)
    <div class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('dashboard.teaching.attendance.course', $course) }}" class="font-semibold text-gray-900 hover:text-primary">
                {{ $course->course_name }}
                @if($course->course_code)<span class="text-gray-500 font-normal">({{ $course->course_code }})</span>@endif
            </a>
            <p class="text-xs text-gray-500 mt-1">
                {{ $course->assignedClassesLabel() ?: '—' }}
                · {{ $course->attendances_count }} mark{{ $course->attendances_count === 1 ? '' : 's' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('dashboard.teaching.attendance.course', $course) }}" class="text-sm text-primary font-medium hover:underline">View records</a>
            <a href="{{ route('dashboard.teaching.attendance.course.pdf', $course) }}" target="_blank" class="text-sm text-gray-600 hover:underline">PDF</a>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-xl border border-dashed border-gray-200 p-10 text-center text-gray-500">
        No courses assigned to you yet.
    </div>
    @endforelse
</div>
@endsection
