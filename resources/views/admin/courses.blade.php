@extends('layouts.admin')

@section('title', 'Courses')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold">Courses</h1>
        <p class="text-gray-600 text-sm mt-1">Manage courses</p>
    </div>
    <a href="{{ route('dashboard.courses.create') }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700 inline-flex items-center justify-center">
        Create Course
    </a>
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-xl">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">{{ session('error') }}</div>
@endif

<div class="space-y-4">
    @foreach($courses as $course)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="font-semibold text-gray-800">{{ $course->course_name }}{{ $course->course_code ? ' (' . $course->course_code . ')' : '' }}</h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ $course->getScheduleLabel() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('web.attendance.form', $course) }}" target="_blank" class="text-blue-600 hover:underline text-sm">Attendance</a>
                <a href="{{ route('dashboard.pdf.export', $course) }}" target="_blank" class="text-gray-600 hover:underline text-sm">PDF</a>
                <a href="{{ route('dashboard.courses.edit', $course) }}" class="text-gray-600 hover:underline text-sm">Edit</a>
                <form action="{{ route('dashboard.courses.destroy', $course) }}" method="POST" class="inline" onsubmit="return confirm('Delete this course?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-4">
    {{ $courses->links() }}
</div>
@endsection
