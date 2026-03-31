{{-- Live camera capture for profile photo. Sets the file input named profile_photo from a canvas JPEG. --}}
@php
    $pf = $prefix ?? 'profile_cam';
    $label = $label ?? 'Profile photo';
@endphp
<div class="space-y-3" data-profile-camera="{{ $pf }}">
    <label class="block text-sm font-medium text-slate-700 mb-1">{{ $label }}</label>
    <p class="text-xs text-slate-500 mb-2">Use your device camera — position your face in the frame, then capture. This photo is used to verify you when marking attendance on the web.</p>
    <div class="rounded-xl overflow-hidden bg-black border border-slate-200 aspect-[4/3] max-h-72 relative">
        <video id="{{ $pf }}_video" playsinline muted class="w-full h-full object-cover hidden"></video>
        <div id="{{ $pf }}_placeholder" class="absolute inset-0 flex items-center justify-center text-slate-400 text-sm p-4 text-center">Tap &quot;Open camera&quot; to start</div>
        <img id="{{ $pf }}_preview" src="" alt="" class="hidden w-full h-full object-cover">
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" id="{{ $pf }}_open" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-medium hover:bg-slate-900">
            Open camera
        </button>
        <button type="button" id="{{ $pf }}_capture" disabled class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 disabled:opacity-45 disabled:pointer-events-none">
            Capture photo
        </button>
        <button type="button" id="{{ $pf }}_retake" class="hidden inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50">
            Retake
        </button>
    </div>
    <p id="{{ $pf }}_err" class="text-red-600 text-sm hidden"></p>
    <input type="file" id="{{ $pf }}_file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @if(!empty($required)) data-camera-required="1" @endif>
</div>
<script>
(function() {
    var p = @json($pf);
    var video = document.getElementById(p + '_video');
    var placeholder = document.getElementById(p + '_placeholder');
    var preview = document.getElementById(p + '_preview');
    var openBtn = document.getElementById(p + '_open');
    var capBtn = document.getElementById(p + '_capture');
    var retakeBtn = document.getElementById(p + '_retake');
    var fileInput = document.getElementById(p + '_file');
    var errEl = document.getElementById(p + '_err');
    var stream = null;

    function showErr(msg) {
        if (!errEl) return;
        if (msg) {
            errEl.textContent = msg;
            errEl.classList.remove('hidden');
        } else {
            errEl.textContent = '';
            errEl.classList.add('hidden');
        }
    }

    function stopCam() {
        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
        if (video) {
            video.srcObject = null;
            video.classList.add('hidden');
        }
        if (placeholder) placeholder.classList.remove('hidden');
    }

    openBtn && openBtn.addEventListener('click', function() {
        showErr('');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showErr('Camera is not available in this browser.');
            return;
        }
        if (!window.isSecureContext) {
            showErr('Camera needs HTTPS, or open the site via localhost / 127.0.0.1 (not a plain http LAN IP).');
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }).then(function(s) {
            stream = s;
            if (video) {
                video.srcObject = stream;
                video.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
                return video.play();
            }
        }).then(function() {
            capBtn && (capBtn.disabled = false);
        }).catch(function(e) {
            showErr(e && e.message ? String(e.message) : 'Could not access the camera.');
        });
    });

    capBtn && capBtn.addEventListener('click', function() {
        if (!video || video.readyState < 2) return;
        var w = video.videoWidth;
        var h = video.videoHeight;
        if (!w || !h) return;
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        canvas.toBlob(function(blob) {
            if (!blob || !fileInput) return;
            try {
                var dt = new DataTransfer();
                dt.items.add(new File([blob], 'profile.jpg', { type: 'image/jpeg' }));
                fileInput.files = dt.files;
            } catch (e) {
                showErr('Could not attach photo. Try another browser.');
                return;
            }
            stopCam();
            capBtn.disabled = true;
            if (preview) {
                preview.src = URL.createObjectURL(blob);
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            }
            retakeBtn && retakeBtn.classList.remove('hidden');
            openBtn && (openBtn.textContent = 'Open camera again');
        }, 'image/jpeg', 0.92);
    });

    retakeBtn && retakeBtn.addEventListener('click', function() {
        if (preview) {
            if (preview.src && preview.src.indexOf('blob:') === 0) URL.revokeObjectURL(preview.src);
            preview.src = '';
            preview.classList.add('hidden');
        }
        if (fileInput) fileInput.value = '';
        retakeBtn.classList.add('hidden');
        stopCam();
        if (placeholder) placeholder.classList.remove('hidden');
        openBtn && (openBtn.textContent = 'Open camera');
    });

    var form = fileInput && fileInput.closest('form');
    if (form && fileInput.getAttribute('data-camera-required')) {
        form.addEventListener('submit', function(ev) {
            if (!fileInput.files || !fileInput.files.length) {
                ev.preventDefault();
                showErr('Please capture a profile photo with the camera before continuing.');
            }
        });
    }
})();
</script>
