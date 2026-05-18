@extends('layouts.admin')

@section('title', 'Lecturer Dashboard')

@section('content')
@php
    $greeting = match (true) {
        now()->hour < 12 => 'Good Morning',
        now()->hour < 17 => 'Good Afternoon',
        default => 'Good Evening',
    };
@endphp

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-primary">{{ $greeting }}, {{ $lecturer->name }}</h1>
    <p class="text-gray-500 text-sm mt-1">Your assigned classes and courses</p>
    @if($lecturer->assignedClassesLabel() !== '')
    <p class="text-sm text-gray-600 mt-2 flex flex-wrap items-center gap-2">
        <i class="fas fa-layer-group text-primary"></i>
        <span>{{ $lecturer->assignedClassesLabel() }}</span>
    </p>
    @endif
    <a href="{{ route('lecturer.password.change.form') }}" class="inline-flex items-center gap-2 mt-3 text-sm text-primary hover:underline">
        <i class="fas fa-key"></i> Change password
    </a>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-book text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">My courses</p>
            <p class="text-2xl font-bold text-gray-800">{{ $courses->count() }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0">
            <i class="fas fa-users-rectangle text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Assigned classes</p>
            <p class="text-2xl font-bold text-gray-800">{{ $classGroups->count() }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4 col-span-2 lg:col-span-2">
        <span class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center text-sky-600 flex-shrink-0">
            <i class="fas fa-user-graduate text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Students (your classes)</p>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($totalStudents) }}</p>
        </div>
    </div>
</div>

@if($classGroups->isEmpty() && $orphanCourses->isEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <span class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400 mx-auto mb-4">
            <i class="fas fa-book text-3xl"></i>
        </span>
        <p class="text-gray-600 font-medium">No classes or courses yet</p>
        <p class="text-gray-500 text-sm mt-1">Ask an administrator to assign you to classes and link your courses.</p>
    </div>
@else
    <div class="space-y-6">
        @foreach($classGroups as $group)
        @php $schoolClass = $group['class']; $classCourses = $group['courses']; @endphp
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $schoolClass->name }}</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Level {{ $schoolClass->level ?? '—' }}
                            @if($schoolClass->faculty) · {{ $schoolClass->faculty->name }}@endif
                            · {{ $schoolClass->students_count }} {{ \Illuminate\Support\Str::plural('student', $schoolClass->students_count) }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('dashboard.students.index', ['class_id' => $schoolClass->id]) }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-white shrink-0">
                    <i class="fas fa-user-graduate text-sky-600"></i>
                    View students
                </a>
            </div>

            @if($classCourses->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-500">
                No courses linked to this class yet.
            </div>
            @else
            <div class="divide-y divide-gray-100">
                @foreach($classCourses as $course)
                @include('dashboard.partials.lecturer-course-card', ['course' => $course, 'schoolClass' => $schoolClass])
                @endforeach
            </div>
            @endif
        </section>
        @endforeach

        @if($orphanCourses->isNotEmpty())
        <section class="bg-white rounded-xl shadow-sm border border-amber-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/80">
                <h2 class="text-lg font-semibold text-amber-950">Courses outside assigned classes</h2>
                <p class="text-sm text-amber-900/80 mt-0.5">These courses are assigned to you but not linked to your class list. Contact admin to align class assignments.</p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($orphanCourses as $course)
                @include('dashboard.partials.lecturer-course-card', ['course' => $course, 'schoolClass' => null])
                @endforeach
            </div>
        </section>
        @endif
    </div>
@endif
@endsection
