/**
 * Laravel Echo + Pusher bootstrap.
 *
 * Gives every Vue page a global `window.Echo` client so they can subscribe
 * to private channels (incident.{ulid}, admin.incidents, rescuer.{id})
 * for real-time incident, assignment, and GPS updates.
 *
 * Channel authentication rides on the regular session cookie via
 * `POST /broadcasting/auth`; no extra tokens required.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'>;
    }
}

/**
 * Minimal, chainable channel shape shared across the app. Real Laravel Echo
 * channels support a lot more, but our Vue pages only need `.listen()`.
 */
export type EchoPrivateChannel = {
    listen(event: string, cb: (payload: unknown) => void): EchoPrivateChannel;
    stopListening(event: string): EchoPrivateChannel;
};

type EchoLike = {
    private(channel: string): EchoPrivateChannel;
    leave(channel: string): void;
};

const key = import.meta.env.VITE_PUSHER_APP_KEY as string | undefined;
const cluster = (import.meta.env.VITE_PUSHER_APP_CLUSTER as string | undefined) ?? 'mt1';
const host = import.meta.env.VITE_PUSHER_HOST as string | undefined;
const port = Number(import.meta.env.VITE_PUSHER_PORT ?? 443);
const scheme = (import.meta.env.VITE_PUSHER_SCHEME as string | undefined) ?? 'https';

export function bootstrapEcho(): void {
    // Don't crash the app if the dev didn't add Pusher keys yet — just skip
    // real-time and let the polling fallback keep things working.
    if (!key) {
        console.warn('[echo] VITE_PUSHER_APP_KEY is missing; real-time updates disabled.');

        return;
    }

    window.Pusher = Pusher;

    const tokenMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: scheme === 'https',
        ...(host ? { wsHost: host, wssHost: host } : {}),
        wsPort: port,
        wssPort: port,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Accept: 'application/json',
                ...(tokenMeta ? { 'X-CSRF-TOKEN': tokenMeta.content } : {}),
            },
        },
    });
}

export function isEchoReady(): boolean {
    return typeof window !== 'undefined' && 'Echo' in window && !!window.Echo;
}

/**
 * Typed accessor for `window.Echo` so Vue pages get IntelliSense and don't
 * have to cast around `unknown`. Returns `null` when Pusher credentials are
 * missing or during SSR.
 */
export function echoClient(): EchoLike | null {
    if (!isEchoReady()) {
return null;
}

    return window.Echo as unknown as EchoLike;
}
