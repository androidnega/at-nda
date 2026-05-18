@extends('layouts.admin')

@section('title', 'Add staff account')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.staff-accounts.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> User management
    </a>
    <h1 class="text-2xl font-bold">Add staff account</h1>
    <p class="text-gray-500 text-sm mt-1">Create an administrator login, or add a lecturer with login and optional course assignments.</p>
</div>

<form method="POST" action="{{ route('dashboard.staff-accounts.store') }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden max-w-2xl">
    @csrf
    <div class="p-6 space-y-5">
        <div>
            <label for="account_type" class="block text-sm font-medium text-gray-700 mb-2">Account type</label>
            <select id="account_type" name="account_type" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="admin" {{ old('account_type', 'admin') === 'admin' ? 'selected' : '' }}>Administrator</option>
                <option value="lecturer" {{ old('account_type') === 'lecturer' ? 'selected' : '' }}>Lecturer</option>
            </select>
        </div>

        <div id="lecturer-account-fields" class="{{ old('account_type') === 'lecturer' ? '' : 'hidden' }} space-y-5">
            <div>
                <span class="block text-sm font-medium text-gray-700 mb-2">Lecturer</span>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="lecturer_source" value="existing" {{ old('lecturer_source', 'existing') !== 'new' ? 'checked' : '' }} class="text-primary focus:ring-primary">
                        Existing name
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="lecturer_source" value="new" {{ old('lecturer_source') === 'new' ? 'checked' : '' }} class="text-primary focus:ring-primary">
                        Create new lecturer
                    </label>
                </div>
            </div>

            <div id="lecturer-existing-fields" class="{{ old('lecturer_source') === 'new' ? 'hidden' : '' }}">
                <label for="lecturer_id" class="block text-sm font-medium text-gray-700 mb-2">Select lecturer</label>
                <select id="lecturer_id" name="lecturer_id" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">Select lecturer...</option>
                    @foreach($lecturers as $lecturer)
                        <option value="{{ $lecturer->id }}" {{ (string) old('lecturer_id') === (string) $lecturer->id ? 'selected' : '' }}>
                            {{ $lecturer->name }}@if($lecturer->schoolClass) · {{ $lecturer->schoolClass->name }}@endif
                        </option>
                    @endforeach
                </select>
                @error('lecturer_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div id="lecturer-new-fields" class="{{ old('lecturer_source') === 'new' ? '' : 'hidden' }} space-y-4">
                <div>
                    <label for="lecturer_name" class="block text-sm font-medium text-gray-700 mb-2">Full name</label>
                    <input type="text" id="lecturer_name" name="lecturer_name" value="{{ old('lecturer_name') }}"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
                    @error('lecturer_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="new_class_ids" class="block text-sm font-medium text-gray-700 mb-2">Assigned classes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select id="new_class_ids" name="class_ids[]" multiple size="6" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ collect(old('class_ids', []))->contains($class->id) ? 'selected' : '' }}>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($courses->isNotEmpty())
            <div>
                <label for="course_ids" class="block text-sm font-medium text-gray-700 mb-2">Assign to courses <span class="text-gray-400 font-normal">(optional)</span></label>
                <select id="course_ids" name="course_ids[]" multiple size="6"
                    class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" {{ collect(old('course_ids', []))->contains($course->id) ? 'selected' : '' }}>
                            {{ $course->course_name }}@if($course->course_code) ({{ $course->course_code }})@endif
                            @if($course->schoolClass) — {{ $course->schoolClass->name }}@endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-2">Hold Ctrl/Cmd to select multiple. You can also assign later on the Courses page.</p>
                @error('course_ids')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @endif

            <p class="text-xs text-gray-500">A temporary password is generated automatically. The lecturer must change it on first login at <strong>/admin</strong>.</p>
        </div>

        <div id="admin-account-fields" class="{{ old('account_type', 'admin') === 'admin' ? '' : 'hidden' }} space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="username"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary"
                placeholder="Alternate login beside email">
            @error('username')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
            <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('password')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6" autocomplete="new-password"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
        </div>
        </div>
    </div>
    <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">Create account</button>
        <a href="{{ route('dashboard.staff-accounts.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
<script>
(function () {
    var type = document.getElementById('account_type');
    var adminWrap = document.getElementById('admin-account-fields');
    var lecturerWrap = document.getElementById('lecturer-account-fields');
    var adminInputs = adminWrap ? adminWrap.querySelectorAll('input[required]') : [];
    var lecturerSelect = document.getElementById('lecturer_id');
    var lecturerName = document.getElementById('lecturer_name');
    var existingWrap = document.getElementById('lecturer-existing-fields');
    var newWrap = document.getElementById('lecturer-new-fields');
    var sourceRadios = document.querySelectorAll('input[name="lecturer_source"]');

    function lecturerSourceIsNew() {
        var checked = document.querySelector('input[name="lecturer_source"]:checked');
        return checked && checked.value === 'new';
    }

    function syncLecturerSource() {
        var isNew = lecturerSourceIsNew();
        if (existingWrap) existingWrap.classList.toggle('hidden', isNew);
        if (newWrap) newWrap.classList.toggle('hidden', !isNew);
        if (lecturerSelect) lecturerSelect.required = !isNew;
        if (lecturerName) lecturerName.required = isNew;
    }

    function sync() {
        var isLecturer = type && type.value === 'lecturer';
        if (adminWrap) adminWrap.classList.toggle('hidden', isLecturer);
        if (lecturerWrap) lecturerWrap.classList.toggle('hidden', !isLecturer);
        adminInputs.forEach(function (el) {
            el.required = !isLecturer;
        });
        if (isLecturer) {
            syncLecturerSource();
        } else if (lecturerSelect) {
            lecturerSelect.required = false;
            if (lecturerName) lecturerName.required = false;
        }
    }

    if (type) type.addEventListener('change', sync);
    sourceRadios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
@endsection
