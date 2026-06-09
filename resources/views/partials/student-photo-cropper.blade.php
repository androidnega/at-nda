{{--
    Shared "crop + upload my profile photo" partial.

    Usage:
      @include('partials.student-photo-cropper')
      @include('partials.student-photo-cropper', ['cropperReload' => true])

    A page hooks into this in two ways:
      1. Add `data-cropper-trigger` to any clickable element. Clicking
         it opens the OS file picker. Common targets: the avatar
         button in the dashboard header, a "Change photo" button on
         the profile page.
      2. (Optional) Add `data-avatar-img` to any <img> tag that
         should be swapped with the new photo URL after a successful
         upload. The matching fallback span can carry
         `data-avatar-fallback` so we hide it once a real photo
         lands.

    Options (Blade vars):
      - $cropperReload (bool, default false): if true, the page
        reloads on a successful upload (use this on the profile
        page so the hero avatar + flash message refresh in one
        round-trip; the dashboard does in-place swaps so it
        leaves this false).
--}}
@php
    $cropperReload   = $cropperReload   ?? false;
    $cropperUploadUrl = $cropperUploadUrl ?? route('student.profile.image.update');
@endphp

{{-- Cropper.js stylesheet. Loading a <link> in body is well-
     supported and triggers a paint update once the CSS arrives. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">

<div id="student-photo-cropper-modal"
     class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4"
     role="dialog" aria-modal="true" aria-labelledby="student-photo-cropper-title">
    <div class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-700">
        <div class="flex items-start justify-between gap-3 p-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">Profile photo</p>
                <h3 id="student-photo-cropper-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Crop your photo</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Drag the photo to frame your face. Pinch or scroll to zoom.</p>
            </div>
            <button type="button" data-spc-close class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="bg-slate-50 dark:bg-slate-950 p-3">
            <div class="relative w-full aspect-square overflow-hidden rounded-xl bg-black">
                {{-- The image element Cropper.js attaches to. We
                     start it hidden + zero-sized so the Cropper-
                     applied wrapper takes over cleanly. --}}
                <img id="student-photo-cropper-image" alt=""
                     style="display:block; max-width:100%;">
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" data-spc-zoom="-0.15"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Zoom out">
                    <i class="fas fa-minus text-xs"></i>
                </button>
                <button type="button" data-spc-zoom="0.15"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Zoom in">
                    <i class="fas fa-plus text-xs"></i>
                </button>
                <button type="button" data-spc-rotate="90"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Rotate 90°">
                    <i class="fas fa-rotate-right text-xs"></i>
                </button>
                <button type="button" data-spc-reset
                        class="inline-flex items-center justify-center px-3 h-9 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Reset
                </button>
            </div>
            <p id="student-photo-cropper-error" class="hidden mt-2 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-3 py-2 text-xs"></p>
        </div>
        <div class="p-4 flex items-center justify-end gap-2 bg-slate-50/60 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800">
            <button type="button" data-spc-close
                    class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Cancel
            </button>
            <button type="button" data-spc-save
                    class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-60 disabled:cursor-wait">
                <i class="fas fa-check text-[11px]"></i>
                <span data-spc-save-label>Save photo</span>
            </button>
        </div>
    </div>
</div>

{{-- The actual file input the cropper consumes. Pages don't have to
     create their own — anything with data-cropper-trigger triggers
     a click on this one.

     We deliberately leave `accept` permissive (image/*) rather than
     pinning a whitelist. iOS file pickers report HEIC photos with a
     variety of MIME types and the picker dialog hides everything
     else; the JS below validates by attempting an actual decode and
     surfaces a specific, friendly error when the browser can't
     render the file (HEIC is the usual offender). --}}
<input type="file" id="student-photo-cropper-source" accept="image/*" class="hidden">

<script>
(function () {
    const UPLOAD_URL    = @json($cropperUploadUrl);
    const CSRF          = @json(csrf_token());
    const RELOAD_AFTER  = @json((bool) $cropperReload);

    const modal     = document.getElementById('student-photo-cropper-modal');
    const imgEl     = document.getElementById('student-photo-cropper-image');
    const errBox    = document.getElementById('student-photo-cropper-error');
    const fileInput = document.getElementById('student-photo-cropper-source');
    const saveBtn   = modal && modal.querySelector('[data-spc-save]');
    const saveLabel = modal && modal.querySelector('[data-spc-save-label]');
    if (!modal || !imgEl || !fileInput || !saveBtn) return;

    let cropper       = null;
    let cropperPromise = null;
    let blobUrl       = null;
    // Guard against double-binding when the partial gets included
    // in multiple slots on the same page during refactors.
    if (modal.dataset.spcBound === '1') return;
    modal.dataset.spcBound = '1';

    function freeBlob() {
        if (blobUrl) { try { URL.revokeObjectURL(blobUrl); } catch (_) {} blobUrl = null; }
    }

    function loadCropper() {
        if (window.Cropper) return Promise.resolve();
        if (cropperPromise) return cropperPromise;
        cropperPromise = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js';
            s.async = true;
            s.onload  = function () { resolve(); };
            s.onerror = function () { reject(new Error('Could not load the image editor. Check your connection and try again.')); };
            document.head.appendChild(s);
        });
        return cropperPromise;
    }

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        if (cropper) { try { cropper.destroy(); } catch (_) {} cropper = null; }
        imgEl.removeAttribute('src');
        freeBlob();
        errBox.classList.add('hidden');
        errBox.textContent = '';
        saveBtn.disabled = false;
        if (saveLabel) saveLabel.textContent = 'Save photo';
        fileInput.value = '';
    }

    function showError(message) {
        errBox.textContent = message;
        errBox.classList.remove('hidden');
    }

    function initCropper() {
        if (cropper) { try { cropper.destroy(); } catch (_) {} cropper = null; }
        if (!window.Cropper) return;
        cropper = new window.Cropper(imgEl, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            cropBoxResizable: true,
            cropBoxMovable: true,
            background: false,
            responsive: true,
            guides: false,
            // We initialize after the image is fully loaded so
            // Cropper computes the natural size correctly.
            ready: function () {
                errBox.classList.add('hidden');
            },
        });
    }

    // Any element on the page with [data-cropper-trigger] opens
    // the OS file picker. We use a single delegated click handler
    // so partials added after page-load (e.g. a future toast) work
    // without rebinding.
    document.addEventListener('click', function (ev) {
        const trigger = ev.target.closest('[data-cropper-trigger]');
        if (!trigger) return;
        // Prevent default for buttons that might be inside a form.
        if (trigger.tagName === 'BUTTON' || trigger.tagName === 'A') {
            ev.preventDefault();
        }
        fileInput.click();
    });

    function fileLooksLikeHeic(file) {
        const name = (file.name || '').toLowerCase();
        const type = (file.type || '').toLowerCase();
        return /\.(heic|heif|heics|heifs)$/i.test(name)
            || type.indexOf('heic') !== -1
            || type.indexOf('heif') !== -1;
    }

    /**
     * Try to decode the file in a throwaway <img> tag BEFORE we
     * commit to opening the modal. This catches files the browser
     * can't render (iPhone HEIC is the usual culprit; CMYK JPEGs
     * are another). On success we resolve with the blob URL; on
     * failure we reject so the caller can show a specific message.
     */
    function decodeImage(file) {
        return new Promise(function (resolve, reject) {
            let url;
            try { url = URL.createObjectURL(file); }
            catch (e) { reject(new Error('createObjectURL failed')); return; }

            const probe = new Image();
            let done = false;
            const finish = function (ok, why) {
                if (done) return;
                done = true;
                probe.onload = probe.onerror = null;
                if (ok) {
                    resolve({ blobUrl: url, naturalWidth: probe.naturalWidth, naturalHeight: probe.naturalHeight });
                } else {
                    try { URL.revokeObjectURL(url); } catch (_) {}
                    reject(new Error(why || 'decode failed'));
                }
            };
            probe.onload = function () {
                // A 0x0 image means decode failed silently (some
                // mobile browsers do this with HEIC instead of
                // firing onerror).
                if (!probe.naturalWidth || !probe.naturalHeight) {
                    finish(false, 'zero dimensions');
                    return;
                }
                finish(true);
            };
            probe.onerror = function () { finish(false, 'image onerror'); };
            // Some browsers need the image off-DOM but still
            // hydrated, so we leave it free-floating and rely on
            // onload/onerror exclusively.
            probe.src = url;
        });
    }

    fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        if (file.size > 10 * 1024 * 1024) {
            alert('Image is too large. Use one under 10 MB.');
            fileInput.value = '';
            return;
        }

        // Reset any previous state before we try.
        freeBlob();
        if (cropper) { try { cropper.destroy(); } catch (_) {} cropper = null; }

        // Heuristic short-circuit: if it really is HEIC, browsers
        // (other than Safari) won't decode it no matter what. Skip
        // the probe and give a precise, helpful message instead.
        if (fileLooksLikeHeic(file)) {
            alert("That's an iPhone HEIC photo, which web browsers can't open directly.\n\nOn your iPhone: open Photos → tap the picture → Share → \"Save as File\" (choose JPEG), then upload that copy.\nOr take a screenshot of the photo and upload the screenshot.");
            fileInput.value = '';
            return;
        }

        // Validate by actually decoding. This is the bit that
        // catches "browser can't render it" without flashing the
        // modal first.
        decodeImage(file).then(function (info) {
            blobUrl = info.blobUrl;
            openModal();
            imgEl.onerror = function () {
                // Belt-and-braces: if anything still goes wrong in
                // the modal's <img>, surface a useful message
                // there rather than a generic "try a different file".
                showError('That image could not be opened by your browser. If it came from an iPhone, save it as JPEG first.');
            };
            imgEl.onload = function () {
                loadCropper()
                    .then(function () { initCropper(); })
                    .catch(function (err) {
                        showError(err && err.message ? err.message : 'Could not load the image editor.');
                    });
            };
            imgEl.src = blobUrl;
        }).catch(function () {
            // Hand the user a message they can act on. The
            // pre-probe failure is almost always either an
            // unsupported decode (HEIC / CMYK / corrupted) or
            // dimensions out of the browser's allowance.
            const ext = (file.name || '').toLowerCase().match(/\.[a-z0-9]+$/);
            const extStr = ext ? ext[0] : '';
            const detail = extStr ? ' (file type: ' + extStr + ')' : '';
            alert("Your browser couldn't open that picture" + detail + ".\n\nTry one of these:\n• Save the image as JPG or PNG and upload that copy.\n• On iPhone: open Photos → Share → \"Save as File\" → JPEG.\n• Or take a screenshot of the photo and upload the screenshot.");
            fileInput.value = '';
        });
    });

    modal.querySelectorAll('[data-spc-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (ev) {
        if (ev.target === modal) closeModal();
    });

    modal.querySelectorAll('[data-spc-zoom]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!cropper) return;
            cropper.zoom(parseFloat(btn.getAttribute('data-spc-zoom') || '0'));
        });
    });
    modal.querySelectorAll('[data-spc-rotate]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!cropper) return;
            cropper.rotate(parseFloat(btn.getAttribute('data-spc-rotate') || '0'));
        });
    });
    const resetBtn = modal.querySelector('[data-spc-reset]');
    if (resetBtn) resetBtn.addEventListener('click', function () {
        if (cropper) cropper.reset();
    });

    saveBtn.addEventListener('click', function () {
        if (!cropper) return;
        errBox.classList.add('hidden');
        saveBtn.disabled = true;
        if (saveLabel) saveLabel.textContent = 'Uploading…';

        const canvas = cropper.getCroppedCanvas({
            width: 512,
            height: 512,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
            fillColor: '#ffffff',
        });
        if (!canvas) {
            showError('Could not render the crop. Try again.');
            saveBtn.disabled = false;
            if (saveLabel) saveLabel.textContent = 'Save photo';
            return;
        }
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

        fetch(UPLOAD_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ image_data: dataUrl }),
        })
        .then(function (r) {
            return r.json().then(function (body) { return { status: r.status, body: body }; });
        })
        .then(function (resp) {
            if (resp.status === 200 && resp.body && resp.body.ok) {
                if (RELOAD_AFTER) {
                    // Profile page wants a server-rendered refresh
                    // so the hero + flash messages update too.
                    window.location.reload();
                    return;
                }
                // In-place swap for any avatar img tag on the page.
                const newUrl = resp.body.url || '';
                document.querySelectorAll('[data-avatar-img]').forEach(function (el) {
                    el.src = newUrl;
                    el.classList.remove('hidden');
                    el.style.display = '';
                });
                document.querySelectorAll('[data-avatar-fallback]').forEach(function (el) {
                    el.classList.add('hidden');
                    el.style.display = 'none';
                });
                closeModal();
            } else {
                let err = 'Upload failed. Try again.';
                if (resp.body) {
                    if (resp.body.error) err = resp.body.error;
                    else if (resp.body.errors) {
                        const first = Object.values(resp.body.errors)[0];
                        if (first && first[0]) err = first[0];
                    }
                }
                showError(err);
                saveBtn.disabled = false;
                if (saveLabel) saveLabel.textContent = 'Save photo';
            }
        })
        .catch(function () {
            showError('Network error. Check your connection and try again.');
            saveBtn.disabled = false;
            if (saveLabel) saveLabel.textContent = 'Save photo';
        });
    });
})();
</script>
