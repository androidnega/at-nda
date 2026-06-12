@extends('layouts.home')

@section('title', 'Download the app — '.config('app.name'))

@section('content')
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-xl">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to sign in
        </a>

        <div class="rounded-3xl bg-white border border-slate-200 shadow-xl overflow-hidden">
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 px-7 py-8 text-white">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-sm">
                        <i class="fab fa-android text-2xl"></i>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold leading-tight">Get the mobile app</h1>
                        <p class="text-emerald-50/90 text-sm mt-0.5">Mark attendance and view your weekly grid on your phone.</p>
                    </div>
                </div>
            </div>

            <div class="px-7 py-7">
                @if($latest)
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Latest version</p>
                            <p class="text-3xl font-extrabold text-slate-900 leading-none mt-1">v{{ $latest->version_name }}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ $latest->humanSize() }}
                                @if($latest->released_at)
                                    &middot; Released {{ $latest->released_at->format('M d, Y') }}
                                @endif
                            </p>
                        </div>
                        @if($latest->is_required)
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 text-rose-700 px-3 py-1 text-xs font-bold border border-rose-200">
                                <i class="fas fa-circle-exclamation"></i> Required update
                            </span>
                        @endif
                    </div>

                    @if($latest->release_notes)
                        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 mb-5">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold mb-1">What's new</p>
                            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $latest->release_notes }}</p>
                        </div>
                    @endif

                    <a href="{{ route('downloads.app.android.latest') }}"
                       class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-4 text-white font-bold text-base hover:from-emerald-700 hover:to-emerald-800 shadow-lg shadow-emerald-500/20 transition">
                        <i class="fas fa-download"></i>
                        Download APK
                    </a>

                    <details class="mt-5 text-xs text-slate-500">
                        <summary class="cursor-pointer hover:text-slate-700 font-semibold">Installation tips</summary>
                        <ol class="mt-2 pl-5 list-decimal space-y-1.5 leading-relaxed">
                            <li>Open the downloaded APK file from your <strong>Downloads</strong> folder.</li>
                            <li>If Android blocks the install, tap <strong>Settings</strong> &rarr; allow installs from this browser, then tap the file again.</li>
                            <li>Sign in with your student ID once the app installs.</li>
                        </ol>
                        @if($latest->apk_sha256)
                            <p class="mt-3 text-[10px] text-slate-400 break-all"><strong>SHA-256:</strong> {{ $latest->apk_sha256 }}</p>
                        @endif
                    </details>
                @else
                    <div class="text-center py-10">
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                            <i class="fas fa-mobile-screen text-2xl"></i>
                        </span>
                        <p class="text-base font-semibold text-slate-700">No build available right now</p>
                        <p class="text-sm text-slate-500 mt-1">An admin will publish the latest APK here soon. Check back shortly.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
