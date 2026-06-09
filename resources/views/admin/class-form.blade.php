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

        @if(\App\Support\SchemaFeatures::hasClassesSemesterWeeks())
        <div>
            <label for="semester_weeks" class="block text-sm font-medium text-gray-700 mb-2">Weeks in semester</label>
            <input type="number"
                   id="semester_weeks"
                   name="semester_weeks"
                   min="{{ \App\Models\SchoolClass::MIN_SEMESTER_WEEKS }}"
                   max="{{ \App\Models\SchoolClass::MAX_SEMESTER_WEEKS }}"
                   step="1"
                   required
                   value="{{ old('semester_weeks', $schoolClass?->semester_weeks ?? \App\Models\SchoolClass::DEFAULT_SEMESTER_WEEKS) }}"
                   class="w-full sm:w-40 border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1.5">
                Total teaching weeks this class meets in the semester. Used as the consistent
                denominator on every course card on the student dashboard
                (e.g. <span class="font-medium text-gray-700">3 / {{ $schoolClass?->semester_weeks ?? \App\Models\SchoolClass::DEFAULT_SEMESTER_WEEKS }} wks</span>),
                so attendance reads the same way across every course on the class.
                Typical values: 12, 14, or 16.
            </p>
            @error('semester_weeks')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        @endif

        @if(\App\Support\SchemaFeatures::hasClassesSemesterDates())
        {{-- Calendar anchor for "which teaching week are we in".
             When start_date is set, the student dashboard derives
             the current week from the calendar instead of guessing
             from rep activity. End date is a soft cap (informational).
             Override is a manual escape hatch for when a public
             holiday or strike shifts the schedule. --}}
        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 space-y-4">
            <div>
                <p class="text-sm font-semibold text-blue-900">Semester calendar</p>
                <p class="text-xs text-blue-900/70 mt-1">
                    Set the start date and the system will tell every student which teaching week the class is currently in
                    (e.g. <span class="font-medium">Week 5 of {{ $schoolClass?->semester_weeks ?? \App\Models\SchoolClass::DEFAULT_SEMESTER_WEEKS }}</span>).
                    Leave blank to fall back to activity-based detection.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="semester_start_date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1.5">Semester start date</label>
                    <input type="date"
                           id="semester_start_date"
                           name="semester_start_date"
                           value="{{ old('semester_start_date', optional($schoolClass?->semester_start_date)->format('Y-m-d')) }}"
                           class="w-full border-2 border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                    <p class="text-[11px] text-gray-500 mt-1">First teaching day. Counts as the start of week 1.</p>
                    @error('semester_start_date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="semester_end_date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1.5">Semester end date <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                    <input type="date"
                           id="semester_end_date"
                           name="semester_end_date"
                           value="{{ old('semester_end_date', optional($schoolClass?->semester_end_date)->format('Y-m-d')) }}"
                           class="w-full border-2 border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                    <p class="text-[11px] text-gray-500 mt-1">Informational. If blank, end is start + weeks above.</p>
                    @error('semester_end_date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="current_week_override" class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1.5">Current week override <span class="text-gray-400 font-normal normal-case">(optional)</span></label>
                <input type="number"
                       id="current_week_override"
                       name="current_week_override"
                       min="0"
                       max="{{ \App\Models\SchoolClass::MAX_SEMESTER_WEEKS }}"
                       step="1"
                       placeholder="leave blank to auto-calculate"
                       value="{{ old('current_week_override', $schoolClass?->current_week_override) }}"
                       class="w-full sm:w-56 border-2 border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                <p class="text-[11px] text-gray-500 mt-1">
                    Use this only when the calendar doesn't reflect reality (e.g. a holiday week shifted things by 7 days).
                    When set, this wins over the start-date calculation.
                </p>
                @error('current_week_override')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            @if($schoolClass)
                @php $computed = $schoolClass->computeCurrentSemesterWeek(); @endphp
                @if($computed !== null)
                    <div class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-xs text-blue-900">
                        <i class="fas fa-circle-info text-blue-600 mr-1"></i>
                        Right now this class is on
                        <strong>Week {{ $computed }} of {{ $schoolClass->resolvedSemesterWeeks() }}</strong>
                        @if($schoolClass->current_week_override !== null)
                            <span class="text-blue-700">(manual override active)</span>
                        @else
                            <span class="text-blue-700">(from start date)</span>
                        @endif
                        — that's what every student card on this class will show.
                    </div>
                @endif
            @endif
        </div>
        @endif

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
