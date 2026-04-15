<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    ChevronRight,
    Clock,
    Home,
    Map,
    Plus,
    Settings,
    User,
    FileText,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AIReportChat from '@/components/resident/AIReportChat.vue';
import ResidentMap from '@/components/resident/ResidentMap.vue';

// ─── Incident shape ───────────────────────────────────────────────────────────
type Incident = {
    id: string;
    type: string;
    type_label: string;
    type_icon: string;
    severity: string;
    severity_label: string;
    severity_color: string;
    status: string;
    status_label: string;
    is_active: boolean;
    title: string;
    description?: string;
    location: { latitude: number; longitude: number; address?: string; barangay?: string; city?: string };
    reported_at: string;
    resolved_at?: string;
};

// ─── Props (from DashboardController) ────────────────────────────────────────
// Accept both plain array AND the { data: [...] } shape that ResourceCollection
// may emit depending on Inertia serialization order.
const props = defineProps<{
    incidents: Incident[] | { data: Incident[] };
    stats: { total: number; active: number; resolved: number };
}>();

// ─── State ────────────────────────────────────────────────────────────────────
const page = usePage<{ auth: { user: { id: number; name: string; email: string; role: string } } }>();
const user = computed(() => page.props.auth.user);
const activeTab = ref<'home' | 'map' | 'history' | 'settings'>('home');
const showReport = ref(false);

