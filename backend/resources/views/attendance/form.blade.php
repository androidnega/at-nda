@extends('layouts.app')

@section('title', 'Mark Attendance - ' . $course->course_name)

@push('head')
    @if($activeSession ?? null)
        <meta name="session-live-id" content="{{ $activeSession->id }}">
    @endif
@endpush

@section('content')
@php
    $sessionMode = $activeSession?->mode ?? 'location';
    $requireFaceVerification = (bool) (($settings->enable_face_verification ?? true) && $activeSession);
@endphp
<div class="max-w-lg mx-auto w-full space-y-4">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <a href="{{ url('/') }}" class="inline-flex items-center text-gray-500 hover:text-gray-700 text-sm mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back
        </a>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800">{{ $course->course_name }}</h1>
        @if($activeSession)
        <p class="text-gray-600 text-sm mt-2">
            @switch($sessionMode)
                @case('qr')
                    You will confirm your identity, then the camera opens to scan the session QR on screen.
                    @break
                @case('hybrid')
                    The session venue was set when it opened. You will scan the session QR to mark attendance.
                    @break
                @case('location')
                    The session venue was set when it opened. Tap below to mark attendance.
                    @break
                @case('wifi')
                    The expected network was set when the session opened. Tap below to check in.
                    @break
                @default
                    Follow the prompts below.
            @endswitch
        </p>
        @endif
        @if($loggedInStudent ?? null)
        <p class="mt-2 text-xs text-slate-500">Signed in as <span class="font-mono font-medium text-slate-700">{{ $loggedInStudent->index_number }}</span></p>
        @endif
        @if($activeSession && $requireFaceVerification)
        <p class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">Web check-in uses <strong>face verification</strong> against your profile photo. Use a clear face photo on your profile, and allow the camera in a <strong>secure context</strong> (HTTPS, or localhost — plain HTTP on a LAN IP may block the camera).</p>
        @endif

        @if(!$activeSession)
            <div class="mt-4 p-4 bg-amber-50 text-amber-800 rounded-xl text-sm font-medium">
                Session closed. Attendance cannot be marked.
            </div>
        @endif
    </div>

    @if($activeSession)
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <input type="hidden" id="wifi_ssid" value="">

        @unless($loggedInStudent ?? null)
        {{-- Guests: enter index number, then Continue --}}
        <div id="attendance-index-row" class="border-b border-gray-100 pb-4 mb-4">
            <label for="index_number" class="block text-sm font-medium text-gray-700 mb-2">Your index number</label>
            <input type="text" id="index_number" name="index_number" autocomplete="username" inputmode="text"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3.5 uppercase tracking-wide font-mono text-base"
                placeholder="e.g. ITN123456" style="text-transform: uppercase;" maxlength="64"
                value="">
            <p id="index-locked-hint" class="hidden mt-2 text-xs text-gray-500">Locked for this check-in. Refresh the page to change it.</p>
            <button type="button" id="btn-step-1"
                onclick="if (typeof window.attendanceContinueClick === 'function') { window.attendanceContinueClick(event); }"
                class="mt-3 w-full bg-blue-600 text-white py-3 rounded-xl font-semibold touch-manipulation active:opacity-90">Continue</button>
        </div>
        @else
        <input type="hidden" id="index_number" name="index_number" value="{{ $loggedInStudent->index_number }}">
        @endunless

        <div id="attendance-flow-status" class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-3 text-sm text-slate-800 mb-4 hidden" role="status" aria-live="polite"></div>

        <div id="step-face-verify" class="hidden mb-4 p-4 rounded-xl border-2 border-slate-200 bg-slate-50 space-y-3">
            <p class="text-base font-semibold text-gray-900">Face verification</p>
            <p class="text-sm text-gray-600">Your live camera is compared to your <strong>profile photo</strong> (face-api.js). Allow camera access, align your face, then tap <strong>Verify face</strong>.</p>
            <div class="rounded-xl overflow-hidden bg-black max-h-64 aspect-video border border-slate-300">
                <video id="face-verify-video" playsinline muted class="w-full h-full object-cover"></video>
            </div>
            <p id="face-verify-status" class="text-sm text-gray-700 min-h-[1.25rem]"></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="btn-face-verify" class="flex-1 min-w-[8rem] bg-slate-800 text-white py-3 rounded-xl font-semibold touch-manipulation">Verify face</button>
                <button type="button" id="btn-face-cancel" class="px-4 py-3 rounded-xl border border-gray-300 text-gray-800 font-medium touch-manipulation">Cancel</button>
            </div>
        </div>

        @if($loggedInStudent ?? null)
        <button type="button" id="btn-start-checkin" class="w-full mb-4 bg-blue-600 text-white py-3 rounded-xl font-semibold">
            @if($sessionMode === 'qr')
                Scan QR code
            @elseif($sessionMode === 'wifi')
                Check in
            @else
                Start check-in
            @endif
        </button>
        @endif

        {{-- Step 2: Location or session verify — visible immediately when signed in so the page is never blank; guests see it after Continue --}}
        <div id="step-2" class="space-y-4 hidden">
            <p id="step-2-title" class="text-base font-semibold text-gray-900">Confirming…</p>
            <div id="location-checking" class="hidden p-4 rounded-xl bg-blue-50 text-blue-800 border border-blue-100 flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                    <i class="fa-solid fa-location-crosshairs animate-location-scan" aria-hidden="true"></i>
                </span>
                <div class="flex-1">
                    <p id="location-checking-msg" class="font-medium">Scanning your location...</p>
                    <p class="text-xs text-blue-700/80 mt-0.5">Please keep this page open while we verify your location.</p>
                </div>
            </div>
            <div id="location-ok" class="hidden p-4 rounded-xl bg-green-50 text-green-800 border border-green-100 flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </span>
                <div class="flex-1">
                    <p class="font-medium">You're within the session location.</p>
                    <p id="location-ok-msg" class="text-xs text-green-700/80 mt-0.5">Marking attendance shortly...</p>
                </div>
            </div>
        </div>


        {{-- Step 4: Processing attendance (rarely shown since auto-continue) --}}
        <div id="step-4" class="hidden space-y-4">
            <p id="step-4-title" class="text-base font-semibold text-gray-900">Processing attendance…</p>
            <div class="space-y-3">
                <div class="p-4 rounded-xl bg-blue-50 text-blue-700 flex items-center gap-2">
                    <span class="text-blue-500">●</span>
                    <span>Please wait while we process your attendance…</span>
                </div>
                {{-- Fallback buttons (rarely needed since we auto-continue) --}}
                <div id="step-4-fallback" class="hidden space-y-2">
                    @if($sessionMode === 'location')
                    <button type="button" id="btn-mark-direct" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">
                        Mark attendance
                    </button>
                    @elseif(in_array($sessionMode, ['qr', 'hybrid'], true))
                    <button type="button" id="btn-scan-qr" class="w-full bg-emerald-600 text-white py-3 rounded-xl font-semibold">
                        Open QR scanner
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <div id="status-message" class="hidden mt-4 p-4 rounded-xl text-sm font-medium"></div>

        <input type="hidden" id="latitude">
        <input type="hidden" id="longitude">
        <input type="hidden" id="session_pk" value="{{ $activeSession?->id ?? '' }}">
        <input type="hidden" id="session_token" value="{{ $activeSession?->qr_token ?? $activeSession?->session_token ?? '' }}">
        <input type="hidden" id="qr_sig" value="">
        <input type="hidden" id="qr_t" value="">
    </div>
    @endif
