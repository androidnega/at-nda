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
    $totalCourses = $classes->sum('courses_count');
@endphp

{{-- Summary cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-graduation-cap text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Total Classes</p>
            <p class="text-2xl font-bold text-gray-800">{{ $totalClasses }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center text-sky-600 flex-shrink-0">
            <i class="fas fa-users text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Students</p>
            <p class="text-2xl font-bold text-gray-800">{{ $totalStudents }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 flex-shrink-0">
            <i class="fas fa-book text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Courses</p>
            <p class="text-2xl font-bold text-gray-800">{{ $totalCourses }}</p>
        </div>
    </div>
</div>

{{-- Class cards grid --}}
@if($classes->isEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <span class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400 mx-auto mb-4">
            <i class="fas fa-graduation-cap text-3xl"></i>
        </span>
        <p class="text-gray-600 font-medium">No classes yet</p>
        <p class="text-gray-500 text-sm mt-1">Create your first class to get started</p>
        <a href="{{ route('dashboard.classes.create') }}" class="inline-flex items-center gap-2 mt-4 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">
            <i class="fas fa-plus"></i>
            Add Class
        </a>
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
        @foreach($classes as $class)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-4">
                    @if($class->logo_path)
                        <img src="{{ $class->logoUrl() }}" alt="{{ $class->name }} logo" class="w-12 h-12 rounded-xl border border-gray-200 object-cover bg-white flex-shrink-0">
                    @else
                        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                            <i class="fas fa-chalkboard-teacher text-xl"></i>
                        </span>
                    @endif
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-800 text-lg">{{ $class->name }}</h3>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-primary/10 text-primary text-sm font-medium">
                                Level {{ $class->level ?? '—' }}
                            </span>
                            @if($class->semester)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium">
                                    {{ $class->semester->display_label }}
                                </span>
                            @endif
                            @if($class->needsAcademicMetadataReview())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-100 text-amber-900 text-xs font-semibold">
                                    <i class="fas fa-exclamation-circle"></i> Update
                                </span>
                            @endif
                        </div>
                        <div class="mt-3 text-sm text-gray-500 space-y-1">
                            <p class="flex items-center gap-2"><i class="fas fa-building text-gray-400 w-4 text-center"></i> {{ $class->faculty?->name ?? '—' }}</p>
                            <p class="flex items-center gap-2"><i class="fas fa-sitemap text-gray-400 w-4 text-center"></i> {{ $class->department?->name ?? '—' }}</p>
                        </div>
                        <div class="mt-4 flex items-center gap-4 text-xs text-gray-500">
                            <span><i class="fas fa-book text-amber-500 mr-1"></i> {{ $class->courses_count }} courses</span>
                            <span><i class="fas fa-user-graduate text-sky-500 mr-1"></i> {{ $class->students_count }} students</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-5 py-3 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between gap-2">
                <a href="{{ route('dashboard.classes.show', $class) }}" class="inline-flex items-center gap-1.5 text-sky-600 hover:text-sky-700 text-sm font-medium">
                    <i class="fas fa-users"></i>
                    Students
                </a>
                <div class="flex gap-2">
                    <a href="{{ route('dashboard.classes.edit', $class) }}" class="inline-flex items-center gap-1.5 text-primary hover:text-primary/80 text-sm font-medium">
                        <i class="fas fa-pen"></i>
                        Edit
                    </a>
                    <form action="{{ route('dashboard.classes.destroy', ['schoolClass' => $class]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this class?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 text-red-600 hover:text-red-700 text-sm font-medium">
                            <i class="fas fa-trash-alt"></i>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
