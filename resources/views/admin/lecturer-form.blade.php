@extends('layouts.admin')

@section('title', $lecturer ? 'Edit lecturer' : 'Add lecturer')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.lecturers.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Lecturers
    </a>
    <h1 class="text-2xl font-bold">{{ $lecturer ? 'Edit lecturer' : 'Add lecturer' }}</h1>
    <p class="text-gray-500 text-sm mt-1">Directory only — name and optional class. No login is created. Assign lecturers to courses (and venues) on each course.</p>
</div>

<form method="POST" action="{{ $lecturer ? route('dashboard.lecturers.update', $lecturer) : route('dashboard.lecturers.store') }}" class="bg-white rounded-xl border border-gray-100 overflow-hidden max-w-2xl">
    @csrf
    @if($lecturer) @method('PUT') @endif

    <div class="p-6 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $lecturer?->name) }}" required placeholder="e.g. Dr. Emmanuel Yeboah"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
            @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="class_ids" class="block text-sm font-medium text-gray-700 mb-2">Assigned classes</label>
            @php $assigned = old('class_ids', $lecturer ? $lecturer->schoolClasses->pluck('id')->all() : []); @endphp
            <select id="class_ids" name="class_ids[]" multiple size="8" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                @foreach($classes ?? [] as $c)
                <option value="{{ $c->id }}" {{ collect($assigned)->contains($c->id) ? 'selected' : '' }}>
                    {{ $c->name }} · L{{ $c->level ?? '—' }}@if($c->department) · {{ $c->department->name }}@endif
                </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple. Lecturers only see students in these classes.</p>
            @error('class_ids')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">{{ $lecturer ? 'Save' : 'Add lecturer' }}</button>
        <a href="{{ route('dashboard.lecturers.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-xl font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection
