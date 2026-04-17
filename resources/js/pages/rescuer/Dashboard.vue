<script setup lang="ts">
/**
 * Rescuer Mission Console — mobile-first, map-prioritized (Grab driver style).
 *
 * Layout:
 *   ┌ Top bar: online toggle, refresh, stats pill ─────────────┐
 *   │                                                            │
 *   │   F U L L - B L E E D   L I V E   T R A C K I N G         │
 *   │   (incident pin + reporter pin + my pulsing pin)          │
 *   │                                                            │
 *   │   ↳ auto-centers and auto-zooms                            │
 *   │                                                            │
 *   │  [Floating: re-center]  [Floating: other missions ▼]      │
 *   ├─ Bottom sheet ────────────────────────────────────────────┤
 *   │  Mission summary + timeline                                │
 *   │  [BIG ACCEPT / ARRIVED / COMPLETE button]                 │
 *   │  [Call] [Open in Google Maps] [Expand brief]              │
 *   └────────────────────────────────────────────────────────────┘
 *
 * The sheet can be collapsed (just the action button visible) or expanded
 * (full AI brief + timeline + other missions queue). The map is always
 * dominant — the rescuer never loses sight of where they're going.
 */

import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BellRing,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock3,
    Flag,
    LogOut,
    MapPin,
    Menu,
    Navigation,
    Phone,
    RefreshCw,
    Sparkles,
    User,
    Volume2,
    VolumeX,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import RescuerAssignmentController from '@/actions/App/Http/Controllers/Rescuer/AssignmentController';
import LiveTrackingMap from '@/components/tracking/LiveTrackingMap.vue';
import { Button } from '@/components/ui/button';
import { useAlarmSound } from '@/composables/useAlarmSound';
import { echoClient } from '@/echo';
import { logout } from '@/routes';

type LatLng = { latitude: number; longitude: number } | null | undefined;

type AssignmentCard = {
    assignment_id: number;
    status: string;
    status_label: string;
    assigned_at?: string;
    notes?: string;
    assigner?: { name?: string };
    incident: {
        id: string;
        type: string;
        type_icon: string;
        title: string;
        severity: string;
        severity_label: string;
        description?: string;
        location: { latitude: number; longitude: number; address?: string; barangay?: string; city?: string };
        reporter?: { name?: string; contact?: string | null };
        reporter_location?: LatLng;
    };
    ai_mission_brief?: string | null;
};

const props = defineProps<{
    assignments: AssignmentCard[];
    overview: {
        total_assignments: number;
        active: number;
        assigned: number;
        on_scene: number;
    };
}>();

const page = usePage<{ auth: { user: { id: number } } }>();
const userId = computed(() => page.props.auth.user.id);

// Position of the resident (reporter) we're currently heading to. Updated in
// real time via Pusher whenever the resident's phone pings a new GPS fix.
const reporterLivePosition = ref<{ latitude: number; longitude: number } | null>(null);

// ─── State ──────────────────────────────────────────────────────────────────
const activeCards = computed(() =>
    props.assignments.filter((card) => !['completed', 'cancelled'].includes(card.status)),
);
const completedToday = computed(
    () =>
        props.assignments.filter(
            (card) =>
                card.status === 'completed' &&
                card.assigned_at &&
                new Date(card.assigned_at).toDateString() === new Date().toDateString(),
        ).length,
);

const isOnline = ref(true);
const selectedId = ref<number | null>(activeCards.value[0]?.assignment_id ?? null);
const selectedMission = computed(
    () => activeCards.value.find((c) => c.assignment_id === selectedId.value) ?? activeCards.value[0] ?? null,
);

// Bottom sheet expansion. On mobile this controls whether the sheet covers
// ~35% or ~75% of the viewport. On desktop it just toggles extra panels.
const sheetExpanded = ref(false);
const menuOpen = ref(false);

