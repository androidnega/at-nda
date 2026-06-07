@extends($layout)

@section('title', 'Timetable')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-6 sm:space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">Timetable</h1>
            <p class="text-gray-500 dark:text-slate-400 text-sm mt-1">Your class schedule</p>
        </div>
        @if($entries->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 w-full lg:w-auto">
                <div class="flex items-center gap-2.5 text-sm text-gray-600 dark:text-slate-300 bg-white dark:bg-slate-900 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm px-4 py-2.5 min-w-[14rem]">
                    <span class="w-9 h-9 rounded-lg bg-primary/10 dark:bg-sky-900/40 flex items-center justify-center text-primary dark:text-sky-300 shrink-0">
                        <i class="fas fa-calendar-week"></i>
                    </span>
                    <span><strong class="text-gray-900 dark:text-slate-100 tabular-nums">{{ $weekProgress['lectures_remaining'] ?? 0 }}</strong> lectures left</span>
                </div>
                <div class="flex items-center gap-2.5 text-sm text-gray-600 dark:text-slate-300 bg-white dark:bg-slate-900 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm px-4 py-2.5 min-w-[14rem]">
                    <span class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-emerald-700 dark:text-emerald-300 shrink-0">
                        <i class="fas fa-hourglass-half"></i>
                    </span>
                    <span><strong class="text-gray-900 dark:text-slate-100 tabular-nums">{{ $weekProgress['credit_hours_remaining'] ?? 0 }}</strong> credit hours left</span>
                </div>
            </div>
        @endif
    </div>

    @if($canManage ?? false)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white dark:bg-slate-900 rounded-xl border border-primary/15 dark:border-slate-700 shadow-sm px-4 py-3.5">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-lg bg-primary/10 dark:bg-sky-900/40 text-primary dark:text-sky-300 flex items-center justify-center shrink-0">
                    <i class="fas fa-user-shield"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Class rep timetable</p>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Build the schedule for your class. Your changes never affect other classes.</p>
                </div>
            </div>
            <a href="{{ route('dashboard.timetable.manage') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90">
                <i class="fas fa-pen-to-square text-xs"></i>
                Manage timetable
            </a>
        </div>
    @endif

    @if($entries->isEmpty())
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-12 sm:p-16 text-center">
            <span class="w-16 h-16 rounded-2xl bg-primary/10 dark:bg-sky-900/40 flex items-center justify-center text-primary dark:text-sky-300 mx-auto mb-4">
                <i class="fas fa-calendar-alt text-3xl"></i>
            </span>
            <p class="text-gray-700 dark:text-slate-200 font-semibold text-lg">No timetable yet</p>
            <p class="text-gray-500 dark:text-slate-400 text-sm mt-2 max-w-md mx-auto">
                @if($canManage ?? false)
                    Your class doesn&rsquo;t have any timetable entries yet. Use <strong>Manage timetable</strong> above to add courses, day, time, lecturer, and venue for your class.
                @else
                    Your class rep hasn&rsquo;t added timetable entries yet. Ask your rep to set up the timetable for this class.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7 gap-4">
            @foreach($orderedDays as $day)
                @php $dayEntries = $byDay->get($day, collect()); @endphp
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden flex flex-col min-h-[140px]">
                    <div class="px-4 py-3 bg-gradient-to-r from-primary/12 via-white to-teal-50/60 dark:from-sky-900/30 dark:via-slate-900 dark:to-emerald-900/20 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between gap-2">
                        <span class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ $day }}</span>
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-primary/90 dark:text-sky-300 bg-primary/10 dark:bg-sky-900/40 px-2 py-0.5 rounded-md tabular-nums">{{ $dayEntries->count() }}</span>
                    </div>
                    <div class="p-3 space-y-2.5 flex-1 bg-gray-50/30 dark:bg-slate-900/40">
                        @foreach($dayEntries as $entry)
                            @php
                                $course = $entry->course ?? null;
                                $courseName = $course?->course_name ?? ($entry->course_name ?? '—');
                                $courseCode = $course?->course_code ?? ($entry->course_code ?? null);
                                $lecturerName = method_exists($entry, 'resolvedLecturerName')
                                    ? ($entry->resolvedLecturerName() ?: ($entry->lecturer?->name ?? $entry->lecturer_name ?? '—'))
                                    : ($entry->lecturer_name ?? ($entry->lecturer?->name ?? '—'));
                                $venueName = method_exists($entry, 'resolvedVenueName')
                                    ? ($entry->resolvedVenueName() ?: ($entry->venueRelation?->name ?? $entry->venue ?? '—'))
                                    : ($entry->venueRelation?->name ?? $entry->venue ?? '—');
                            @endphp
                            <div class="rounded-xl border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 shadow-sm hover:shadow-md hover:border-primary/25 dark:hover:border-sky-700 transition-all duration-200 ring-1 ring-transparent hover:ring-primary/10 dark:hover:ring-sky-700/40">
                                <p class="font-semibold text-gray-900 dark:text-slate-100 text-sm leading-snug">
                                    {{ $courseName }}
                                    @if($courseCode)
                                        <span class="text-gray-500 dark:text-slate-400 font-medium">({{ $courseCode }})</span>
                                    @endif
                                </p>
                                <p class="text-xs font-bold text-primary dark:text-sky-300 mt-2 tabular-nums inline-flex items-center gap-1.5">
                                    <i class="fas fa-clock text-[10px] opacity-80"></i>
                                    {{ \Carbon\Carbon::parse($entry->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($entry->end_time)->format('H:i') }}
                                </p>
                                <p class="text-[11px] text-gray-600 dark:text-slate-300 mt-2 flex items-start gap-1.5">
                                    <i class="fas fa-chalkboard-user text-gray-400 dark:text-slate-500 mt-0.5 shrink-0"></i>
                                    <span>{{ $lecturerName ?: '—' }}</span>
                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-1 flex items-start gap-1.5">
                                    <i class="fas fa-location-dot text-gray-400 dark:text-slate-500 mt-0.5 shrink-0"></i>
                                    <span>{{ $venueName ?: '—' }}</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
