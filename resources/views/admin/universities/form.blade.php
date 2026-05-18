@extends('layouts.admin')

@section('title', $university ? 'Edit School' : 'Create School')

@section('content')
@php
    $existingLogoUrl = $university?->logoUrl();
@endphp
<div class="mb-6">
    <a href="{{ route('dashboard.universities.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Schools
    </a>
    <h1 class="text-2xl font-bold">{{ $university ? 'Edit' : 'Create' }} School</h1>
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-50 text-red-800 rounded-xl border border-red-100">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ $university ? route('dashboard.universities.update', $university) : route('dashboard.universities.store') }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-100 overflow-hidden">
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
            <p class="block text-sm font-medium text-gray-700 mb-2">School logo (PDF &amp; timetable)</p>
            <label for="school_logo" class="flex flex-col sm:flex-row sm:items-center gap-3 w-full border-2 border-dashed border-gray-200 rounded-xl px-4 py-4 cursor-pointer hover:border-primary/40 hover:bg-primary/5">
                <span class="inline-flex items-center justify-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium shrink-0">
                    <i class="fas fa-upload"></i> Choose image
                </span>
                <span id="school-logo-filename" class="text-sm text-gray-500 truncate">PNG, JPEG, or WebP · max 4 MB</span>
                <input type="file" id="school_logo" name="school_logo" accept="image/png,image/jpeg,image/jpg,image/webp,image/*" class="sr-only">
            </label>
            @error('school_logo')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror

            <div class="mt-4 flex flex-wrap items-start gap-4">
                <div class="relative h-24 w-24 shrink-0 rounded-xl border border-gray-200 bg-gray-50 overflow-hidden">
                    <img
                        id="school-logo-preview-img"
                        src="{{ $existingLogoUrl ?? '' }}"
                        alt="School logo preview"
                        class="absolute inset-0 h-full w-full object-contain bg-white p-1 {{ $existingLogoUrl ? 'block' : 'hidden' }}"
                    >
                    <div id="school-logo-preview-empty" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 {{ $existingLogoUrl ? 'hidden' : 'flex' }}">
                        <i class="fas fa-image text-2xl mb-1"></i>
                        <span class="text-[10px] uppercase tracking-wide">Preview</span>
                    </div>
                </div>
                <div class="min-w-0 pt-1">
                    <p class="text-sm font-medium text-gray-700">Preview</p>
                    <p class="text-xs text-gray-500 mt-0.5">Shows immediately when you pick a file. Click <strong>Save</strong> to store it on the server.</p>
                    @if($university?->hasStoredLogo())
                        <label class="mt-3 inline-flex items-center gap-2 text-xs text-red-700 cursor-pointer">
                            <input type="checkbox" id="remove_school_logo" name="remove_school_logo" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            Remove saved logo
                        </label>
                    @endif
                </div>
            </div>
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

<script>
(function () {
    var fileInput = document.getElementById('school_logo');
    var fileName = document.getElementById('school-logo-filename');
    var img = document.getElementById('school-logo-preview-img');
    var empty = document.getElementById('school-logo-preview-empty');
    var remove = document.getElementById('remove_school_logo');
    var savedUrl = @json($existingLogoUrl);

    function showImage(src) {
        if (!img || !empty) return;
        img.src = src;
        img.classList.remove('hidden');
        img.classList.add('block');
        img.style.display = 'block';
        empty.classList.add('hidden');
        empty.classList.remove('flex');
    }

    function showEmpty() {
        if (!img || !empty) return;
        img.removeAttribute('src');
        img.classList.add('hidden');
        img.classList.remove('block');
        img.style.display = 'none';
        empty.classList.remove('hidden');
        empty.classList.add('flex');
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) return;
            if (remove) remove.checked = false;
            if (fileName) fileName.textContent = file.name;
            if (!file.type || file.type.indexOf('image/') !== 0) {
                if (fileName) fileName.textContent = 'Please choose an image file (PNG, JPEG, or WebP).';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                showImage(e.target.result);
            };
            reader.onerror = function () {
                if (fileName) fileName.textContent = 'Could not read that file. Try another image.';
            };
            reader.readAsDataURL(file);
        });
    }

    if (remove) {
        remove.addEventListener('change', function () {
            if (remove.checked) {
                showEmpty();
                if (fileInput) fileInput.value = '';
                if (fileName) fileName.textContent = 'Logo will be removed when you save.';
            } else if (savedUrl) {
                showImage(savedUrl);
                if (fileName) fileName.textContent = 'Saved logo restored in preview.';
            }
        });
    }
})();
</script>
@endsection
