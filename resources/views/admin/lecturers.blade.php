@extends('layouts.admin')

@section('title', 'Lecturers')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Lecturers</h1>
        <p class="text-gray-500 text-sm mt-1">Teaching staff directory (name + optional class). <strong>Administrator logins</strong> are under <a href="{{ route('dashboard.staff-accounts.index') }}" class="text-primary hover:underline">User management</a>. Assign each course’s lecturer and venue on the <a href="{{ route('dashboard.courses.index') }}" class="text-primary hover:underline">Courses</a> page.</p>
    </div>
    @if(session()->has('admin_id'))
    <a href="{{ route('dashboard.lecturers.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">
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
        <table class="w-full min-w-[480px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Home class</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Courses</th>
                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($lecturers as $l)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium">{{ $l->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $l->schoolClass?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $l->courses()->count() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('dashboard.lecturers.edit', $l) }}" class="text-primary hover:underline text-sm font-medium">Edit</a>
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
                    <td colspan="4" class="px-4 py-12 text-center text-gray-500">
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
