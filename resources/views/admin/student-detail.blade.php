@extends('layouts.admin')

@section('title', $student->index_number . ' - Student')

@section('content')
<div class="mb-6">
    <a href="{{ request()->headers->get('referer') ?: route('dashboard.students.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <div class="flex flex-col sm:flex-row sm:items-start gap-4 mt-2">
        @if($student->profileImageUrl())
        <img src="{{ $student->profileImageUrl() }}" alt="" class="h-20 w-20 rounded-full object-cover border border-gray-200 flex-shrink-0">
        @else
        <span class="h-20 w-20 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xl font-semibold flex-shrink-0">{{ $student->avatarInitials() }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl sm:text-3xl font-bold text-primary">
                @if($student->getDisplayName() !== '')
                    {{ $student->getDisplayName() }}
                @else
                    <span class="font-mono text-xl sm:text-2xl">{{ $student->index_number }}</span>
                @endif
            </h1>
            <p class="text-gray-500 text-sm mt-1 flex items-center gap-2 flex-wrap">
                @if($student->getDisplayName() !== '')
                    <span class="font-mono text-gray-800">{{ $student->index_number }}</span>
                @endif
                @if($student->isRep())
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Rep</span>
                @endif
                <span class="text-gray-400">·</span>
                <span>{{ $student->getProgramLabel() }}</span>
            </p>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-6 p-4 bg-red-50 text-red-800 rounded-xl border border-red-100 flex items-center gap-2">
        <i class="fas fa-exclamation-circle text-red-600"></i>
        {{ session('error') }}
    </div>
@endif

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
            <i class="fas fa-book text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Courses (class)</p>
            <p class="text-2xl font-bold text-gray-800">{{ $coursesCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-700 flex-shrink-0">
            <i class="fas fa-check text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Present marks</p>
            <p class="text-2xl font-bold text-gray-800">{{ $presentCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-700 flex-shrink-0">
            <i class="fas fa-times text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Absent (est.)</p>
            <p class="text-2xl font-bold text-gray-800">{{ $absentCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
        <span class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center text-sky-700 flex-shrink-0">
            <i class="fas fa-layer-group text-xl"></i>
        </span>
        <div class="min-w-0">
            <p class="text-gray-500 text-sm font-medium">Class</p>
            <p class="text-lg font-bold text-gray-800 truncate">{{ $student->schoolClass?->name ?? '—' }}</p>
        </div>
    </div>
</div>

{{-- Identity & account --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Profile &amp; account</h2>
        <p class="text-sm text-gray-500 mt-0.5">All stored fields for this student.</p>
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-0 sm:divide-x sm:divide-gray-100">
        <div class="p-5 space-y-4 sm:border-b border-gray-100">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Index number</dt>
                <dd class="mt-1 font-mono text-sm text-gray-900">{{ $student->index_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">First name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->first_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Middle name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->middle_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Last name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->last_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->phone_number ?? '—' }}</dd>
            </div>
        </div>
        <div class="p-5 space-y-4 border-t sm:border-t-0 border-gray-100">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Program (from index)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->getProgramLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Department (onboarding)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->department?->name ?? '—' }}</dd>
            </div>
            @if($student->department?->faculty)
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Faculty (onboarding)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->department->faculty->name }}</dd>
            </div>
            @endif
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Bound IP</dt>
                <dd class="mt-1 font-mono text-sm text-gray-900">{{ $student->bound_ip ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Push notifications</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $student->deviceToken ? 'Device token registered' : 'Not registered' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Record</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <span class="block">Created {{ $student->created_at?->format('Y-m-d H:i') ?? '—' }}</span>
                    <span class="block text-gray-600">Updated {{ $student->updated_at?->format('Y-m-d H:i') ?? '—' }}</span>
                </dd>
            </div>
        </div>
    </dl>
</div>

{{-- Class enrollment --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Class enrollment</h2>
    </div>
    <div class="p-5">
        @if($student->schoolClass)
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Class name</dt>
                    <dd class="mt-1 font-medium text-gray-900">{{ $student->schoolClass->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Level</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->level ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Faculty</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->faculty?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Department</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->department?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Semester</dt>
                    <dd class="mt-1 text-gray-900">{{ $student->schoolClass->semester?->display_label ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Week rows (class courses)</dt>
                    <dd class="mt-1 tabular-nums text-gray-900">{{ $totalWeeks }}</dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-gray-600">No class assigned.</p>
        @endif
    </div>
</div>

@if($student->classReps->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Class rep assignments</h2>
        <p class="text-sm text-gray-500 mt-0.5">Rep roles per class.</p>
    </div>
    <ul class="divide-y divide-gray-100">
        @foreach($student->classReps as $cr)
        <li class="px-5 py-3 flex items-center justify-between gap-3">
            <span class="font-medium">{{ $cr->schoolClass?->name ?? '—' }}</span>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-xs {{ $cr->isMainRep() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">{{ $cr->isMainRep() ? 'Rep' : 'Assist' }}</span>
                <form action="{{ route('dashboard.students.remove-rep', $student) }}" method="POST" class="inline" onsubmit="return confirm('Remove rep assignment?')">
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $cr->class_id }}">
                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Remove</button>
                </form>
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

@if(isset($recentAttendance) && $recentAttendance->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Recent attendance</h2>
        <p class="text-sm text-gray-500 mt-0.5">Latest {{ $recentAttendance->count() }} marks recorded.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Course</th>
                    <th class="px-4 py-3">Week</th>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentAttendance as $a)
                <tr class="hover:bg-gray-50/80">
                    <td class="px-4 py-3 text-gray-900">{{ $a->course?->course_name ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums text-gray-700">W{{ $a->attendanceWeek?->week_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $a->attendance_time?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="text-gray-800">{{ $a->status ?? '—' }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Assign as class rep</h2>
        <p class="text-sm text-gray-500 mt-0.5">Make this student a class rep or assistant rep for a class.</p>
    </div>
    <form method="POST" action="{{ route('dashboard.students.assign-rep', $student) }}" class="p-5 flex flex-wrap gap-4 items-end">
        @csrf
        <div class="min-w-[200px] flex-1">
            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select id="class_id" name="class_id" required class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary">
                <option value="">Select class...</option>
                @foreach($classes ?? [] as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select id="role" name="role" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20">
                <option value="rep">Class Rep</option>
                <option value="assist">Assistant Rep</option>
            </select>
        </div>
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-primary/90">Assign</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Reset password</h2>
        <p class="text-sm text-gray-500 mt-0.5">Generate a new password for this student. Copy and share it with them.</p>
    </div>
    <form method="POST" action="{{ route('dashboard.students.reset-password', $student) }}" class="p-5">
        @csrf
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-primary/90" onclick="return confirm('Generate a new password? The current password will be replaced.')">
            <i class="fas fa-key mr-1"></i> Generate password
        </button>
    </form>
</div>

@if(session()->has('admin_id'))
<div class="rounded-xl border border-red-200 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-red-100 bg-red-50">
        <h2 class="font-semibold text-red-950">Remove student</h2>
        <p class="text-sm text-red-900/90 mt-0.5">Permanently deletes this student, rep assignments, attendance marks, and device registration. This cannot be undone.</p>
    </div>
    <div class="p-5">
        <form method="POST" action="{{ route('dashboard.students.destroy', $student) }}" onsubmit="return confirm('Permanently delete this student ({{ e($student->index_number) }})? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg border-2 border-red-600 bg-white text-red-700 px-5 py-2.5 text-sm font-semibold hover:bg-red-50">
                <i class="fas fa-user-minus"></i> Remove student
            </button>
        </form>
    </div>
</div>
@endif
@endsection
