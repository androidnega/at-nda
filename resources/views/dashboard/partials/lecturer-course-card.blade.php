@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\SchoolClass|null $schoolClass */
    $weeks = $course->relationLoaded('attendanceWeeks') ? $course->attendanceWeeks : collect();
    $cancelledWeeks = $weeks->filter(fn ($w) => $w->isCancelled())->count();
    $totalWeeks = $weeks->count();
    $scheduleLabel = $course->getScheduleLabel();
@endphp
<article class="px-5 py-4">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex items-start gap-3 min-w-0 flex-1">
            <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fas fa-book-open"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 leading-tight truncate">{{ $course->course_name }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-1.5">
                    @if($course->course_code)<span class="font-mono">{{ $course->course_code }}</span><span class="text-slate-300">·</span>@endif
                    <span class="truncate">{{ $course->assignedClassesLabel() ?: '—' }}</span>
                </p>
                <p class="text-[11px] text-slate-400 mt-1 flex flex-wrap items-center gap-x-2">
                    <span class="inline-flex items-center gap-1">
                        <i class="fas fa-clock text-[10px]"></i>{{ $scheduleLabel }}
                    </span>
                    @if($totalWeeks > 0)
                        <span class="text-slate-300">·</span>
                        <span class="inline-flex items-center gap-1">
                            <i class="fas fa-calendar-week text-[10px]"></i>{{ $totalWeeks }} week{{ $totalWeeks === 1 ? '' : 's' }}
                        </span>
                        @if($cancelledWeeks > 0)
                            <span class="text-slate-300">·</span>
                            <span class="inline-flex items-center gap-1 text-amber-700">
                                <i class="fas fa-ban text-[10px]"></i>{{ $cancelledWeeks }} cancelled
                            </span>
                        @endif
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-1.5 shrink-0">
            <a href="{{ route('web.attendance.form', $course) }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg bg-primary text-white px-3 py-1.5 text-[12px] font-semibold hover:bg-primary/90">
                <i class="fas fa-clipboard-check"></i> Mark
            </a>
            <a href="{{ route('dashboard.teaching.attendance.course', $course) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 hover:bg-slate-50">
                <i class="fas fa-list-check text-indigo-600 text-[11px]"></i> Attendance
            </a>
            <a href="{{ route('dashboard.pdf.export', $course) }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 hover:bg-slate-50">
                <i class="fas fa-file-pdf text-red-500 text-[11px]"></i> PDF
            </a>
        </div>
    </div>
</article>
