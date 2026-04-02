@extends('layouts.admin')

@section('title', 'Schools')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Schools</h1>
        <p class="text-gray-500 text-sm mt-1">Manage universities and assign faculties</p>
    </div>
    <a href="{{ route('dashboard.universities.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">
        <i class="fas fa-plus"></i>
        Add School
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
        <table class="w-full min-w-[420px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">School</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Location</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Faculties</th>
                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($universities as $u)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $u->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $u->location ?: '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $u->faculties_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('dashboard.universities.edit', $u) }}" class="text-primary hover:underline text-sm font-medium">Edit</a>
                        <form action="{{ route('dashboard.universities.destroy', $u) }}" method="POST" class="inline ml-2" onsubmit="return confirm('Delete this school?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-12 text-center text-gray-500">No schools yet. <a href="{{ route('dashboard.universities.create') }}" class="text-primary hover:underline">Create one</a></td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-100">
        {{ $universities->links() }}
    </div>
</div>
@endsection
