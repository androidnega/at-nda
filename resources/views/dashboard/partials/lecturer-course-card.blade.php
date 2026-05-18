@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\SchoolClass|null $schoolClass */
@endphp
<article class="p-5 sm:p-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
            <span class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-book-open"></i>
            </span>
            <div class="min-w-0">
                <h3 class="font-semibold text-gray-900 text-lg">{{ $course->course_name }}</h3>
                <p class="text-sm text-gray-500 mt-0.5">
                    @if($course->course_code)<span class="font-mono">{{ $course->course_code }}</span> · @endif
                    {{ $course->assignedClassesLabel() ?: '—' }}
                </p>
                <p class="text-xs text-gray-400 mt-1">{{ $course->getScheduleLabel() }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            @if($schoolClass)
            <a href="{{ route('dashboard.classes.show', $schoolClass) }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-upload text-sky-600"></i> Class roster
            </a>
            <a href="{{ route('dashboard.students.index', ['class_id' => $schoolClass->id]) }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-users text-sky-600"></i> Students
            </a>
            @endif
            <a href="{{ route('dashboard.teaching.attendance.course', $course) }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-list-check text-indigo-600"></i> Attendance
            </a>
            <a href="{{ route('dashboard.pdf.export', $course) }}" target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-pdf text-red-600"></i> Attendance PDF
            </a>
            <a href="{{ route('web.attendance.form', $course) }}" target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-primary/30 bg-primary/5 text-xs font-medium text-primary hover:bg-primary/10">
                <i class="fas fa-clipboard-check"></i> Mark attendance
            </a>
        </div>
    </div>

    @if($course->attendanceWeeks->isNotEmpty())
    <div class="mt-4 pt-4 border-t border-gray-100">
        <p class="text-xs font-semibold text-gray-700 mb-2">Teaching weeks</p>
        <ul class="space-y-2 list-none p-0 m-0">
            @foreach($course->attendanceWeeks->sortBy('week_number') as $week)
            <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs border-b border-gray-50 last:border-0 pb-2 last:pb-0">
                <div>
                    <span class="font-medium text-gray-800">Week {{ $week->week_number }}</span>
                    @if($week->week_date)
                        <span class="text-gray-500 ml-1">{{ $week->week_date->format('M j, Y') }}</span>
                    @endif
                    @if($week->isCancelled())
                        <span class="ml-2 text-amber-800 font-semibold">Cancelled</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($week->isCancelled())
                        <form action="{{ route('lecturer.courses.week.uncancel', [$course, $week]) }}" method="post" class="inline">
                            @csrf
                            <button type="submit" class="text-primary font-semibold hover:underline">Restore</button>
                        </form>
                    @else
                        <form action="{{ route('lecturer.courses.week.cancel', [$course, $week]) }}" method="post" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="note" placeholder="Note (optional)" class="border border-gray-200 rounded px-2 py-1 text-xs w-36 max-w-full">
                            <button type="submit" class="rounded-md bg-amber-700 text-white px-2.5 py-1 text-xs font-semibold hover:bg-amber-800">Cancel week</button>
                        </form>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</article>
