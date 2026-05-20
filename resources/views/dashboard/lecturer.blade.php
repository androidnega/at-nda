@extends('layouts.admin')

@section('title', 'Lecturer Dashboard')

@section('content')
@php
    $greeting = match (true) {
        now()->hour < 12 => 'Good morning',
        now()->hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };
    $classChips = collect();
    if ($lecturer->relationLoaded('schoolClasses') && $lecturer->schoolClasses->isNotEmpty()) {
        $classChips = $lecturer->schoolClasses->pluck('name');
    } elseif ($lecturer->schoolClass) {
        $classChips = collect([$lecturer->schoolClass->name]);
    }
    $today = $today ?? now();
    $todaySlots = $todaySlots ?? collect();
    $activeSessions = $activeSessions ?? collect();
    $marksThisWeek = $marksThisWeek ?? 0;
@endphp

{{-- Hero --}}
<section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-white to-sky-50 p-5 sm:p-7 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-sky-600">{{ $today->format('l, F j') }}</p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">{{ $greeting }}, {{ $lecturer->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Here's a quick look at your teaching today.</p>
            @if($classChips->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($classChips as $chip)
                        <span class="inline-flex items-center gap-1 rounded-full bg-white border border-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                            <i class="fas fa-layer-group text-indigo-500 text-[10px]"></i>
                            {{ $chip }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-start gap-2 shrink-0">
            <a href="{{ route('dashboard.teaching.attendance.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i class="fas fa-clipboard-check text-sky-600"></i>
                Attendance hub
            </a>
            <a href="{{ route('lecturer.password.change.form') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i class="fas fa-key text-slate-500"></i>
                Change password
            </a>
        </div>
    </div>
</section>

@if (session('success'))
    <div class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <i class="fas fa-check-circle text-emerald-600"></i>
        {{ session('success') }}
    </div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    @php
        $stats = [
            ['label' => 'Courses', 'value' => $courses->count(), 'icon' => 'fa-book', 'tone' => 'bg-sky-100 text-sky-700'],
            ['label' => 'Classes', 'value' => $classGroups->count(), 'icon' => 'fa-users-rectangle', 'tone' => 'bg-indigo-100 text-indigo-700'],
            ['label' => 'Students', 'value' => number_format($totalStudents), 'icon' => 'fa-user-graduate', 'tone' => 'bg-amber-100 text-amber-700'],
            ['label' => 'Marks this week', 'value' => number_format($marksThisWeek), 'icon' => 'fa-check-double', 'tone' => 'bg-emerald-100 text-emerald-700'],
        ];
    @endphp
    @foreach($stats as $stat)
        <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 flex items-center gap-3 sm:gap-4">
            <span class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl {{ $stat['tone'] }} flex items-center justify-center shrink-0">
                <i class="fas {{ $stat['icon'] }} text-lg"></i>
            </span>
            <div class="min-w-0">
                <p class="text-[11px] sm:text-xs font-medium uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                <p class="text-xl sm:text-2xl font-bold text-slate-900 tabular-nums leading-tight">{{ $stat['value'] }}</p>
            </div>
        </div>
    @endforeach
</div>

{{-- Active sessions strip --}}
@if($activeSessions->isNotEmpty())
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2 text-emerald-900">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </span>
                <p class="text-sm font-semibold">{{ $activeSessions->count() }} active session{{ $activeSessions->count() === 1 ? '' : 's' }}</p>
            </div>
            <a href="{{ route('dashboard.teaching.attendance.index') }}" class="text-xs font-semibold text-emerald-800 hover:underline">View all</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach($activeSessions as $session)
                <a href="{{ route('dashboard.teaching.attendance.course', $session->course) }}"
                   class="flex items-center justify-between gap-3 rounded-xl bg-white border border-emerald-200 px-3 py-2.5 hover:border-emerald-400">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ $session->course?->course_name ?? 'Course' }}</p>
                        <p class="text-[11px] text-slate-500 truncate">
                            @if($session->course?->course_code)<span class="font-mono">{{ $session->course->course_code }}</span> · @endif
                            Started {{ optional($session->start_time ?? $session->created_at)->diffForHumans() }}
                        </p>
                    </div>
                    <i class="fas fa-chevron-right text-emerald-700 text-xs"></i>
                </a>
            @endforeach
        </div>
    </div>
@endif

{{-- Today's teaching --}}
<section class="mb-6 rounded-2xl border border-slate-200 bg-white overflow-hidden">
    <header class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center">
                <i class="fas fa-calendar-day text-sm"></i>
            </span>
            <div>
                <p class="text-sm font-semibold text-slate-900 leading-none">Today's teaching</p>
                <p class="text-[11px] text-slate-500 mt-0.5">{{ $today->format('l') }} · {{ $todaySlots->count() }} slot{{ $todaySlots->count() === 1 ? '' : 's' }}</p>
            </div>
        </div>
    </header>

    @if($todaySlots->isEmpty())
        <div class="px-5 py-8 sm:py-10 text-center">
            <span class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                <i class="fas fa-mug-hot text-lg"></i>
            </span>
            <p class="mt-3 text-sm font-medium text-slate-700">No classes scheduled today</p>
            <p class="text-xs text-slate-500 mt-1">Enjoy the time off — your upcoming weeks are still active in the Attendance hub.</p>
        </div>
    @else
        <ul class="divide-y divide-slate-100 list-none p-0 m-0">
            @foreach($todaySlots as $slot)
                @php
                    $slotCourse = $slot['course'];
                    $slotClass = $slot['class'];
                    $startLabel = $slot['start_time'] ? \Carbon\Carbon::parse($slot['start_time'])->format('H:i') : '—';
                    $endLabel = $slot['end_time'] ? \Carbon\Carbon::parse($slot['end_time'])->format('H:i') : '—';
                @endphp
                <li class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:bg-slate-50/60">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="hidden sm:flex flex-col items-center justify-center w-14 shrink-0 rounded-lg bg-sky-50 text-sky-800 py-1.5">
                            <span class="text-[10px] font-semibold tracking-wider uppercase">{{ $startLabel }}</span>
                            <span class="text-[10px] text-sky-600">{{ $endLabel }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $slotCourse->course_name }}</p>
                            <p class="text-[11px] text-slate-500 truncate flex flex-wrap items-center gap-x-1.5">
                                @if($slotCourse->course_code)<span class="font-mono">{{ $slotCourse->course_code }}</span>@endif
                                <span class="sm:hidden">· {{ $startLabel }}–{{ $endLabel }}</span>
                                @if($slotClass)
                                    <span>· {{ $slotClass->name }}</span>
                                @endif
                                @if($slot['venue'])
                                    <span class="text-slate-400">·</span>
                                    <span class="inline-flex items-center gap-1"><i class="fas fa-location-dot text-[10px]"></i>{{ $slot['venue'] }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                        <a href="{{ route('web.attendance.form', $slotCourse) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-primary text-white px-3 py-1.5 text-[12px] font-semibold hover:bg-primary/90">
                            <i class="fas fa-clipboard-check"></i>
                            Mark
                        </a>
                        <a href="{{ route('dashboard.teaching.attendance.course', $slotCourse) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] font-medium text-slate-700 hover:bg-white">
                            <i class="fas fa-list-check text-indigo-600 text-[11px]"></i>
                            Open
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- Classes & courses --}}
@if($classGroups->isEmpty() && $orphanCourses->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center">
        <span class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-book text-3xl"></i>
        </span>
        <p class="text-slate-700 font-medium">No classes or courses yet</p>
        <p class="text-slate-500 text-sm mt-1">Ask an administrator to assign you to classes and link your courses.</p>
    </div>
@else
    <div class="mb-3 flex items-end justify-between">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-800">My teaching</h2>
            <p class="text-[11px] text-slate-500">All your assigned classes and the courses you teach within each.</p>
        </div>
    </div>
    <div class="space-y-5">
        @foreach($classGroups as $group)
            @php $schoolClass = $group['class']; $classCourses = $group['courses']; @endphp
            <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                            <i class="fas fa-layer-group"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-slate-900 leading-tight">{{ $schoolClass->name }}</p>
                            <p class="text-[11px] text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-1.5">
                                <span>Level {{ $schoolClass->level ?? '—' }}</span>
                                @if($schoolClass->faculty)<span class="text-slate-300">·</span><span>{{ $schoolClass->faculty->name }}</span>@endif
                                <span class="text-slate-300">·</span>
                                <span class="inline-flex items-center gap-1"><i class="fas fa-user-graduate text-[10px]"></i>{{ $schoolClass->students_count }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                        <a href="{{ route('dashboard.classes.show', $schoolClass) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 hover:bg-slate-50">
                            <i class="fas fa-upload text-sky-600 text-[11px]"></i>
                            Roster
                        </a>
                        <a href="{{ route('dashboard.students.index', ['class_id' => $schoolClass->id]) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-700 hover:bg-slate-50">
                            <i class="fas fa-user-graduate text-sky-600 text-[11px]"></i>
                            Students
                        </a>
                    </div>
                </header>

                @if($classCourses->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-slate-500">
                        No courses linked to this class yet.
                    </div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($classCourses as $course)
                            @include('dashboard.partials.lecturer-course-card', ['course' => $course, 'schoolClass' => $schoolClass])
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach

        @if($orphanCourses->isNotEmpty())
            <section class="rounded-2xl border border-amber-200 bg-white overflow-hidden">
                <header class="px-5 py-3.5 border-b border-amber-100 bg-amber-50/60">
                    <p class="text-sm font-semibold text-amber-900">Courses outside assigned classes</p>
                    <p class="text-[11px] text-amber-900/70 mt-0.5">These courses are assigned to you but not linked to any of your classes. Contact admin to align them.</p>
                </header>
                <div class="divide-y divide-slate-100">
                    @foreach($orphanCourses as $course)
                        @include('dashboard.partials.lecturer-course-card', ['course' => $course, 'schoolClass' => null])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endif
@endsection
