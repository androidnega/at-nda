@extends('layouts.admin')

@section('title', $course ? 'Edit Course' : 'Create Course')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">{{ $course ? 'Edit' : 'Create' }} Course</h1>
    <p class="text-gray-600 text-sm mt-1">{{ $course ? 'Update course details' : 'Add a new course. Create only when there is an actual new course to add.' }}</p>
</div>

<form method="POST" action="{{ $course ? route('dashboard.courses.update', $course) : route('dashboard.courses.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    @csrf
    @if($course) @method('PUT') @endif

    <div class="p-6 space-y-5">
        <div class="border-b border-gray-100 pb-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Course Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                    <select id="class_id" name="class_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                        @foreach($classes ?? [] as $c)
                        <option value="{{ $c->id }}" {{ old('class_id', $course?->class_id) == $c->id ? 'selected' : '' }}>{{ $c->name }} · Level {{ $c->level ?? '—' }}</option>
                        @endforeach
                    </select>
                    @error('class_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="course_name" class="block text-sm font-medium text-gray-700 mb-2">Course Name</label>
                    <input type="text" id="course_name" name="course_name" value="{{ old('course_name', $course?->course_name) }}" required
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('course_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="course_code" class="block text-sm font-medium text-gray-700 mb-2">Course Code</label>
                    <input type="text" id="course_code" name="course_code" value="{{ old('course_code', $course?->course_code) }}" placeholder="e.g. CS203"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('course_code')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="border-b border-gray-100 pb-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Weekly Schedule</h3>
            <p class="text-xs text-gray-500 mb-3">Each course runs once per week. Set the day and time.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="day_of_week" class="block text-sm font-medium text-gray-700 mb-2">Day</label>
                    <select id="day_of_week" name="day_of_week" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                        @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d)
                        <option value="{{ $d }}" {{ old('day_of_week', $course?->day_of_week) == $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                    @error('day_of_week')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                    <input type="time" id="start_time" name="start_time" value="{{ old('start_time', $course?->start_time ? \Carbon\Carbon::parse($course->start_time)->format('H:i') : '09:00') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('start_time')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                    <input type="time" id="end_time" name="end_time" value="{{ old('end_time', $course?->end_time ? \Carbon\Carbon::parse($course->end_time)->format('H:i') : '11:00') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('end_time')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="venue_id" class="block text-sm font-medium text-gray-700 mb-2">Venue</label>
                    <select id="venue_id" name="venue_id" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary">
                        <option value="">— None —</option>
                        @foreach($venues ?? [] as $v)
                        <option value="{{ $v->id }}" {{ old('venue_id', $course?->venue_id) == $v->id ? 'selected' : '' }}>{{ $v->name }}{{ $v->code ? ' (' . $v->code . ')' : '' }}</option>
                        @endforeach
                    </select>
                    @error('venue_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="lecturer_id" class="block text-sm font-medium text-gray-700 mb-2">Assigned lecturer</label>
                    <select id="lecturer_id" name="lecturer_id" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary">
                        <option value="">— None —</option>
                        @foreach($lecturers ?? [] as $l)
                        <option value="{{ $l->id }}" {{ old('lecturer_id', $course?->lecturer_id) == $l->id ? 'selected' : '' }}>{{ $l->name }}{{ $l->schoolClass ? ' · ' . $l->schoolClass->name : '' }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Add people under <strong>Lecturers</strong> first, then assign here.</p>
                    @error('lecturer_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        @if(session()->has('admin_id'))
        <div class="border-b border-gray-100 pb-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Default session location (optional)</h3>
            <p class="text-xs text-gray-500 mb-3">When set, course reps can open <strong>location</strong> or <strong>hybrid</strong> sessions without entering coordinates — they can still override with GPS or manual entry.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="location_lat" class="block text-sm font-medium text-gray-700 mb-2">Latitude</label>
                    <input type="text" inputmode="decimal" id="location_lat" name="location_lat" value="{{ old('location_lat', $course?->location_lat) }}" placeholder="e.g. 5.6037"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('location_lat')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="location_lng" class="block text-sm font-medium text-gray-700 mb-2">Longitude</label>
                    <input type="text" inputmode="decimal" id="location_lng" name="location_lng" value="{{ old('location_lng', $course?->location_lng) }}" placeholder="e.g. -0.1870"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('location_lng')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="attendance_range_m" class="block text-sm font-medium text-gray-700 mb-2">Range (meters)</label>
                    <input type="number" id="attendance_range_m" name="attendance_range_m" value="{{ old('attendance_range_m', $course?->attendance_range_m) }}" min="1" max="5000" placeholder="e.g. 100"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @error('attendance_range_m')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div class="border-b border-gray-100 pb-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Attendance settings</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="attendance_window_minutes" class="block text-sm font-medium text-gray-700 mb-2">Offline sync window (min)</label>
                    <input type="number" id="attendance_window_minutes" name="attendance_window_minutes" value="{{ old('attendance_window_minutes', $course?->attendance_window_minutes ?? 60) }}" min="1"
                        class="w-full max-w-xs border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">How long offline records stay valid for sync</p>
                    @error('attendance_window_minutes')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="next_week_number" class="block text-sm font-medium text-gray-700 mb-2">Next week number (optional)</label>
                    <input type="number" id="next_week_number" name="next_week_number" value="{{ old('next_week_number', $course?->next_week_number) }}" min="1" max="500" placeholder="Leave empty for auto"
                        class="w-full max-w-xs border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">When the next <strong>new</strong> week row is created, use this number (then counts up). Use <a href="{{ route('dashboard.attendance-weeks.index') }}" class="text-primary font-medium underline">Attendance weeks</a> to reset data.</p>
                    @error('next_week_number')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700">Save</button>
        <a href="{{ route('dashboard.courses.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection
