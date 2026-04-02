@php $pl = $profileLayout ?? 'student'; @endphp
@extends($pl === 'classrep' ? 'layouts.classrep' : 'layouts.student')

@section('title', 'Profile')

@if($pl === 'classrep')
@section('header')
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm text-gray-500">
        <a href="{{ route('dashboard.dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-800 truncate">Profile</span>
    </nav>
@endsection
@else
@section('breadcrumb')
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm text-slate-500">
        <a href="{{ route('dashboard.dashboard') }}" class="hover:text-amber-700 transition-colors">Dashboard</a>
        <span class="text-slate-300">/</span>
        <span class="font-semibold text-slate-800 truncate">Profile</span>
    </nav>
@endsection
@endif

@section('content')
<div class="w-full {{ $pl === 'classrep' ? 'max-w-6xl mx-auto' : 'max-w-lg sm:max-w-xl' }}">
    <div class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-7">
        <h1 class="text-xl font-bold text-slate-900">{{ $student->hasCompletedProfile() ? 'Your profile' : 'Complete your profile' }}</h1>
        <p class="text-slate-500 text-sm mt-1">Name, department, phone and photo stay in sync with the mobile app</p>

        @if (session('error'))
            <div class="mt-4 p-4 bg-red-50 text-red-800 rounded-xl text-sm border border-red-100">{{ session('error') }}</div>
        @endif

        @if($student->profileImageUrl())
        <div class="mt-4 flex justify-center">
            <img src="{{ $student->profileImageUrl() }}" alt="" class="h-24 w-24 rounded-full object-cover border-2 border-slate-100">
        </div>
        @endif

        <form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="first_name" class="block text-sm font-medium text-slate-700 mb-2">First Name</label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('first_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="middle_name" class="block text-sm font-medium text-slate-700 mb-2">Middle name <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $student->middle_name) }}" autocomplete="additional-name"
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('middle_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="last_name" class="block text-sm font-medium text-slate-700 mb-2">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('last_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone_number" class="block text-sm font-medium text-slate-700 mb-2">Phone <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number', $student->phone_number) }}" inputmode="tel" autocomplete="tel"
                    placeholder="e.g. 0244123456"
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('phone_number')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @include('partials.student-profile-camera', [
                'prefix' => 'profile_edit',
                'required' => !$student->profile_image,
                'label' => $student->profile_image ? 'Update profile photo' : 'Profile photo',
            ])
            @error('profile_photo')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            <div>
                <label for="faculty_id" class="block text-sm font-medium text-slate-700 mb-2">Faculty</label>
                <select id="faculty_id" name="faculty_id" required class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <option value="">Select faculty...</option>
                    @foreach($faculties as $f)
                    <option value="{{ $f->id }}" {{ (old('faculty_id') ?? $prefillFacultyId ?? '') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                    @endforeach
                </select>
                @error('faculty_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="department_id" class="block text-sm font-medium text-slate-700 mb-2">Department</label>
                <select id="department_id" name="department_id" required class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <option value="">Select faculty first...</option>
                </select>
                @error('department_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full bg-amber-700 text-white py-3 rounded-xl font-semibold hover:bg-amber-800 transition">
                Save
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const facultySelect = document.getElementById('faculty_id');
    const deptSelect = document.getElementById('department_id');
    const faculties = @json($faculties);
    const oldDept = '{{ old("department_id") ?? $prefillDepartmentId ?? "" }}';

    function updateDepartments() {
        const fid = parseInt(facultySelect.value, 10);
        deptSelect.innerHTML = '<option value="">Select department...</option>';
        if (!fid) return;
        const faculty = faculties.find(function(f) { return f.id === fid; });
        if (faculty && faculty.departments) {
            faculty.departments.forEach(function(d) {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name;
                if (oldDept == d.id) opt.selected = true;
                deptSelect.appendChild(opt);
            });
        }
    }
    facultySelect.addEventListener('change', updateDepartments);
    if (facultySelect.value) updateDepartments();
})();
</script>
@endpush
