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
    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 px-6 py-16 text-center">
        <span class="mx-auto mb-3 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <i class="fas fa-book text-2xl"></i>
        </span>
        <p class="text-sm font-semibold text-gray-700">No courses linked to your class yet</p>
        <p class="text-xs text-gray-500 mt-1">Courses appear here once they are assigned to a class you rep.</p>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($courses as $course)
            @php
                $repClassLabel = \App\Support\RepCourseAccess::repClassLabelForCourse($rep, $course);
                $lastSession = $course->attendanceSessions->first();
                $lecturerStatus = $lastSession?->lecturer_status;
                $marks = (int) ($course->attendances_count ?? 0);
            @endphp
            <div class="group flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm hover:border-primary/40 hover:shadow-md transition overflow-hidden">
                <a href="{{ route('dashboard.class-attendance.course', $course) }}"
                   class="block p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary flex-shrink-0">
                            <i class="fas fa-book text-lg"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-gray-900 leading-snug group-hover:text-primary">
                                {{ $course->course_name }}
                            </h2>
                            @if($course->course_code)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $course->course_code }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[11px] text-gray-600">
                        @if($repClassLabel !== '—')
                            <span class="inline-flex items-center gap-1">
                                <i class="fas fa-layer-group text-gray-400"></i>
                                {{ $repClassLabel }}
                            </span>
                        @endif
                        @if($course->hasSchedule())
                            <span class="inline-flex items-center gap-1">
                                <i class="fas fa-clock text-gray-400"></i>
                                {{ $course->getScheduleLabel() }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <div class="flex-1 rounded-xl border border-gray-100 bg-gray-50/70 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Total marks</p>
                            <p class="text-lg font-bold text-gray-900 tabular-nums leading-tight">{{ $marks }}</p>
                        </div>
                        <div class="flex-1 rounded-xl border border-gray-100 bg-gray-50/70 px-3 py-2">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Lecturer</p>
                            @if($lecturerStatus === 'absent')
                                <p class="text-sm font-bold text-rose-700 leading-tight">
                                    <i class="fas fa-user-slash text-[11px]"></i> Absent
                                </p>
                            @elseif($lecturerStatus)
                                <p class="text-sm font-bold text-emerald-700 leading-tight">
                                    <i class="fas fa-user-check text-[11px]"></i> Present
                                </p>
                            @else
                                <p class="text-sm font-semibold text-gray-400 leading-tight">—</p>
                            @endif
                        </div>
                    </div>
                </a>

                <div class="flex items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/60 px-5 py-2.5">
                    <a href="{{ route('dashboard.class-attendance.course', $course) }}"
                       class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                        View attendance
                        <i class="fas fa-chevron-right text-[10px] opacity-80"></i>
                    </a>
                    <a href="{{ route('dashboard.class-attendance.course.pdf', $course) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-red-700 hover:bg-red-50">
                        <i class="fas fa-file-pdf text-red-500"></i>
                        PDF
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
