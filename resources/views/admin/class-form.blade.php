@extends('layouts.admin')

@section('title', $schoolClass ? 'Edit Class' : 'Add Class')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">{{ $schoolClass ? 'Edit' : 'Add' }} Class</h1>
    <p class="text-gray-600 text-sm mt-1">{{ $schoolClass ? 'Update class details under school → faculty → department.' : 'Onboard a class under school → faculty → department (name, level, semester).' }}</p>
</div>

@if (session('error'))
    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">{{ session('error') }}</div>
@endif

@if($schoolClass?->needsAcademicMetadataReview())
    <div class="mb-4 p-4 bg-amber-50 text-amber-950 rounded-xl border border-amber-200 text-sm">
        <strong class="font-semibold">This class needs an update:</strong>
        set a <span class="font-medium">semester</span> and a valid <span class="font-medium">level</span> (100–400), then save.
    </div>
@endif

<form method="POST" action="{{ $schoolClass ? route('dashboard.classes.update', $schoolClass) : route('dashboard.classes.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    @csrf
    @if($schoolClass) @method('PUT') @endif

    <div class="p-6 space-y-5">
        <div>
            <label for="university_id" class="block text-sm font-medium text-gray-700 mb-2">School</label>
            <select name="university_id" id="university_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                <option value="">Select school...</option>
                @foreach($universities as $u)
                <option value="{{ $u->id }}" {{ (string) old('university_id', $schoolClass?->university_id ?? $schoolClass?->faculty?->university_id) === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="faculty_id" class="block text-sm font-medium text-gray-700 mb-2">Faculty</label>
            <select name="faculty_id" id="faculty_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                <option value="">Select faculty...</option>
                @foreach($faculties as $f)
                <option value="{{ $f->id }}" data-university="{{ $f->university_id }}" {{ old('faculty_id', $schoolClass?->faculty_id) == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department_id" id="department_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                <option value="">Select department...</option>
                @foreach($faculties as $f)
                    @foreach($f->departments as $d)
                    <option value="{{ $d->id }}" data-faculty="{{ $f->id }}" {{ old('department_id', $schoolClass?->department_id) == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Class Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. BTECH Group A"
                value="{{ old('name', $schoolClass?->name) }}"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="level" class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                <select name="level" id="level" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @foreach([100, 200, 300, 400] as $l)
                    <option value="{{ $l }}" {{ (string) old('level', $schoolClass?->level) === (string) $l ? 'selected' : '' }}>Level {{ $l }}</option>
                    @endforeach
                </select>
                @if($schoolClass && ($next = $schoolClass->suggestedNextLevel()))
                    <p class="text-xs text-gray-500 mt-1.5">If this cohort has moved up, you can set level to <span class="font-medium text-gray-700">Level {{ $next }}</span>.</p>
                @endif
                @error('level')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="qualification" class="block text-sm font-medium text-gray-700 mb-2">Qualification</label>
                @php
                    $currentQualification = old('qualification', $schoolClass?->qualification ?? 'degree');
                @endphp
                <select name="qualification" id="qualification" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    @foreach(\App\Models\SchoolClass::QUALIFICATION_LABELS as $key => $label)
                    <option value="{{ $key }}" {{ (string) $currentQualification === (string) $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1.5">Determines which catalog of courses can be added to this class. Lecturers are shared across all qualifications.</p>
                @error('qualification')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="semester_id" class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
            @if($semesters->isEmpty())
                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">No semesters yet. <a href="{{ route('dashboard.semesters.create') }}" class="font-medium text-primary underline">Add a semester</a> first.</p>
            @else
                <select name="semester_id" id="semester_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
                    <option value="">Select semester…</option>
                    @foreach($semesters as $sem)
                    <option value="{{ $sem->id }}" {{ (string) old('semester_id', $schoolClass?->semester_id) === (string) $sem->id ? 'selected' : '' }}>{{ $sem->display_label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1.5">Change this when the class runs in a new term or year.</p>
            @endif
            @error('semester_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <p class="text-xs text-gray-500">Attendance PDFs use the <strong>school logo</strong> from <a href="{{ route('dashboard.universities.index') }}" class="text-primary underline">Schools</a>.</p>
    </div>

    <div class="px-6 py-4 bg-gray-50 flex flex-wrap items-center gap-3">
        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none" @if($semesters->isEmpty()) disabled title="Add a semester first" @endif>Save</button>
        <a href="{{ route('dashboard.classes.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
        @if($semesters->isEmpty())
            <span class="text-sm text-amber-800">Add at least one semester before saving.</span>
        @endif
    </div>
</form>

<script>
(function() {
    var school = document.getElementById('university_id');
    var faculty = document.getElementById('faculty_id');
    var dept = document.getElementById('department_id');
    var facultyOpts = faculty.querySelectorAll('option[value]');
    var deptOpts = dept.querySelectorAll('option[data-faculty]');
    function filterFaculty() {
        var u = school.value;
        facultyOpts.forEach(function(o) {
            if (!o.value) return;
            var show = !u || o.dataset.university == u;
            o.hidden = !show;
            o.disabled = !show;
        });
        filterDept();
    }
    function filterDept() {
        var v = faculty.value;
        deptOpts.forEach(function(o) {
            o.style.display = (o.dataset.faculty == v || o.value == '') ? '' : 'none';
            o.disabled = (o.value && o.dataset.faculty != v);
        });
        if (!dept.querySelector('option:not([disabled]):not([value=""]):checked')) dept.value = '';
    }
    school.addEventListener('change', filterFaculty);
    faculty.addEventListener('change', filterDept);
    filterFaculty();
})();
</script>
@endsection
