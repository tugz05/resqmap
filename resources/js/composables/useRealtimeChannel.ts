/**
 * Tiny wrapper around `window.Echo.private(...)` that makes it painless
 * to subscribe from inside a Vue component's setup() block.
 *
 * Usage:
 *   const channel = useRealtimeChannel('incident.' + id);
 *   channel?.listen('.incident.status-changed', (e) => { ... });
 *
 * Returns the channel reference (or `null` if Echo isn't available, e.g.
 * during SSR or when Pusher credentials are missing) and automatically
 * leaves the channel on unmount so you don't stack subscriptions while
 * navigating between pages.
 */

import { onBeforeUnmount } from 'vue';
import { echoClient  } from '@/echo';
import type {EchoPrivateChannel} from '@/echo';

export function useRealtimeChannel(channelName: string): EchoPrivateChannel | null {
    const echo = echoClient();

    if (!echo) {
return null;
}

    const channel = echo.private(channelName);

    onBeforeUnmount(() => {
        try {
            echo.leave(channelName);
        } catch {
            // Ignore — channel may already be torn down by a parallel navigation.
        }
    });

    return channel;
}
