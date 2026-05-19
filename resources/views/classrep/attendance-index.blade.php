@extends('layouts.classrep')

@section('title', 'Attendance')

@section('content')
<div class="mb-5">
    <h1 class="text-lg sm:text-xl font-bold text-gray-900">Attendance</h1>
    <p class="text-xs text-gray-500 mt-0.5">Choose a course to see who attended, or open the PDF roster preview</p>
</div>

<div class="space-y-2">
    @forelse($courses as $course)
        <div class="group flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 hover:border-primary/40 hover:bg-primary/[0.02]">
            <a href="{{ route('dashboard.class-attendance.course', $course) }}" class="min-w-0 flex-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded">
                <p class="font-semibold text-gray-900 text-sm leading-snug group-hover:text-primary">
                    {{ $course->course_name }}
                    @if($course->course_code)
                        <span class="text-gray-500 font-normal">({{ $course->course_code }})</span>
                    @endif
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                    @php $repClassLabel = \App\Support\RepCourseAccess::repClassLabelForCourse($rep, $course); @endphp
                    @if($repClassLabel !== '—')
                        <span><i class="fas fa-layer-group text-gray-400 mr-0.5"></i>{{ $repClassLabel }}</span>
                    @endif
                    @if($course->hasSchedule())
                        <span>{{ $course->getScheduleLabel() }}</span>
                    @endif
                    <span class="text-gray-600 font-medium tabular-nums">{{ $course->attendances_count }} mark{{ $course->attendances_count === 1 ? '' : 's' }}</span>
                    @php $lastSession = $course->attendanceSessions->first(); @endphp
                    @if($lastSession?->lecturer_status)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-semibold {{ $lastSession->lecturer_status === 'absent' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                            <i class="fas fa-user-tie"></i>
                            Lecturer {{ $lastSession->lecturer_status === 'absent' ? 'Absent' : 'Present' }}
                        </span>
                    @endif
                </div>
            </a>
            <div class="flex flex-wrap items-center gap-2 shrink-0 sm:pl-2">
                <a href="{{ route('dashboard.class-attendance.course', $course) }}"
                   class="inline-flex items-center gap-1 text-xs font-medium text-primary">
                    View
                    <i class="fas fa-chevron-right text-[10px] opacity-70"></i>
                </a>
                <span class="hidden sm:inline text-gray-200">|</span>
                <a href="{{ route('dashboard.class-attendance.course.pdf', $course) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-100 hover:border-gray-300">
                    <i class="fas fa-file-pdf text-red-500"></i>
                    PDF preview
                </a>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 py-10 text-center text-sm text-gray-500">
            No courses linked to your class yet.
        </div>
    @endforelse
</div>
@endsection
