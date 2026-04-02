{{-- Right column: same image, overlay, and centered brand as home sign-in (expects $heroImage; optional $heroLoading eager|lazy) --}}
<section class="order-1 lg:order-2 w-full lg:w-[min(100%,32rem)] lg:max-w-[32rem] lg:flex-none mx-auto lg:mx-0 min-h-[200px] sm:min-h-[240px] lg:min-h-[min(85vh,28rem)] rounded-2xl border border-gray-900/15 overflow-hidden">
    <div class="relative h-full min-h-[200px] sm:min-h-[240px] lg:min-h-[min(85vh,28rem)]">
        <img
            src="{{ $heroImage }}"
            alt=""
            class="absolute inset-0 w-full h-full object-cover"
            loading="{{ $heroLoading ?? 'lazy' }}"
            decoding="async"
            referrerpolicy="no-referrer-when-downgrade"
        >
        <div class="absolute inset-0 bg-black/58 pointer-events-none" aria-hidden="true"></div>
        @include('partials.atenda-hero-brand')
    </div>
</section>
