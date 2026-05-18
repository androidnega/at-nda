{{-- Right column hero image only (no brand overlay). --}}
@php
    $heroSrc = $heroImage ?? '';
    if (! str_starts_with($heroSrc, 'http://') && ! str_starts_with($heroSrc, 'https://')) {
        $heroSrc = asset(ltrim($heroSrc, '/'));
    }
@endphp
<section class="order-1 lg:order-2 w-full flex-none min-h-0 max-h-[22vh] sm:max-h-[26vh] lg:max-h-none lg:flex-none lg:w-[min(100%,32rem)] lg:h-[min(26rem,82vh)] mx-auto lg:mx-0 rounded-2xl border border-gray-950/60 overflow-hidden shadow-2xl shadow-black/40 ring-1 ring-white/5">
    <div class="relative h-full min-h-[4.5rem] bg-gray-950">
        <img
            src="{{ $heroSrc }}"
            alt="Lecturer using a laptop for school attendance management"
            class="absolute inset-0 w-full h-full object-cover"
            loading="{{ $heroLoading ?? 'lazy' }}"
            decoding="async"
            referrerpolicy="no-referrer-when-downgrade"
        >
        <div class="absolute inset-0 bg-gradient-to-br from-black/55 via-black/30 to-black/50 pointer-events-none" aria-hidden="true"></div>
    </div>
</section>
