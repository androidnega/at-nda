{{-- Lock document to viewport: no page scroll, no pinch-zoom (with viewport meta). --}}
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
}
html.app-viewport-lock body {
    height: 100%;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
    width: 100%;
    overscroll-behavior: none;
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
