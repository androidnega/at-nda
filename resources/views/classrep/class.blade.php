@extends('layouts.classrep')

@section('title', 'My Class')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-primary">My Class</h1>
    <p class="text-gray-500 text-sm mt-1">Classes you represent</p>
</div>

@if($classes->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 p-12 text-center">
        <span class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mx-auto mb-4">
            <i class="fas fa-layer-group text-3xl"></i>
        </span>
        <p class="text-gray-600 font-medium">No classes assigned</p>
        <p class="text-gray-500 text-sm mt-1">You have not been assigned as rep for any class yet</p>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($classes as $class)
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start gap-4">
                <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                    <i class="fas fa-layer-group text-xl"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <h2 class="font-semibold text-gray-800 text-lg">{{ $class->name }}</h2>
                    @if($class->code)
                        <p class="text-sm text-gray-500">{{ $class->code }}</p>
                    @endif
                    @if($class->department)
                        <p class="text-sm text-gray-600 mt-1">{{ $class->department->name }}</p>
                    @endif
                    <div class="flex gap-4 mt-3 text-sm">
                        <span class="text-gray-500"><i class="fas fa-users text-primary/70 mr-1"></i> {{ $class->students_count }} students</span>
                        <span class="text-gray-500"><i class="fas fa-book text-primary/70 mr-1"></i> {{ $class->courses_count }} courses</span>
                    </div>
                </div>
            </div>

            @php $assignedCourses = $class->relationLoaded('assignedCourses') ? $class->assignedCourses : collect(); @endphp
            @if($assignedCourses->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Assigned courses</p>
                    <ul class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                        @foreach($assignedCourses as $course)
                            <li class="flex items-start gap-2 text-sm">
                                <i class="fas fa-book text-primary/60 text-xs mt-1"></i>
                                <span class="text-gray-700">
                                    <span class="font-medium">{{ $course->course_name }}</span>
                                    @if($course->course_code)
                                        <span class="text-gray-500"> ({{ $course->course_code }})</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500">No courses assigned to this class yet.</p>
                </div>
            @endif
        </div>
        @endforeach
    </div>
@endif
@endsection
