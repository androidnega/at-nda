@extends('layouts.classrep')

@section('title', 'Dashboard')

@section('content')
<div class="w-full min-w-0 space-y-6">
    {{-- Hero: photo + flat tint overlay --}}
    <div class="relative overflow-hidden rounded-2xl text-white">
        <div
            class="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('https://thumbs.dreamstime.com/b/people-working-coworking-space-using-computers-mobile-devices-individuals-engage-tasks-their-desks-426022434.jpg');"
            aria-hidden="true"
        ></div>
        {{-- Darken photo for contrast --}}
        <div class="absolute inset-0 bg-black/45" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-teal-900/65"></div>
        <div class="relative z-10 px-5 py-6 sm:px-8 sm:py-8">
            <p class="text-teal-100/90 text-xs font-semibold uppercase tracking-widest">Dashboard</p>
            <h1 class="text-2xl sm:text-3xl font-bold mt-1 tracking-tight">Hello, {{ $student->getDisplayNameOrIndex() }}</h1>
            <p class="text-teal-100/80 text-sm mt-2 max-w-xl">{{ now()->format('l, F j, Y') }} · Stay on top of sessions and attendance</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('dashboard.session') }}" class="inline-flex items-center gap-2 rounded-xl bg-white text-teal-800 px-4 py-2.5 text-sm font-semibold hover:bg-teal-50">
                    <i class="fas fa-play-circle"></i> Open session
                </a>
                <a href="{{ route('dashboard.class-attendance.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 border border-white/25 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/15">
                    <i class="fas fa-clipboard-list"></i> Attendance
                </a>
            </div>
        </div>
    </div>

    {{-- Stat grid: flat tinted cards, each a distinct palette (no gradients) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
        <div class="rounded-xl border border-[#c5d4e0] bg-[#edf3f8] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#3d5a6e]">Students</span>
                <span class="w-9 h-9 rounded-xl bg-[#d4e4ef] flex items-center justify-center text-[#1f4558]"><i class="fas fa-users text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#1a3344] tabular-nums">{{ $studentsCount }}</p>
        </div>
        <div class="rounded-xl border border-[#e0cfc0] bg-[#f7f0ea] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#6b4a38]">Courses</span>
                <span class="w-9 h-9 rounded-xl bg-[#ead9cc] flex items-center justify-center text-[#5c3d2e]"><i class="fas fa-book text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#3d281c] tabular-nums">{{ $coursesCount }}</p>
        </div>
        <div class="rounded-2xl border border-[#c4d2be] bg-[#eef4ec] p-4 shadow-sm shadow-[#c4d2be]/25">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#3f5a42]">7 days</span>
                <span class="w-9 h-9 rounded-xl bg-[#dce8d8] flex items-center justify-center text-[#2d4a32]"><i class="fas fa-chart-line text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#243828] tabular-nums">{{ $weekAttendanceMarks }}</p>
            <p class="text-[10px] text-[#4d6350] mt-1 font-medium">Marks recorded</p>
        </div>
        <div class="rounded-xl border border-[#d4c8de] bg-[#f4eef8] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#5c476f]">Today</span>
                <span class="w-9 h-9 rounded-xl bg-[#e5daf0] flex items-center justify-center text-[#4a3560]"><i class="fas fa-sun text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#352847] tabular-nums">{{ $todayAttendanceMarks }}</p>
            <p class="text-[10px] text-[#6b5578] mt-1 font-medium">Today&rsquo;s marks</p>
        </div>
        <div class="rounded-xl border border-[#c8c6d8] bg-[#ecebf4] p-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#45425c]">All-time</span>
                <span class="w-9 h-9 rounded-xl bg-[#dad8ea] flex items-center justify-center text-[#38354f]"><i class="fas fa-clipboard-check text-sm"></i></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-[#252238] tabular-nums">{{ $totalAttendanceMarks }}</p>
            <p class="text-[10px] text-[#5a5670] mt-1 font-medium">Total marks</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-[#c5d4e0] bg-[#f5f9fc] overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-[#c5d4e0] flex flex-wrap items-center justify-between gap-2 bg-[#edf3f8]">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#d4e4ef] text-[#1f4558]">
                        <i class="fas fa-calendar-day text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-[#1a3344] tracking-tight">Today&rsquo;s schedule</h2>
                    </div>
                </div>
                <span class="shrink-0 inline-flex items-center rounded-lg border border-[#b8ccdb] bg-white/90 px-3 py-1.5 text-xs font-semibold tabular-nums text-[#2d4a5c]">{{ now()->format('M j, Y') }}</span>
            </div>
            <div class="p-3 sm:p-4 space-y-2.5">
                @forelse($todayCourses as $c)
                    <div class="flex items-stretch gap-3 rounded-xl border border-[#dce7ee] bg-white p-3 sm:p-3.5">
                        <span class="hidden sm:flex w-1 shrink-0 rounded-full bg-[#8eb4c8]" aria-hidden="true"></span>
                        <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div class="min-w-0 flex gap-3">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#eef5fa] text-[#2d5a6e] ring-1 ring-[#dce7ee]">
                                    <i class="fas fa-book-open text-[11px]"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-[#142a38] leading-snug truncate">{{ $c->course_name }}</p>
                                    <p class="text-[12px] text-[#5a6f7c] mt-1 leading-relaxed">
                                        <span class="text-[#3d5a6e] font-medium">{{ $c->getScheduleLabel() }}</span>
                                    </p>
                                </div>
                            </div>
                            @if($c->activeSession())
                                <span class="shrink-0 self-start inline-flex items-center gap-1.5 rounded-lg border border-[#b5d9c4] bg-[#ecf6f0] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#1f5c36]">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#2d8f4e]" aria-hidden="true"></span>
                                    Live
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#c5d4e0] bg-[#fafcfd] px-4 py-10 text-center">
                        <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#e8f0f6] text-[#6b8fa3]">
                            <i class="fas fa-mug-hot text-lg"></i>
                        </span>
                        <p class="text-sm font-medium text-[#3d5a6e]">Nothing on your timetable today</p>
                        <p class="text-xs text-[#7a919c] mt-1.5 max-w-xs mx-auto">When courses are scheduled for this weekday, they&rsquo;ll show up here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Shortcuts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <a href="{{ route('dashboard.students.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-user-friends text-primary w-5 text-center"></i> Students
                </a>
                <a href="{{ route('dashboard.timetable') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-calendar-alt text-primary w-5 text-center"></i> Timetable
                </a>
                <a href="{{ route('dashboard.my-class') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-layer-group text-primary w-5 text-center"></i> My class
                </a>
                <a href="{{ route('dashboard.class-attendance.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-medium text-slate-800 hover:border-primary/30 hover:bg-primary/5">
                    <i class="fas fa-clipboard-list text-primary w-5 text-center"></i> Attendance
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
