@extends('layouts.admin')

@section('title', 'Lecturers')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Lecturers</h1>
        <p class="text-gray-500 text-sm mt-1 max-w-2xl">
            Teaching staff directory. Assign <strong>classes</strong> here (who they can view in the dashboard) and
            link <strong>courses</strong> on the <a href="{{ route('dashboard.courses.index') }}" class="text-primary hover:underline">Courses</a> page.
            Every lecturer added here automatically gets a login under
            <a href="{{ route('dashboard.staff-accounts.index') }}" class="text-primary hover:underline">User management</a>;
            the temporary password is shown once on the confirmation banner above.
        </p>
    </div>
    @if(session()->has('admin_id'))
    <a href="{{ route('dashboard.lecturers.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90 shrink-0">
        <i class="fas fa-plus"></i>
        Add lecturer
    </a>
    @endif
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

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Username</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Assigned classes</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Courses teaching</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Staff login</th>
                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($lecturers as $l)
                @php
                    $classesLabel = $l->assignedClassesLabel();
                    if ($classesLabel === '' && $l->courses->isNotEmpty()) {
                        $fromCourses = $l->courses
                            ->flatMap(fn ($c) => $c->assignedClassesLabel() !== '' ? explode(', ', $c->assignedClassesLabel()) : [])
                            ->unique()
                            ->filter()
                            ->values();
                        $classesLabel = $fromCourses->join(', ');
                    }
                @endphp
                <tr class="hover:bg-gray-50/50 align-top">
                    <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ $l->name }}</td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-700 whitespace-nowrap">
                        @if(!empty($l->username))
                            {{ $l->username }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-[200px]">
                        @if($classesLabel !== '')
                        <span class="inline-flex items-start gap-1.5">
                            <i class="fas fa-layer-group text-indigo-500 mt-0.5 text-xs"></i>
                            <span>{{ $classesLabel }}</span>
                        </span>
                        @else
                        <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                        @if($l->courses_count > 0)
                        <ul class="space-y-1.5">
                            @foreach($l->courses as $course)
                            <li>
                                <span class="font-medium text-gray-800">{{ $course->course_name }}</span>
                                @if($course->course_code)
                                <span class="text-gray-500">({{ $course->course_code }})</span>
                                @endif
                                @php $courseClasses = $course->assignedClassesLabel(); @endphp
                                @if($courseClasses !== '')
                                <span class="block text-xs text-gray-500 mt-0.5">
                                    <i class="fas fa-layer-group text-gray-400 mr-0.5"></i>{{ $courseClasses }}
                                </span>
                                @endif
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <span class="text-gray-400">No courses linked</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                        @if($l->hasStaffLogin())
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-green-50 text-green-800 text-xs font-medium border border-green-100">
                            <i class="fas fa-circle-check"></i>
                            Active
                        </span>
                        @if($l->staffLoginLabel() !== '')
                        <p class="text-gray-600 mt-1 font-mono text-xs">{{ $l->staffLoginLabel() }}</p>
                        @endif
                        @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium">
                            No login
                        </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('dashboard.lecturers.edit', $l) }}" class="text-primary hover:underline text-sm font-medium">Edit</a>
                        @if(session()->has('admin_id') && ! $l->hasStaffLogin())
                        <form action="{{ route('dashboard.staff-accounts.lecturers.reset-password', $l) }}" method="POST" class="inline ml-2"
                              onsubmit="return confirm('Issue a login for {{ addslashes($l->name) }}? The temporary password will be shown once on the next screen.')">
                            @csrf
                            <button type="submit" class="text-emerald-700 hover:underline text-sm font-medium">Issue login</button>
                        </form>
                        @endif
                        @if(session()->has('admin_id'))
                        <form action="{{ route('dashboard.lecturers.destroy', $l) }}" method="POST" class="inline ml-2" onsubmit="return confirm('Remove this lecturer? They will be unassigned from courses.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Delete</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                        No lecturers yet.
                        @if(session()->has('admin_id'))
                        <a href="{{ route('dashboard.lecturers.create') }}" class="text-primary hover:underline font-medium">Add one</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-100">
        {{ $lecturers->links() }}
    </div>
</div>
@endsection
