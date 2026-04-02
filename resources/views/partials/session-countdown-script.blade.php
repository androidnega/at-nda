<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function formatRemaining(ms) {
        if (ms <= 0) return null;
        var s = Math.floor(ms / 1000);
        var h = Math.floor(s / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;
        if (h > 0) return h + ':' + pad(m) + ':' + pad(sec);
        return m + ':' + pad(sec);
    }
    function tick(el) {
        var raw = el.getAttribute('data-expires');
        if (!raw) return;
        var end = new Date(raw).getTime();
        var now = Date.now();
        var rem = end - now;
        var display = el.querySelector('[data-countdown-display]');
        var label = el.querySelector('[data-countdown-label]');
        if (!display) return;
        if (rem <= 0) {
            display.textContent = '00:00';
            display.classList.add('text-rose-300');
            if (label) label.textContent = 'Session ended';
            return;
        }
        var txt = formatRemaining(rem);
        display.textContent = txt || '—';
        if (rem < 5 * 60 * 1000 && label) {
            label.textContent = 'Ending soon';
            display.classList.add('animate-pulse');
        }
    }
    document.querySelectorAll('[data-session-countdown]').forEach(function (el) {
        tick(el);
        setInterval(function () { tick(el); }, 1000);
    });
})();
</script>
