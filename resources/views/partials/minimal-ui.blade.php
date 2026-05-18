{{-- Flat, minimal surfaces: no box shadows, no CSS animations, no transitions --}}
<style id="minimal-ui-global">
[class*="shadow-sm"]:not([class*="drop-shadow"]),
[class*="shadow-md"]:not([class*="drop-shadow"]),
[class*="shadow-lg"]:not([class*="drop-shadow"]),
[class*="shadow-xl"]:not([class*="drop-shadow"]),
[class*="shadow-2xl"]:not([class*="drop-shadow"]),
[class*="shadow-inner"]:not([class*="drop-shadow"]),
[class*="shadow-["]:not([class*="drop-shadow"]) {
    box-shadow: none !important;
}
[class*="animate-"] {
    animation: none !important;
}
.transition-all, .transition, .transition-colors, .transition-opacity, .transition-shadow, .transition-transform,
[class*="duration-75"], [class*="duration-100"], [class*="duration-150"], [class*="duration-200"], [class*="duration-300"], [class*="duration-500"] {
    transition: none !important;
}
.sidebar-transition {
    transition: none !important;
}
.backdrop-blur-sm, .backdrop-blur, .backdrop-blur-md, .backdrop-blur-lg, [class*="backdrop-blur-"] {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}
.active\:scale-95:active {
    transform: none !important;
}
html {
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
}
body {
    touch-action: manipulation;
    overscroll-behavior: none;
}
</style>
