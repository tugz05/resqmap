<script setup lang="ts">
/**
 * Admin Incident Queue (oversight-only, Grab-style).
 *
 * Admins NEVER manually pick rescuers. The AI Dispatch Agent matches the
 * nearest online rescuer automatically once a report is verified (either by
 * OpenAI vision or by an admin clicking "Approve"). Admin responsibilities:
 *   1. Re-check AI verification verdicts
 *   2. Approve / reject uncertain reports
 *   3. Observe live tracking while the rescuer closes in
 *   4. Resolve the case when the on-scene work is done
 */

import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    BadgeCheck,
    Bell,
    BellOff,
    Bot,
    CheckCircle2,
    ChevronRight,
    Clock,
    Gauge,
    Loader2,
    MapPin,
    Phone,
    RefreshCw,
    Search,
    ShieldCheck,
    Siren,
    Sparkles,
    User,
    XCircle,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import IncidentWorkflowController from '@/actions/App/Http/Controllers/Admin/IncidentWorkflowController';
import LiveTrackingMap from '@/components/tracking/LiveTrackingMap.vue';
import { Button } from '@/components/ui/button';
import { useAlarmSound } from '@/composables/useAlarmSound';
import { echoClient } from '@/echo';

type LatLng = { latitude: number; longitude: number } | null | undefined;

type Incident = {
    id: string;
    type: string;
    type_label: string;
    type_icon: string;
    severity: string;
    severity_label: string;
    status: string;
    status_label: string;
    is_active: boolean;
    title: string;
    description?: string;
    location: { latitude: number; longitude: number; address?: string; barangay?: string; city?: string };
    reporter?: { id?: number; name?: string };
    reporter_location?: LatLng;
    rescuer_location?: LatLng;
    assigned_rescuer?: {
        id?: number;
        name?: string;
        agency?: string | null;
        contact?: string | null;
        status?: string;
        status_label?: string;
        assigned_at?: string | null;
        accepted_at?: string | null;
        arrived_at?: string | null;
    } | null;
    ai_verification: {
        status?: 'pending' | 'processing' | 'completed' | 'failed';
        verdict?: 'true' | 'false' | 'uncertain' | null;
        confidence?: number | null;
        summary_cebuano?: string | null;
        red_flags?: string[];
        recommended_action?: string | null;
        verified_at?: string | null;
        queued_at?: string | null;
        started_at?: string | null;
        attempts?: number;
        error?: string | null;
    };
    ai_dispatch?: {
        recommended_rescuer_id?: number | null;
        auto_dispatched?: boolean;
        candidates?: Array<{
            rescuer_id: number;
            name: string;
            distance_km: number | null;
            eta_minutes: number | null;
            rank: number;
        }>;
    };
    reported_at: string;
    verified_at?: string | null;
    dispatched_at?: string | null;
};

const props = defineProps<{
    incidents: Incident[];
    overview: {
        total_reports: number;
        pending: number;
        verified: number;
        dispatched: number;
        resolved: number;
        high_priority: number;
    };
    coverage: { municipalities: number; barangays: number };
}>();

const page = usePage<{ flash?: { success?: string; error?: string; info?: string } }>();

// ─── Filter state ────────────────────────────────────────────────────────────
type Filter = 'all' | 'needs_review' | 'dispatched' | 'resolved';
const filter = ref<Filter>('all');
const search = ref('');

const filteredIncidents = computed<Incident[]>(() => {
    let list = props.incidents;

    if (filter.value === 'needs_review') {
        list = list.filter(
            (i) =>
                i.status === 'pending' ||
                i.ai_verification.status === 'failed' ||
                i.ai_verification.verdict === 'false' ||
                i.ai_verification.verdict === 'uncertain',
        );
    } else if (filter.value === 'dispatched') {
        list = list.filter((i) => ['dispatched', 'en_route', 'on_scene'].includes(i.status));
    } else if (filter.value === 'resolved') {
        list = list.filter((i) => ['resolved', 'cancelled'].includes(i.status));
    }

    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        list = list.filter(
            (i) =>
                i.title.toLowerCase().includes(q) ||
                (i.reporter?.name ?? '').toLowerCase().includes(q) ||
                (i.location.barangay ?? '').toLowerCase().includes(q) ||
                (i.location.city ?? '').toLowerCase().includes(q),
        );
    }

    return list;
});

