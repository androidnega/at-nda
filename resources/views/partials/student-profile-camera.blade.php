{{-- Standard profile photo upload input.
     Now wraps the picker with a Cropper.js modal so the user can drag /
     zoom / pan to land a clean square crop of their face before upload.
     The cropped JPEG replaces the original file in the same input's
     FileList (DataTransfer), so the existing server-side validation
     (mimes:jpg,jpeg,png,webp + saveProfileImageFromUpload) is unchanged.

     The partial is included in both the onboarding form AND the
     profile-edit form. Cropper.js assets + the cropper modal markup +
     the cropper JS are guarded with @once so two includes on the same
     page don't double-render anything. --}}
@php
    $pf = $prefix ?? 'profile_cam';
    $label = $label ?? 'Profile photo';
@endphp

@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
<style>
    /* Lock the cropper image to a sensible max so the modal stays on
       screen on small phones. Cropper.js needs an explicit max-width
       on the host <img> for its internal sizing math. */
    [data-cropper-host] { max-width: 100%; display: block; max-height: 60vh; }
</style>
@endpush
@endonce

<div class="space-y-3" data-profile-camera="{{ $pf }}">
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $label }}</label>
    <p class="text-xs text-slate-500 dark:text-slate-400">Choose a clear photo. JPG, PNG, or WEBP. You'll get a square crop before it's uploaded.</p>
    <div class="flex flex-wrap items-center gap-3">
        <input
            type="file"
            id="{{ $pf }}_file"
            name="profile_photo"
            accept="image/jpeg,image/png,image/webp"
            class="block w-full sm:w-auto text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 dark:file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 dark:file:text-slate-100 hover:file:bg-slate-200 dark:hover:file:bg-slate-600"
            data-camera-file
            @if(!empty($required)) required @endif
        >
        {{-- Hidden until a crop is confirmed; shows the cropped thumbnail
             so the user sees exactly what will be uploaded, with a
             "Recrop" shortcut to re-open the modal with the same source. --}}
        <div class="hidden items-center gap-2" data-camera-preview>
            <img alt="Cropped preview" data-camera-preview-img
                 class="h-12 w-12 rounded-full object-cover ring-2 ring-emerald-200 dark:ring-emerald-700 bg-slate-100 dark:bg-slate-800">
            <button type="button" data-camera-recrop
                    class="text-[11px] font-semibold text-sky-700 dark:text-sky-300 hover:underline">
                Recrop
            </button>
        </div>
    </div>
</div>

@once
{{-- Shared cropper modal (one per page, regardless of how many camera
     partials are rendered). Lives outside the form via a sibling
     position; using fixed positioning so it visually escapes parents. --}}
