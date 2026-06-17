@extends('layouts.admin')

@section('title', 'Classes')

@section('content')
<div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">Classes</h1>
        <p class="text-gray-500 text-sm mt-1">Faculty, Department, Class name and Level. To wipe attendance for a class or the whole system, use <a href="{{ route('dashboard.attendance-weeks.index') }}" class="text-primary font-medium hover:underline">Attendance reset</a>.</p>
    </div>
    <a href="{{ route('dashboard.classes.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90 shrink-0">
        <i class="fas fa-plus"></i>
        <span>Add Class</span>
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-6 p-4 bg-red-50 text-red-800 rounded-xl border border-red-100 flex items-center gap-2">
        <i class="fas fa-exclamation-circle text-red-600"></i>
        {{ session('error') }}
    </div>
@endif

@if(isset($classesNeedingReview) && $classesNeedingReview->isNotEmpty())
    <div class="mb-6 p-4 bg-amber-50 text-amber-950 rounded-xl border border-amber-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex gap-3">
            <span class="shrink-0 w-10 h-10 rounded-xl bg-amber-200/80 flex items-center justify-center text-amber-900">
                <i class="fas fa-layer-group"></i>
            </span>
            <div>
                <p class="font-semibold text-amber-950">{{ $classesNeedingReview->count() }} class(es) need semester or level</p>
                <p class="text-sm text-amber-900/90 mt-0.5">Legacy rows may be missing a semester or valid level. Open each class and set <span class="font-medium">Semester</span> and <span class="font-medium">Level</span>.</p>
            </div>
        </div>
        <a href="{{ route('dashboard.semesters.index') }}" class="shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white border border-amber-300 text-amber-950 text-sm font-medium hover:bg-amber-100/80">Manage semesters</a>
    </div>
@endif

@php
    $totalClasses = $classes->count();
    $totalStudents = $classes->sum('students_count');
    $totalCourses = $classes->sum(fn ($c) => (int) ($c->courses_count_all ?? 0));
    $totalLecturers = $classes->sum(fn ($c) => (int) ($c->lecturers_count_all ?? 0));
@endphp

