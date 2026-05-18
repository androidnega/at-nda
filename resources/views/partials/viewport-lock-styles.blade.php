{{-- Lock document to viewport: no page scroll; no zoom (mobile meta + desktop JS). --}}
<style id="viewport-lock-styles">
html.app-viewport-lock {
    height: 100%;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    width: 100%;
    position: fixed;
    inset: 0;
    touch-action: manipulation;
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
}
html.app-viewport-lock body {
    height: 100%;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    width: 100%;
    overscroll-behavior: none;
    touch-action: manipulation;
}
.app-dashboard-shell {
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    width: 100%;
}
.app-dashboard-main {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
}
.auth-signin-shell {
    height: 100%;
    height: 100dvh;
    max-height: 100dvh;
    width: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem;
    box-sizing: border-box;
}
@media (min-width: 640px) {
    .auth-signin-shell { padding: 1.25rem; }
}
@media (min-width: 1024px) {
    .auth-signin-shell { padding: 2rem; }
}
.auth-signin-grid {
    width: 100%;
    max-width: 64rem;
    height: 100%;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    min-height: 0;
    overflow: hidden;
}
@media (min-width: 1024px) {
    .auth-signin-grid {
        flex-direction: row;
        gap: 0;
    }
}
</style>
<script>
(function () {
    var root = document.documentElement;
    if (!root.classList.contains('app-viewport-lock')) return;

    var zoomKeys = { '+': 1, '-': 1, '=': 1, '_': 1, '0': 1, Add: 1, Subtract: 1, Equal: 1, NumpadAdd: 1, NumpadSubtract: 1 };

    function preventZoom(e) {
        if (e.ctrlKey || e.metaKey) e.preventDefault();
    }

    function preventZoomKey(e) {
        if (!(e.ctrlKey || e.metaKey)) return;
        if (zoomKeys[e.key]) e.preventDefault();
    }

    function preventGesture(e) {
        e.preventDefault();
    }

    var opts = { passive: false, capture: true };
    window.addEventListener('wheel', preventZoom, opts);
    document.addEventListener('wheel', preventZoom, opts);
    document.addEventListener('keydown', preventZoomKey, opts);
    document.addEventListener('gesturestart', preventGesture, opts);
    document.addEventListener('gesturechange', preventGesture, opts);
    document.addEventListener('gestureend', preventGesture, opts);
})();
</script>
