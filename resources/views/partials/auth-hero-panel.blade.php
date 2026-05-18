{{-- Right column: dark-themed hero (expects $heroImage; optional $heroLoading eager|lazy) --}}
@php
    $heroSrc = $heroImage ?? '';
    if (! str_starts_with($heroSrc, 'http://') && ! str_starts_with($heroSrc, 'https://')) {
        $heroSrc = asset(ltrim($heroSrc, '/'));
    }
@endphp
<section class="order-1 lg:order-2 w-full lg:w-[min(100%,32rem)] lg:max-w-[32rem] lg:flex-none mx-auto lg:mx-0 min-h-[200px] sm:min-h-[240px] lg:min-h-[min(85vh,28rem)] rounded-2xl border border-gray-950/60 overflow-hidden shadow-2xl shadow-black/40 ring-1 ring-white/5">
    <div class="relative h-full min-h-[200px] sm:min-h-[240px] lg:min-h-[min(85vh,28rem)] bg-gray-950">
        <img
            src="{{ $heroSrc }}"
            alt="Lecturer using a laptop for school attendance management"
            class="absolute inset-0 w-full h-full object-cover"
            loading="{{ $heroLoading ?? 'lazy' }}"
            decoding="async"
            referrerpolicy="no-referrer-when-downgrade"
        >
        <div class="absolute inset-0 bg-gradient-to-br from-black/55 via-black/30 to-black/50 pointer-events-none" aria-hidden="true"></div>
        @include('partials.atenda-hero-brand')
    </div>
</section>
