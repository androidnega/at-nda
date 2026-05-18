@props(['heroImage' => null, 'heroLoading' => 'lazy'])

@php
    $heroImage = $heroImage ?? \App\Support\AuthHeroImage::pathForViews();
@endphp

<div class="auth-signin-shell">
    <div class="auth-signin-grid">
        <section class="order-2 lg:order-1 w-full max-w-md mx-auto lg:mx-0 lg:w-[min(100%,22rem)] shrink-0 lg:z-20 lg:-mr-8 xl:-mr-12 max-h-[min(100%,100%)] overflow-y-auto overscroll-contain
            bg-white rounded-2xl border border-gray-200/90 flex flex-col justify-center py-4 px-5 sm:px-6 sm:py-5">
            {{ $slot }}
        </section>

        @include('partials.auth-hero-panel', [
            'heroImage' => $heroImage,
            'heroLoading' => $heroLoading,
        ])
    </div>
</div>
