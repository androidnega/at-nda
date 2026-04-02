import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;

if (!key) {
    console.debug('[a-tenda] WebSockets: set VITE_REVERB_APP_KEY and run the Reverb server (php artisan reverb:start).');
} else {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
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
