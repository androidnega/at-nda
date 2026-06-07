{{-- Standard profile photo upload input. --}}
@php
    $pf = $prefix ?? 'profile_cam';
    $label = $label ?? 'Profile photo';
@endphp
<div class="space-y-3" data-profile-camera="{{ $pf }}">
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $label }}</label>
    <p class="text-xs text-slate-500 dark:text-slate-400">Choose a clear photo. JPG, PNG, or WEBP. The server optimizes it automatically (max 500KB).</p>
    <div>
        <input
            type="file"
            id="{{ $pf }}_file"
            name="profile_photo"
            accept="image/jpeg,image/png,image/webp"
            class="block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 dark:file:text-slate-100 hover:file:bg-slate-200 dark:hover:file:bg-slate-600"
            @if(!empty($required)) required @endif
        >
    </div>
</div>