// ─── Normalise incidents (unwrap { data: [...] } if needed) ──────────────────
// Renamed to `incidentList` to avoid conflicting with the defineProps `incidents` binding.
const incidentList = computed<Incident[]>(() => {
    const raw = props.incidents;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object' && Array.isArray((raw as { data: Incident[] }).data)) {
        return (raw as { data: Incident[] }).data;
    }
    return [];
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-400',
    verified: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    dispatched: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-400',
    en_route: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
    on_scene: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

const severityDot: Record<string, string> = {
    low: 'bg-green-500',
    medium: 'bg-yellow-500',
    high: 'bg-orange-500',
    critical: 'bg-red-500',
};

function timeAgo(isoDate: string): string {
    const diff = Date.now() - new Date(isoDate).getTime();
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'just now';
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.floor(h / 24)}d ago`;
}

const activeIncidents = computed(() => incidentList.value.filter((i) => i.is_active));
const resolvedIncidents = computed(() => incidentList.value.filter((i) => !i.is_active));
const firstName = computed(() => user.value.name.split(' ')[0]);
</script>

<template>
    <Head title="My Dashboard" />

    <div class="relative min-h-svh overflow-x-hidden bg-slate-50 pb-28 dark:bg-slate-950">

        <!-- ═══ TOP HEADER ══════════════════════════════════════════════════ -->
        <header class="sticky top-0 z-40 flex items-center justify-between border-b border-slate-200/60 bg-white/90 px-4 py-3 backdrop-blur-md dark:border-slate-800/60 dark:bg-slate-950/90">
            <div class="flex items-center gap-2.5">
                <img src="/images/logo/resqmap.png" alt="ResQMap" class="h-8 w-auto" />
                <span class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">ResQMap</span>
            </div>
            <div class="flex items-center gap-2">
                <span
                    v-if="stats.active > 0"
                    class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                >{{ stats.active }}</span>
                <button class="relative rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                    <Bell class="h-5 w-5" />
                </button>
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-rose-600 text-xs font-bold text-white">
                    {{ firstName.charAt(0).toUpperCase() }}
                </div>
            </div>
        </header>

        <!-- ═══ TAB CONTENT ════════════════════════════════════════════════ -->

        <!-- HOME TAB ──────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'home'" class="px-4 pt-5">
            <!-- Greeting -->
            <div class="mb-5">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    Hello, {{ firstName }} 👋
                </h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    Stay safe. Report emergencies instantly.
                </p>
            </div>

            <!-- Stats row -->
            <div class="mb-6 grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white p-3 text-center shadow-sm dark:bg-slate-900">
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats.total }}</div>
                    <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Total Reports</div>
                </div>
                <div class="rounded-2xl bg-red-50 p-3 text-center shadow-sm dark:bg-red-950/30">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.active }}</div>
                    <div class="mt-0.5 text-xs text-red-600/70 dark:text-red-400/70">Active</div>
                </div>
                <div class="rounded-2xl bg-green-50 p-3 text-center shadow-sm dark:bg-green-950/30">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.resolved }}</div>
                    <div class="mt-0.5 text-xs text-green-600/70 dark:text-green-400/70">Resolved</div>
                </div>
            </div>

            <!-- Active incidents -->
            <div v-if="activeIncidents.length > 0" class="mb-4">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500" />
                    </span>
                    Active Reports
                </h2>
                <div class="space-y-3">
                    <div
                        v-for="incident in activeIncidents"
                        :key="incident.id"
                        class="flex items-start gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-900"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl dark:bg-slate-800">
                            {{ incident.type_icon }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ incident.title }}</p>
                                <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold', statusColors[incident.status]]">
                                    {{ incident.status_label }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ incident.location.address ?? `${incident.location.barangay ?? ''} ${incident.location.city ?? ''}`.trim() }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span :class="['h-1.5 w-1.5 rounded-full', severityDot[incident.severity]]" />
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ incident.severity_label }} · {{ timeAgo(incident.reported_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state for home -->
            <div v-if="incidentList.length === 0" class="mt-8 flex flex-col items-center py-10 text-center">
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-900">
                    <AlertTriangle class="h-9 w-9 text-slate-400" />
                </div>
                <p class="text-base font-semibold text-slate-700 dark:text-slate-300">No reports yet</p>
                <p class="mt-1 max-w-xs text-sm text-slate-500 dark:text-slate-400">
                    Tap the <span class="font-semibold text-rose-500">red button</span> below to report an emergency.
                </p>
            </div>

            <!-- Recent resolved (collapsed list) -->
            <div v-if="resolvedIncidents.length > 0" class="mb-4">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">
                    Recent History
                </h2>
                <div class="space-y-2">
                    <div
                        v-for="incident in resolvedIncidents.slice(0, 3)"
                        :key="incident.id"
                        class="flex items-center gap-3 rounded-xl bg-white/60 px-3 py-2.5 dark:bg-slate-900/60"
                    >
                        <CheckCircle2 class="h-4 w-4 shrink-0 text-green-500" />
                        <span class="flex-1 truncate text-sm text-slate-600 dark:text-slate-300">{{ incident.title }}</span>
                        <span class="text-xs text-slate-400">{{ timeAgo(incident.reported_at) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAP TAB ──────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'map'" class="h-[calc(100svh-8rem)]">
            <ResidentMap :incidents="incidentList" />
        </div>

        <!-- HISTORY TAB ──────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'history'" class="px-4 pt-5">
            <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">My Reports</h1>
            <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">All your submitted incident reports.</p>

            <div v-if="incidentList.length === 0" class="mt-10 flex flex-col items-center py-10 text-center">
                <FileText class="mb-4 h-16 w-16 text-slate-300 dark:text-slate-700" />
                <p class="text-base font-semibold text-slate-700 dark:text-slate-300">No reports yet</p>
                <p class="mt-1 text-sm text-slate-500">Your incident reports will appear here.</p>
            </div>

            <div class="space-y-3">
                <div
                    v-for="incident in incidentList"
                    :key="incident.id"
                    class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
                >
                    <div class="flex items-start gap-3 p-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl dark:bg-slate-800">
                            {{ incident.type_icon }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate font-semibold text-slate-900 dark:text-white">{{ incident.title }}</p>
                                <span :class="['shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold', statusColors[incident.status]]">
                                    {{ incident.status_label }}
                                </span>
                            </div>
                            <p v-if="incident.description" class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ incident.description }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400 dark:text-slate-500">
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ timeAgo(incident.reported_at) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span :class="['h-1.5 w-1.5 rounded-full', severityDot[incident.severity]]" />
                                    {{ incident.severity_label }}
                                </span>
                                <span v-if="incident.location.city">{{ incident.location.barangay ?? '' }} {{ incident.location.city }}</span>
                            </div>
                        </div>
                        <ChevronRight class="mt-1 h-4 w-4 shrink-0 text-slate-300" />
                    </div>
                    <!-- Progress bar for active incidents -->
                    <div v-if="incident.is_active" class="h-1 bg-slate-100 dark:bg-slate-800">
                        <div
                            :class="['h-full rounded-full transition-all duration-500', {
                                'w-1/5 bg-yellow-400': incident.status === 'pending',
                                'w-2/5 bg-blue-500': incident.status === 'verified',
                                'w-3/5 bg-purple-500': incident.status === 'dispatched',
                                'w-4/5 bg-orange-500': incident.status === 'en_route' || incident.status === 'on_scene',
                                'w-full bg-green-500': incident.status === 'resolved',
                            }]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- SETTINGS TAB ─────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'settings'" class="px-4 pt-5">
            <h1 class="mb-5 text-2xl font-bold text-slate-900 dark:text-white">Settings</h1>

            <!-- Profile card -->
            <div class="mb-5 flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-900">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-rose-600 text-xl font-bold text-white">
                    {{ firstName.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ user.name }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ user.email }}</p>
                    <span class="mt-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-400">
                        Resident
                    </span>
                </div>
            </div>

            <!-- Settings links -->
            <div class="space-y-2">
                <Link
                    v-for="item in [
                        { label: 'Edit Profile', href: '/settings/profile', icon: 'user' },
                        { label: 'Security & Password', href: '/settings/security', icon: 'shield' },
                        { label: 'Appearance', href: '/settings/appearance', icon: 'palette' },
                    ]"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center justify-between rounded-2xl bg-white p-4 shadow-sm transition-colors hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/80"
                >
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ item.label }}</span>
                    <ChevronRight class="h-4 w-4 text-slate-400" />
                </Link>
            </div>
        </div>

        <!-- ═══ BOTTOM NAVIGATION BAR ═══════════════════════════════════════ -->
        <nav class="fixed inset-x-0 bottom-0 z-50 flex justify-center px-4 pb-5">
            <div class="relative flex w-full max-w-sm items-center justify-around rounded-[2rem] bg-[#111827] px-3 py-3.5 shadow-2xl shadow-black/40">

                <!-- Home -->
                <button
                    class="flex flex-col items-center gap-1 px-3 py-1 transition-colors"
                    :class="activeTab === 'home' ? 'text-white' : 'text-slate-500'"
                    @click="activeTab = 'home'"
                >
                    <Home class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Home</span>
                    <span v-if="activeTab === 'home'" class="mt-0.5 h-1 w-1 rounded-full bg-white" />
                    <span v-else class="mt-0.5 h-1 w-1" />
                </button>

                <!-- Map -->
                <button
                    class="flex flex-col items-center gap-1 px-3 py-1 transition-colors"
                    :class="activeTab === 'map' ? 'text-white' : 'text-slate-500'"
                    @click="activeTab = 'map'"
                >
                    <Map class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Live Map</span>
                    <span v-if="activeTab === 'map'" class="mt-0.5 h-1 w-1 rounded-full bg-white" />
                    <span v-else class="mt-0.5 h-1 w-1" />
                </button>

                <!-- Center spacer for FAB -->
                <div class="w-16 shrink-0" />

                <!-- History -->
                <button
                    class="flex flex-col items-center gap-1 px-3 py-1 transition-colors"
                    :class="activeTab === 'history' ? 'text-white' : 'text-slate-500'"
                    @click="activeTab = 'history'"
                >
                    <FileText class="h-5 w-5" />
                    <span class="text-[9px] font-medium">History</span>
                    <span v-if="activeTab === 'history'" class="mt-0.5 h-1 w-1 rounded-full bg-white" />
                    <span v-else class="mt-0.5 h-1 w-1" />
                </button>

                <!-- Settings -->
                <button
                    class="flex flex-col items-center gap-1 px-3 py-1 transition-colors"
                    :class="activeTab === 'settings' ? 'text-white' : 'text-slate-500'"
                    @click="activeTab = 'settings'"
                >
                    <Settings class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Settings</span>
                    <span v-if="activeTab === 'settings'" class="mt-0.5 h-1 w-1 rounded-full bg-white" />
                    <span v-else class="mt-0.5 h-1 w-1" />
                </button>

                <!-- ═══ CENTER FAB — Report Button ═════════════════════════ -->
                <button
                    class="report-fab absolute -top-7 left-1/2 flex h-[3.75rem] w-[3.75rem] -translate-x-1/2 items-center justify-center rounded-full shadow-2xl shadow-rose-600/60 transition-transform duration-200 active:scale-90"
                    @click="showReport = true"
                    aria-label="Report an emergency"
                >
                    <!-- Gradient fill -->
                    <span class="absolute inset-0 rounded-full bg-gradient-to-br from-orange-400 via-rose-500 to-pink-600" />
                    <!-- Pulse ring -->
                    <span class="absolute inset-0 rounded-full bg-rose-500/50 animate-ping" />
                    <!-- Icon -->
                    <Plus class="relative z-10 h-7 w-7 text-white" />
                </button>
            </div>
        </nav>

        <!-- ═══ AI REPORT CHAT OVERLAY ════════════════════════════════════ -->
        <AIReportChat
            v-if="showReport"
            :user="user"
            @close="showReport = false"
        />
    </div>
</template>

<style scoped>
.report-fab::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(251, 146, 60, 0.3), rgba(244, 63, 94, 0.3));
    z-index: -1;
}
</style>
