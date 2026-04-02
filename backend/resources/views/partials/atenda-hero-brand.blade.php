{{-- Brand block: vertically centered on the hero image --}}
<div class="absolute inset-0 z-10 flex items-center justify-center p-5 sm:p-7 text-white pointer-events-none">
    <div class="flex items-center gap-3 sm:gap-4 max-w-sm">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg font-bold border border-white/25" aria-hidden="true">a</span>
        <div>
            <p class="text-xl sm:text-2xl font-bold tracking-tight">{{ config('app.name') }}</p>
            <p class="text-xs text-white font-medium mt-0.5">Digital attendance system</p>
        </div>
    </div>
</div>
