@extends('layouts.admin')

@section('title', $semester ? 'Edit semester' : 'Add semester')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ $semester ? 'Edit' : 'Add' }} semester</h1>
    <p class="text-gray-500 text-sm mt-1">Use a clear year label (e.g. <span class="font-mono">2025/2026</span>) and term 1 or 2.</p>
</div>

@if (session('error'))
    <div class="mb-4 p-4 bg-red-50 text-red-800 rounded-lg border border-red-100">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ $semester ? route('dashboard.semesters.update', $semester) : route('dashboard.semesters.store') }}" class="bg-white border border-gray-200 rounded-lg overflow-hidden max-w-lg">
    @csrf
    @if($semester) @method('PUT') @endif

    <div class="p-6 space-y-4">
        <div>
            <label for="year_label" class="block text-sm font-medium text-gray-700 mb-2">Academic year</label>
            <input type="text" id="year_label" name="year_label" required maxlength="32" placeholder="2025/2026"
                value="{{ old('year_label', $semester?->year_label) }}"
                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/30 focus:border-primary font-mono text-sm">
            @error('year_label')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="term" class="block text-sm font-medium text-gray-700 mb-2">Term</label>
            <select name="term" id="term" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/30">
                <option value="1" {{ old('term', $semester?->term) == 1 ? 'selected' : '' }}>1</option>
                <option value="2" {{ old('term', $semester?->term) == 2 ? 'selected' : '' }}>2</option>
            </select>
        </div>
        <div>
            <label for="label" class="block text-sm font-medium text-gray-700 mb-2">Custom label <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" id="label" name="label" maxlength="128" placeholder="Defaults to year · Semester N"
                value="{{ old('label', $semester?->label) }}"
                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/30">
        </div>
    </div>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex gap-3">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90">Save</button>
        <a href="{{ route('dashboard.semesters.index') }}" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-lg font-medium hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection
