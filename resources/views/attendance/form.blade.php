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
    {{-- Minimal top strip: back arrow + course code chip. All the
         previously-on-page chatter (course title, mode description,
         signed-in line, face-check warning) was redundant — the big
         tap button below now carries the entire intent. --}}
    <div class="flex items-center justify-between gap-3 pt-1">
        <a href="{{ url('/') }}" aria-label="Back"
           class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-slate-200 px-3 py-1.5 text-[11px] font-mono font-semibold text-slate-700 truncate max-w-[70%]">
            <i class="fas fa-book-open text-slate-400 text-[10px]"></i>
            {{ $course->course_code ?? $course->course_name }}
        </span>
    </div>

    @if(!$activeSession)
    <div class="p-4 bg-amber-50 text-amber-800 rounded-xl text-sm font-medium border border-amber-100">
        Session closed. Attendance cannot be marked.
    </div>
    @endif

    @if($activeSession)
    <div class="rounded-xl px-4 sm:px-6 pt-2 pb-6">
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
        {{-- Single morphing CTA: idle → locating → got-it → opening-camera
             → marking → done / error. The button itself communicates
             every state; no extra panels, no chatty paragraphs. --}}
        @php
            $isCheckInOut = $activeSession?->isCheckInCheckoutMode() ?? false;
            $ctaLabel = match (true) {
                $sessionMode === 'qr'     => 'Scan QR',
                $sessionMode === 'wifi'   => 'Check In',
                $sessionMode === 'hybrid' => 'Check In',
                $isCheckInOut             => 'Clock In',
                default                   => 'Check In',
            };
        @endphp
        <div class="my-8 flex flex-col items-center select-none">
            <button type="button" id="btn-start-checkin"
                data-mode="{{ $sessionMode }}"
                data-state="idle"
                data-default-label="{{ $ctaLabel }}"
                aria-label="{{ $ctaLabel }}"
                class="cta-tap relative h-48 w-48 sm:h-52 sm:w-52 rounded-[2rem] text-white shadow-xl transition-all duration-200 ease-out active:scale-95 focus:outline-none focus:ring-4 overflow-hidden touch-manipulation">
                {{-- Pulse ring (auto-paused in non-idle states via CSS) --}}
                <span class="cta-ring pointer-events-none absolute inset-2 rounded-[1.75rem] ring-2"></span>
                <span class="relative z-10 flex h-full flex-col items-center justify-center gap-2 px-4">
                    <i id="cta-icon" class="fa-solid fa-hand-pointer text-[3.25rem] drop-shadow-sm leading-none" aria-hidden="true"></i>
                    <span id="cta-label" class="text-sm font-bold tracking-wider uppercase">{{ $ctaLabel }}</span>
                </span>
            </button>
            {{-- Tiny hint line under the button — also state-driven. --}}
            <p id="cta-hint" class="mt-3 text-xs text-slate-500 min-h-[1rem]"></p>
        </div>
        @endif

        {{-- Kept for the QR/face-verify flow to keep its existing
             hooks. step-2 is intentionally empty visually now —
             every state shows up on the button itself. --}}
        <div id="step-2" class="hidden">
            <div id="location-checking" class="hidden"></div>
            <div id="location-ok" class="hidden">
                <p id="location-ok-msg" class="sr-only">Marking attendance shortly...</p>
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

        @if($activeSession && in_array($sessionMode, ['qr', 'hybrid'], true))
        {{-- Manual session-code panel: hidden by default. Shown only when
             the QR scanner fails (no camera, scan timeout, invalid code,
             explicit "Can't scan?" link). Set by JS that listens for
             scanner failures. --}}
        <div id="session-code-fallback" class="mt-4 p-4 rounded-xl border border-slate-200 bg-slate-50/80 space-y-2 hidden" data-fallback-panel>
            <p class="text-sm font-semibold text-gray-800">
                <i class="fas fa-keyboard text-slate-500 mr-1"></i>
                Can't scan? Enter the session code instead
            </p>
            <p class="text-xs text-gray-600">
                Ask the rep to read out the 6-character code on screen.
                <span class="block mt-0.5 text-[10px] text-amber-700 font-medium">
                    <i class="fas fa-stopwatch mr-0.5"></i>
                    The code rotates every few seconds &mdash; type it in fast.
                </span>
            </p>
            <label for="manual_session_code" class="sr-only">Session code</label>
            <input type="text" id="manual_session_code" autocomplete="off" inputmode="text"
                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 font-mono text-base uppercase tracking-[0.18em] text-center"
                placeholder="e.g. K7HM2P" maxlength="48"
                style="text-transform:uppercase">
            <button type="button" id="btn-session-code-mark"
                class="w-full bg-slate-800 text-white py-3 rounded-xl text-sm font-semibold touch-manipulation">
                Mark with session code
            </button>
        </div>
        <button type="button" id="btn-show-session-code"
                class="mt-3 w-full text-xs font-medium text-slate-500 hover:text-slate-800 underline-offset-2 hover:underline"
                onclick="(function(){var p=document.getElementById('session-code-fallback');if(p){p.classList.remove('hidden');this.classList.add('hidden');var i=document.getElementById('manual_session_code');if(i)i.focus();}}).call(this);">
            Can't scan? Enter the session code manually
        </button>
        @endif
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
    <div class="p-4 bg-black/80 text-center space-y-1">
        <p class="text-white text-sm">Use the <strong class="font-semibold">back camera</strong> and scan from another device or screen when possible.</p>
        <p class="text-white/70 text-xs">If the camera fails, use <strong>session code</strong> on the form below.</p>
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