type NextStep = { status: string; label: string; mobileLabel: string; emoji: string };
const statusFlow: Record<string, NextStep | null> = {
    assigned: { status: 'accepted', label: 'Accept Mission', mobileLabel: 'Accept', emoji: '👍' },
    accepted: { status: 'en_route', label: 'Start Driving', mobileLabel: 'Drive', emoji: '🚗' },
    en_route: { status: 'on_scene', label: 'I Have Arrived', mobileLabel: 'Arrived', emoji: '📍' },
    on_scene: { status: 'completed', label: 'Complete Mission', mobileLabel: 'Done', emoji: '✅' },
    completed: null,
    cancelled: null,
};

const severityPalette: Record<string, string> = {
    low: 'from-sky-500 to-sky-600',
    medium: 'from-amber-500 to-orange-500',
    high: 'from-orange-500 to-red-500',
    critical: 'from-rose-600 to-red-700',
};

const severityRing: Record<string, string> = {
    low: 'ring-sky-400',
    medium: 'ring-amber-400',
    high: 'ring-orange-400',
    critical: 'ring-rose-500',
};

const statusPill: Record<string, string> = {
    assigned: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    accepted: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    en_route: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
    on_scene: 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300',
    completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    cancelled: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const missionTimeline = computed(() => {
    if (!selectedMission.value) {
return [];
}

    const steps = [
        { key: 'assigned', label: 'New' },
        { key: 'accepted', label: 'Accepted' },
        { key: 'en_route', label: 'En Route' },
        { key: 'on_scene', label: 'On Scene' },
        { key: 'completed', label: 'Done' },
    ];
    const currentIdx = steps.findIndex((s) => s.key === selectedMission.value?.status);

    return steps.map((step, idx) => ({
        ...step,
        active: idx <= currentIdx,
        current: idx === currentIdx,
    }));
});

function timeAgo(iso?: string | null): string {
    if (!iso) {
return '—';
}

    const diff = Math.max(0, Date.now() - new Date(iso).getTime());
    const mins = Math.floor(diff / 60000);

    if (mins < 1) {
return 'just now';
}

    if (mins < 60) {
return `${mins}m ago`;
}

    const hrs = Math.floor(mins / 60);

    if (hrs < 24) {
return `${hrs}h ago`;
}

    return `${Math.floor(hrs / 24)}d ago`;
}

function refresh(): void {
    router.reload({ only: ['assignments', 'overview'] });
}

// ─── Live location push + polling (Grab driver behaviour) ───────────────────
const currentGps = ref<{ latitude: number; longitude: number } | null>(null);

let geoWatchId: number | null = null;
let locationPushTimer: ReturnType<typeof setInterval> | null = null;
let refreshTimer: ReturnType<typeof setInterval> | null = null;

async function pushLocation(lat: number, lng: number): Promise<void> {
    try {
        const tokenMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        await fetch('/location/ping', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(tokenMeta ? { 'X-CSRF-TOKEN': tokenMeta.content } : {}),
            },
            body: JSON.stringify({ latitude: lat, longitude: lng }),
        });
    } catch {
        /* silent */
    }
}

// ─── Pusher subscriptions (real-time updates) ───────────────────────────────
// We subscribe to:
//   - rescuer.{id}                      : dispatched a new mission.
//   - incident.{ulid}                   : status/assignment changes on the current mission.
//   - incident.{ulid}.reporter          : resident's live GPS while we're en route.
//
// Polling is only a fallback (30s) for the case where Pusher drops the socket.
let currentIncidentChannel: string | null = null;
let currentReporterChannel: string | null = null;

function reloadAssignments(): void {
    router.reload({ only: ['assignments', 'overview'] });
}

// Mission alarm — loud "emergency" chime the moment the AI Dispatch Agent
// routes a new incident to this rescuer. Same sound the admin hears on
// new reports, so the two sides stay in lockstep.
const { play: playAlarm, muted: alarmMuted, toggleMute: toggleAlarmMute } = useAlarmSound();

function subscribeToRescuerFeed(): void {
    const echo = echoClient();

    if (!echo) {
        return;
    }

    echo.private(`rescuer.${userId.value}`)
        .listen('.incident.rescuer-assigned', () => {
            // Brand new dispatch landed on our feed — alert + reload.
            playAlarm();
            reloadAssignments();
        });
}

function subscribeToCurrentMission(): void {
    const echo = echoClient();

    if (!echo) {
return;
}

    const ulid = selectedMission.value?.incident.id;

    if (!ulid) {
return;
}

    // Tear down the previous mission's channels first.
    if (currentIncidentChannel && currentIncidentChannel !== `incident.${ulid}`) {
        try {
 echo.leave(currentIncidentChannel); 
} catch { /* ignore */ }
    }

    if (currentReporterChannel && currentReporterChannel !== `incident.${ulid}.reporter`) {
        try {
 echo.leave(currentReporterChannel); 
} catch { /* ignore */ }

        reporterLivePosition.value = null;
    }

    currentIncidentChannel = `incident.${ulid}`;
    currentReporterChannel = `incident.${ulid}.reporter`;

    echo.private(currentIncidentChannel)
        .listen('.incident.status-changed', () => reloadAssignments())
        .listen('.incident.assignment-status-changed', () => reloadAssignments())
        .listen('.incident.rescuer-assigned', () => reloadAssignments());

    echo.private(currentReporterChannel)
        .listen('.user.location-moved', (payload: unknown) => {
            const p = payload as { latitude?: number; longitude?: number };

            if (typeof p.latitude === 'number' && typeof p.longitude === 'number') {
                reporterLivePosition.value = { latitude: p.latitude, longitude: p.longitude };
            }
        });
}

watch(
    () => selectedMission.value?.incident.id,
    (newUlid, oldUlid) => {
        if (newUlid === oldUlid) {
return;
}

        reporterLivePosition.value = null;
        subscribeToCurrentMission();
    },
);

onMounted(() => {
    if (typeof navigator !== 'undefined' && 'geolocation' in navigator) {
        geoWatchId = navigator.geolocation.watchPosition(
            (pos) => {
                currentGps.value = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                };
            },
            () => { /* ignore */ },
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 8000 },
        );
    }

    locationPushTimer = setInterval(() => {
        if (!isOnline.value) {
return;
}

        if (!currentGps.value) {
return;
}

        if (activeCards.value.length === 0) {
return;
}

        pushLocation(currentGps.value.latitude, currentGps.value.longitude);
    }, 5000);

    // Fallback polling — only every 30s, Pusher is primary.
    refreshTimer = setInterval(() => {
        if (document.visibilityState !== 'visible') {
return;
}

        reloadAssignments();
    }, 30000);

    subscribeToRescuerFeed();
    subscribeToCurrentMission();
});

onBeforeUnmount(() => {
    if (geoWatchId !== null && typeof navigator !== 'undefined' && 'geolocation' in navigator) {
        navigator.geolocation.clearWatch(geoWatchId);
    }

    if (locationPushTimer) {
clearInterval(locationPushTimer);
}

    if (refreshTimer) {
clearInterval(refreshTimer);
}

    const echo = echoClient();

    if (echo) {
        try {
 echo.leave(`rescuer.${userId.value}`); 
} catch { /* ignore */ }

        if (currentIncidentChannel) {
 try {
 echo.leave(currentIncidentChannel); 
} catch { /* ignore */ } 
}

        if (currentReporterChannel) {
 try {
 echo.leave(currentReporterChannel); 
} catch { /* ignore */ } 
}
    }
});

function openMapsTo(): string {
    const loc = selectedMission.value?.incident.location;

    if (!loc) {
return '#';
}

    return `https://www.google.com/maps/dir/?api=1&destination=${loc.latitude},${loc.longitude}&travelmode=driving`;
}

const otherMissions = computed(() =>
    activeCards.value.filter((c) => c.assignment_id !== selectedMission.value?.assignment_id),
);
</script>