</div>

{{-- QR Scanner modal --}}
<div id="qr-scanner-modal" class="fixed inset-0 z-50 bg-black flex flex-col" style="display: none;">
    <div class="flex-1 relative overflow-hidden">
        <div id="qr-reader" class="w-full h-full"></div>
        <div id="qr-scanner-overlay" class="absolute inset-0 pointer-events-none flex items-center justify-center">
            <div class="relative w-64 h-64 sm:w-80 sm:h-80">
                <div class="absolute inset-0 rounded-2xl border-2 border-white/60"></div>
                <div class="absolute top-0 left-0 w-10 h-10 border-t-4 border-l-4 border-blue-500 rounded-tl-xl"></div>
                <div class="absolute top-0 right-0 w-10 h-10 border-t-4 border-r-4 border-blue-500 rounded-tr-xl"></div>
                <div class="absolute bottom-0 left-0 w-10 h-10 border-b-4 border-l-4 border-blue-500 rounded-bl-xl"></div>
                <div class="absolute bottom-0 right-0 w-10 h-10 border-b-4 border-r-4 border-blue-500 rounded-br-xl"></div>
                <div class="absolute left-0 right-0 top-1/2 h-0.5 bg-blue-500/80"></div>
            </div>
        </div>
        <div id="qr-verified-overlay" class="absolute inset-0 bg-green-700/95 flex flex-col items-center justify-center opacity-0" style="display: none;">
            <div class="mb-4 text-white">
                <svg class="w-20 h-20 sm:w-24 sm:h-24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5" opacity="0.35"/>
                    <path d="M7 12.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="text-2xl font-bold text-white">QR Code Verified ✓</p>
        </div>
        <button type="button" id="close-qr-modal" class="absolute top-4 right-4 z-30 p-3 bg-black/50 rounded-full text-white hover:bg-black/70">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div class="p-4 bg-black/80 text-center">
        <p class="text-white text-sm">Point your camera at the QR code on the screen</p>
        <p class="text-white/70 text-xs mt-1">Make sure the QR code is live, not a screenshot</p>
    </div>
