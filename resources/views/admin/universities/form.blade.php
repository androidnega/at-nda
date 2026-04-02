@extends('layouts.admin')

@section('title', $university ? 'Edit School' : 'Create School')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.universities.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Schools
    </a>
    <h1 class="text-2xl font-bold">{{ $university ? 'Edit' : 'Create' }} School</h1>
</div>

<form method="POST" action="{{ $university ? route('dashboard.universities.update', $university) : route('dashboard.universities.store') }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    @csrf
    @if($university) @method('PUT') @endif

    <div class="p-6 space-y-6">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">School name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $university?->name) }}" required placeholder="e.g. Takoradi Technical University"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="location" class="block text-sm font-medium text-gray-700 mb-2">Location (optional)</label>
            <input type="text" id="location" name="location" value="{{ old('location', $university?->location) }}" placeholder="e.g. Takoradi, Ghana"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('location')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Faculties in this school</label>
            <p class="text-xs text-gray-500 mb-3">Select the faculties that belong to this school.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @forelse($faculties as $faculty)
                @php $isChecked = in_array($faculty->id, old('faculty_ids', $assignedFacultyIds ?? []), true); @endphp
                <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 hover:bg-gray-50">
                    <input type="checkbox" name="faculty_ids[]" value="{{ $faculty->id }}" {{ $isChecked ? 'checked' : '' }}
                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-sm text-gray-700">{{ $faculty->name }}</span>
                </label>
                @empty
                <p class="text-sm text-gray-500">No faculties yet.</p>
                @endforelse
            </div>
            @error('faculty_ids')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">Save</button>
        <a href="{{ route('dashboard.universities.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection
