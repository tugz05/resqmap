/**
 * Alarm bell for emergency events.
 *
 * Plays `/sounds/emergency.mp3` when triggered, with a persistent mute toggle
 * saved to localStorage so the operator's preference survives reloads.
 *
 * Handles two browser pitfalls:
 *   1. **Autoplay policy** — modern browsers block audio until the user has
 *      interacted with the page. We swallow the rejection silently; as soon
 *      as the operator clicks anywhere the next alarm will work.
 *   2. **Rapid re-triggers** — if three reports arrive in two seconds we
 *      restart the sound from the beginning rather than layering it, so the
 *      UX sounds like one clear alarm per event instead of a mush.
 *
 * Usage:
 *   const { play, muted, toggleMute } = useAlarmSound();
 *   channel.listen('.incident.new', () => play());
 */

import { onBeforeUnmount, ref } from 'vue';

const STORAGE_KEY = 'resqmap.alarm.muted';
const DEFAULT_SRC = '/sounds/emergency.mp3';

export function useAlarmSound(src: string = DEFAULT_SRC) {
    const muted = ref<boolean>(readMutedFromStorage());

    let audio: HTMLAudioElement | null = null;

    if (typeof Audio !== 'undefined') {
        audio = new Audio(src);
        audio.preload = 'auto';
        // Moderate volume — loud enough to catch attention in a quiet room,
        // not loud enough to shatter eardrums when the tab is refreshed.
        audio.volume = 0.9;
    }

    function play(): void {
        if (muted.value || !audio) {
return;
}

        try {
            audio.pause();
            audio.currentTime = 0;
            void audio.play().catch(() => {
                // Autoplay blocked — will succeed after the next user interaction.
            });
        } catch {
            // ignore
        }
    }

    function stop(): void {
        if (!audio) {
return;
}

        try {
            audio.pause();
            audio.currentTime = 0;
        } catch {
            // ignore
        }
    }

    function toggleMute(): void {
        muted.value = !muted.value;

        if (typeof localStorage !== 'undefined') {
            try {
                localStorage.setItem(STORAGE_KEY, muted.value ? '1' : '0');
            } catch {
                // ignore (private mode / quota exceeded)
            }
        }

        if (muted.value) {
stop();
}
    }

    onBeforeUnmount(() => {
        stop();
        audio = null;
    });

    return { play, stop, muted, toggleMute };
}

function readMutedFromStorage(): boolean {
    if (typeof localStorage === 'undefined') {
return false;
}

    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}
