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
    <p class="text-gray-500 text-sm mt-1">Lecturer · Only your linked courses/classes are visible</p>
    <a href="{{ route('lecturer.password.change.form') }}" class="inline-flex items-center gap-2 mt-3 text-sm text-primary hover:underline">
        <i class="fas fa-key"></i> Change password
    </a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 sm:mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-book text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">My Courses</p>
            <p class="text-2xl font-bold text-gray-800">{{ $courses->count() }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0">
            <i class="fas fa-users-rectangle text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Classes I Teach</p>
            <p class="text-2xl font-bold text-gray-800">{{ $courses->pluck('class_id')->filter()->unique()->count() }}</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
    @forelse($courses as $course)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <span class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 flex-shrink-0">
                    <i class="fas fa-chalkboard-teacher text-xl"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-800">{{ $course->course_name }}</h3>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $course->course_code ?? '—' }} · {{ $course->schoolClass?->name ?? '—' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $course->getScheduleLabel() }}</p>
                </div>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-50/50 border-t border-gray-100 text-xs text-gray-500">
            Assigned class: <span class="font-medium text-gray-700">{{ $course->schoolClass?->name ?? '—' }}</span>
        </div>
        @if($course->attendanceWeeks->isNotEmpty())
        <div class="px-5 py-4 border-t border-gray-100 bg-white">
            <p class="text-xs font-semibold text-gray-700 mb-2">Teaching weeks</p>
            <ul class="space-y-2 list-none p-0 m-0">
                @foreach($course->attendanceWeeks->sortBy('week_number') as $week)
                <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs border-b border-gray-50 last:border-0 pb-2 last:pb-0">
                    <div>
                        <span class="font-medium text-gray-800">W{{ $week->week_number }}</span>
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
                                <input type="text" name="note" placeholder="Note" class="border border-gray-200 rounded px-2 py-1 text-xs w-32 max-w-full">
                                <button type="submit" class="rounded-md bg-amber-700 text-white px-2 py-1 text-xs font-semibold hover:bg-amber-800">Cancel week</button>
                            </form>
                        @endif
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    @empty
    <div class="col-span-full bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <span class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400 mx-auto mb-4">
            <i class="fas fa-book text-3xl"></i>
        </span>
        <p class="text-gray-600 font-medium">No courses assigned</p>
        <p class="text-gray-500 text-sm mt-1">Contact admin to assign courses to your account</p>
    </div>
    @endforelse
</div>
@endsection
