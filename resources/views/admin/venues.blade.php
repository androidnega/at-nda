@extends('layouts.admin')

@section('title', 'Venues')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Venues</h1>
        <p class="text-gray-500 text-sm mt-1">Manage rooms and assign to courses</p>
    </div>
    <a href="{{ route('dashboard.venues.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">
        <i class="fas fa-plus"></i>
        Add Venue
    </a>
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
        <table class="w-full min-w-[400px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Code</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Building</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Capacity</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Courses</th>
                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($venues as $v)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium">{{ $v->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $v->code ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $v->building ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $v->capacity ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $v->courses_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('dashboard.venues.edit', $v) }}" class="text-primary hover:underline text-sm font-medium">Edit</a>
                        <form action="{{ route('dashboard.venues.destroy', $v) }}" method="POST" class="inline ml-2" onsubmit="return confirm('Delete this venue?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">No venues. <a href="{{ route('dashboard.venues.create') }}" class="text-primary hover:underline">Create one</a></td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-100">
        {{ $venues->links() }}
    </div>
</div>
@endsection
