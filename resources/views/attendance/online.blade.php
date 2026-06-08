@extends('layouts.app')

@section('title', 'Online attendance — ' . $course->course_name)

@section('content')
<div class="max-w-md mx-auto px-4 py-6">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-indigo-50/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">Online attendance</p>
            <h1 class="text-lg font-bold text-gray-900 mt-0.5">{{ $course->course_name }}</h1>
            <p class="text-[12px] text-gray-600 mt-0.5">
                {{ $course->course_code ? $course->course_code . ' · ' : '' }}{{ $student->index_number }}
            </p>
            <div class="mt-2 flex items-center gap-2 text-[11px] text-indigo-700">
                <i class="fas fa-clock"></i>
                <span>Session ends in <span class="font-mono font-bold tabular-nums" data-online-countdown>--:--</span></span>
            </div>
        </div>

        <div id="online-feedback" class="hidden m-4 mb-0 rounded-lg border px-3 py-2 text-[12px]" role="status" aria-live="polite"></div>

        @if($allowQr)
        <div class="p-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                <i class="fas fa-qrcode text-indigo-600 text-[13px]"></i> Upload QR screenshot
            </h2>
            <p class="text-[11px] text-gray-500 mb-3">Take a screenshot of the QR your rep posted in the lecture, then upload it here. Works fully on your device — the image never leaves your phone.</p>
            <label class="block">
                <input type="file" accept="image/*" id="online-qr-file" capture="environment"
                       class="hidden">
                <span class="inline-flex items-center justify-center w-full gap-2 rounded-lg border-2 border-dashed border-indigo-300 bg-indigo-50/40 px-4 py-6 text-sm font-semibold text-indigo-700 cursor-pointer hover:bg-indigo-50">
                    <i class="fas fa-upload"></i>
                    Choose QR image
                </span>
            </label>
            <p id="online-qr-status" class="hidden text-[11px] text-gray-600 mt-2"></p>
        </div>
        @endif

        @if($allowCode)
        <form id="online-code-form" class="p-4 space-y-3">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                <i class="fas fa-keyboard text-indigo-600 text-[13px]"></i> Or enter the session code
            </h2>
            <p class="text-[11px] text-gray-500 -mt-1">Your rep will share a code that looks like <code class="font-mono text-[10.5px] bg-gray-100 rounded px-1">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit((string) ($course->course_code ?? 'CODE'), 6, '')) }}-XXXX</code>.</p>
            <input type="text" id="online-code-input" name="session_code" required maxlength="48"
                   placeholder="e.g. {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit((string) ($course->course_code ?? 'CODE'), 6, '')) }}-1234" autocomplete="off"
                   inputmode="text"
                   style="text-transform:uppercase"
                   class="w-full text-base text-center font-mono tracking-wider border border-gray-200 rounded-lg px-3 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400">
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-indigo-800">
                <i class="fas fa-check"></i> Submit code
            </button>
        </form>
        @endif

        <div class="p-3 border-t border-gray-100 bg-gray-50/60 text-center">
            <a href="{{ route('dashboard.dashboard') }}" class="text-[12px] text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left text-[10px]"></i> Back to dashboard
            </a>
        </div>
    </div>
</div>

