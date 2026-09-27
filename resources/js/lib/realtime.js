import { ref } from 'vue';

/**
 * Koneksi websocket (Laravel Reverb) opsional. Bila VITE_REVERB_APP_KEY kosong, semua fungsi
 * di sini tidak melakukan apa-apa dan halaman tetap memakai polling.
 * Library Echo/Pusher dimuat terpisah (dynamic import) hanya jika websocket aktif.
 */
export const realtimeEnabled = Boolean(import.meta.env.VITE_REVERB_APP_KEY);

/** true selama websocket tersambung — halaman boleh memperlambat polling. */
export const connected = ref(false);

let echoPromise;

function echo() {
    if (!realtimeEnabled) return Promise.resolve(null);

    echoPromise ??= Promise.all([import('laravel-echo'), import('pusher-js')])
        .then(([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;
            const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
            const instance = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS: scheme === 'https',
                enabledTransports: ['ws', 'wss'],
            });

            const connection = instance.connector.pusher.connection;
            connection.bind('state_change', ({ current }) => (connected.value = current === 'connected'));

            return instance;
        })
        .catch(() => null);

    return echoPromise;
}

/**
 * Berlangganan kanal publik; `callback(payload)` dipanggil setiap event `.lot.updated`.
 * Mengembalikan fungsi untuk berhenti berlangganan.
 */
export function onLotUpdated(channel, callback) {
    let stopped = false;
    echo().then((e) => !stopped && e?.channel(channel).listen('.lot.updated', callback));

    return () => {
        stopped = true;
        echo().then((e) => e?.leave(channel));
    };
}

/** Notifikasi pribadi (kanal privat pengguna). */
export function onUserNotification(userId, callback) {
    let stopped = false;
    const channel = `App.Models.User.${userId}`;
    echo().then((e) => !stopped && e?.private(channel).notification(callback));

    return () => {
        stopped = true;
        echo().then((e) => e?.leave(channel));
    };
}
