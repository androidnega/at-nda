@extends('layouts.admin')

@section('title', 'Mobile app releases')

@section('content')
<div class="space-y-6">
    {{-- ───── Header ───── --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary">Mobile app releases</h1>
            <p class="text-gray-500 text-sm mt-1">
                Upload new Android builds. Toggle <strong>Published</strong> to expose a build to students; toggle <strong>Required</strong> to force the in-app update prompt.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($phpUploadReady))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
            <p class="font-semibold"><i class="fas fa-check-circle mr-1"></i>Server ready for APK uploads</p>
            <p class="mt-1 text-emerald-900/90">
                PHP allows uploads up to <strong>{{ $phpUploadMaxMb }} MB</strong> (post max {{ $phpPostMaxMb ?? '?' }} MB).
                Web form accepts builds up to <strong>{{ $maxUploadMb }} MB</strong>.
            </p>
        </div>
    @elseif(($phpUploadMaxMb ?? null) !== null)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i>Server upload limit is too low for APK uploads</p>
            <p class="mt-1 text-amber-900/90">
                PHP allows uploads up to <strong>{{ $phpUploadMaxMb }} MB</strong> (post max {{ $phpPostMaxMb ?? '?' }} MB).
                Current builds need at least <strong>{{ $minPhpUploadMb ?? 70 }} MB</strong> (your v1.0.4 APK is ~65 MB).
                Target: <strong>{{ $targetPhpUploadMb ?? 500 }}M</strong> / <strong>{{ ($targetPhpUploadMb ?? 500) + 20 }}M</strong> post.
            </p>
            <p class="mt-2 text-amber-900/90 text-xs">
                A 503 during upload means the web server rejected the body <em>before</em> Laravel ran — raise PHP limits first.
            </p>
            <pre class="mt-2 text-xs bg-white/80 border border-amber-200 rounded-lg px-3 py-2 overflow-x-auto">cd ~/at-enda.manuelcode.info
git pull origin phase1-throttle-only
php artisan app-releases:ensure-php-limits --write
php artisan view:clear</pre>
            <p class="mt-2 text-amber-900/90 text-xs">
                Wait ~5 minutes, then refresh. If still low, set limits in cPanel → MultiPHP INI Editor for <strong>at-enda.manuelcode.info</strong>.
            </p>
        </div>
    @endif

    @if(empty($phpUploadReady))
    <details class="rounded-2xl border border-slate-200 bg-slate-50/80 overflow-hidden group">
        <summary class="px-5 py-4 cursor-pointer text-sm font-semibold text-slate-800 list-none flex items-center justify-between">
            <span><i class="fas fa-terminal mr-1 text-slate-500"></i>Upload via PuTTY (fallback)</span>
            <i class="fas fa-chevron-down text-xs text-slate-400 group-open:rotate-180 transition"></i>
        </summary>
        <div class="px-5 pb-4 border-t border-slate-200/80">
            <p class="text-xs text-slate-600 mt-3">Upload the APK with cPanel File Manager or SFTP first, then register:</p>
            <pre class="mt-2 text-xs text-slate-800 overflow-x-auto whitespace-pre-wrap font-mono leading-relaxed bg-white rounded-lg border border-slate-200 px-3 py-2">cd ~/at-enda.manuelcode.info

php artisan app-releases:register ~/at-enda-v1.0.3-arm64.apk \
  --version-name=1.0.3 --version-code=4 \
  --notes="v1.0.3 release" --publish</pre>
        </div>
    </details>
    @endif

    {{-- ───── Upload form ───── --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-semibold text-slate-700">
                <i class="fas fa-cloud-arrow-up mr-1 text-slate-500"></i>Upload new release
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Max APK size: {{ $maxUploadMb }} MB. (platform, version code) must be unique.</p>
        </div>
        <form id="apk-upload-form" method="POST" action="{{ route('dashboard.app-releases.store') }}" enctype="multipart/form-data" class="p-5 space-y-4"
              data-php-upload-mb="{{ $phpUploadMaxMb ?? 0 }}"
              data-min-php-mb="{{ $minPhpUploadMb ?? 70 }}"
              data-max-apk-mb="{{ $maxUploadMb ?? 500 }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 block mb-1">Platform</span>
                    <select name="platform" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="android" selected>Android (APK)</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 block mb-1">Version name <span class="text-rose-500">*</span></span>
                    <input type="text" name="version_name" value="{{ old('version_name') }}" placeholder="e.g. 1.2.3" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                    <span class="text-[10px] text-slate-400 mt-1 block">User-facing label (semver style).</span>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 block mb-1">Version code <span class="text-rose-500">*</span></span>
                    <input type="number" name="version_code" value="{{ old('version_code') }}" min="1" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                    <span class="text-[10px] text-slate-400 mt-1 block">Monotonically increasing integer. Must match android/app/build.gradle versionCode.</span>
                </label>
            </div>

            <label class="block">
                <span class="text-xs font-semibold text-slate-600 block mb-1">APK file <span class="text-rose-500">*</span></span>
                <input type="file" name="apk" id="apk-file-input" accept=".apk,application/vnd.android.package-archive,application/octet-stream" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary/90" />
                <p id="apk-file-hint" class="text-[11px] mt-1.5 text-slate-500 hidden"></p>
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-slate-600 block mb-1">Release notes (optional)</span>
                <textarea name="release_notes" rows="3" placeholder="What's new in this build?" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('release_notes') }}</textarea>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-600 block mb-1">Minimum supported version code</span>
                    <input type="number" name="min_supported_version_code" value="{{ old('min_supported_version_code') }}" min="0" placeholder="e.g. 8" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                    <span class="text-[10px] text-slate-400 mt-1 block">Anything below this is force-updated even when this release isn't marked required.</span>
                </label>
                <label class="flex items-start gap-2 pt-6">
                    <input type="checkbox" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary" />
                    <span class="text-xs text-slate-700">
                        <span class="font-semibold">Publish immediately</span>
                        <span class="block text-slate-500">Visible on the public download page and to the in-app update check.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 pt-6">
                    <input type="checkbox" name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                    <span class="text-xs text-slate-700">
                        <span class="font-semibold text-rose-700">Force update</span>
                        <span class="block text-slate-500">App refuses to continue until the user installs this version.</span>
                    </span>
                </label>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-slate-500">Upload runs in the queue below — the release is saved only after the file finishes transferring.</p>
                <button type="submit" id="apk-upload-submit" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-upload"></i>Queue upload
                </button>
            </div>
        </form>

        <div id="apk-upload-queue" class="hidden border-t border-slate-100 bg-slate-50/60 px-5 py-4 space-y-3" aria-live="polite">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <i class="fas fa-list-check mr-1 text-slate-400"></i>Upload queue
                </h3>
                <span id="apk-queue-status" class="text-[11px] font-medium text-slate-500"></span>
            </div>
            <div id="apk-queue-items" class="space-y-2"></div>
        </div>
    </div>

    {{-- ───── Releases table ───── --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-semibold text-slate-700">
                <i class="fas fa-mobile-screen mr-1 text-slate-500"></i>Releases ({{ $releases->count() }})
            </h2>
        </div>
        @if($releases->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-500">
                No releases uploaded yet.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Platform</th>
                            <th class="px-4 py-2.5 text-left">Version</th>
                            <th class="px-4 py-2.5 text-left">Size</th>
                            <th class="px-4 py-2.5 text-left">Uploaded</th>
                            <th class="px-4 py-2.5 text-left">Published</th>
                            <th class="px-4 py-2.5 text-left">Required</th>
                            <th class="px-4 py-2.5 text-left">File</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($releases as $r)
                            @php $missing = ! $r->fileExists(); @endphp
                            <tr class="{{ $missing ? 'bg-amber-50/50' : '' }}">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-xs font-semibold">
                                        <i class="fab fa-android text-[10px]"></i>{{ ucfirst($r->platform) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800">v{{ $r->version_name }}</div>
                                    <div class="text-[11px] text-slate-500">code {{ $r->version_code }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $r->humanSize() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($r->released_at)->format('M d, Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('dashboard.app-releases.update', $r) }}" class="inline-flex">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_published" value="{{ $r->is_published ? 0 : 1 }}">
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border {{ $r->is_published ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $r->is_published ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            {{ $r->is_published ? 'Published' : 'Draft' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('dashboard.app-releases.update', $r) }}" class="inline-flex">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_required" value="{{ $r->is_required ? 0 : 1 }}">
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border {{ $r->is_required ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100' }}">
                                            <i class="fas {{ $r->is_required ? 'fa-circle-exclamation' : 'fa-circle' }} text-[10px]"></i>
                                            {{ $r->is_required ? 'Forced' : 'Optional' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if($missing)
                                        <span class="text-amber-700"><i class="fas fa-triangle-exclamation mr-1"></i>missing</span>
                                    @else
                                        <a href="{{ route('downloads.app.android.versioned', ['versionCode' => $r->version_code]) }}" class="text-primary hover:underline inline-flex items-center gap-1">
                                            <i class="fas fa-download"></i>{{ basename($r->apk_path) }}
                                        </a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('dashboard.app-releases.destroy', $r) }}" class="inline-flex" onsubmit="return confirm('Delete this release? The APK file will also be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-700 text-xs font-semibold">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @if($r->release_notes)
                                <tr class="bg-slate-50/40">
                                    <td colspan="8" class="px-4 py-2 text-[11px] text-slate-600">
                                        <strong class="text-slate-700">Release notes:</strong> {{ $r->release_notes }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('apk-upload-form');
    const queuePanel = document.getElementById('apk-upload-queue');
    const queueItems = document.getElementById('apk-queue-items');
    const queueStatus = document.getElementById('apk-queue-status');
    const submitBtn = document.getElementById('apk-upload-submit');
    const fileInput = document.getElementById('apk-file-input');
    const fileHint = document.getElementById('apk-file-hint');
    if (!form || !queuePanel) return;

    const phpUploadMb = parseFloat(form.dataset.phpUploadMb || '0') || 0;
    const minPhpMb = parseFloat(form.dataset.minPhpMb || '70') || 70;
    const maxApkMb = parseFloat(form.dataset.maxApkMb || '500') || 500;

    const jobs = [];
    let active = false;

    function serverLimitMessage(fileMb) {
        return 'This APK is ' + fileMb.toFixed(1) + ' MB but PHP only allows ' + phpUploadMb + ' MB. '
            + 'Raise upload_max_filesize to at least ' + minPhpMb + 'M (500M recommended) in cPanel, '
            + 'run php artisan app-releases:ensure-php-limits --write, wait 5 min, then retry.';
    }

    fileInput?.addEventListener('change', function () {
        const f = fileInput.files && fileInput.files[0];
        if (!f || !fileHint) return;
        const mb = f.size / (1024 * 1024);
        fileHint.classList.remove('hidden');
        if (mb > maxApkMb) {
            fileHint.className = 'text-[11px] mt-1.5 text-rose-600 font-medium';
            fileHint.textContent = 'File is ' + mb.toFixed(1) + ' MB — max ' + maxApkMb + ' MB for this form.';
        } else if (phpUploadMb > 0 && mb > phpUploadMb) {
            fileHint.className = 'text-[11px] mt-1.5 text-rose-600 font-medium';
            fileHint.textContent = serverLimitMessage(mb);
        } else if (phpUploadMb > 0 && phpUploadMb < minPhpMb) {
            fileHint.className = 'text-[11px] mt-1.5 text-amber-700 font-medium';
            fileHint.textContent = 'Selected: ' + mb.toFixed(1) + ' MB — server PHP limit (' + phpUploadMb + ' MB) may be too low. Upload will likely fail with 503.';
        } else {
            fileHint.className = 'text-[11px] mt-1.5 text-slate-500';
            fileHint.textContent = 'Selected: ' + mb.toFixed(1) + ' MB — ready to queue.';
        }
    });

    function humanSize(bytes) {
        if (!bytes) return '—';
        const mb = bytes / (1024 * 1024);
        return mb >= 1 ? mb.toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB';
    }

    function renderQueue() {
        if (jobs.length === 0) {
            queuePanel.classList.add('hidden');
            queueStatus.textContent = '';
            return;
        }
        queuePanel.classList.remove('hidden');
        const pending = jobs.filter(j => j.status === 'queued').length;
        const uploading = jobs.filter(j => j.status === 'uploading').length;
        const done = jobs.filter(j => j.status === 'done').length;
        const failed = jobs.filter(j => j.status === 'error').length;
        queueStatus.textContent = [
            pending ? pending + ' queued' : '',
            uploading ? uploading + ' uploading' : '',
            done ? done + ' saved' : '',
            failed ? failed + ' failed' : '',
        ].filter(Boolean).join(' · ');

        queueItems.innerHTML = jobs.map((job, idx) => {
            const pct = Math.min(100, Math.max(0, job.progress));
            const barColor = job.status === 'error' ? 'bg-rose-500'
                : (job.status === 'done' ? 'bg-emerald-500' : 'bg-primary');
            const icon = job.status === 'done' ? 'fa-check-circle text-emerald-600'
                : (job.status === 'error' ? 'fa-circle-xmark text-rose-600'
                : (job.status === 'uploading' ? 'fa-spinner fa-spin text-primary' : 'fa-clock text-slate-400'));
            const label = job.status === 'done' ? 'Saved to releases'
                : (job.status === 'error' ? (job.error || 'Upload failed')
                : (job.status === 'uploading' ? 'Uploading… ' + pct + '%' : 'Waiting…'));
            return `
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm" data-job="${idx}">
                    <div class="flex items-start gap-3">
                        <i class="fas ${icon} mt-0.5"></i>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="text-sm font-semibold text-slate-800">v${job.versionName}</span>
                                <span class="text-[11px] text-slate-500">code ${job.versionCode}</span>
                                <span class="text-[11px] text-slate-400">${humanSize(job.fileSize)}</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-0.5">${label}</p>
                            <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div class="${barColor} h-full transition-all duration-200" style="width:${pct}%"></div>
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    function processNext() {
        if (active) return;
        const job = jobs.find(j => j.status === 'queued');
        if (!job) {
            submitBtn.disabled = false;
            return;
        }
        active = true;
        job.status = 'uploading';
        renderQueue();

        const fd = new FormData(form);
        fd.set('version_name', job.versionName);
        fd.set('version_code', job.versionCode);
        fd.set('apk', job.file);
        fd.set('release_notes', job.releaseNotes);
        if (job.isPublished) fd.set('is_published', '1');
        if (job.isRequired) fd.set('is_required', '1');
        if (job.minSupported) fd.set('min_supported_version_code', job.minSupported);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.timeout = 0; // large APKs on slow links
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        const token = form.querySelector('input[name="_token"]');
        if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token.value);

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                job.progress = Math.round((e.loaded / e.total) * 100);
            } else if (job.fileSize > 0) {
                job.progress = Math.min(99, Math.round((e.loaded / job.fileSize) * 100));
            }
            renderQueue();
        });

        xhr.addEventListener('load', () => {
            active = false;
            let payload = {};
            try { payload = JSON.parse(xhr.responseText || '{}'); } catch (_) {}
            if (xhr.status >= 200 && xhr.status < 300) {
                job.status = 'done';
                job.progress = 100;
                renderQueue();
                setTimeout(() => window.location.reload(), 800);
            } else {
                job.status = 'error';
                if (xhr.status === 503 || xhr.status === 413) {
                    job.error = 'Server rejected the upload (' + xhr.status + '). PHP/web server body limit is too low — '
                        + 'set upload_max_filesize ≥ ' + minPhpMb + 'M and post_max_size ≥ ' + (minPhpMb + 5) + 'M in cPanel, '
                        + 'then run: php artisan app-releases:ensure-php-limits --write';
                } else {
                    job.error = payload.message
                        || (payload.errors && Object.values(payload.errors).flat().join(' '))
                        || ('Server error (' + xhr.status + ')');
                }
                renderQueue();
                processNext();
            }
        });

        xhr.addEventListener('error', () => {
            active = false;
            job.status = 'error';
            job.error = 'Network error or connection dropped. If the file is ~65 MB, the host may be blocking uploads over 50 MB (503).';
            renderQueue();
            processNext();
        });

        xhr.addEventListener('timeout', () => {
            active = false;
            job.status = 'error';
            job.error = 'Upload timed out. Try again or use the PuTTY register command below.';
            renderQueue();
            processNext();
        });

        job.progress = 1;
        renderQueue();
        xhr.send(fd);
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const fileInputEl = form.querySelector('input[name="apk"]');
        const file = fileInputEl && fileInputEl.files ? fileInputEl.files[0] : null;
        const versionName = (form.querySelector('input[name="version_name"]') || {}).value || '';
        const versionCode = (form.querySelector('input[name="version_code"]') || {}).value || '';
        if (!file || !versionName || !versionCode) {
            alert('Choose an APK and fill in version name and code.');
            return;
        }

        const fileMb = file.size / (1024 * 1024);
        if (fileMb > maxApkMb) {
            alert('APK is ' + fileMb.toFixed(1) + ' MB — max allowed is ' + maxApkMb + ' MB.');
            return;
        }
        if (phpUploadMb > 0 && fileMb > phpUploadMb) {
            alert(serverLimitMessage(fileMb));
            return;
        }

        jobs.push({
            file,
            fileSize: file.size,
            versionName,
            versionCode,
            releaseNotes: (form.querySelector('textarea[name="release_notes"]') || {}).value || '',
            isPublished: !!(form.querySelector('input[name="is_published"]') || {}).checked,
            isRequired: !!(form.querySelector('input[name="is_required"]') || {}).checked,
            minSupported: (form.querySelector('input[name="min_supported_version_code"]') || {}).value || '',
            status: 'queued',
            progress: 0,
            error: '',
        });

        submitBtn.disabled = true;
        fileInput.value = '';
        renderQueue();
        processNext();
    });
})();
</script>
@endpush