</div>
<style>
/* QR Scanner fixes */
#qr-reader {
    position: relative !important;
    width: 100% !important;
    height: 100% !important;
}

#qr-reader > div {
    width: 100% !important;
    height: 100% !important;
}

#qr-reader video {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
}

#qr-reader canvas {
    display: none !important;
}

/* Hide the default QR scanner UI elements */
#qr-reader__dashboard_section {
    display: none !important;
}

#qr-reader__header_message {
    display: none !important;
}

@keyframes locationScan {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(0.82); opacity: 0.65; }
}

.animate-location-scan {
    animation: locationScan 1s ease-in-out infinite;
}
</style>
@endsection

@push('scripts')
@if($activeSession ?? null)
@if($requireFaceVerification)
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
@endif
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
window.attendanceContinueClick = function() {};

function runAttendanceFlow() {
    const courseId = {!! json_encode($course->id) !!};
    const isLoggedIn = {!! json_encode(!empty($loggedInStudent)) !!};
    const loggedInIndex = {!! json_encode(optional($loggedInStudent)->index_number ?? '') !!};
    const sessionMode = {!! json_encode($activeSession?->mode ?? 'location') !!};
    const requireFaceVerification = {!! json_encode((bool) ($settings->enable_face_verification ?? true)) !!};
    const configuredFaceThreshold = {!! json_encode((float) ($settings->face_match_threshold ?? 0.5)) !!};
    const skipLocation = true;
    const sessionTokenInput = document.getElementById('session_token');
    const sessionPkInput = document.getElementById('session_pk');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    let currentStep = 1;
    let verifiedData = null;
    let qrScanner = null;
    let attendanceMarked = false;
    const isWifiMode = sessionMode === 'wifi';
    var faceModelsPromise = null;
    function loadFaceModelsOnce() {
        if (faceModelsPromise) return faceModelsPromise;
        if (typeof faceapi === 'undefined') {
            faceModelsPromise = Promise.reject(new Error('Face API not loaded'));
            return faceModelsPromise;
        }
        var base = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights';
        faceModelsPromise = Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri(base),
            faceapi.nets.faceLandmark68Net.loadFromUri(base),
            faceapi.nets.faceRecognitionNet.loadFromUri(base),
        ]);
        return faceModelsPromise;
    }
    function stopFaceVerifyVideo() {
        var v = document.getElementById('face-verify-video');
        if (v && v.srcObject) {
            v.srcObject.getTracks().forEach(function(t) { t.stop(); });
            v.srcObject = null;
        }
    }
    /**
     * @returns {Promise<boolean>}
     */
    function openFaceVerificationModal(referenceImageUrl) {
        return new Promise(function(resolve) {
            var panel = document.getElementById('step-face-verify');
            var statusEl = document.getElementById('face-verify-status');
            var video = document.getElementById('face-verify-video');
            var btn = document.getElementById('btn-face-verify');
            var btnCancel = document.getElementById('btn-face-cancel');
            if (!panel || !video) {
                resolve(false);
                return;
            }
            if (!requireFaceVerification) {
                resolve(true);
                return;
            }
            if (typeof faceapi === 'undefined') {
                showStatus('Face verification library did not load. Refresh the page.', 'error');
                resolve(false);
                return;
            }
            var done = false;
            function finish(ok) {
                if (done) return;
                done = true;
                stopFaceVerifyVideo();
                panel.classList.add('hidden');
                if (btn) btn.onclick = null;
                if (btnCancel) btnCancel.onclick = null;
                resolve(ok);
            }
            panel.classList.remove('hidden');
            if (statusEl) statusEl.textContent = 'Loading face models…';
            loadFaceModelsOnce().then(function() {
                if (statusEl) statusEl.textContent = 'Starting camera…';
                var cam = canUseWebCamera();
                if (!cam.ok) {
                    if (statusEl) statusEl.textContent = cam.message;
                    showStatus(cam.message, 'error');
                    finish(false);
                    return;
                }
                return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            }).then(function(stream) {
                if (!stream) return;
                video.srcObject = stream;
                return video.play();
            }).then(function() {
                if (statusEl) statusEl.textContent = 'Align your face, then tap Verify face.';
            }).catch(function(err) {
                var raw = err && err.message ? String(err.message) : String(err || '');
                var msg = /fetch|weight|network|Failed to load/i.test(raw) || (err && err.name === 'TypeError')
                    ? 'Could not load face recognition models. Check your internet connection and refresh.'
                    : explainCameraError(err);
                if (statusEl) statusEl.textContent = msg;
                showStatus(msg, 'error');
                finish(false);
            });
            if (btnCancel) {
                btnCancel.onclick = function() {
                    finish(false);
                };
            }
            if (btn) {
                btn.onclick = function() {
                    (async function() {
                        try {
                            if (statusEl) statusEl.textContent = 'Checking…';
                            var refImg = await faceapi.fetchImage(referenceImageUrl);
                            var refDet = await faceapi.detectSingleFace(refImg, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.45 })).withFaceLandmarks().withFaceDescriptor();
                            if (!refDet) {
                                if (statusEl) statusEl.textContent = 'Could not read a face from your profile photo. Update your profile with a clear face photo.';
                                return;
                            }
                            var liveDet = await faceapi.detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.45 })).withFaceLandmarks().withFaceDescriptor();
                            if (!liveDet) {
                                if (statusEl) statusEl.textContent = 'No face detected. Face the camera in good light.';
                                return;
                            }
                            var dist = faceapi.euclideanDistance(refDet.descriptor, liveDet.descriptor);
                            if (dist > configuredFaceThreshold) {
                                if (statusEl) statusEl.textContent = 'Face does not match your profile. Try again with similar lighting to your profile photo.';
                                return;
                            }
                            if (statusEl) statusEl.textContent = 'Verified ✓';
                            finish(true);
                        } catch (e) {
                            if (statusEl) statusEl.textContent = (e && e.message) ? e.message : 'Verification error';
                        }
                    })();
                };
            }
        });
    }
    function needsQrScan() {
        return sessionMode === 'qr' || sessionMode === 'hybrid';
    }

    function getIndexNumber() {
        return document.getElementById('index_number').value.trim().toUpperCase();
    }

    function setIndexLocked(locked) {
        var inp = document.getElementById('index_number');
        var btn = document.getElementById('btn-step-1');
        var hint = document.getElementById('index-locked-hint');
        if (inp) {
            inp.readOnly = !!locked;
            inp.setAttribute('aria-readonly', locked ? 'true' : 'false');
            inp.classList.toggle('bg-slate-50', !!locked);
            inp.classList.toggle('text-slate-800', !!locked);
        }
        if (hint) hint.classList.toggle('hidden', !locked);
        if (btn) {
            btn.classList.toggle('hidden', !!locked);
            btn.setAttribute('aria-hidden', locked ? 'true' : 'false');
        }
    }

    function updateFlowStatus(text) {
        var el = document.getElementById('attendance-flow-status');
        if (!el) return;
        if (!text) {
            el.textContent = '';
            el.classList.add('hidden');
            return;
        }
        el.textContent = text;
        el.classList.remove('hidden');
    }

    function showStatus(message, type) {
        const el = document.getElementById('status-message');
        if (!el) return;
        el.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');
        el.textContent = message;
        if (type === 'success') {
            el.classList.add('bg-green-100', 'text-green-800');
        } else {
            el.classList.add('bg-red-100', 'text-red-800');
        }
    }

    function hideStatus() {
        const el = document.getElementById('status-message');
        if (el) el.classList.add('hidden');
    }

    function waitForHtml5Qrcode(cb, attempts) {
        attempts = attempts || 0;
        if (typeof Html5Qrcode !== 'undefined') {
            cb();
            return;
        }
        if (attempts > 100) {
            showStatus('QR scanner library did not load. Refresh the page.', 'error');
            return;
        }
        setTimeout(function() { waitForHtml5Qrcode(cb, attempts + 1); }, 50);
    }

    /** Browsers only expose the camera on HTTPS, localhost, or 127.0.0.1 — not on http://192.168.x.x */
    function canUseWebCamera() {
        if (typeof navigator === 'undefined' || !navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            return { ok: false, message: 'This browser does not support camera access for scanning.' };
        }
        if (window.isSecureContext) {
            return { ok: true };
        }
        return {
            ok: false,
            message: 'Camera cannot be used on this address. Open the site with HTTPS, or use http://localhost or http://127.0.0.1 from this computer — plain http:// with a network IP (e.g. 192.168.x.x) blocks the camera in Chrome, Edge, and Safari.',
        };
    }

    function explainCameraError(err) {
        var name = err && err.name;
        var msg = String((err && err.message) || err || '');
        if (!window.isSecureContext) {
            return 'Camera requires a secure context. Use HTTPS, or open via localhost/127.0.0.1 instead of a LAN IP over http.';
        }
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || /denied|permission/i.test(msg)) {
            return 'Camera access was blocked. Tap the lock/camera icon in the address bar and allow the camera, then try again.';
        }
        if (name === 'NotFoundError' || /no.*camera|device.*not found/i.test(msg)) {
            return 'No usable camera was found. On a laptop, allow the webcam; on a phone, check that no other app is using the camera.';
        }
        if (name === 'NotReadableError' || /in use|busy|could not start/i.test(msg)) {
            return 'The camera is already in use. Close other tabs or apps using the camera, then try again.';
        }
        if (name === 'OverconstrainedError') {
            return 'This device could not open the camera with the requested settings. Try another browser or update this page.';
        }
        return msg ? ('Camera error: ' + msg) : 'Camera could not be started.';
    }

    function startQrScannerCamera(qrScanner, scanConfig, onDecode, onScanErr) {
        var baseConfig = scanConfig || { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };

        function tryStopClear() {
            try {
                if (typeof qrScanner.stop === 'function') {
                    return qrScanner.stop().catch(function() {}).then(function() {
                        try { if (typeof qrScanner.clear === 'function') qrScanner.clear(); } catch (e2) {}
                    });
                }
            } catch (e) {}
            try { if (typeof qrScanner.clear === 'function') qrScanner.clear(); } catch (e3) {}
            return Promise.resolve();
        }

        return Html5Qrcode.getCameras().then(function(devices) {
            if (devices && devices.length) {
                var preferred = devices.find(function(d) {
                    return /back|rear|environment|wide|ultra/i.test(d.label || '');
                });
                var cam = preferred || devices[0];
                return qrScanner.start(cam.id, baseConfig, onDecode, onScanErr);
            }
            return Promise.reject(new Error('no enumerated cameras'));
        }).catch(function() {
            return tryStopClear().then(function() {
                return qrScanner.start({ facingMode: 'environment' }, baseConfig, onDecode, onScanErr);
            });
        }).catch(function() {
            return tryStopClear().then(function() {
                return qrScanner.start({ facingMode: 'user' }, baseConfig, onDecode, onScanErr);
            });
        }).catch(function() {
            return tryStopClear().then(function() {
                return Html5Qrcode.getCameras().then(function(devices2) {
                    if (devices2 && devices2.length) {
                        return qrScanner.start(devices2[0].id, baseConfig, onDecode, onScanErr);
                    }
                    throw new Error('No camera available');
                });
            });
        });
    }

    function buildMarkPayload(indexNumber, overrides) {
        overrides = overrides || {};
        var payload = {
            index_number: indexNumber,
            course_id: courseId,
            session_token: overrides.session_token !== undefined ? overrides.session_token : (sessionTokenInput ? sessionTokenInput.value : ''),
            session_id: overrides.session_id !== undefined ? overrides.session_id : (sessionPkInput && sessionPkInput.value ? parseInt(sessionPkInput.value, 10) : null),
        };
        if (sessionMode === 'qr') {
            return payload;
        }
        if (sessionMode === 'wifi') {
            return payload;
        }
        if (latInput && latInput.value) payload.latitude = parseFloat(latInput.value);
        if (lngInput && lngInput.value) payload.longitude = parseFloat(lngInput.value);
        return payload;
    }

    async function submitAttendance(payload, delayRedirect) {
        try {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
            const res = await fetch('{{ route("web.attendance.mark") }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            var rawText = await res.text();
            var data = {};
            try {
                data = rawText ? JSON.parse(rawText) : {};
            } catch (parseErr) {
                showStatus('Invalid response from server. Try again or refresh the page.', 'error');
                return;
            }
            if (!res.ok) {
                showStatus(data.message || ('Request failed (' + res.status + ')'), 'error');
                return;
            }
            if (data.success && data.redirect) {
                attendanceMarked = true;
                var nextUrl = String(data.redirect).trim();
                function go() {
                    try {
                        var resolved = new URL(nextUrl, window.location.href);
                        window.location.href = resolved.href;
                    } catch (urlErr) {
                        showStatus('Could not open the success page. Your attendance may still be saved — check with your lecturer.', 'error');
                    }
                }
                if (delayRedirect) {
                    setTimeout(go, delayRedirect);
                } else {
                    go();
                }
            } else {
                showStatus(data.message || 'Error', 'error');
            }
        } catch (err) {
            showStatus((err && err.message) ? err.message : 'Network error. Check your connection and try again.', 'error');
        }
    }

    function autoMarkAttendance(indexNumber) {
        submitAttendance(buildMarkPayload(indexNumber), 0);
    }

    function safeClearQrScanner() {
        if (!qrScanner) return;
        try {
            if (typeof qrScanner.clear === 'function') {
                qrScanner.clear();
            }
        } catch (e) {}
    }

    function openQrScanner() {
        hideStatus();
        var indexNumber = getIndexNumber();
        if (!indexNumber) return;
        var camCheck = canUseWebCamera();
        if (!camCheck.ok) {
            showStatus(camCheck.message, 'error');
            alert(camCheck.message);
            return;
        }
        var qrModal = document.getElementById('qr-scanner-modal');
        var qrOverlay = document.getElementById('qr-scanner-overlay');
        var qrVerified = document.getElementById('qr-verified-overlay');
        waitForHtml5Qrcode(function() {
            if (!qrModal) return;
            qrModal.style.display = 'flex';
            if (qrVerified) { qrVerified.style.display = 'none'; qrVerified.style.opacity = '0'; }
            if (qrOverlay) qrOverlay.style.display = 'flex';
            if (qrScanner) {
                try {
                    qrScanner.stop().then(function() {
                        safeClearQrScanner();
                    }).catch(function() {});
                } catch (e) {}
            }
            qrScanner = new Html5Qrcode('qr-reader');
            var scanCfg = {
                fps: 10,
                qrbox: { width: 260, height: 260 },
                aspectRatio: 1.0
            };

            function onDecoded(decoded) {
                try {
                    const data = JSON.parse(decoded);
                    var qrTok = data.token != null ? data.token : data.qr_token;
                    if (data.course_id == courseId && qrTok) {
                        qrScanner.stop().then(function() {
                            safeClearQrScanner();
                            if (sessionTokenInput) sessionTokenInput.value = qrTok;
                            if (sessionPkInput && data.session_id != null) sessionPkInput.value = String(data.session_id);
                            var sigEl = document.getElementById('qr_sig');
                            var tEl = document.getElementById('qr_t');
                            if (sigEl) sigEl.value = '';
                            if (tEl) tEl.value = '';
                            if (qrOverlay) qrOverlay.style.display = 'none';
                            if (qrVerified) {
                                qrVerified.style.display = 'flex';
                                qrVerified.style.opacity = '1';
                            }
                            var sid = data.session_id != null ? parseInt(data.session_id, 10) : null;
                            setTimeout(function() {
                                if (qrVerified) qrVerified.style.display = 'none';
                                var qm = document.getElementById('qr-scanner-modal');
                                if (qm) qm.style.display = 'none';
                                updateFlowStatus('Marking attendance…');
                                submitAttendance(buildMarkPayload(indexNumber, {
                                    session_token: qrTok,
                                    session_id: sid
                                }), 0);
                            }, 1000);
                        }).catch(function() {});
                    }
                } catch (e) {}
            }
            function onScanFailure(error) {
                /* non-fatal: no QR in frame yet */
            }

            startQrScannerCamera(qrScanner, scanCfg, onDecoded, onScanFailure).catch(function(err) {
                console.error('Failed to start QR scanner:', err);
                showStatus(explainCameraError(err), 'error');
                alert(explainCameraError(err));
                if (qrModal) qrModal.style.display = 'none';
            });
        });
    }

    function showStep(n) {
        currentStep = n;
        [2, 4].forEach(function(s) {
            const el = document.getElementById('step-' + s);
            if (!el) return;
            if (s === 2 && skipLocation && n !== 2) el.classList.add('hidden');
            else el.classList.toggle('hidden', s !== n);
        });
        if (n === 2) updateFlowStatus('Confirming your session…');
        else if (n === 4) updateFlowStatus('');
    }

    var indexInputEl = document.getElementById('index_number');
    if (indexInputEl) {
        indexInputEl.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    function applyVerifySuccess(data, indexNumber) {
        document.getElementById('location-checking').classList.add('hidden');
        if (data.verified) {
            verifiedData = data;
            document.getElementById('location-ok').classList.remove('hidden');
            var okMsg = document.getElementById('location-ok-msg');
            var secondsRemaining = 6;
            if (okMsg) okMsg.textContent = 'Marking attendance in ' + secondsRemaining + ' seconds...';
            var countdown = setInterval(function() {
                secondsRemaining -= 1;
                if (secondsRemaining <= 0) {
                    clearInterval(countdown);
                    if (okMsg) okMsg.textContent = 'Marking attendance now...';
                    return;
                }
                if (okMsg) okMsg.textContent = 'Marking attendance in ' + secondsRemaining + ' seconds...';
            }, 1000);
            setTimeout(function() {
                clearInterval(countdown);
                document.getElementById('step-2').classList.add('hidden');
                if (isWifiMode) {
                    // Wi-Fi mode: auto-mark attendance immediately
                    updateFlowStatus('Marking attendance…');
                    autoMarkAttendance(indexNumber);
                    return;
                }
                if (needsQrScan()) {
                    updateFlowStatus('Opening camera to scan the session QR…');
                    openQrScanner();
                } else {
                    // Location mode: auto-mark attendance immediately
                    updateFlowStatus('Marking attendance…');
                    autoMarkAttendance(indexNumber);
                }
            }, 6000);
        } else {
            showStatus(data.message || 'Verification failed', 'error');
        }
    }

    async function postVerify(indexNumber, lat, lng) {
        const body = { index_number: indexNumber, course_id: courseId };
        if (lat != null && lng != null && !isNaN(lat) && !isNaN(lng)) {
            body.latitude = lat;
            body.longitude = lng;
        }
        var sessionTokenInput = document.getElementById('session_token');
        var sessionPkInput = document.getElementById('session_pk');
        if (sessionTokenInput && sessionTokenInput.value) {
            body.session_token = sessionTokenInput.value;
        }
        if (sessionPkInput && sessionPkInput.value) {
            body.session_id = parseInt(sessionPkInput.value, 10);
        }
        const r = await fetch('{{ route("web.attendance.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        });
        var data = {};
        try {
            data = await r.json();
        } catch (e) {
            throw new Error('Invalid response from server');
        }
        if (!r.ok) {
            document.getElementById('location-checking').classList.add('hidden');
            showStatus(data.message || ('HTTP ' + r.status), 'error');
            return { ok: false, data: data };
        }
        return { ok: true, data: data };
    }

    async function runLocationStep(indexNumber) {
        if (sessionMode === 'qr') {
            return;
        }
        var step2title = document.getElementById('step-2-title');
        if (step2title) step2title.textContent = 'Confirming your session…';
        var lcm = document.getElementById('location-checking-msg');
        if (lcm) lcm.textContent = 'Scanning your location...';
        document.getElementById('location-checking').classList.remove('hidden');
        document.getElementById('location-ok').classList.add('hidden');
        try {
            const result = await postVerify(indexNumber, null, null);
            if (!result.ok) {
                document.getElementById('location-checking').classList.add('hidden');
                return;
            }
            const data = result.data;
            if (!data.verified) {
                document.getElementById('location-checking').classList.add('hidden');
                showStatus(data.message || 'Verification failed', 'error');
                return;
            }
            if (requireFaceVerification && !data.profile_image_url) {
                document.getElementById('location-checking').classList.add('hidden');
                showStatus('Add a profile photo in your account before marking attendance.', 'error');
                return;
            }
            document.getElementById('location-checking').classList.add('hidden');
            if (requireFaceVerification) {
                var s2hide = document.getElementById('step-2');
                if (s2hide) s2hide.classList.add('hidden');
                updateFlowStatus('Face verification');
                const faceOk = await openFaceVerificationModal(data.profile_image_url);
                if (!faceOk) {
                    updateFlowStatus('');
                    showStatus('Face verification was cancelled or did not match your profile.', 'error');
                    return;
                }
            }
            applyVerifySuccess(data, indexNumber);
        } catch (e) {
            document.getElementById('location-checking').classList.add('hidden');
            showStatus(e && e.message ? e.message : 'Verification failed', 'error');
        }
    }

    function prepareCheckInUi(indexNumber) {
        const btn = document.getElementById('btn-step-1');
        const startBtn = document.getElementById('btn-start-checkin');
        if (btn) btn.disabled = true;
        if (startBtn) startBtn.disabled = true;
        hideStatus();
        setIndexLocked(true);
        showStep(2);
        var locChk = document.getElementById('location-checking');
        var locOk = document.getElementById('location-ok');
        if (locChk) locChk.classList.add('hidden');
        if (locOk) locOk.classList.add('hidden');
    }

    function releaseCheckInUi() {
        const btn = document.getElementById('btn-step-1');
        const startBtn = document.getElementById('btn-start-checkin');
        if (btn) btn.disabled = false;
        if (startBtn) startBtn.disabled = false;
    }

    async function startCheckIn(ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        const indexNumber = getIndexNumber();
        if (!indexNumber) { showStatus('Enter your index number', 'error'); return; }
        
        // QR-only: verify session + face match, then open scanner (never request geolocation here)
        if (sessionMode === 'qr') {
            updateFlowStatus('Confirming session…');
            try {
                const result = await postVerify(indexNumber, null, null);
                if (!result.ok) return;
                const data = result.data;
                if (!data.verified) {
                    showStatus(data.message || 'Verification failed', 'error');
                    return;
                }
                if (requireFaceVerification && !data.profile_image_url) {
                    showStatus('Add a profile photo in your account before marking attendance.', 'error');
                    return;
                }
                if (requireFaceVerification) {
                    updateFlowStatus('Face verification');
                    const faceOk = await openFaceVerificationModal(data.profile_image_url);
                    if (!faceOk) {
                        updateFlowStatus('');
                        showStatus('Face verification was cancelled or did not match your profile.', 'error');
                        return;
                    }
                }
                updateFlowStatus('Opening camera to scan the session QR…');
                openQrScanner();
            } catch (e) {
                showStatus(e && e.message ? e.message : 'Verification failed', 'error');
            }
            return;
        }

        prepareCheckInUi(indexNumber);
        updateFlowStatus('Confirming your session…');
        var locChk2 = document.getElementById('location-checking');
        if (locChk2) locChk2.classList.remove('hidden');
        try {
            await runLocationStep(indexNumber);
        } catch (e) {
            showStatus(e && e.message ? e.message : 'Could not verify your profile. Check your connection and try again.', 'error');
            var step2El = document.getElementById('step-2');
            if (step2El) step2El.classList.add('hidden');
            updateFlowStatus('');
            if (!isLoggedIn) {
                setIndexLocked(false);
            }
        } finally {
            releaseCheckInUi();
        }
    }

    window.attendanceContinueClick = startCheckIn;
    window.startCheckIn = startCheckIn; // Make globally available for fallback

    var startCheckinBtn = document.getElementById('btn-start-checkin');
    if (startCheckinBtn) {
        startCheckinBtn.addEventListener('click', startCheckIn);
        console.log('Start check-in button handler attached');
    } else {
        console.warn('Start check-in button not found in DOM');
    }

    if (isLoggedIn && loggedInIndex) {
        var idxEl = document.getElementById('index_number');
        if (idxEl) idxEl.value = loggedInIndex;
        if (document.getElementById('btn-step-1')) setIndexLocked(true);
        updateFlowStatus('Ready to start check-in');
    }


    var markDirectBtn = document.getElementById('btn-mark-direct');
    if (markDirectBtn) markDirectBtn.addEventListener('click', function() {
        const indexNumber = getIndexNumber();
        if (!indexNumber) return;
        const payload = {
            index_number: indexNumber,
            course_id: courseId,
            session_token: sessionTokenInput ? sessionTokenInput.value : null,
            session_id: (sessionPkInput && sessionPkInput.value) ? parseInt(sessionPkInput.value, 10) : null,
            qr_sig: (document.getElementById('qr_sig') ? document.getElementById('qr_sig').value : null) || null,
            qr_t: (document.getElementById('qr_t') && document.getElementById('qr_t').value) ? parseInt(document.getElementById('qr_t').value, 10) : null,
            attendance_time: new Date().toISOString(),
        };
        this.disabled = true;
        submitAttendance(payload);
    });

    var scanQrBtn = document.getElementById('btn-scan-qr');
    if (scanQrBtn) scanQrBtn.addEventListener('click', function() {
        updateFlowStatus('Opening camera to scan the session QR…');
        openQrScanner();
    });

    var closeQrBtn = document.getElementById('close-qr-modal');
    if (closeQrBtn) closeQrBtn.addEventListener('click', function() {
        var qrModal = document.getElementById('qr-scanner-modal');
        if (qrModal) qrModal.style.display = 'none';
        if (qrScanner) {
            qrScanner.stop().then(function() {
                safeClearQrScanner();
            }).catch(function(e) {
                console.warn('QR Scanner stop error:', e);
            });
        }
        if (!attendanceMarked && needsQrScan()) {
            var s4 = document.getElementById('step-4');
            if (s4) s4.classList.remove('hidden');
            updateFlowStatus('Tap below to open the scanner again');
        }
    });
}

/* Script is after markup in @stack('scripts'); DOM is ready — run immediately so
   window.attendanceContinueClick exists before any tap (avoids dead Continue on slow DOMContentLoaded). */

// Emergency fallback handlers in case main script fails
window.attendanceContinueClick = function() {
    alert('Attendance system loading... Please wait a moment and try again.');
};

// Attach critical button handlers immediately, before main flow
document.addEventListener('DOMContentLoaded', function() {
    var startBtn = document.getElementById('btn-start-checkin');
    if (startBtn && !startBtn.onclick) {
        startBtn.onclick = function() {
            if (typeof window.startCheckIn === 'function') {
                window.startCheckIn();
            } else {
                alert('Attendance system still loading. Please wait and try again.');
            }
        };
    }
});

try {
    runAttendanceFlow();
    console.log('Attendance flow initialized successfully');
} catch (setupErr) {
    console.error('Attendance flow init failed', setupErr);
    alert('Attendance system failed to load. Error: ' + (setupErr.message || 'Unknown error'));
    window.attendanceContinueClick = function() {
        alert('Attendance page could not start. Please refresh the page.');
    };
}
</script>
@endif
@endpush
