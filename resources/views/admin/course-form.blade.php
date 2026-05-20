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
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Classes</label>
                    @php $courseClassIds = collect(old('class_ids', $course ? $course->assignedClassIds() : []))->map(fn ($id) => (int) $id); @endphp
                    <div class="border-2 border-gray-200 rounded-xl p-3 max-h-56 overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @forelse($classes ?? [] as $c)
                            <label class="flex items-start gap-2 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="class_ids[]" value="{{ $c->id }}" {{ $courseClassIds->contains($c->id) ? 'checked' : '' }}
                                    class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="text-sm text-gray-800 leading-snug">
                                    <span class="font-medium">{{ $c->name }}</span>
                                    <span class="text-gray-500"> · L{{ $c->level ?? '—' }}</span>
                                </span>
                            </label>
                            @empty
                            <p class="text-sm text-gray-500 col-span-full p-2">No classes yet. <a href="{{ route('dashboard.classes.create') }}" class="text-primary underline">Create a class</a> first.</p>
                            @endforelse
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5">Select every class that takes this course. A lecturer may teach many classes; each student only marks attendance from their class roster.</p>
                    @error('class_ids')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
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
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Per-class settings</h3>
            <p class="text-xs text-gray-500">Day, time, credit hours, lecturer and venue for this course are picked by each class rep on their per-class timetable, so two classes that share the course can run different schedules.</p>
        </div>

        @if(session()->has('admin_id'))
        <div class="border-b border-gray-100 pb-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Default session location (optional)</h3>
            <p class="text-xs text-gray-500 mb-3">When set, class reps can open <strong>location</strong> or <strong>hybrid</strong> sessions without entering coordinates — they can still override with GPS or manual entry.</p>
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
