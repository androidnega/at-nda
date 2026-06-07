import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;

// Gate the WebSocket connection on a *valid* Reverb host. On shared
// hosting (cPanel) there's no Reverb process running, the build still
// embeds the dev env value "localhost", and every page would otherwise
// spam the console with "WebSocket connection to wss://localhost:8080
// failed" errors. We skip the Echo init when:
//   * no key is configured at build time, OR
//   * the configured host is loopback but the page itself is on a
//     real public domain (clear sign of dev env baked into prod).
const pageHost = typeof window !== 'undefined' ? window.location.hostname : '';
const configuredHost = (import.meta.env.VITE_REVERB_HOST || '').trim();
const isLoopback = (h) => h === 'localhost' || h === '127.0.0.1' || h === '0.0.0.0' || h === '::1';
const looksLikeProdServingDevReverb = configuredHost && isLoopback(configuredHost) && !isLoopback(pageHost);

if (!key) {
    console.debug('[a-tenda] WebSockets: set VITE_REVERB_APP_KEY and run the Reverb server (php artisan reverb:start).');
} else if (looksLikeProdServingDevReverb) {
    // Don't try to talk to wss://localhost:8080 from a public page —
    // that always fails and just pollutes the browser console.
    console.debug('[a-tenda] WebSockets: VITE_REVERB_HOST is loopback while the page is on ' + pageHost + ' — skipping Echo init.');
} else {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const host = configuredHost || pageHost;
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    const sessionMeta = document.querySelector('meta[name="session-live-id"]');
    if (sessionMeta && sessionMeta.content) {
        const sid = sessionMeta.content;
        window.Echo.channel('attendance.session.' + sid).listen('.session.live', function (e) {
            if (e.action === 'attendance_marked' && typeof e.present_count === 'number') {
                const el = document.getElementById('qr-scanned-count');
                if (el) {
                    el.textContent = String(e.present_count);
                }
            }
            if (e.action === 'session_closed') {
                window.location.reload();
            }
        });
    }

    const classMeta = document.querySelector('meta[name="broadcast-class-id"]');
    if (classMeta && classMeta.content) {
        let reloadTimer = null;
        window.Echo.channel('class.' + classMeta.content + '.attendance').listen('.session.live', function () {
            if (reloadTimer) {
                return;
            }
            reloadTimer = setTimeout(function () {
                reloadTimer = null;
                window.location.reload();
            }, 1200);
        });
    }
}
