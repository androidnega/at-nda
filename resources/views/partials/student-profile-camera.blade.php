{{-- Profile photo control. Clicking the button opens the shared
     cropper modal (see partials/student-photo-cropper.blade.php),
     which crops to a square 1:1 and posts the result directly to
     the image upload endpoint — no multipart submission tangled
     into the surrounding form. --}}
@php
    $pf = $prefix ?? 'profile_cam';
    $label = $label ?? 'Profile photo';
    $student = $student ?? (isset($s) ? $s : null);
    $hasPhoto = $student && method_exists($student, 'hasSettledProfileImage')
        ? $student->hasSettledProfileImage()
        : (bool) ($student?->profile_image ?? false);
    $previewUrl = $hasPhoto && $student ? $student->profileImageUrl() : null;
    // Hosts that want a denser layout (e.g. the trimmed student
    // profile page) can pass showHelper=false to drop the verbose
    // "JPG / PNG / WEBP" paragraph.
    $showHelper = $showHelper ?? true;
@endphp
<div class="space-y-3" data-profile-camera="{{ $pf }}">
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $label }}</label>
    <div class="flex items-center gap-3">
        <div class="relative shrink-0 w-16 h-16 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 overflow-hidden">
            @if($previewUrl)
                <img data-avatar-img src="{{ $previewUrl }}" alt=""
                     onerror="this.style.display='none'; var f=this.parentNode.querySelector('[data-avatar-fallback]'); if (f) f.style.display='flex';"
                     class="absolute inset-0 w-full h-full object-cover">
                <span data-avatar-fallback class="absolute inset-0 w-full h-full items-center justify-center text-slate-400 text-xl font-bold hidden">
                    <i class="fas fa-user"></i>
                </span>
            @else
                <span data-avatar-fallback class="absolute inset-0 w-full h-full flex items-center justify-center text-slate-400 text-xl font-bold">
                    <i class="fas fa-user"></i>
                </span>
                <img data-avatar-img src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden">
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <button type="button" data-cropper-trigger
                    class="inline-flex items-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-sm font-semibold">
                <i class="fas fa-camera text-[12px]"></i>
                <span>{{ $hasPhoto ? 'Change photo' : 'Choose photo' }}</span>
            </button>
            @if($showHelper)
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                    You'll see a square preview so you can frame your face before uploading. JPG, PNG, or WEBP.
                </p>
            @endif
        </div>
    </div>
</div>
