@extends('layouts.admin')

@section('title', $venue ? 'Edit Venue' : 'Create Venue')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.venues.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Venues
    </a>
    <h1 class="text-2xl font-bold">{{ $venue ? 'Edit' : 'Create' }} Venue</h1>
</div>

<form method="POST" action="{{ $venue ? route('dashboard.venues.update', $venue) : route('dashboard.venues.store') }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    @csrf
    @if($venue) @method('PUT') @endif

    <div class="p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $venue?->name) }}" required placeholder="e.g. Room 101"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Code</label>
            <input type="text" id="code" name="code" value="{{ old('code', $venue?->code) }}" placeholder="e.g. R101"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('code')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="building" class="block text-sm font-medium text-gray-700 mb-2">Building</label>
            <input type="text" id="building" name="building" value="{{ old('building', $venue?->building) }}" placeholder="e.g. CS Block"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('building')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="capacity" class="block text-sm font-medium text-gray-700 mb-2">Capacity</label>
            <input type="number" id="capacity" name="capacity" value="{{ old('capacity', $venue?->capacity) }}" min="1" placeholder="e.g. 50"
                class="w-full max-w-xs border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('capacity')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">Save</button>
        <a href="{{ route('dashboard.venues.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection
