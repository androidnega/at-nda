@extends('layouts.admin')

@section('title', $university ? 'Edit School' : 'Create School')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.universities.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Schools
    </a>
    <h1 class="text-2xl font-bold">{{ $university ? 'Edit' : 'Create' }} School</h1>
</div>

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
            <label for="school_logo" class="block text-sm font-medium text-gray-700 mb-2">School logo (PDF &amp; timetable)</label>
            <input type="file" id="school_logo" name="school_logo" accept="image/png,image/jpeg,image/webp"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-medium">
            <p class="text-xs text-gray-500 mt-1">Shown on attendance PDFs for every class under this school. PNG, JPEG, or WebP up to 4&nbsp;MB.</p>
            @error('school_logo')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror

            <div id="school-logo-preview-wrap" class="mt-4 flex flex-wrap items-start gap-4 {{ ($university && $university->hasStoredLogo()) ? '' : 'hidden' }}">
                <x-school-logo-thumb
                    id="school-logo-preview-thumb"
                    :url="$university?->logoUrl()"
                    :name="old('name', $university?->name ?? '')"
                    size="lg"
                />
                <div class="min-w-0 pt-1">
                    <p class="text-sm font-medium text-gray-700">Preview</p>
                    <p class="text-xs text-gray-500 mt-0.5">Updates when you choose a new file. Save to apply.</p>
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
    var wrap = document.getElementById('school-logo-preview-wrap');
    var thumb = document.getElementById('school-logo-preview-thumb');
    var img = thumb ? thumb.querySelector('.school-logo-thumb-img') : null;
    var remove = document.getElementById('remove_school_logo');
    var hadSavedLogo = @json((bool) ($university && $university->hasStoredLogo()));

    function showWrap() {
        if (wrap) wrap.classList.remove('hidden');
    }
    function hideWrap() {
        if (wrap) wrap.classList.add('hidden');
    }
    function setPreviewSrc(src) {
        if (!img) return;
        img.src = src;
        img.classList.remove('hidden');
        var fallback = thumb ? thumb.querySelector('.school-logo-thumb-fallback') : null;
        if (fallback) {
            fallback.classList.add('hidden');
            fallback.classList.remove('flex');
        }
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) return;
            if (remove) remove.checked = false;
            var reader = new FileReader();
            reader.onload = function (e) {
                setPreviewSrc(e.target.result);
                showWrap();
            };
            reader.readAsDataURL(file);
        });
    }

    if (remove) {
        remove.addEventListener('change', function () {
            if (remove.checked) {
                if (img) {
                    img.removeAttribute('src');
                    img.style.display = 'none';
                }
                if (!fileInput || !fileInput.files.length) {
                    hideWrap();
                }
            } else if (hadSavedLogo && img) {
                setPreviewSrc(@json($university?->logoUrl()));
                showWrap();
            }
        });
    }

    document.querySelectorAll('.school-logo-thumb-img').forEach(function (el) {
        el.addEventListener('error', function () {
            el.style.display = 'none';
            var fb = el.parentElement && el.parentElement.querySelector('.school-logo-thumb-fallback');
            if (fb) {
                fb.classList.remove('hidden');
                fb.classList.add('flex');
            }
        });
    });
})();
</script>
@endsection