{{-- jsQR: small (~50KB) pure-JS QR decoder. Used to read the token out of
     the screenshot without sending the image to the server, so the entire
     QR-upload flow works fully client-side and stays private. --}}
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js" defer></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const expiresAtIso = @json(optional($expiresAt)->toIso8601String());
    const successUrl = @json(route('web.attendance.success', ['course' => $course->id]));
    const qrUrl = @json(route('web.attendance.online.qr', ['course' => $course->id]));
    const codeUrl = @json(route('web.attendance.online.code', ['course' => $course->id]));

    const feedback = document.getElementById('online-feedback');

    function notify(kind, message) {
        if (!feedback) return;
        feedback.className = 'm-4 mb-0 rounded-lg border px-3 py-2 text-[12px]';
        const tone = kind === 'error'
            ? ' border-rose-200 bg-rose-50 text-rose-800'
            : kind === 'success'
                ? ' border-emerald-200 bg-emerald-50 text-emerald-800'
                : ' border-indigo-200 bg-indigo-50 text-indigo-800';
        feedback.className += tone;
        feedback.textContent = message;
        feedback.classList.remove('hidden');
    }

    /* Countdown — flips to "Session ended" when the deadline passes; the
       backend rejects late submissions anyway, this just gives the student
       a fast visual signal so they know not to bother. */
    const countdownEl = document.querySelector('[data-online-countdown]');
    if (expiresAtIso && countdownEl) {
        const deadline = new Date(expiresAtIso).getTime();
        function tick() {
            const ms = deadline - Date.now();
            if (ms <= 0) {
                countdownEl.textContent = 'ended';
                countdownEl.classList.add('text-rose-700');
                notify('error', 'This online session has ended. Ask your rep to reopen it if you still need to mark.');
                return;
            }
            const total = Math.floor(ms / 1000);
            const m = String(Math.floor(total / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            countdownEl.textContent = m + ':' + s;
            setTimeout(tick, 1000);
        }
        tick();
    }

    async function postJson(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        });
        const data = await r.json().catch(() => ({}));
        return { ok: r.ok, status: r.status, data };
    }

    /* QR upload: read file → draw onto an offscreen canvas → run jsQR over
       the pixel buffer → POST the decoded payload string to the server. */
    const qrFile = document.getElementById('online-qr-file');
    const qrStatus = document.getElementById('online-qr-status');
    if (qrFile) {
        qrFile.addEventListener('change', async function (e) {
            const file = (e.target.files || [])[0];
            if (!file) return;
            qrStatus.textContent = 'Reading image…';
            qrStatus.classList.remove('hidden');
            try {
                const img = await readFileAsImage(file);
                const payload = decodeQrFromImage(img);
                if (!payload) {
                    qrStatus.textContent = 'No QR found in this image. Try a tighter screenshot.';
                    notify('error', 'Could not read a QR code from that image.');
                    return;
                }
                qrStatus.textContent = 'Submitting…';
                const r = await postJson(qrUrl, { qr_payload: payload });
                if (r.ok && r.data && r.data.ok) {
                    notify('success', r.data.message || 'Marked present.');
                    setTimeout(() => { window.location.href = r.data.redirect || successUrl; }, 800);
                } else {
                    qrStatus.textContent = '';
                    notify('error', (r.data && r.data.message) || 'Could not submit. Try again.');
                }
            } catch (err) {
                qrStatus.textContent = '';
                notify('error', 'Image error: ' + (err && err.message ? err.message : 'unknown'));
            }
        });
    }

    function readFileAsImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function () {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = () => reject(new Error('Could not decode image'));
                img.src = reader.result;
            };
            reader.onerror = () => reject(new Error('Could not read file'));
            reader.readAsDataURL(file);
        });
    }

    function decodeQrFromImage(img) {
        if (typeof jsQR === 'undefined') return null;
        // Cap canvas at 1024px to keep jsQR fast on phones.
        const max = 1024;
        let w = img.naturalWidth || img.width;
        let h = img.naturalHeight || img.height;
        if (w > max || h > max) {
            const scale = max / Math.max(w, h);
            w = Math.round(w * scale);
            h = Math.round(h * scale);
        }
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(img, 0, 0, w, h);
        const data = ctx.getImageData(0, 0, w, h);
        const code = jsQR(data.data, w, h, { inversionAttempts: 'attemptBoth' });
        return code ? code.data : null;
    }

    /* Manual code entry: trims, uppercases, posts. */
    const codeForm = document.getElementById('online-code-form');
    if (codeForm) {
        codeForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const codeInput = document.getElementById('online-code-input');
            const code = (codeInput.value || '').trim().toUpperCase();
            if (code.length < 3) {
                notify('error', 'Enter the full session code.');
                return;
            }
            const r = await postJson(codeUrl, { session_code: code });
            if (r.ok && r.data && r.data.ok) {
                notify('success', r.data.message || 'Marked present.');
                setTimeout(() => { window.location.href = r.data.redirect || successUrl; }, 800);
            } else {
                notify('error', (r.data && r.data.message) || 'Could not submit. Check the code and try again.');
            }
        });
    }
})();
</script>
@endsection
