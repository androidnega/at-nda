@extends($layout)

@section('title', 'Timetable')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-6 sm:space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Timetable</h1>
            <p class="text-gray-500 text-sm mt-1">Your class schedule</p>
        </div>
        @if($courses->isNotEmpty())
            <div class="flex items-center gap-2 text-sm text-gray-600 bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-2.5">
                <span class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                    <i class="fas fa-calendar-week"></i>
                </span>
                <span><strong class="text-gray-900">{{ $courses->count() }}</strong> sessions this week</span>
            </div>
        @endif
    </div>

    @if($courses->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 sm:p-16 text-center">
            <span class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mx-auto mb-4">
                <i class="fas fa-calendar-alt text-3xl"></i>
            </span>
            <p class="text-gray-700 font-semibold text-lg">No timetable yet</p>
            <p class="text-gray-500 text-sm mt-2 max-w-md mx-auto">Courses for your class don&rsquo;t have day and time set, or you&rsquo;re not linked to a class.</p>
        </div>
    @else
        {{-- Week grid: admin-style cards per day --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7 gap-4">
            @foreach($orderedDays as $day)
                @php $dayCourses = $byDay->get($day, collect()); @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col min-h-[140px]">
                    <div class="px-4 py-3 bg-gradient-to-r from-primary/12 via-white to-teal-50/60 border-b border-gray-100 flex items-center justify-between gap-2">
                        <span class="text-sm font-bold text-gray-800">{{ $day }}</span>
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-primary/90 bg-primary/10 px-2 py-0.5 rounded-md tabular-nums">{{ $dayCourses->count() }}</span>
                    </div>
                    <div class="p-3 space-y-2.5 flex-1 bg-gray-50/30">
                        @foreach($dayCourses as $course)
                            <div class="rounded-xl border border-gray-100 bg-white p-3 shadow-sm hover:shadow-md hover:border-primary/25 transition-all duration-200 ring-1 ring-transparent hover:ring-primary/10">
                                <p class="font-semibold text-gray-900 text-sm leading-snug">
                                    {{ $course->course_name }}
                                    @if($course->course_code)
                                        <span class="text-gray-500 font-medium">({{ $course->course_code }})</span>
                                    @endif
                                </p>
                                <p class="text-xs font-bold text-primary mt-2 tabular-nums inline-flex items-center gap-1.5">
                                    <i class="fas fa-clock text-[10px] opacity-80"></i>
                                    {{ \Carbon\Carbon::parse($course->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($course->end_time)->format('H:i') }}
                                </p>
                                <p class="text-[11px] text-gray-600 mt-2 flex items-start gap-1.5">
                                    <i class="fas fa-chalkboard-user text-gray-400 mt-0.5 shrink-0"></i>
                                    <span>{{ $course->lecturer_name ?: ($course->lecturer?->name ?? '—') }}</span>
                                </p>
                                <p class="text-[11px] text-gray-500 mt-1 flex items-start gap-1.5">
                                    <i class="fas fa-location-dot text-gray-400 mt-0.5 shrink-0"></i>
                                    <span>{{ $course->venueRelation?->name ?? $course->venue ?? '—' }}</span>
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