<template>
    <Head title="Rescuer · Mission Console" />

    <!-- Full-viewport layout: top bar + map + bottom sheet -->
    <div class="fixed inset-0 flex flex-col bg-slate-900 text-slate-100 overflow-hidden">

        <!-- ═══ TOP BAR (compact on mobile) ═════════════════════════════════ -->
        <header class="relative z-[600] flex shrink-0 items-center justify-between gap-2 bg-slate-950/95 px-3 py-2.5 ring-1 ring-white/5 backdrop-blur-md sm:px-5 sm:py-3">
            <div class="flex min-w-0 items-center gap-2">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-rose-600 shadow-lg shadow-red-500/30 sm:h-10 sm:w-10">
                    <Flag class="h-4 w-4 sm:h-5 sm:w-5" />
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-sm font-bold tracking-tight sm:text-base">
                        Mission Console
                    </h1>
                    <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                        <span class="relative flex h-1.5 w-1.5">
                            <span v-if="isOnline" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="isOnline ? 'bg-emerald-400' : 'bg-slate-500'" />
                        </span>
                        <span class="font-medium" :class="isOnline ? 'text-emerald-400' : 'text-slate-400'">
                            {{ isOnline ? 'Online' : 'Offline' }}
                        </span>
                        <span class="text-slate-600">·</span>
                        <span>{{ overview.active }} active</span>
                        <span class="hidden text-slate-600 sm:inline">·</span>
                        <span class="hidden sm:inline">{{ completedToday }} done today</span>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <!-- Online toggle -->
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-full transition active:scale-95"
                    :class="isOnline
                        ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/40'
                        : 'bg-slate-800 text-slate-400 ring-1 ring-slate-700'"
                    @click="isOnline = !isOnline"
                    :aria-label="isOnline ? 'Go offline' : 'Go online'"
                >
                    <BellRing class="h-4 w-4" />
                </button>
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-full transition active:scale-95"
                    :class="alarmMuted
                        ? 'bg-slate-800 text-slate-500 ring-1 ring-slate-700'
                        : 'bg-rose-500/15 text-rose-400 ring-1 ring-rose-500/40'"
                    @click="toggleAlarmMute"
                    :title="alarmMuted ? 'Emergency alarm is muted — click to unmute' : 'Emergency alarm is on — click to mute'"
                    :aria-label="alarmMuted ? 'Unmute alarm' : 'Mute alarm'"
                >
                    <VolumeX v-if="alarmMuted" class="h-4 w-4" />
                    <Volume2 v-else class="h-4 w-4" />
                </button>
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 text-slate-300 transition active:scale-95"
                    @click="refresh"
                    aria-label="Refresh"
                >
                    <RefreshCw class="h-4 w-4" />
                </button>
                <button
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 text-slate-300 transition active:scale-95"
                    @click="menuOpen = !menuOpen"
                    aria-label="Menu"
                >
                    <Menu class="h-4 w-4" />
                </button>
            </div>

            <!-- Slide-down menu -->
            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div
                    v-if="menuOpen"
                    class="absolute right-3 top-full mt-1 w-48 overflow-hidden rounded-xl bg-slate-900 shadow-2xl ring-1 ring-white/10"
                >
                    <Link
                        :href="logout()"
                        as="button"
                        class="flex w-full items-center gap-2 px-3 py-2.5 text-sm text-rose-300 transition hover:bg-rose-500/10"
                    >
                        <LogOut class="h-4 w-4" />
                        Log out
                    </Link>
                </div>
            </transition>
        </header>

        <!-- ═══ MAP (dominant real-estate) ══════════════════════════════════ -->
        <div class="relative flex-1 overflow-hidden">
            <LiveTrackingMap
                v-if="selectedMission"
                :key="selectedMission.assignment_id"
                :incident="{
                    latitude: selectedMission.incident.location.latitude,
                    longitude: selectedMission.incident.location.longitude,
                    title: selectedMission.incident.title,
                    type_icon: selectedMission.incident.type_icon,
                }"
                :reporter="reporterLivePosition ?? selectedMission.incident.reporter_location"
                :rescuer="currentGps"
                rescuer-name="You"
                :rescuer-status="selectedMission.status_label"
                flush
            />

            <!-- No active mission (map is a standby screen) -->
            <div
                v-else
                class="flex h-full flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-950 to-emerald-950/30 px-6 text-center"
            >
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500/10 ring-4 ring-emerald-500/20">
                    <CheckCircle2 class="h-10 w-10 text-emerald-400" />
                </div>
                <h2 class="text-xl font-bold text-white">You're all caught up!</h2>
                <p class="mt-2 max-w-xs text-sm text-slate-400">
                    No active missions right now. Stay online — dispatches will show up here instantly.
                </p>
                <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-white/5 px-3 py-1.5 text-xs font-semibold text-emerald-400 ring-1 ring-white/10">
                    <Activity class="h-3.5 w-3.5" />
                    Waiting for dispatch
                </div>
            </div>

            <!-- Floating severity banner (mobile-visible, doesn't cover the map) -->
            <div
                v-if="selectedMission"
                class="pointer-events-none absolute left-3 right-3 top-3 z-[600] flex justify-center"
            >
                <div
                    class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-white shadow-lg shadow-black/30 ring-1 ring-white/20 backdrop-blur-sm"
                    :class="`bg-gradient-to-r ${severityPalette[selectedMission.incident.severity] ?? 'from-slate-600 to-slate-700'}`"
                >
                    <Zap class="h-3.5 w-3.5" />
                    <span class="text-[10px] font-bold uppercase tracking-widest">
                        {{ selectedMission.incident.severity_label }}
                    </span>
                    <span class="h-1 w-1 rounded-full bg-white/60" />
                    <span class="text-[10px] font-bold">{{ selectedMission.status_label }}</span>
                </div>
            </div>

            <!-- Other missions pill (top-right, collapsible) -->
            <div
                v-if="otherMissions.length > 0"
                class="absolute right-3 top-14 z-[600] flex flex-col items-end gap-2"
            >
                <button
                    v-for="card in otherMissions"
                    :key="card.assignment_id"
                    class="group flex max-w-[250px] items-center gap-2 rounded-full bg-slate-900/90 px-3 py-1.5 text-xs shadow-lg ring-1 ring-white/10 backdrop-blur-md transition hover:bg-slate-800/90 active:scale-95"
                    @click="selectedId = card.assignment_id"
                >
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-2"
                        :class="severityRing[card.incident.severity] ?? 'ring-slate-500'"
                    >
                        <span class="text-sm">{{ card.incident.type_icon }}</span>
                    </span>
                    <span class="truncate font-medium text-slate-100">{{ card.incident.title }}</span>
                </button>
            </div>
        </div>

        <!-- ═══ BOTTOM SHEET (mission details + actions) ════════════════════ -->
        <div
            v-if="selectedMission"
            class="relative z-[600] shrink-0 overflow-hidden rounded-t-3xl bg-white text-slate-900 shadow-[0_-20px_50px_-15px_rgba(0,0,0,0.6)] ring-1 ring-black/10 transition-all duration-300 ease-out dark:bg-slate-900 dark:text-white dark:ring-white/10"
        >
            <!-- Drag handle (tap to expand) -->
            <button
                class="group flex w-full flex-col items-center gap-0.5 pt-2 pb-1"
                @click="sheetExpanded = !sheetExpanded"
                :aria-label="sheetExpanded ? 'Collapse details' : 'Expand details'"
            >
                <span class="h-1 w-10 rounded-full bg-slate-300 transition group-hover:bg-slate-400 dark:bg-slate-700 dark:group-hover:bg-slate-600" />
                <span class="flex items-center gap-0.5 text-[10px] font-medium text-slate-400">
                    <component :is="sheetExpanded ? ChevronDown : ChevronUp" class="h-3 w-3" />
                    {{ sheetExpanded ? 'Collapse' : 'More' }}
                </span>
            </button>

            <div class="max-h-[70svh] overflow-y-auto overscroll-contain px-4 pb-5 sm:px-5">

                <!-- ── ALWAYS-VISIBLE: Mission header ───────────────────── -->
                <div class="flex items-start gap-3 py-2">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-2xl ring-2 dark:bg-slate-800"
                        :class="severityRing[selectedMission.incident.severity] ?? 'ring-slate-400'"
                    >
                        {{ selectedMission.incident.type_icon }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-base font-bold leading-tight sm:text-lg">
                            {{ selectedMission.incident.title }}
                        </h2>
                        <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                            <MapPin class="h-3 w-3 shrink-0" />
                            <span class="truncate">
                                {{
                                    selectedMission.incident.location.address ||
                                    `${selectedMission.incident.location.barangay ?? ''}, ${selectedMission.incident.location.city ?? ''}`
                                }}
                            </span>
                        </p>
                        <p class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-500">
                            <Clock3 class="h-3 w-3" />
                            Assigned {{ timeAgo(selectedMission.assigned_at) }}
                        </p>
                    </div>
                </div>

                <!-- ── ALWAYS-VISIBLE: Timeline stepper (compact) ─────── -->
                <div class="mt-3 flex items-center gap-1">
                    <template v-for="(step, idx) in missionTimeline" :key="step.key">
                        <div class="flex flex-col items-center gap-1">
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold transition"
                                :class="step.current
                                    ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/40 ring-4 ring-emerald-100 dark:ring-emerald-950'
                                    : step.active
                                      ? 'bg-emerald-500 text-white'
                                      : 'bg-slate-200 text-slate-500 dark:bg-slate-800'"
                            >
                                <CheckCircle2 v-if="step.active && !step.current" class="h-3.5 w-3.5" />
                                <span v-else>{{ idx + 1 }}</span>
                            </div>
                            <span
                                class="text-[9px] font-semibold uppercase tracking-wide"
                                :class="step.active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                            >
                                {{ step.label }}
                            </span>
                        </div>
                        <div
                            v-if="idx < missionTimeline.length - 1"
                            class="mt-[-14px] h-0.5 flex-1 rounded-full"
                            :class="missionTimeline[idx + 1].active ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-800'"
                        />
                    </template>
                </div>

                <!-- ── ALWAYS-VISIBLE: Primary action button ──────────── -->
                <div class="mt-4">
                    <Form
                        v-if="statusFlow[selectedMission.status]"
                        v-bind="RescuerAssignmentController.updateStatus.form(selectedMission.assignment_id)"
                        :options="{ preserveScroll: true }"
                        @success="refresh"
                        v-slot="{ processing }"
                    >
                        <input type="hidden" name="status" :value="statusFlow[selectedMission.status]?.status ?? ''" />
                        <Button
                            type="submit"
                            :disabled="processing"
                            size="lg"
                            class="h-14 w-full bg-gradient-to-r from-emerald-500 to-teal-600 text-base font-bold shadow-lg shadow-emerald-500/40 hover:from-emerald-600 hover:to-teal-700 active:scale-[0.98]"
                        >
                            <span class="mr-2 text-xl">{{ statusFlow[selectedMission.status]?.emoji }}</span>
                            {{ processing ? 'Updating…' : statusFlow[selectedMission.status]?.label }}
                            <Navigation class="ml-auto h-5 w-5" />
                        </Button>
                    </Form>
                </div>

                <!-- ── ALWAYS-VISIBLE: Quick actions ──────────────────── -->
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <a
                        :href="openMapsTo()"
                        target="_blank"
                        rel="noopener"
                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-slate-100 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                    >
                        <Navigation class="h-4 w-4" />
                        <span class="hidden sm:inline">Navigate</span>
                    </a>
                    <a
                        v-if="selectedMission.incident.reporter?.contact"
                        :href="`tel:${selectedMission.incident.reporter.contact}`"
                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-500 text-sm font-semibold text-white shadow-md shadow-emerald-500/25 transition active:scale-95"
                    >
                        <Phone class="h-4 w-4" />
                        <span class="hidden sm:inline">Reporter</span>
                    </a>
                    <a
                        v-else
                        :href="`tel:911`"
                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-rose-500 text-sm font-semibold text-white shadow-md shadow-rose-500/25 transition active:scale-95"
                    >
                        <Phone class="h-4 w-4" />
                        <span class="hidden sm:inline">Command</span>
                    </a>
                    <button
                        class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-slate-100 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                        @click="sheetExpanded = !sheetExpanded"
                    >
                        <Sparkles class="h-4 w-4 text-indigo-500" />
                        <span>{{ sheetExpanded ? 'Hide' : 'Brief' }}</span>
                    </button>
                </div>

                <!-- ── EXPANDED: AI brief + reporter + description ───── -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[2000px] opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="max-h-[2000px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-if="sheetExpanded" class="mt-4 space-y-3 overflow-hidden">
                        <!-- Reporter info -->
                        <div v-if="selectedMission.incident.reporter?.name" class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/60">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                                <User class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Reporter</p>
                                <p class="truncate text-sm font-semibold">{{ selectedMission.incident.reporter.name }}</p>
                                <p v-if="selectedMission.incident.reporter.contact" class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ selectedMission.incident.reporter.contact }}
                                </p>
                            </div>
                            <a
                                v-if="selectedMission.incident.reporter.contact"
                                :href="`tel:${selectedMission.incident.reporter.contact}`"
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white shadow-md"
                                aria-label="Call reporter"
                            >
                                <Phone class="h-4 w-4" />
                            </a>
                        </div>

                        <!-- AI Mission Brief -->
                        <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 p-3 dark:from-indigo-950/40 dark:to-violet-950/40">
                            <div class="mb-1.5 flex items-center gap-1.5">
                                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white">
                                    <Sparkles class="h-3 w-3" />
                                </div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-900 dark:text-indigo-200">
                                    AI Mission Brief
                                </p>
                            </div>
                            <p class="whitespace-pre-line text-xs leading-relaxed text-slate-800 dark:text-slate-100">
                                {{ selectedMission.ai_mission_brief || 'Proceed carefully and follow standard incident response protocol. Maintain radio contact with command center.' }}
                            </p>
                            <p
                                v-if="selectedMission.notes"
                                class="mt-2 rounded-lg bg-white/60 px-2.5 py-1.5 text-[11px] text-slate-700 ring-1 ring-indigo-200 dark:bg-slate-900/60 dark:text-slate-200 dark:ring-indigo-900"
                            >
                                <span class="font-semibold text-indigo-700 dark:text-indigo-300">Note:</span>
                                {{ selectedMission.notes }}
                            </p>
                        </div>

                        <!-- Description (reporter's notes) -->
                        <div v-if="selectedMission.incident.description" class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/60">
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Reporter notes</p>
                            <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-200">
                                {{ selectedMission.incident.description }}
                            </p>
                        </div>

                        <!-- Other missions queue -->
                        <div v-if="otherMissions.length > 0">
                            <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Other active missions ({{ otherMissions.length }})
                            </p>
                            <div class="space-y-1.5">
                                <button
                                    v-for="card in otherMissions"
                                    :key="card.assignment_id"
                                    class="flex w-full items-center gap-2 rounded-xl bg-slate-50 p-2.5 text-left transition active:scale-[0.98] dark:bg-slate-800/60"
                                    @click="selectedId = card.assignment_id; sheetExpanded = false"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-white"
                                        :class="severityPalette[card.incident.severity] ?? 'from-slate-400 to-slate-500'"
                                    >
                                        <span class="text-base">{{ card.incident.type_icon }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold">{{ card.incident.title }}</p>
                                        <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">
                                            {{ card.incident.location.barangay ?? '—' }} · {{ timeAgo(card.assigned_at) }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase"
                                        :class="statusPill[card.status] ?? statusPill.assigned"
                                    >
                                        {{ card.status_label }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- ═══ EMPTY STATE: No missions — still keep action drawer compact ══ -->
        <div
            v-else
            class="relative z-[600] shrink-0 rounded-t-3xl bg-white p-4 text-center text-slate-700 shadow-[0_-20px_50px_-15px_rgba(0,0,0,0.4)] ring-1 ring-black/10 dark:bg-slate-900 dark:text-slate-200 dark:ring-white/10"
        >
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Stay online to receive new dispatches. The nearest rescuer gets matched automatically.
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Lock the page from scrolling — we want the map to fill the viewport. */
:deep(html),
:deep(body) {
    overflow: hidden;
}
</style>