/* ------- Morphing CTA button states ------------------------------ */
/* Single source of truth for the big tap button look. Each state
   only changes 3 things: background colour, ring colour, and whether
   the soft inner pulse is animating (idle = on, everything else = off
   so the active state doesn't look like an alert). The icon swap is
   handled by JS rewriting the <i> class list. */
.cta-tap                       { background-color: #059669; box-shadow: 0 16px 32px -10px rgba(5,150,105,0.45); }
.cta-tap .cta-ring             { border-color: rgba(255,255,255,0.30); animation: cta-pulse 2.4s cubic-bezier(0,0,0.2,1) infinite; }
.cta-tap:hover:not(:disabled)  { background-color: #047857; }
.cta-tap:focus                 { --tw-ring-color: rgba(110, 231, 183, 1); }

.cta-tap[data-state="idle"]    { background-color: #059669; }
.cta-tap[data-state="locating"]      { background-color: #0284c7; box-shadow: 0 16px 32px -10px rgba(2,132,199,0.45); }
.cta-tap[data-state="opening_camera"]{ background-color: #4338ca; box-shadow: 0 16px 32px -10px rgba(67,56,202,0.45); }
.cta-tap[data-state="marking"]       { background-color: #0d9488; box-shadow: 0 16px 32px -10px rgba(13,148,136,0.45); }
.cta-tap[data-state="success"]       { background-color: #16a34a; box-shadow: 0 16px 32px -10px rgba(22,163,74,0.45); }
.cta-tap[data-state="error"]         { background-color: #e11d48; box-shadow: 0 16px 32px -10px rgba(225,29,72,0.45); }

/* Pulse only while idle — feels alive. In every other state we want
   the user to read it as "working", not "alert me". */
.cta-tap[data-state="idle"] .cta-ring        { animation: cta-pulse 2.4s cubic-bezier(0,0,0.2,1) infinite; }
.cta-tap:not([data-state="idle"]) .cta-ring  { animation: none; opacity: 0.4; }

/* While locating, the map crosshairs sweep — gives the "scanning…" feel
   without needing a separate progress bar / spinner / paragraph. */
.cta-tap[data-state="locating"] #cta-icon { animation: cta-locating 1.6s ease-in-out infinite; }
.cta-tap[data-state="opening_camera"] #cta-icon,
.cta-tap[data-state="marking"] #cta-icon { animation: cta-pulse-soft 1.1s ease-in-out infinite; }

@keyframes cta-pulse {
    0%   { transform: scale(1);   opacity: 1; }
    75%  { transform: scale(1.12); opacity: 0; }
    100% { transform: scale(1.12); opacity: 0; }
}
@keyframes cta-locating {
    0%, 100% { transform: scale(1)    rotate(0deg); opacity: 1; }
    50%      { transform: scale(0.92) rotate(20deg); opacity: 0.75; }
}
@keyframes cta-pulse-soft {
    0%, 100% { opacity: 1;   transform: scale(1); }
    50%      { opacity: 0.7; transform: scale(0.94); }
}

.cta-tap:disabled { cursor: default; }
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
    let qrScanSubmitting = false; // one decode per modal open — kills the "verified flashes on reopen" race
    let qrPendingVerifiedTimer = null; // outstanding setTimeout from a previous scan; cleared on reopen
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
        // If we just surfaced an error and the cause looks QR / camera /
        // scan related, automatically reveal the manual session-code panel
        // so the student isn't stranded.
        if (type !== 'success') {
            var lower = String(message || '').toLowerCase();
            var looksLikeQrFailure = lower.indexOf('qr') !== -1
                || lower.indexOf('camera') !== -1
                || lower.indexOf('scan') !== -1
                || lower.indexOf('invalid') !== -1;
            if (looksLikeQrFailure) {
                var panel = document.getElementById('session-code-fallback');
                if (panel) panel.classList.remove('hidden');
                var toggle = document.getElementById('btn-show-session-code');
                if (toggle) toggle.classList.add('hidden');
            }
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
        payload.client_meta = collectClientMeta();
        return payload;
    }

    /**
     * Snapshot of low-cost browser signals we send up with every mark so
     * a rep / admin can spot two distinct devices behind the same person
     * trying to "mark for a friend". Nothing here is PII; it's the kind
     * of data the browser already advertises in normal page loads.
     */
    function collectClientMeta() {
        try {
            var screenStr = (screen && screen.width && screen.height)
                ? (screen.width + 'x' + screen.height)
                : '';
            var tz = '';
            try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
            return {
                platform: (navigator.platform || '').slice(0, 32),
                screen: screenStr,
                tz: tz,
                lang: (navigator.language || '').slice(0, 16),
                cores: typeof navigator.hardwareConcurrency === 'number' ? navigator.hardwareConcurrency : null,
                memory: typeof navigator.deviceMemory === 'number' ? navigator.deviceMemory : null,
                pixel_ratio: window.devicePixelRatio || null,
                touch: 'ontouchstart' in window || (navigator.maxTouchPoints || 0) > 0,
                app: 'web'
            };
        } catch (e) {
            return null;
        }
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
                var errMsg = data.message || ('Request failed (' + res.status + ')');
                if (typeof setCtaState === 'function') setCtaState('error', { hint: errMsg });
                showStatus(errMsg, 'error');
                if (needsQrScan() && /invalid qr|qr code|qr token|expired/i.test(errMsg)) {
                    if (sessionTokenInput) sessionTokenInput.value = '';
                    if (sessionPkInput) sessionPkInput.value = '';
                    if (typeof setCtaState === 'function') setCtaState('opening_camera', { hint: 'Code expired — reopening scanner…' });
                    setTimeout(openQrScanner, 600);
                }
                return;
            }
            if (data.success && data.redirect) {
                attendanceMarked = true;
                if (typeof setCtaState === 'function') setCtaState('success', { hint: 'Redirecting…' });
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

    // Take a scanner instance by argument so we can't accidentally clear the
    // *next* scanner instance from an async .then() that fired late (the
    // outer `qrScanner` is reassigned every openQrScanner() call).
    function safeClearScannerInstance(inst) {
        if (!inst) return;
        try {
            if (typeof inst.clear === 'function') {
                inst.clear();
            }
        } catch (e) {}
    }

    // Hard reset of the modal: stops any running scanner, clears any pending
    // "verified → submit" timeout, clears the verified overlay, clears the
    // stale signed token from a previous scan. Called both on close and at
    // the top of every openQrScanner() so a failed first scan can never
    // leak state into the next attempt.
    function resetQrModal() {
        qrScanSubmitting = false;
        if (qrPendingVerifiedTimer) {
            clearTimeout(qrPendingVerifiedTimer);
            qrPendingVerifiedTimer = null;
        }
        var qrOverlay = document.getElementById('qr-scanner-overlay');
        var qrVerified = document.getElementById('qr-verified-overlay');
        if (qrVerified) { qrVerified.style.display = 'none'; qrVerified.style.opacity = '0'; }
        if (qrOverlay) qrOverlay.style.display = 'flex';
        if (sessionTokenInput) sessionTokenInput.value = '';
        if (sessionPkInput) sessionPkInput.value = '';
        var sigEl = document.getElementById('qr_sig');
        var tEl = document.getElementById('qr_t');
        if (sigEl) sigEl.value = '';
        if (tEl) tEl.value = '';
        if (qrScanner) {
            var dyingScanner = qrScanner;
            try {
                dyingScanner.stop().then(function() {
                    safeClearScannerInstance(dyingScanner);
                }).catch(function() {});
            } catch (e) {}
        }
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
        resetQrModal();
        waitForHtml5Qrcode(function() {
            if (!qrModal) return;
            qrModal.style.display = 'flex';
            if (qrVerified) { qrVerified.style.display = 'none'; qrVerified.style.opacity = '0'; }
            if (qrOverlay) qrOverlay.style.display = 'flex';
            // Build the new scanner and capture it in `instance` so every
            // closure below operates on THIS instance, even if a later
            // openQrScanner() reassigns the outer `qrScanner` variable.
            var instance = new Html5Qrcode('qr-reader');
            qrScanner = instance;
            var scanCfg = {
                fps: 12,
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    var s = Math.min(viewfinderWidth, viewfinderHeight, 320);
                    return { width: Math.floor(s * 0.85), height: Math.floor(s * 0.85) };
                },
                aspectRatio: 1.0
            };

            function onDecoded(decoded) {
                // One decode per modal open. Without this, html5-qrcode can
                // fire onDecoded multiple times for buffered frames between
                // stop() being called and stop() actually settling, which
                // was the root of "verified flashes / wrong-token submitted"
                // reports on the rep + student QR flow.
                if (qrScanSubmitting) return;
                try {
                    const data = JSON.parse(decoded);
                    var qrTok = data.token != null ? data.token : data.qr_token;
                    // Devtools breadcrumb — pairs with the server-side
                    // [QR-DEBUG] log entries so we can correlate what
                    // the camera saw with what the backend received.
                    try {
                        console.info('[QR-DEBUG][scan]', {
                            scanned_course_id: data.course_id,
                            expected_course_id: courseId,
                            session_id: data.session_id,
                            token_len: qrTok ? String(qrTok).length : 0,
                            token_head: qrTok ? String(qrTok).slice(0, 8) : '',
                            decoded_at: new Date().toISOString(),
                        });
                    } catch (logErr) { /* no-op */ }
                    if (data.course_id != courseId || !qrTok) return;

                    qrScanSubmitting = true;
                    var sid = data.session_id != null ? parseInt(data.session_id, 10) : null;

                    instance.stop().then(function() {
                        safeClearScannerInstance(instance);
                        if (sessionTokenInput) sessionTokenInput.value = qrTok;
                        if (sessionPkInput && sid != null) sessionPkInput.value = String(sid);
                        if (qrOverlay) qrOverlay.style.display = 'none';
                        if (qrVerified) {
                            qrVerified.style.display = 'flex';
                            qrVerified.style.opacity = '1';
                        }
                        if (qrPendingVerifiedTimer) clearTimeout(qrPendingVerifiedTimer);
                        qrPendingVerifiedTimer = setTimeout(function() {
                            qrPendingVerifiedTimer = null;
                            if (qrVerified) qrVerified.style.display = 'none';
                            var qm = document.getElementById('qr-scanner-modal');
                            if (qm) qm.style.display = 'none';
                            updateFlowStatus('Marking attendance…');
                            submitAttendance(buildMarkPayload(indexNumber, {
                                session_token: qrTok,
                                session_id: sid
                            }), 0);
                        }, 1000);
                    }).catch(function() {
                        // stop() failed — reset our guard so the user can
                        // retry instead of being stuck on a frozen overlay.
                        qrScanSubmitting = false;
                    });
                } catch (e) {
                    // Decoded payload wasn't JSON-shaped for us; let the
                    // scanner keep running. Don't keep the guard up.
                    qrScanSubmitting = false;
                }
            }
            function onScanFailure(error) {
                /* non-fatal: no QR in frame yet */
            }

            startQrScannerCamera(instance, scanCfg, onDecoded, onScanFailure).catch(function(err) {
                console.error('Failed to start QR scanner:', err);
                showStatus(explainCameraError(err), 'error');
                alert(explainCameraError(err));
                if (qrModal) qrModal.style.display = 'none';
                qrScanSubmitting = false;
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
        if (data.verified) {
            verifiedData = data;
            // Brief "verified" state on the morphing CTA — no separate
            // green panel anymore, just the button glowing green for a
            // moment before the next phase starts. Kept short (2s) so
            // it doesn't feel sluggish; the old 6s countdown was only
            // needed because the user had to *read* the green panel.
            setCtaState('got_location', { label: 'Verified', hint: 'Marking attendance…' });
            setTimeout(function() {
                if (isWifiMode) {
                    setCtaState('marking');
                    autoMarkAttendance(indexNumber);
                    return;
                }
                if (needsQrScan()) {
                    setCtaState('opening_camera', { hint: 'Allow camera if your phone asks.' });
                    openQrScanner();
                } else {
                    setCtaState('marking');
                    autoMarkAttendance(indexNumber);
                }
            }, 1600);
        } else {
            setCtaState('error', { hint: data.message || 'Verification failed.' });
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

    // ---- Morphing CTA state machine -----------------------------------
    // setCtaState(name, { label?, hint? }) — swaps the button's icon,
    // colour and label. Defined here (not inline at the markup) so the
    // states stay in sync with the CSS [data-state] selectors and can
    // be triggered from anywhere in the flow.
    const ctaStates = {
        idle:           { icon: 'fa-hand-pointer',         label: null,            hint: '' },
        locating:       { icon: 'fa-location-crosshairs',  label: 'Locating you…', hint: 'Allow location if your phone asks.' },
        got_location:   { icon: 'fa-circle-check',         label: 'Got you',       hint: 'Verifying with the server…' },
        opening_camera: { icon: 'fa-camera',               label: 'Opening camera…', hint: 'Allow camera if your phone asks.' },
        marking:        { icon: 'fa-spinner fa-spin',      label: 'Marking…',      hint: '' },
        success:        { icon: 'fa-circle-check',         label: 'Marked',        hint: '' },
        error:          { icon: 'fa-triangle-exclamation', label: 'Try again',     hint: 'Tap to retry.' },
    };
    function setCtaState(name, overrides) {
        const btn = document.getElementById('btn-start-checkin');
        if (!btn) return;
        const cfg = ctaStates[name];
        if (!cfg) return;
        btn.dataset.state = name;
        const iconEl = document.getElementById('cta-icon');
        if (iconEl) iconEl.className = 'fa-solid ' + cfg.icon + ' text-[3.25rem] drop-shadow-sm leading-none';
        const labelEl = document.getElementById('cta-label');
        const labelText = (overrides && overrides.label) || cfg.label || btn.dataset.defaultLabel || 'Check In';
        if (labelEl) labelEl.textContent = labelText;
        const hintEl = document.getElementById('cta-hint');
        if (hintEl) hintEl.textContent = (overrides && overrides.hint != null) ? overrides.hint : cfg.hint;
        // Re-enable on idle/error so the rep can tap again.
        btn.disabled = (name !== 'idle' && name !== 'error');
    }
    // Expose for legacy callers (e.g. applyVerifySuccess timers).
    window.attendanceSetCtaState = setCtaState;

    /**
     * Ask the browser for a GPS fix *up front* (so the OS permission
     * popup appears in the same tap), with a sensible cascade:
     *   - high-accuracy GPS, 7 s timeout
     *   - low-accuracy Wi-Fi/network, 12 s timeout
     * Resolves with { lat, lng, accuracy } or null if both timed out.
     * Permission-denied returns { denied: true } so the caller can
     * surface a clear message instead of looping into low-accuracy.
     */
    async function acquireStudentGps() {
        if (!('geolocation' in navigator)) return null;
        const getPos = (opts) => new Promise((resolve, reject) =>
            navigator.geolocation.getCurrentPosition(resolve, reject, opts));
        try {
            const p = await getPos({ enableHighAccuracy: true, timeout: 7000, maximumAge: 30000 });
            return { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy || 0 };
        } catch (e1) {
            if (e1 && e1.code === 1) return { denied: true };
        }
        try {
            const p = await getPos({ enableHighAccuracy: false, timeout: 12000, maximumAge: 5 * 60 * 1000 });
            return { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy || 0 };
        } catch (e2) {
            if (e2 && e2.code === 1) return { denied: true };
        }
        return null;
    }

    async function runLocationStep(indexNumber) {
        if (sessionMode === 'qr') {
            return;
        }
        // 1. Ask the OS for location FIRST so the permission popup
        //    appears in the same gesture the student initiated. Saves a
        //    round-trip and avoids the old "server says missing coords"
        //    surprise.
        setCtaState('locating');
        const fix = await acquireStudentGps();
        if (fix && fix.denied) {
            setCtaState('error', { label: 'Allow location', hint: 'Tap the lock icon in the address bar to allow location, then try again.' });
            return;
        }
        if (fix && typeof fix.lat === 'number') {
            if (latInput) latInput.value = String(fix.lat);
            if (lngInput) lngInput.value = String(fix.lng);
        }
        // 2. Now verify with the server (it does the geofence check).
        setCtaState('got_location');
        try {
            const result = await postVerify(indexNumber, fix ? fix.lat : null, fix ? fix.lng : null);
            if (!result.ok) {
                setCtaState('error', { hint: 'Verification failed.' });
                return;
            }
            const data = result.data;
            if (!data.verified) {
                setCtaState('error', { hint: data.message || 'Verification failed.' });
                showStatus(data.message || 'Verification failed', 'error');
                return;
            }
            if (requireFaceVerification && !data.profile_image_url) {
                setCtaState('error', { hint: 'Add a profile photo first.' });
                showStatus('Add a profile photo in your account before marking attendance.', 'error');
                return;
            }
            if (requireFaceVerification) {
                updateFlowStatus('Face verification');
                const faceOk = await openFaceVerificationModal(data.profile_image_url);
                if (!faceOk) {
                    setCtaState('error', { hint: 'Face check cancelled.' });
                    updateFlowStatus('');
                    showStatus('Face verification was cancelled or did not match your profile.', 'error');
                    return;
                }
            }
            applyVerifySuccess(data, indexNumber);
        } catch (e) {
            setCtaState('error', { hint: 'Network error. Tap to retry.' });
            showStatus(e && e.message ? e.message : 'Verification failed', 'error');
        }
    }

    function prepareCheckInUi(indexNumber) {
        const btn = document.getElementById('btn-step-1');
        if (btn) btn.disabled = true;
        hideStatus();
        setIndexLocked(true);
        // The morphing CTA now carries every state; we don't reveal the
        // old verbose step-2 panel anymore.
    }

    function releaseCheckInUi() {
        const btn = document.getElementById('btn-step-1');
        if (btn) btn.disabled = false;
        // Caller decides the CTA's final state via setCtaState().
    }

    async function startCheckIn(ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        const indexNumber = getIndexNumber();
        if (!indexNumber) { showStatus('Enter your index number', 'error'); return; }

        // QR-only: ask the server for session/face checks, then open the
        // scanner. Camera permission gets requested at scanner-open time
        // — that *is* upfront from the student's perspective (one tap →
        // OS popup), no separate location request.
        if (sessionMode === 'qr') {
            setCtaState('opening_camera', { hint: 'Confirming session…' });
            try {
                const result = await postVerify(indexNumber, null, null);
                if (!result.ok) { setCtaState('error', { hint: 'Verification failed.' }); return; }
                const data = result.data;
                if (!data.verified) {
                    setCtaState('error', { hint: data.message || 'Verification failed.' });
                    showStatus(data.message || 'Verification failed', 'error');
                    return;
                }
                if (requireFaceVerification && !data.profile_image_url) {
                    setCtaState('error', { hint: 'Add a profile photo first.' });
                    showStatus('Add a profile photo in your account before marking attendance.', 'error');
                    return;
                }
                if (requireFaceVerification) {
                    setCtaState('opening_camera', { label: 'Face check', hint: 'Look at the camera.' });
                    const faceOk = await openFaceVerificationModal(data.profile_image_url);
                    if (!faceOk) {
                        setCtaState('error', { hint: 'Face check cancelled.' });
                        showStatus('Face verification was cancelled or did not match your profile.', 'error');
                        return;
                    }
                }
                setCtaState('opening_camera', { hint: 'Opening scanner…' });
                openQrScanner();
            } catch (e) {
                setCtaState('error', { hint: 'Network error. Tap to retry.' });
                showStatus(e && e.message ? e.message : 'Verification failed', 'error');
            }
            return;
        }

        prepareCheckInUi(indexNumber);
        try {
            // runLocationStep now requests GPS itself BEFORE the server
            // call (so the OS popup lands in the same tap), and morphs
            // the CTA from "locating…" → "got you" → "marking…".
            await runLocationStep(indexNumber);
        } catch (e) {
            setCtaState('error', { hint: 'Network error. Tap to retry.' });
            showStatus(e && e.message ? e.message : 'Could not verify your profile. Check your connection and try again.', 'error');
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
        startCheckinBtn.addEventListener('click', function(ev) {
            // After an error the button stays interactive — a fresh tap
            // resets it to idle and re-runs the whole flow instead of
            // requiring the student to refresh.
            if (startCheckinBtn.dataset.state === 'error') {
                setCtaState('idle');
            }
            startCheckIn(ev);
        });
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
        // Full reset so the next openQrScanner() starts from a clean state
        // (no stale verified overlay, no pending submit timer, no leftover
        // signed token in the form inputs).
        resetQrModal();
        if (!attendanceMarked && needsQrScan()) {
            var s4 = document.getElementById('step-4');
            if (s4) s4.classList.remove('hidden');
            updateFlowStatus('Tap below to open the scanner again');
        }
    });

    var sessionCodeBtn = document.getElementById('btn-session-code-mark');
    var manualSessionCodeEl = document.getElementById('manual_session_code');
    if (sessionCodeBtn && manualSessionCodeEl) {
        sessionCodeBtn.addEventListener('click', function() {
            hideStatus();
            var code = (manualSessionCodeEl.value || '').trim();
            if (!code) {
                showStatus('Enter the session code from your lecturer’s screen.', 'error');
                return;
            }
            var indexNumber = getIndexNumber();
            if (!indexNumber) return;
            var payload = {
                index_number: indexNumber,
                course_id: courseId,
                session_id: (sessionPkInput && sessionPkInput.value) ? parseInt(sessionPkInput.value, 10) : null,
                session_code: code,
                attendance_time: new Date().toISOString(),
            };
            if (latInput && latInput.value) payload.latitude = parseFloat(latInput.value);
            if (lngInput && lngInput.value) payload.longitude = parseFloat(lngInput.value);
            payload.client_meta = collectClientMeta();
            sessionCodeBtn.disabled = true;
            submitAttendance(payload).finally(function() {
                sessionCodeBtn.disabled = false;
            });
        });
    }
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