{{-- Summary strip --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
    <div class="relative overflow-hidden rounded-2xl bg-white p-4 sm:p-5 ring-1 ring-slate-200/70">
        <div class="absolute inset-x-0 top-0 h-0.5 bg-primary"></div>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Classes</p>
        <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums leading-none">{{ $totalClasses }}</p>
    </div>
    <div class="relative overflow-hidden rounded-2xl bg-white p-4 sm:p-5 ring-1 ring-slate-200/70">
        <div class="absolute inset-x-0 top-0 h-0.5 bg-sky-500"></div>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Students</p>
        <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums leading-none">{{ $totalStudents }}</p>
    </div>
    <div class="relative overflow-hidden rounded-2xl bg-white p-4 sm:p-5 ring-1 ring-slate-200/70">
        <div class="absolute inset-x-0 top-0 h-0.5 bg-amber-500"></div>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Courses</p>
        <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums leading-none">{{ $totalCourses }}</p>
    </div>
    <div class="relative overflow-hidden rounded-2xl bg-white p-4 sm:p-5 ring-1 ring-slate-200/70">
        <div class="absolute inset-x-0 top-0 h-0.5 bg-indigo-500"></div>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lecturers</p>
        <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums leading-none">{{ $totalLecturers }}</p>
    </div>
</div>

{{-- Class cards --}}
@if($classes->isEmpty())
    <div class="rounded-2xl bg-white ring-1 ring-slate-200/70 px-6 py-14 sm:py-16 text-center">
        <span class="inline-flex w-14 h-14 rounded-2xl bg-slate-100 items-center justify-center text-slate-400 mb-4">
            <i class="fas fa-graduation-cap text-2xl"></i>
        </span>
        <p class="text-slate-800 font-semibold">No classes yet</p>
        <p class="text-slate-500 text-sm mt-1 max-w-sm mx-auto">Create your first class to organise students, courses, and lecturers.</p>
        <a href="{{ route('dashboard.classes.create') }}" class="inline-flex items-center gap-2 mt-5 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-primary/90 transition-colors">
            <i class="fas fa-plus text-xs"></i>
            Add Class
        </a>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
        @foreach($classes as $class)
        @php
            $qualKey = $class->resolvedQualification();
            $qualLabel = $class->qualificationLabel();
            $qualRing = match ($qualKey) {
                'hnd' => 'ring-emerald-200/80 text-emerald-700 bg-emerald-50/60',
                'diploma' => 'ring-amber-200/80 text-amber-800 bg-amber-50/60',
                default => 'ring-indigo-200/80 text-indigo-700 bg-indigo-50/60',
            };
            $coursesCount = (int) ($class->courses_count_all ?? $class->courses_count ?? 0);
            $lecturersCount = (int) ($class->lecturers_count_all ?? 0);
            $logoUrl = $class->logoUrl();
            $initial = strtoupper(mb_substr(trim($class->name), 0, 1));
        @endphp
        <article class="group flex flex-col rounded-2xl bg-white ring-1 ring-slate-200/70 overflow-hidden transition duration-200 hover:ring-slate-300/90 hover:shadow-[0_8px_30px_-12px_rgba(15,23,42,0.18)]">
            <div class="p-5 sm:p-5">
                <div class="flex items-start gap-3.5">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="w-11 h-11 rounded-xl object-cover ring-1 ring-slate-200/80 shrink-0 bg-white">
                    @else
                        <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-100 to-slate-50 ring-1 ring-slate-200/80 flex items-center justify-center text-sm font-bold text-slate-500 shrink-0">
                            {{ $initial }}
                        </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-slate-900 text-base leading-snug truncate" title="{{ $class->name }}">{{ $class->name }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500 truncate" title="{{ $class->faculty?->name }} · {{ $class->department?->name }}">
                            {{ $class->faculty?->name ?? '—' }}
                            @if($class->department?->name)
                                <span class="text-slate-300 mx-1">·</span>{{ $class->department->name }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="mt-3.5 flex flex-wrap gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1 ring-slate-200/90 bg-slate-50 text-slate-600">
                        Level {{ $class->level ?? '—' }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wide ring-1 {{ $qualRing }}">
                        {{ $qualLabel }}
                    </span>
                    @if($class->semester)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1 ring-slate-200/90 bg-white text-slate-600">
                            {{ $class->semester->display_label }}
                        </span>
                    @endif
                    @if($class->needsAcademicMetadataReview())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold ring-1 ring-amber-200/90 bg-amber-50 text-amber-800">
                            <i class="fas fa-circle-exclamation text-[9px]"></i>
                            Needs update
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-3 border-t border-slate-100 bg-slate-50/40">
                <div class="px-3 py-3.5 text-center border-r border-slate-100">
                    <p class="text-base font-semibold text-slate-900 tabular-nums">{{ $class->students_count }}</p>
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400 mt-0.5">Students</p>
                </div>
                <div class="px-3 py-3.5 text-center border-r border-slate-100" title="Includes direct assignments and shared courses via the course_class pivot">
                    <p class="text-base font-semibold text-slate-900 tabular-nums">{{ $coursesCount }}</p>
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400 mt-0.5">Courses</p>
                </div>
                <div class="px-3 py-3.5 text-center">
                    <p class="text-base font-semibold text-slate-900 tabular-nums">{{ $lecturersCount }}</p>
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-400 mt-0.5">Lecturers</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5 p-2.5 border-t border-slate-100">
                <a href="{{ route('dashboard.classes.show', $class) }}"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 ring-1 ring-slate-200/60 transition-colors">
                    <i class="fas fa-users text-[10px] text-slate-400"></i>
                    Students
                </a>
                <a href="{{ route('dashboard.classes.edit', $class) }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:text-primary hover:bg-primary/5 ring-1 ring-slate-200/60 transition-colors"
                   title="Edit class">
                    <i class="fas fa-pen text-xs"></i>
                </a>
                <form action="{{ route('dashboard.classes.destroy', ['schoolClass' => $class]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this class?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 ring-1 ring-slate-200/60 transition-colors"
                            title="Delete class">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </form>
            </div>
        </article>
        @endforeach
    </div>
@endif
@endsection
