@extends('layouts.admin')

@section('title', 'Semesters')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Semesters</h1>
        <p class="text-gray-500 text-sm mt-1">Academic year and term (e.g. 2025/2026 · Semester 1). Classes pick one; you can add new rows each year.</p>
    </div>
    <a href="{{ route('dashboard.semesters.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90">
        <i class="fas fa-plus"></i> Add semester
    </a>
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-lg border border-green-100">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-50 text-red-800 rounded-lg border border-red-100">{{ session('error') }}</div>
@endif

@if($semesters->isEmpty())
    <div class="bg-white border border-gray-200 rounded-lg p-10 text-center text-gray-600">
        <p class="font-medium">No semesters yet</p>
        <p class="text-sm mt-1">Create at least one before assigning classes.</p>
        <a href="{{ route('dashboard.semesters.create') }}" class="inline-flex mt-4 text-primary font-medium">Add semester</a>
    </div>
@else
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Label</th>
                    <th class="px-4 py-3">Year</th>
                    <th class="px-4 py-3">Term</th>
                    <th class="px-4 py-3 text-right">Classes</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($semesters as $s)
                <tr class="hover:bg-gray-50/80">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $s->display_label }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $s->year_label }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $s->term }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ $s->school_classes_count }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('dashboard.semesters.edit', $s) }}" class="text-primary font-medium mr-3">Edit</a>
                        <form action="{{ route('dashboard.semesters.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('Delete this semester?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 font-medium">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
