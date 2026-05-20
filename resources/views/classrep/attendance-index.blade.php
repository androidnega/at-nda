@extends('layouts.classrep')

@section('title', 'Attendance')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Attendance</h1>
        <p class="text-sm text-gray-500 mt-1">Pick a course to review attendance marks and weekly status, or open the PDF roster.</p>
    </div>
    @if($courses->isNotEmpty())
        <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600">
            <i class="fas fa-book text-primary/70"></i>
            <span class="font-medium text-gray-800">{{ $courses->count() }}</span>
            <span class="text-gray-500">course{{ $courses->count() === 1 ? '' : 's' }}</span>
        </div>
    @endif
</div>

@if($courses->isEmpty())
    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 px-6 py-12 text-center">
        <span class="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <i class="fas fa-book text-xl"></i>
        </span>
        <p class="text-sm font-semibold text-gray-700">No courses linked to your class yet</p>
        <p class="text-xs text-gray-500 mt-1">Only courses an admin has assigned to a class you rep show up here.</p>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        @foreach($courses as $course)
            @php
                $repClassLabel = \App\Support\RepCourseAccess::repClassLabelForCourse($rep, $course);
            @endphp
            <div class="group flex flex-col rounded-xl border border-gray-200 bg-white hover:border-primary/40 hover:shadow-sm transition overflow-hidden">
                <a href="{{ route('dashboard.class-attendance.course', $course) }}"
                   class="block px-4 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                    <div class="flex items-start gap-2.5">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary flex-shrink-0">
                            <i class="fas fa-book text-sm"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-gray-900 leading-snug group-hover:text-primary truncate">
                                {{ $course->course_name }}
                            </h2>
                            <p class="text-[11px] text-gray-500 mt-0.5 truncate">
                                @if($course->course_code){{ $course->course_code }}@endif
                                @if($course->course_code && $repClassLabel !== '—') · @endif
                                @if($repClassLabel !== '—'){{ $repClassLabel }}@endif
                            </p>
                            @if($course->hasSchedule())
                                <p class="text-[11px] text-gray-500 mt-0.5 inline-flex items-center gap-1">
                                    <i class="fas fa-clock text-gray-400 text-[10px]"></i>
                                    {{ $course->getScheduleLabel() }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>

                <div class="flex items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/60 px-3 py-1.5">
                    <a href="{{ route('dashboard.class-attendance.course', $course) }}"
                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline">
                        View weeks
                        <i class="fas fa-chevron-right text-[9px] opacity-80"></i>
                    </a>
                    <a href="{{ route('dashboard.class-attendance.course.pdf', $course) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2 py-1 text-[10px] font-semibold text-red-700 hover:bg-red-50">
                        <i class="fas fa-file-pdf text-red-500"></i>
                        Semester PDF
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
