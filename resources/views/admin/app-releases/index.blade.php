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

    {{-- ───── Upload form ───── --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-sm font-semibold text-slate-700">
                <i class="fas fa-cloud-arrow-up mr-1 text-slate-500"></i>Upload new release
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Max APK size: {{ $maxUploadMb }} MB. (platform, version code) must be unique.</p>
        </div>
        <form method="POST" action="{{ route('dashboard.app-releases.store') }}" enctype="multipart/form-data" class="p-5 space-y-4">
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
                <input type="file" name="apk" accept=".apk,application/vnd.android.package-archive,application/octet-stream" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary/90" />
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

            <div class="pt-2 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">
                    <i class="fas fa-upload"></i>Upload release
                </button>
            </div>
        </form>
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