const selectedId = ref<string | null>(props.incidents[0]?.id ?? null);
const selected = computed<Incident | null>(
    () => props.incidents.find((i) => i.id === selectedId.value) ?? filteredIncidents.value[0] ?? null,
);

function selectIncident(incident: Incident): void {
    selectedId.value = incident.id;
}

// ─── Verdict styling ─────────────────────────────────────────────────────────
const verdictPill = computed(() => {
    if (!selected.value) {
return null;
}

    const v = selected.value.ai_verification;

    if (v.status === 'pending') {
        return { label: 'AI queued', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300', icon: Clock };
    }

    if (v.status === 'processing') {
        return { label: 'AI analyzing…', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300', icon: Loader2 };
    }

    if (v.status === 'failed') {
        return { label: 'AI failed', cls: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300', icon: XCircle };
    }

    if (v.verdict === 'true') {
        return { label: `Verified true · ${v.confidence ?? 0}%`, cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', icon: CheckCircle2 };
    }

    if (v.verdict === 'false') {
        return { label: `Likely fake · ${v.confidence ?? 0}%`, cls: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300', icon: XCircle };
    }

    return { label: `Uncertain · ${v.confidence ?? 0}%`, cls: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', icon: AlertTriangle };
});

const statusPill: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',
    verified: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    dispatched: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300',
    en_route: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    on_scene: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

const severityDot: Record<string, string> = {
    low: 'bg-green-500',
    medium: 'bg-yellow-500',
    high: 'bg-orange-500',
    critical: 'bg-red-500',
};

function timeAgo(iso?: string | null): string {
    if (!iso) {
return '—';
}

    const diff = Date.now() - new Date(iso).getTime();
    const m = Math.floor(diff / 60000);

    if (m < 1) {
return 'just now';
}

    if (m < 60) {
return `${m}m ago`;
}

    const h = Math.floor(m / 60);

    if (h < 24) {
return `${h}h ago`;
}

    return `${Math.floor(h / 24)}d ago`;
}

// ─── Real-time (Pusher) + polling fallback ──────────────────────────────────
// Admins subscribe to `admin.incidents` for system-wide changes, plus the
// currently-selected incident's own channel + rescuer location stream so
// the detail pane animates live without pressing Refresh.
let pollTimer: ReturnType<typeof setInterval> | null = null;
let selectedIncidentChannel: string | null = null;
let selectedRescuerChannel: string | null = null;

const rescuerLivePosition = ref<{ latitude: number; longitude: number } | null>(null);

// Emergency alarm — fires when a resident files a brand-new report so the
// duty officer hears it even if they're not staring at the screen.
const { play: playAlarm, muted: alarmMuted, toggleMute: toggleAlarmMute } = useAlarmSound();

function reloadIncidents(): void {
    router.reload({ only: ['incidents', 'overview'] });
}

function subscribeToAdminFeed(): void {
    const echo = echoClient();

    if (!echo) {
        return;
    }

    echo.private('admin.incidents')
        .listen('.incident.new', () => {
            playAlarm();
            reloadIncidents();
        })
        .listen('.incident.status-changed', () => reloadIncidents())
        .listen('.incident.ai-verified', () => reloadIncidents())
        .listen('.incident.rescuer-assigned', () => reloadIncidents())
        .listen('.incident.assignment-status-changed', () => reloadIncidents());
}

function subscribeToSelectedIncident(): void {
    const echo = echoClient();

    if (!echo) {
return;
}

    const ulid = selected.value?.id;

    if (!ulid) {
return;
}

    const incidentChannel = `incident.${ulid}`;
    const rescuerChannel = `incident.${ulid}.rescuer`;

    if (selectedIncidentChannel && selectedIncidentChannel !== incidentChannel) {
        try {
 echo.leave(selectedIncidentChannel); 
} catch { /* ignore */ }
    }

    if (selectedRescuerChannel && selectedRescuerChannel !== rescuerChannel) {
        try {
 echo.leave(selectedRescuerChannel); 
} catch { /* ignore */ }

        rescuerLivePosition.value = null;
    }

    selectedIncidentChannel = incidentChannel;
    selectedRescuerChannel = rescuerChannel;

    echo.private(incidentChannel)
        .listen('.incident.status-changed', () => reloadIncidents())
        .listen('.incident.ai-verified', () => reloadIncidents())
        .listen('.incident.rescuer-assigned', () => reloadIncidents())
        .listen('.incident.assignment-status-changed', () => reloadIncidents());

    echo.private(rescuerChannel)
        .listen('.user.location-moved', (payload: unknown) => {
            const p = payload as { latitude?: number; longitude?: number };

            if (typeof p.latitude === 'number' && typeof p.longitude === 'number') {
                rescuerLivePosition.value = { latitude: p.latitude, longitude: p.longitude };
            }
        });
}

watch(
    () => selected.value?.id,
    (newUlid, oldUlid) => {
        if (newUlid === oldUlid) {
return;
}

        rescuerLivePosition.value = null;
        subscribeToSelectedIncident();
    },
);

onMounted(() => {
    // Fallback polling — Pusher is primary; we fall back every 30s in case
    // the WebSocket drops or credentials are missing.
    pollTimer = setInterval(() => {
        if (document.visibilityState !== 'visible') {
return;
}

        reloadIncidents();
    }, 30000);

    subscribeToAdminFeed();
    subscribeToSelectedIncident();
});

onBeforeUnmount(() => {
    if (pollTimer) {
clearInterval(pollTimer);
}

    const echo = echoClient();

    if (echo) {
        try {
 echo.leave('admin.incidents'); 
} catch { /* ignore */ }

        if (selectedIncidentChannel) {
 try {
 echo.leave(selectedIncidentChannel); 
} catch { /* ignore */ } 
}

        if (selectedRescuerChannel) {
 try {
 echo.leave(selectedRescuerChannel); 
} catch { /* ignore */ } 
}
    }
});

// ─── Action helpers (bound via `v-bind` on <Form>) ─────────────────────────
const retryDispatchForm = (id: string) => IncidentWorkflowController.retryDispatch.form({ incident: id });
const aiVerifyForm = (id: string) => IncidentWorkflowController.aiVerify.form({ incident: id });
const statusForm = (id: string) => IncidentWorkflowController.updateStatus.form({ incident: id });
</script>

<template>
    <Head title="Incident Queue" />

    <div class="min-h-svh bg-slate-50 dark:bg-slate-950">

        <!-- ═══ STICKY HEADER ═════════════════════════════════════════════ -->
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/90">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 lg:px-8">
                <div>
                    <h1 class="flex items-center gap-2 text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                        <Siren class="h-5 w-5 text-rose-500" />
                        Incident Queue
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        AI auto-dispatches the nearest rescuer. You just verify and monitor.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="flex h-2 w-2">
                        <span class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-emerald-400 opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    </span>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Live</span>
                    <Button
                        size="sm"
                        variant="outline"
                        class="ml-2"
                        :title="alarmMuted ? 'Emergency alarm is muted — click to unmute' : 'Emergency alarm is on — click to mute'"
                        @click="toggleAlarmMute"
                    >
                        <BellOff v-if="alarmMuted" class="mr-1 h-3.5 w-3.5 text-slate-400" />
                        <Bell v-else class="mr-1 h-3.5 w-3.5 text-rose-500" />
                        {{ alarmMuted ? 'Muted' : 'Alarm' }}
                    </Button>
                    <Button size="sm" variant="outline" @click="router.reload({ only: ['incidents', 'overview'] })">
                        <RefreshCw class="mr-1 h-3.5 w-3.5" />
                        Refresh
                    </Button>
                </div>
            </div>

            <!-- KPI strip -->
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-2 px-4 pb-3 sm:grid-cols-5 lg:px-8">
                <div class="rounded-xl bg-white p-2.5 shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total</div>
                    <div class="mt-0.5 text-xl font-bold text-slate-900 dark:text-white">{{ overview.total_reports }}</div>
                </div>
                <div class="rounded-xl bg-white p-2.5 shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-yellow-600 dark:text-yellow-400">Pending</div>
                    <div class="mt-0.5 text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ overview.pending }}</div>
                </div>
                <div class="rounded-xl bg-white p-2.5 shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Verified</div>
                    <div class="mt-0.5 text-xl font-bold text-blue-600 dark:text-blue-400">{{ overview.verified }}</div>
                </div>
                <div class="rounded-xl bg-white p-2.5 shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-400">Dispatched</div>
                    <div class="mt-0.5 text-xl font-bold text-purple-600 dark:text-purple-400">{{ overview.dispatched }}</div>
                </div>
                <div class="rounded-xl bg-white p-2.5 shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">High priority</div>
                    <div class="mt-0.5 text-xl font-bold text-rose-600 dark:text-rose-400">{{ overview.high_priority }}</div>
                </div>
            </div>
        </header>

        <!-- Flash messages -->
        <div v-if="page.props.flash?.success" class="mx-auto mt-3 max-w-7xl px-4 lg:px-8">
            <div class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-900">
                <CheckCircle2 class="h-4 w-4" />
                {{ page.props.flash.success }}
            </div>
        </div>
        <div v-if="page.props.flash?.error" class="mx-auto mt-3 max-w-7xl px-4 lg:px-8">
            <div class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-800 ring-1 ring-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:ring-rose-900">
                <XCircle class="h-4 w-4" />
                {{ page.props.flash.error }}
            </div>
        </div>

        <!-- ═══ BODY — split layout ══════════════════════════════════════ -->
        <div class="mx-auto grid max-w-7xl gap-4 px-4 py-4 lg:grid-cols-[380px_1fr] lg:px-8">

            <!-- ── LEFT: incident list ─────────────────────────────────── -->
            <aside class="flex flex-col gap-3">

                <!-- Filter pills -->
                <div class="flex flex-wrap gap-1.5 rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                    <button
                        v-for="f in (['all', 'needs_review', 'dispatched', 'resolved'] as Filter[])"
                        :key="f"
                        @click="filter = f"
                        :class="[
                            'flex-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition',
                            filter === f
                                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                                : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                        ]"
                    >
                        {{ f === 'all' ? 'All' : f === 'needs_review' ? 'Needs review' : f === 'dispatched' ? 'In progress' : 'Closed' }}
                    </button>
                </div>

                <!-- Search -->
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search title, reporter, barangay…"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm outline-none ring-offset-2 transition focus:ring-2 focus:ring-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                    />
                </div>

                <!-- List -->
                <div class="space-y-2 overflow-y-auto pr-1" style="max-height: calc(100svh - 280px)">
                    <button
                        v-for="incident in filteredIncidents"
                        :key="incident.id"
                        @click="selectIncident(incident)"
                        :class="[
                            'group w-full rounded-2xl border p-3 text-left transition',
                            selected?.id === incident.id
                                ? 'border-rose-500 bg-white shadow-lg ring-2 ring-rose-500/20 dark:bg-slate-900'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700',
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl dark:bg-slate-800">
                                {{ incident.type_icon }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ incident.title }}</p>
                                    <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold', statusPill[incident.status]]">
                                        {{ incident.status_label }}
                                    </span>
                                </div>
                                <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                    <MapPin class="h-3 w-3" />
                                    {{ incident.location.barangay ?? '—' }} · {{ incident.location.city ?? '—' }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span :class="['h-1.5 w-1.5 rounded-full', severityDot[incident.severity]]" />
                                    <span class="text-[10px] text-slate-400">{{ incident.severity_label }}</span>
                                    <span class="text-[10px] text-slate-300 dark:text-slate-600">·</span>
                                    <span class="text-[10px] text-slate-400">{{ timeAgo(incident.reported_at) }}</span>

                                    <!-- AI pill -->
                                    <span
                                        v-if="incident.ai_verification.status === 'pending'"
                                        class="ml-auto inline-flex items-center gap-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        <Clock class="h-2.5 w-2.5" /> queued
                                    </span>
                                    <span
                                        v-else-if="incident.ai_verification.status === 'processing'"
                                        class="ml-auto inline-flex items-center gap-1 rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                                    >
                                        <Loader2 class="h-2.5 w-2.5 animate-spin" /> AI
                                    </span>
                                    <span
                                        v-else-if="incident.ai_verification.verdict === 'true'"
                                        class="ml-auto inline-flex items-center gap-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        <BadgeCheck class="h-2.5 w-2.5" /> true
                                    </span>
                                    <span
                                        v-else-if="incident.ai_verification.verdict === 'false'"
                                        class="ml-auto inline-flex items-center gap-1 rounded-full bg-red-100 px-1.5 py-0.5 text-[9px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-300"
                                    >
                                        <XCircle class="h-2.5 w-2.5" /> fake
                                    </span>
                                    <span
                                        v-else-if="incident.ai_verification.verdict === 'uncertain'"
                                        class="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                    >
                                        <AlertTriangle class="h-2.5 w-2.5" /> ?
                                    </span>
                                </div>
                            </div>
                            <ChevronRight class="mt-2 h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-slate-500 dark:text-slate-600" />
                        </div>
                    </button>

                    <div
                        v-if="filteredIncidents.length === 0"
                        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800"
                    >
                        <Activity class="mb-2 h-8 w-8 text-slate-300 dark:text-slate-700" />
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Nothing matches this filter.</p>
                    </div>
                </div>
            </aside>

            <!-- ── RIGHT: detail pane ─────────────────────────────────── -->
            <main v-if="selected" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">

                <!-- Detail header -->
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/60 px-5 py-4 dark:border-slate-800">
                    <div class="flex items-start gap-3">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl dark:bg-slate-800">
                            {{ selected.type_icon }}
                        </div>
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">
                                {{ selected.title }}
                                <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold', statusPill[selected.status]]">
                                    {{ selected.status_label }}
                                </span>
                            </h2>
                            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                <User class="h-3 w-3" />
                                Reported by <span class="font-medium">{{ selected.reporter?.name ?? 'Unknown' }}</span>
                                · {{ timeAgo(selected.reported_at) }}
                            </p>
                            <p class="mt-1 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                <MapPin class="h-3 w-3" />
                                {{ selected.location.address ?? `${selected.location.barangay ?? ''} ${selected.location.city ?? ''}`.trim() }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <span v-if="verdictPill" :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold', verdictPill.cls]">
                            <component :is="verdictPill.icon" class="h-3.5 w-3.5" :class="{ 'animate-spin': selected.ai_verification.status === 'processing' }" />
                            {{ verdictPill.label }}
                        </span>
                    </div>
                </div>

                <!-- ═══ LIVE TRACKING (always-on, the heart of the UI) ═══ -->
                <div class="border-b border-slate-200/60 p-5 dark:border-slate-800">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                            <Activity class="h-4 w-4 text-emerald-500" />
                            Live Tracking
                        </h3>
                        <span v-if="selected.assigned_rescuer" class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            Auto-matched by AI Dispatch Agent
                        </span>
                    </div>

                    <LiveTrackingMap
                        :key="selected.id"
                        :incident="{
                            latitude: selected.location.latitude,
                            longitude: selected.location.longitude,
                            title: selected.title,
                            type_icon: selected.type_icon,
                        }"
                        :reporter="selected.reporter_location"
                        :rescuer="rescuerLivePosition ?? selected.rescuer_location"
                        :rescuer-name="selected.assigned_rescuer?.name ?? null"
                        :rescuer-status="selected.assigned_rescuer?.status_label ?? null"
                        height="340px"
                    />

                    <!-- Dispatch summary card -->
                    <div v-if="selected.assigned_rescuer" class="mt-3 flex items-center gap-3 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 p-4 ring-1 ring-emerald-200 dark:from-emerald-950/40 dark:to-teal-950/40 dark:ring-emerald-900/60">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500 text-white">
                            <Bot class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                    {{ selected.assigned_rescuer.name }}
                                </p>
                                <span class="rounded-full bg-white/80 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-slate-900/60 dark:text-emerald-300 dark:ring-emerald-800">
                                    {{ selected.assigned_rescuer.status_label }}
                                </span>
                            </div>
                            <p class="truncate text-xs text-slate-600 dark:text-slate-400">
                                <span v-if="selected.assigned_rescuer.agency">{{ selected.assigned_rescuer.agency }}</span>
                                <span v-if="selected.assigned_rescuer.contact"> · {{ selected.assigned_rescuer.contact }}</span>
                                <span v-if="!selected.assigned_rescuer.agency && !selected.assigned_rescuer.contact">Rescuer on the way</span>
                            </p>
                        </div>
                        <a
                            v-if="selected.assigned_rescuer.contact"
                            :href="`tel:${selected.assigned_rescuer.contact}`"
                            class="flex items-center gap-1 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 dark:bg-slate-900 dark:text-emerald-300 dark:hover:bg-slate-800"
                        >
                            <Phone class="h-3.5 w-3.5" />
                            Call
                        </a>
                    </div>

                    <!-- No rescuer yet -->
                    <div
                        v-else-if="selected.status === 'verified' || selected.status === 'dispatched'"
                        class="mt-3 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:ring-amber-900/60"
                    >
                        <AlertTriangle class="mt-0.5 h-5 w-5 text-amber-600" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                No rescuer available within range
                            </p>
                            <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300/80">
                                AI Dispatch Agent couldn't find an online rescuer nearby. Retry once responders are back online.
                            </p>
                            <Form
                                v-bind="retryDispatchForm(selected.id)"
                                class="mt-2 inline-block"
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <Button type="submit" size="sm" variant="outline" :disabled="processing">
                                    <Loader2 v-if="processing" class="mr-1 h-3.5 w-3.5 animate-spin" />
                                    <Sparkles v-else class="mr-1 h-3.5 w-3.5" />
                                    Retry AI Dispatch
                                </Button>
                            </Form>
                        </div>
                    </div>

                    <!-- Still pending admin verification -->
                    <div
                        v-else-if="selected.status === 'pending'"
                        class="mt-3 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200 dark:bg-slate-800/50 dark:ring-slate-700"
                    >
                        <ShieldCheck class="mt-0.5 h-5 w-5 text-slate-500" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                Awaiting your review
                            </p>
                            <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">
                                Once you approve, the AI Dispatch Agent will auto-match the nearest rescuer — no manual picking.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ═══ AI VERIFICATION PANEL ═══════════════════════════ -->
                <div class="border-b border-slate-200/60 p-5 dark:border-slate-800">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                            <Sparkles class="h-4 w-4 text-indigo-500" />
                            AI Verification
                            <span v-if="selected.ai_verification.attempts" class="text-xs font-normal text-slate-400">
                                · {{ selected.ai_verification.attempts }} attempt{{ selected.ai_verification.attempts === 1 ? '' : 's' }}
                            </span>
                        </h3>
                        <Form
                            v-bind="aiVerifyForm(selected.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <Button type="submit" size="sm" variant="outline" :disabled="processing">
                                <Loader2 v-if="processing" class="mr-1 h-3.5 w-3.5 animate-spin" />
                                <RefreshCw v-else class="mr-1 h-3.5 w-3.5" />
                                Re-run AI check
                            </Button>
                        </Form>
                    </div>

                    <div
                        v-if="selected.ai_verification.status === 'processing' || selected.ai_verification.status === 'pending'"
                        class="flex items-center gap-3 rounded-2xl bg-blue-50 p-4 ring-1 ring-blue-200 dark:bg-blue-950/40 dark:ring-blue-900/60"
                    >
                        <Loader2 class="h-5 w-5 animate-spin text-blue-500" />
                        <div class="text-sm text-blue-900 dark:text-blue-200">
                            AI vision analysis is running in the background…
                        </div>
                    </div>

                    <div
                        v-else-if="selected.ai_verification.status === 'failed'"
                        class="rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-200 dark:bg-rose-950/40 dark:ring-rose-900/60"
                    >
                        <div class="flex items-start gap-2">
                            <XCircle class="mt-0.5 h-5 w-5 text-rose-500" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">Verification failed</p>
                                <p v-if="selected.ai_verification.error" class="mt-1 break-words text-xs text-rose-800/80 dark:text-rose-300/80">
                                    {{ selected.ai_verification.error }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="selected.ai_verification.verdict" class="space-y-3">
                        <p v-if="selected.ai_verification.summary_cebuano" class="rounded-xl bg-slate-50 p-3 text-sm italic text-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                            "{{ selected.ai_verification.summary_cebuano }}"
                        </p>

                        <div v-if="(selected.ai_verification.red_flags?.length ?? 0) > 0" class="rounded-xl bg-amber-50 p-3 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:ring-amber-900/60">
                            <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">Red flags</p>
                            <ul class="space-y-1 text-xs text-amber-900 dark:text-amber-200">
                                <li v-for="flag in selected.ai_verification.red_flags" :key="flag" class="flex items-start gap-1.5">
                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-amber-600" />
                                    <span>{{ flag }}</span>
                                </li>
                            </ul>
                        </div>

                        <p v-if="selected.ai_verification.recommended_action" class="text-xs text-slate-600 dark:text-slate-400">
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Recommendation:</span>
                            {{ selected.ai_verification.recommended_action }}
                        </p>
                    </div>

                    <div v-else class="text-sm text-slate-500 dark:text-slate-400">
                        No AI verdict yet. Click "Re-run AI check" to queue one.
                    </div>
                </div>

                <!-- ═══ ADMIN OVERSIGHT ACTIONS ═════════════════════════ -->
                <div class="p-5">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <Gauge class="h-4 w-4 text-rose-500" />
                        Admin Actions
                    </h3>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <!-- Approve (pending → verified → auto-dispatch) -->
                        <Form
                            v-if="selected.status === 'pending'"
                            v-bind="statusForm(selected.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <input type="hidden" name="status" value="verified" />
                            <Button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700" :disabled="processing">
                                <Loader2 v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
                                <CheckCircle2 v-else class="mr-1 h-4 w-4" />
                                Approve & auto-dispatch
                            </Button>
                        </Form>

                        <!-- Reject (pending → cancelled) -->
                        <Form
                            v-if="selected.status === 'pending'"
                            v-bind="statusForm(selected.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <input type="hidden" name="status" value="cancelled" />
                            <Button type="submit" variant="outline" class="w-full border-rose-300 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40" :disabled="processing">
                                <Loader2 v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
                                <XCircle v-else class="mr-1 h-4 w-4" />
                                Reject report
                            </Button>
                        </Form>

                        <!-- Resolve (on_scene → resolved) -->
                        <Form
                            v-if="selected.status === 'on_scene'"
                            v-bind="statusForm(selected.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <input type="hidden" name="status" value="resolved" />
                            <Button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700" :disabled="processing">
                                <Loader2 v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
                                <CheckCircle2 v-else class="mr-1 h-4 w-4" />
                                Mark resolved
                            </Button>
                        </Form>

                        <!-- Cancel (verified/dispatched/en_route/on_scene → cancelled) -->
                        <Form
                            v-if="['verified', 'dispatched', 'en_route', 'on_scene'].includes(selected.status)"
                            v-bind="statusForm(selected.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <input type="hidden" name="status" value="cancelled" />
                            <Button type="submit" variant="outline" class="w-full border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" :disabled="processing">
                                <Loader2 v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
                                <XCircle v-else class="mr-1 h-4 w-4" />
                                Cancel incident
                            </Button>
                        </Form>

                        <!-- Closed state notice -->
                        <div
                            v-if="['resolved', 'cancelled'].includes(selected.status)"
                            class="col-span-full rounded-xl bg-slate-50 p-3 text-center text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400"
                        >
                            This incident is closed. No further actions required.
                        </div>
                    </div>

                    <!-- Description (hidden by default, shows if set) -->
                    <div v-if="selected.description" class="mt-4 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                        <p class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Reporter notes</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ selected.description }}</p>
                    </div>
                </div>
            </main>

            <!-- Empty pane -->
            <main v-else class="flex items-center justify-center rounded-2xl bg-white p-12 text-center shadow-sm ring-1 ring-slate-200/60 dark:bg-slate-900 dark:ring-slate-800">
                <div>
                    <Activity class="mx-auto mb-3 h-12 w-12 text-slate-300 dark:text-slate-700" />
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Select an incident from the list.</p>
                </div>
            </main>
        </div>
    </div>
</template>
