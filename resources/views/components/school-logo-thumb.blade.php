@props([
    'url' => null,
    'dataUri' => null,
    'name' => '',
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'w-10 h-10',
        'lg' => 'h-16 w-16',
        default => 'w-12 h-12',
    };
    $imgSrc = $dataUri ?? $url;
    $hasImg = filled($imgSrc);
@endphp

<div {{ $attributes->merge(['class' => "$sizeClass rounded-xl border border-gray-200 bg-gray-50 flex-shrink-0 overflow-hidden relative"]) }}>
    @if($hasImg)
        <img
            src="{{ $imgSrc }}"
            alt="{{ $name !== '' ? $name . ' logo' : 'School logo' }}"
            class="school-logo-thumb-img w-full h-full object-contain bg-white p-0.5"
            loading="lazy"
            decoding="async"
        >
    @endif
    <span class="school-logo-thumb-fallback absolute inset-0 flex items-center justify-center bg-primary/10 text-primary {{ $hasImg ? 'hidden' : '' }}" aria-hidden="true">
        <i class="fas fa-university text-lg"></i>
    </span>
</div>