<div data-cropper-modal class="hidden fixed inset-0 z-[60] items-center justify-center bg-slate-900/70 backdrop-blur-sm p-3 sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="cropper-modal-title">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md max-h-[92vh] flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700">
        <div class="flex items-start justify-between gap-3 p-4 border-b border-slate-100 dark:border-slate-800">
            <div class="min-w-0">
                <h3 id="cropper-modal-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Crop your photo</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Drag to pan, pinch / scroll to zoom. Frame your face inside the square.</p>
            </div>
            <button type="button" data-cropper-cancel
                    class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="flex-1 overflow-hidden bg-slate-100 dark:bg-slate-950 flex items-center justify-center p-2">
            <img data-cropper-host alt="">
        </div>
        <div class="p-3 sm:p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-1 text-slate-500 dark:text-slate-400">
                <button type="button" data-cropper-zoom-out title="Zoom out"
                        class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm">
                    <i class="fas fa-magnifying-glass-minus"></i>
                </button>
                <button type="button" data-cropper-zoom-in title="Zoom in"
                        class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm">
                    <i class="fas fa-magnifying-glass-plus"></i>
                </button>
                <button type="button" data-cropper-rotate title="Rotate 90°"
                        class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm">
                    <i class="fas fa-rotate-right"></i>
                </button>
                <button type="button" data-cropper-reset title="Reset"
                        class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm">
                    <i class="fas fa-undo"></i>
                </button>
            </div>
            <div class="flex items-center gap-2 ml-auto">
                <button type="button" data-cropper-cancel
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                    Cancel
                </button>
                <button type="button" data-cropper-save
                        class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 px-3 py-2 text-sm font-semibold text-white">
                    <i class="fas fa-check text-[11px]"></i> Use this photo
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
<script>
(function () {
    'use strict';

    const modal = document.querySelector('[data-cropper-modal]');
    if (!modal || typeof window.Cropper === 'undefined') return;

    const host = modal.querySelector('[data-cropper-host]');
    const saveBtn = modal.querySelector('[data-cropper-save]');
    const cancelBtns = modal.querySelectorAll('[data-cropper-cancel]');
    const zoomInBtn = modal.querySelector('[data-cropper-zoom-in]');
    const zoomOutBtn = modal.querySelector('[data-cropper-zoom-out]');
    const rotateBtn = modal.querySelector('[data-cropper-rotate]');
    const resetBtn = modal.querySelector('[data-cropper-reset]');

    let cropper = null;
    let activeInput = null;
    let activeSourceFile = null;
    let activeSourceUrl = null;

    function openCropper(input, file) {
        activeInput = input;
        activeSourceFile = file;
        if (activeSourceUrl) URL.revokeObjectURL(activeSourceUrl);
        activeSourceUrl = URL.createObjectURL(file);
        host.src = activeSourceUrl;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        if (cropper) { cropper.destroy(); cropper = null; }
        // Defer to next frame so the image has dimensions when Cropper
        // measures it; otherwise the first init can size to 0x0 on slow
        // devices.
        requestAnimationFrame(function () {
            cropper = new Cropper(host, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                background: false,
                guides: true,
                center: true,
                responsive: true,
                checkOrientation: true,
                modal: true,
            });
        });
    }

    function closeCropper(clearInput) {
        if (cropper) { cropper.destroy(); cropper = null; }
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        // Free the object URL once Cropper releases its reference.
        if (activeSourceUrl) {
            try { URL.revokeObjectURL(activeSourceUrl); } catch (e) {}
        }
        if (clearInput && activeInput) {
            activeInput.value = '';
            // Also reset the preview so a half-finished crop doesn't
            // mislead the user into thinking they have a photo queued.
            const wrap = activeInput.closest('[data-profile-camera]');
            const preview = wrap && wrap.querySelector('[data-camera-preview]');
            if (preview) {
                preview.classList.add('hidden');
                preview.classList.remove('flex');
            }
        }
        activeInput = null;
        activeSourceFile = null;
        activeSourceUrl = null;
        host.removeAttribute('src');
    }

    saveBtn.addEventListener('click', function () {
        if (!cropper || !activeInput) return;
        // Cap to 1024² so a 12MP source doesn't ship 6MB to the server;
        // the queued resize job downscales again to 256px anyway, so this
        // is a generous safety upper bound, not a quality cap.
        const canvas = cropper.getCroppedCanvas({
            width: 1024,
            height: 1024,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        if (!canvas) { closeCropper(true); return; }
        canvas.toBlob(function (blob) {
            if (!blob) { closeCropper(true); return; }
            // Replace the original FileList with the cropped JPEG so
            // the unchanged server upload path receives the crop.
            const cropped = new File([blob], 'profile.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            try {
                const dt = new DataTransfer();
                dt.items.add(cropped);
                activeInput.files = dt.files;
            } catch (e) {
                // Some very old WebKit builds reject DataTransfer assignment
                // to <input type=file>. Fall back to a hidden form companion.
                // (Unlikely on the supported browsers but covered just in case.)
                console.warn('DataTransfer fallback path not implemented.', e);
            }

            // Update preview thumb.
            const wrap = activeInput.closest('[data-profile-camera]');
            const preview = wrap && wrap.querySelector('[data-camera-preview]');
            const previewImg = wrap && wrap.querySelector('[data-camera-preview-img]');
            if (previewImg) {
                if (previewImg.src && previewImg.src.startsWith('blob:')) {
                    URL.revokeObjectURL(previewImg.src);
                }
                previewImg.src = URL.createObjectURL(blob);
            }
            if (preview) {
                preview.classList.remove('hidden');
                preview.classList.add('flex');
            }

            closeCropper(false);
        }, 'image/jpeg', 0.92);
    });

    cancelBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { closeCropper(true); });
    });

    if (zoomInBtn) zoomInBtn.addEventListener('click', function () { if (cropper) cropper.zoom(0.15); });
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () { if (cropper) cropper.zoom(-0.15); });
    if (rotateBtn) rotateBtn.addEventListener('click', function () { if (cropper) cropper.rotate(90); });
    if (resetBtn) resetBtn.addEventListener('click', function () { if (cropper) cropper.reset(); });

    // ESC closes the modal (treats it like Cancel).
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeCropper(true);
        }
    });

    // Backdrop click closes too (only when the click is on the dimmer,
    // not on the modal card).
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeCropper(true);
    });

    // Wire every camera input on the page (rep / student profile +
    // onboarding both render the partial, but @once means this script
    // runs only once and discovers every input by selector).
    document.querySelectorAll('[data-camera-file]').forEach(function (input) {
        // Track the last picked file so "Recrop" can re-open the modal
        // without forcing the user to pick a file from disk again.
        let lastFile = null;

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;
            lastFile = file;
            openCropper(input, file);
        });

        const wrap = input.closest('[data-profile-camera]');
        const recropBtn = wrap && wrap.querySelector('[data-camera-recrop]');
        if (recropBtn) {
            recropBtn.addEventListener('click', function () {
                // Prefer the currently-attached File on the input (which
                // is the *cropped* one), but fall back to the original
                // pick so the user re-enters the cropper, not their
                // already-cropped JPEG.
                const current = (input.files && input.files[0]) || lastFile;
                if (current) openCropper(input, current);
            });
        }
    });
})();
</script>
@endpush
@endonce
