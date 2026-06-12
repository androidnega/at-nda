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
@endif

@section('content')
@php
    $isStudentLayout = $pl !== 'classrep';
    $initials = $student->avatarInitials();
    // Identity (names, faculty, department) is editable only during the
    // first onboarding pass through this page. Once the profile is
    // complete, the fields render as read-only chips — the server-side
    // gate in StudentDashboardController::profileUpdate enforces the
    // same rule for spoofed POSTs.
    $identityLocked = $student->hasCompletedProfile();
    // Always trust the class the admin assigned this student to.
    // The legacy students.department_id column drifts out of sync
    // when admins move cohorts between departments and was the
    // source of "wrong faculty / wrong department" complaints on
    // this very page.
    $facultyLabel = optional($student->effectiveFaculty())->name;
    $departmentLabel = optional($student->effectiveDepartment())->name;
    $middleDisplay = trim((string) ($student->middle_name ?? ''));
@endphp

<div class="w-full {{ $pl === 'classrep' ? 'max-w-6xl mx-auto' : 'max-w-md mx-auto lg:max-w-2xl' }} space-y-5 sm:space-y-6">

    @if($isStudentLayout)
    {{-- Banking-app style hero: avatar + identity + a couple of stats --}}
    <div class="rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white p-5 sm:p-6 shadow-lg shadow-sky-500/20 dark:shadow-sky-500/10">
        <div class="flex items-center gap-4">
            @if($student->profileImageUrl())
                <img src="{{ $student->profileImageUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover ring-2 ring-white/40 shrink-0">
            @else
                <span class="h-16 w-16 rounded-full bg-white/15 backdrop-blur ring-2 ring-white/30 text-white flex items-center justify-center text-xl font-bold shrink-0">{{ $initials }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-[10px] uppercase tracking-widest font-semibold text-sky-100/80">Account holder</p>
                <p class="text-lg font-bold leading-tight truncate">{{ $student->getDisplayNameOrIndex() }}</p>
                <p class="text-xs text-sky-100 font-mono mt-0.5">{{ $student->index_number }}</p>
                @php $heroDept = $student->effectiveDepartment(); @endphp
                @if($heroDept?->name)
                    <p class="text-[11px] text-sky-100/90 mt-0.5 truncate">{{ $heroDept->name }}</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-5 sm:p-7">
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ $student->hasCompletedProfile() ? 'Your profile' : 'Complete your profile' }}</h1>

        @if (session('error'))
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-200 rounded-xl text-sm border border-red-100 dark:border-red-900/50">{{ session('error') }}</div>
        @endif

        @if (session('success'))
            <div class="mt-4 p-4 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 rounded-xl text-sm border border-emerald-100 dark:border-emerald-900/50">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf

            @if($identityLocked)
                {{-- Identity is locked once onboarding is complete.
                     Names + faculty + department come from the original
                     student record and can only be corrected by an
                     administrator on the back-office portal. --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                            <i class="fas fa-lock mr-1 text-[10px]"></i> Identity (locked)
                        </p>
                        <p class="text-[10.5px] text-slate-400 dark:text-slate-500">Contact an admin to correct</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">First name</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $student->first_name ?: '—' }}</p>
                        </div>
                        @if($middleDisplay !== '')
                        <div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Middle name</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $middleDisplay }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Last name</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $student->last_name ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Faculty</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $facultyLabel ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Department</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $departmentLabel ?: '—' }}</p>
                        </div>
                    </div>
                </div>
            @else
                {{-- First-time onboarding — these fields are required on
                     the very first save and then disappear from this
                     page forever. --}}
                <div>
                    <label for="first_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                    @error('first_name')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="middle_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Middle name <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $student->middle_name) }}" autocomplete="additional-name"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                    @error('middle_name')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                    @error('last_name')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            @endif
            <div>
                <label for="phone_number" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Phone <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span></label>
                <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number', $student->phone_number) }}" inputmode="tel" autocomplete="tel"
                    placeholder="e.g. 0244123456"
                    class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                @error('phone_number')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @if(\App\Support\SchemaFeatures::hasStudentsEmail())
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    Email <span class="text-slate-400 dark:text-slate-500 font-normal">(used for password reset)</span>
                </label>
                <input type="email" id="email" name="email" value="{{ old('email', $student->email) }}" inputmode="email" autocomplete="email"
                    placeholder="you@example.com"
                    class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                @error('email')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @include('partials.student-profile-camera', [
                'prefix' => 'profile_edit',
                'required' => !$student->profile_image,
                'label' => $student->profile_image ? 'Update profile photo' : 'Profile photo',
                'student' => $student,
                'showHelper' => false,
            ])
            @error('profile_photo')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
            @unless($identityLocked)
                {{-- Faculty + department only appear during first
                     onboarding. They're shown above in read-only form
                     once the profile is complete. --}}
                <div>
                    <label for="faculty_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Faculty</label>
                    <select id="faculty_id" name="faculty_id" required
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                        <option value="">Select faculty...</option>
                        @foreach($faculties as $f)
                        <option value="{{ $f->id }}" {{ (old('faculty_id') ?? $prefillFacultyId ?? '') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                        @endforeach
                    </select>
                    @error('faculty_id')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Department</label>
                    <select id="department_id" name="department_id" required
                        class="w-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-xl px-4 py-3 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500">
                        <option value="">Select faculty first...</option>
                    </select>
                    @error('department_id')<p class="text-red-500 dark:text-red-400 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            @endunless
            <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400 text-white py-3 rounded-xl font-semibold transition-colors shadow-sm">
                Save changes
            </button>
        </form>
    </div>

    {{-- About this app — concise credit for the developer, kept
         out of the form so it doesn't compete with the inputs. --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <div class="shrink-0 h-9 w-9 rounded-xl bg-sky-100 dark:bg-sky-900/40 flex items-center justify-center text-sky-700 dark:text-sky-300">
                <i class="fas fa-mobile-screen-button text-[14px]"></i>
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">About this app</h2>
                <p class="text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed mt-1">
                    {{ config('app.name', 'a-tenda') }} is a private attendance system built for tertiary classrooms.
                </p>
                <p class="text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed mt-2">
                    Developed by <span class="font-semibold text-slate-800 dark:text-slate-200">Emmanuel Kofi Kwofie</span>
                    (&ldquo;Manuel&rdquo;), a student at
                    <span class="font-semibold text-slate-800 dark:text-slate-200">Takoradi Technical University</span> &middot;
                    Department of Computer Science.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-[12px]">
                    @if (Route::has('about'))
                        <a href="{{ route('about') }}" class="inline-flex items-center gap-1.5 text-sky-700 dark:text-sky-300 hover:underline font-semibold">
                            <i class="fas fa-circle-info text-[11px]"></i> About the developer
                        </a>
                    @endif
                    @if (Route::has('downloads.app.landing'))
                        <a href="{{ route('downloads.app.landing') }}" class="inline-flex items-center gap-1.5 text-slate-700 dark:text-slate-300 hover:underline font-semibold">
                            <i class="fab fa-android text-[11px]"></i> Get the mobile app
                        </a>
                    @endif
                    <span class="text-slate-400 dark:text-slate-500">&copy; {{ date('Y') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Shared cropper modal. Reload-after-save keeps the hero
         avatar + flash message in sync with one server round-trip. --}}
    @include('partials.student-photo-cropper', ['cropperReload' => true])
</div>
@endsection

@push('scripts')
<script>
(function() {
    const facultySelect = document.getElementById('faculty_id');
    const deptSelect = document.getElementById('department_id');
    // When the identity block is locked (post-onboarding) these
    // selects are not in the DOM — bail out instead of throwing.
    if (!facultySelect || !deptSelect) return;

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
