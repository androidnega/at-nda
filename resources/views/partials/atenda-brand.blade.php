{{--
  a-tenda = app / system name. Use compact on follow-up steps (password, set-password).
  @param bool $compact Smaller mark + wordmark
  @param string|null $brandMb Override bottom margin (e.g. mb-4 for tighter home card)
--}}
@php
    $compact = $compact ?? false;
    $brandMb = $brandMb ?? ($compact ? 'mb-6' : 'mb-8');
@endphp
<div class="{{ $brandMb }}">
    <div class="flex items-center gap-3 sm:gap-4">
        <div
            class="{{ $compact ? 'h-10 w-10 rounded-xl text-base' : 'h-14 w-14 sm:h-16 sm:w-16 rounded-2xl text-2xl sm:text-3xl' }} flex shrink-0 items-center justify-center bg-gradient-to-br from-rose-500 to-rose-600 text-white font-bold ring-1 ring-white/10"
            aria-hidden="true"
        >a</div>
        <div class="min-w-0 flex-1">
            @if($compact)
                <p class="text-xl sm:text-2xl font-bold tracking-tight leading-tight">
                    <span class="bg-gradient-to-r from-rose-600 to-rose-500 bg-clip-text text-transparent">{{ config('app.name') }}</span>
                </p>
                <p class="mt-0.5 text-[11px] sm:text-xs text-gray-500">Digital attendance system</p>
            @else
                <p class="text-3xl sm:text-4xl font-bold tracking-tight leading-[1.1]">
                    <span class="bg-gradient-to-r from-rose-600 via-rose-500 to-rose-600 bg-clip-text text-transparent">{{ config('app.name') }}</span>
                </p>
                <p class="mt-2 text-sm text-gray-500">Digital attendance system</p>
            @endif
        </div>
    </div>
</div>
