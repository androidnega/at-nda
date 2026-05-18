{{-- Right column hero image only (no brand overlay). --}}
@php
    $heroSrc = $heroImage ?? '';
    if (! str_starts_with($heroSrc, 'http://') && ! str_starts_with($heroSrc, 'https://')) {
        $heroSrc = asset(ltrim($heroSrc, '/'));
    }
@endphp
<section class="order-1 lg:order-2 flex-none shrink-0 w-full max-w-md aspect-[3/2] max-h-[30vh] sm:max-h-[34vh] mx-auto lg:aspect-auto lg:max-h-none lg:max-w-none lg:flex-none lg:w-[min(100%,32rem)] lg:h-[min(26rem,82vh)] lg:mx-0 rounded-2xl border border-gray-950/60 overflow-hidden shadow-2xl shadow-black/40 ring-1 ring-white/5">
    <div class="relative w-full h-full min-h-[9rem] sm:min-h-[10rem] lg:min-h-0 bg-gray-950">
        <img
            src="{{ $heroSrc }}"
            alt="Lecturer using a laptop for school attendance management"
            class="absolute inset-0 w-full h-full object-cover object-center"
            loading="{{ $heroLoading ?? 'lazy' }}"
            decoding="async"
            referrerpolicy="no-referrer-when-downgrade"
        >
        <div class="absolute inset-0 bg-gradient-to-br from-black/25 via-black/10 to-black/30 pointer-events-none" aria-hidden="true"></div>
    </div>
</section>
