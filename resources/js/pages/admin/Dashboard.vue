<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import incidents from '@/routes/admin/incidents';
import {
    Activity,
    ArrowDown,
    ArrowUp,
    BarChart3,
    Clock3,
    MapPin,
    Minus,
    Navigation,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

type DailyPoint = { date: string; label: string; weekday: string; count: number };
type TypeBreakdown = { key: string; label: string; icon: string; count: number };
type SeverityBreakdown = { key: string; label: string; count: number };
type StatusBreakdown = { key: string; label: string; count: number };
type BarangayRow = { barangay: string; city?: string | null; count: number };

const props = defineProps<{
    period: { label: string; window: string };
    kpi: {
        total_all: number;
        active: number;
        resolved: number;
        resolution_rate: number;
        reports_7d: number;
        reports_prev_7d: number;
        reports_trend_pct: number | null;
        critical_30d: number;
    };
    responseTimes: {
        report_to_verify: number | null;
        verify_to_dispatch: number | null;
        dispatch_to_resolve: number | null;
        end_to_end: number | null;
    };
    dailyTrend: DailyPoint[];
    incidentsByType: TypeBreakdown[];
    incidentsBySeverity: SeverityBreakdown[];
    incidentsByStatus: StatusBreakdown[];
    topBarangays: BarangayRow[];
    aiStats: {
        verified_count: number;
        avg_confidence: number;
        verdict_distribution: Array<{ verdict: string; count: number }>;
        dispatch_runs: number;
        dispatch_avg_km: number | null;
        dispatch_avg_eta: number | null;
    };
    rescuerStats: {
        total: number;
        online: number;
        busy: number;
        available: number;
    };
    coverage: { municipalities: number; barangays: number; provinces: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: adminDashboard(),
            },
        ],
    },
});

const maxDaily = computed(() => Math.max(1, ...props.dailyTrend.map((p) => p.count)));

const trendIcon = computed(() => {
    if (props.kpi.reports_trend_pct === null) return Minus;
    if (props.kpi.reports_trend_pct > 0) return ArrowUp;
    if (props.kpi.reports_trend_pct < 0) return ArrowDown;
    return Minus;
});

const trendTone = computed(() => {
    if (props.kpi.reports_trend_pct === null) return 'text-slate-500';
    if (props.kpi.reports_trend_pct > 0) return 'text-rose-600 dark:text-rose-400';
    if (props.kpi.reports_trend_pct < 0) return 'text-emerald-600 dark:text-emerald-400';
    return 'text-slate-500';
});

const severityPalette: Record<string, string> = {
    low: 'bg-sky-500',
    medium: 'bg-amber-500',
    high: 'bg-orange-500',
    critical: 'bg-red-600',
};

const statusPalette: Record<string, string> = {
    pending: 'bg-amber-500',
    verified: 'bg-blue-500',
    dispatched: 'bg-violet-500',
    en_route: 'bg-indigo-500',
    on_scene: 'bg-teal-500',
    resolved: 'bg-emerald-500',
    cancelled: 'bg-slate-400',
};

const typePalette = [
    'from-rose-500 to-rose-600',
    'from-amber-500 to-orange-500',
    'from-emerald-500 to-teal-600',
    'from-sky-500 to-indigo-600',
    'from-violet-500 to-purple-600',
    'from-cyan-500 to-blue-600',
    'from-pink-500 to-rose-600',
    'from-slate-400 to-slate-600',
];

const totalByType = computed(() => props.incidentsByType.reduce((s, t) => s + t.count, 0));
const totalBySeverity = computed(() => props.incidentsBySeverity.reduce((s, t) => s + t.count, 0));
const totalByStatus = computed(() => props.incidentsByStatus.reduce((s, t) => s + t.count, 0));
const topBarangayMax = computed(() => Math.max(1, ...props.topBarangays.map((r) => r.count)));

// Donut via conic-gradient for severity
const severityDonut = computed(() => {
    const palette = ['#0ea5e9', '#f59e0b', '#f97316', '#dc2626'];
    let cursor = 0;
    const stops: string[] = [];
    props.incidentsBySeverity.forEach((seg, i) => {
        if (totalBySeverity.value === 0) return;
        const pct = (seg.count / totalBySeverity.value) * 100;
        stops.push(`${palette[i % palette.length]} ${cursor}% ${cursor + pct}%`);
        cursor += pct;
    });
    if (stops.length === 0) {
        return 'conic-gradient(#e2e8f0 0% 100%)';
    }
    return `conic-gradient(${stops.join(', ')})`;
});

const aiVerdictTone: Record<string, string> = {
    true: 'bg-emerald-500',
    false: 'bg-rose-500',
    uncertain: 'bg-amber-500',
};

function formatMinutes(value: number | null): string {
    if (value === null) return '—';
    if (value < 60) return `${value}m`;
    const h = Math.floor(value / 60);
    const m = value % 60;
    return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

function refresh(): void {
    router.reload();
}

// SVG sparkline for daily trend
const sparkPath = computed(() => {
    if (props.dailyTrend.length === 0) return '';
    const w = 100;
    const h = 40;
    const step = w / Math.max(1, props.dailyTrend.length - 1);
    return props.dailyTrend
        .map((point, i) => {
            const x = i * step;
            const y = h - (point.count / maxDaily.value) * (h - 4) - 2;
            return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
        })
        .join(' ');
});
</script>

<template>
    <Head title="ResQMap · Analytics Dashboard" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <!-- Hero header -->
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/85">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 md:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30">
                        <BarChart3 class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Analytics &amp; Insights</h1>
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                                {{ period.label }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Live operational intelligence for ResQMap Command Center
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="incidents.index().url">
                        <Button variant="outline" size="sm">
                            <Activity class="mr-2 h-3.5 w-3.5" />
                            Go to Incident Queue
                        </Button>
                    </Link>
                    <Button variant="outline" size="sm" @click="refresh">
                        <RefreshCw class="mr-2 h-3.5 w-3.5" />
                        Refresh
                    </Button>
                </div>
            </div>
        </header>

        <div class="space-y-6 p-4 md:p-6">
            <!-- Primary KPI Row -->
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <!-- Reports this week -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reports this week</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ kpi.reports_7d }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                            <TrendingUp class="h-5 w-5" />
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-xs">
                        <span :class="['inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 font-semibold', trendTone]">
                            <component :is="trendIcon" class="h-3 w-3" />
                            <span v-if="kpi.reports_trend_pct !== null">{{ Math.abs(kpi.reports_trend_pct) }}%</span>
                            <span v-else>no prior data</span>
                        </span>
                        <span class="text-slate-500">vs last week ({{ kpi.reports_prev_7d }})</span>
                    </div>
                    <!-- Sparkline -->
                    <svg v-if="dailyTrend.length" class="mt-3 h-10 w-full" viewBox="0 0 100 40" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="sparkGrad" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1" stop-opacity="0.4" />
                                <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="`${sparkPath} L 100 40 L 0 40 Z`" fill="url(#sparkGrad)" />
                        <path :d="sparkPath" fill="none" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <!-- Resolution rate -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Resolution rate</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ kpi.resolution_rate }}%</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ kpi.resolved }} resolved of {{ kpi.total_all }} total
                    </p>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" :style="{ width: `${kpi.resolution_rate}%` }" />
                    </div>
                </div>

                <!-- Avg end-to-end response -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Avg. response time</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{{ formatMinutes(responseTimes.end_to_end) }}</p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                            <Clock3 class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Report to resolution</p>
                    <div class="mt-3 grid grid-cols-3 gap-1 text-center">
                        <div class="rounded-md bg-slate-50 px-1 py-1.5 dark:bg-slate-800/50">
                            <p class="text-[9px] uppercase tracking-wide text-slate-500">Verify</p>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ formatMinutes(responseTimes.report_to_verify) }}</p>
                        </div>
                        <div class="rounded-md bg-slate-50 px-1 py-1.5 dark:bg-slate-800/50">
                            <p class="text-[9px] uppercase tracking-wide text-slate-500">Dispatch</p>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ formatMinutes(responseTimes.verify_to_dispatch) }}</p>
                        </div>
                        <div class="rounded-md bg-slate-50 px-1 py-1.5 dark:bg-slate-800/50">
                            <p class="text-[9px] uppercase tracking-wide text-slate-500">Resolve</p>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ formatMinutes(responseTimes.dispatch_to_resolve) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Rescuer Fleet -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Rescuer fleet</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">
                                {{ rescuerStats.online }}<span class="text-lg text-slate-400">/{{ rescuerStats.total }}</span>
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-300">
                            <Users class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">online now</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-md bg-emerald-50 px-2 py-1.5 dark:bg-emerald-950/30">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Available</p>
                            <p class="text-sm font-bold text-emerald-800 dark:text-emerald-200">{{ rescuerStats.available }}</p>
                        </div>
                        <div class="rounded-md bg-amber-50 px-2 py-1.5 dark:bg-amber-950/30">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">On mission</p>
                            <p class="text-sm font-bold text-amber-800 dark:text-amber-200">{{ rescuerStats.busy }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Secondary stats chips -->
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                        <Activity class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Active incidents</p>
                        <p class="text-xl font-bold text-slate-900 dark:text-white">{{ kpi.active }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-red-600 text-white">
                        <Sparkles class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Critical (30d)</p>
                        <p class="text-xl font-bold text-slate-900 dark:text-white">{{ kpi.critical_30d }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white">
                        <MapPin class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Coverage</p>
                        <p class="text-xl font-bold text-slate-900 dark:text-white">{{ coverage.municipalities }} cities · {{ coverage.barangays }} brgy</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white">
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">AI confidence avg</p>
                        <p class="text-xl font-bold text-slate-900 dark:text-white">{{ aiStats.avg_confidence }}%</p>
                    </div>
                </div>
            </section>

            <!-- Charts Row 1: Daily trend + Severity donut -->
            <section class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <!-- Daily trend (bars) -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Reports over the last 7 days</h2>
                            <p class="text-xs text-slate-500">Daily incident count trend</p>
                        </div>
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                            7-day view
                        </span>
                    </div>
                    <div class="mt-6 flex h-52 items-end gap-2">
                        <div
                            v-for="point in dailyTrend"
                            :key="point.date"
                            class="group flex flex-1 flex-col items-center gap-1.5"
                        >
                            <span class="text-[10px] font-semibold text-slate-500">{{ point.count }}</span>
                            <div
                                class="relative w-full overflow-hidden rounded-t-lg bg-gradient-to-t from-indigo-500 to-violet-500 transition group-hover:from-indigo-600 group-hover:to-violet-600"
                                :style="{ height: `${Math.max(6, (point.count / maxDaily) * 100)}%` }"
                            >
                                <div class="absolute inset-x-0 top-0 h-1/3 bg-white/10" />
                            </div>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ point.weekday }}</span>
                            <span class="text-[9px] text-slate-400">{{ point.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- Severity Donut -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">By severity</h2>
                    <p class="text-xs text-slate-500">Last 30 days</p>

                    <div class="mt-4 flex items-center justify-center">
                        <div
                            class="relative flex h-40 w-40 items-center justify-center rounded-full"
                            :style="{ background: severityDonut }"
                        >
                            <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white dark:bg-slate-900">
                                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ totalBySeverity }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">incidents</p>
                            </div>
                        </div>
                    </div>

                    <ul class="mt-5 space-y-2">
                        <li v-for="sev in incidentsBySeverity" :key="sev.key" class="flex items-center gap-3 text-sm">
                            <span class="h-2.5 w-2.5 rounded-full" :class="severityPalette[sev.key] ?? 'bg-slate-400'" />
                            <span class="flex-1 capitalize text-slate-700 dark:text-slate-200">{{ sev.label }}</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ sev.count }}</span>
                            <span class="w-10 text-right text-xs text-slate-500">
                                {{ totalBySeverity ? Math.round((sev.count / totalBySeverity) * 100) : 0 }}%
                            </span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Charts Row 2: Type breakdown + Status funnel -->
            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">By incident type</h2>
                    <p class="text-xs text-slate-500">Top reported categories</p>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(type, i) in incidentsByType"
                            :key="type.key"
                            class="group"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ type.icon }}</span>
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ type.label }}</span>
                                </div>
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    {{ type.count }}
                                    <span class="text-slate-400">
                                        · {{ totalByType ? Math.round((type.count / totalByType) * 100) : 0 }}%
                                    </span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r transition-all"
                                    :class="typePalette[i % typePalette.length]"
                                    :style="{ width: `${totalByType ? (type.count / totalByType) * 100 : 0}%` }"
                                />
                            </div>
                        </div>
                        <p v-if="incidentsByType.length === 0" class="py-6 text-center text-sm text-slate-500">
                            No incidents in this period.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Status funnel</h2>
                    <p class="text-xs text-slate-500">How reports move through the pipeline</p>

                    <div class="mt-4 space-y-2">
                        <div
                            v-for="stat in incidentsByStatus"
                            :key="stat.key"
                            class="flex items-center gap-3"
                        >
                            <span class="w-24 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                {{ stat.label }}
                            </span>
                            <div class="flex-1 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="flex h-7 items-center justify-end rounded-lg px-2 text-xs font-bold text-white transition-all"
                                    :class="statusPalette[stat.key] ?? 'bg-slate-400'"
                                    :style="{ width: `${Math.max(3, totalByStatus ? (stat.count / totalByStatus) * 100 : 0)}%` }"
                                >
                                    <span v-if="stat.count > 0">{{ stat.count }}</span>
                                </div>
                            </div>
                            <span class="w-10 text-right text-xs text-slate-500">
                                {{ totalByStatus ? Math.round((stat.count / totalByStatus) * 100) : 0 }}%
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Charts Row 3: AI Performance + Top Barangays -->
            <section class="grid gap-4 xl:grid-cols-2">
                <!-- AI Performance panel -->
                <div class="overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-5 shadow-sm dark:border-indigo-900 dark:from-indigo-950/40 dark:via-slate-900 dark:to-violet-950/40">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white">
                            <Sparkles class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">AI Agent Performance</h2>
                            <p class="text-xs text-slate-500">Verification + Dispatch activity</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-xl bg-white p-3 dark:bg-slate-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Verifications</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ aiStats.verified_count }}</p>
                            <p class="text-[11px] text-slate-500">AI image checks run</p>
                        </div>
                        <div class="rounded-xl bg-white p-3 dark:bg-slate-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Avg. confidence</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ aiStats.avg_confidence }}%</p>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" :style="{ width: `${aiStats.avg_confidence}%` }" />
                            </div>
                        </div>
                        <div class="rounded-xl bg-white p-3 dark:bg-slate-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Dispatch runs</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ aiStats.dispatch_runs }}</p>
                            <p class="text-[11px] text-slate-500">Nearest-rescuer rankings</p>
                        </div>
                        <div class="rounded-xl bg-white p-3 dark:bg-slate-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Avg. nearest</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                {{ aiStats.dispatch_avg_km !== null ? `${aiStats.dispatch_avg_km} km` : '—' }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                ETA {{ aiStats.dispatch_avg_eta !== null ? `${aiStats.dispatch_avg_eta} min` : '—' }}
                            </p>
                        </div>
                    </div>

                    <!-- Verdict distribution -->
                    <div class="mt-4 rounded-xl bg-white p-3 dark:bg-slate-900">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Verdict distribution</p>
                        <div class="mt-2 flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <template v-for="v in aiStats.verdict_distribution" :key="v.verdict">
                                <div
                                    v-if="aiStats.verified_count > 0"
                                    :class="aiVerdictTone[v.verdict] ?? 'bg-slate-400'"
                                    :style="{ width: `${(v.count / aiStats.verified_count) * 100}%` }"
                                    class="h-full"
                                />
                            </template>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-3 text-xs">
                            <div v-for="v in aiStats.verdict_distribution" :key="v.verdict" class="flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="aiVerdictTone[v.verdict] ?? 'bg-slate-400'" />
                                <span class="capitalize text-slate-700 dark:text-slate-200">{{ v.verdict }}</span>
                                <span class="font-bold text-slate-900 dark:text-white">{{ v.count }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Barangays -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Hotspot barangays</h2>
                            <p class="text-xs text-slate-500">Top areas by report volume</p>
                        </div>
                        <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                            Risk map
                        </span>
                    </div>

                    <ol class="mt-4 space-y-2">
                        <li
                            v-for="(row, idx) in topBarangays"
                            :key="`${row.barangay}|${row.city}`"
                            class="flex items-center gap-3"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-bold text-white"
                                :class="idx === 0
                                    ? 'bg-gradient-to-br from-rose-500 to-red-600'
                                    : idx === 1
                                      ? 'bg-gradient-to-br from-orange-500 to-amber-500'
                                      : idx === 2
                                        ? 'bg-gradient-to-br from-amber-500 to-yellow-500'
                                        : 'bg-slate-300 text-slate-700 dark:bg-slate-700 dark:text-slate-200'"
                            >
                                {{ idx + 1 }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ row.barangay }}
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    <MapPin class="inline h-3 w-3" /> {{ row.city ?? '—' }}
                                </p>
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-rose-500 to-orange-500"
                                        :style="{ width: `${(row.count / topBarangayMax) * 100}%` }"
                                    />
                                </div>
                            </div>
                            <span class="text-lg font-bold text-slate-900 dark:text-white">{{ row.count }}</span>
                        </li>
                        <li v-if="topBarangays.length === 0" class="py-6 text-center text-sm text-slate-500">
                            No location data in this period.
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Navigation shortcuts -->
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link :href="incidents.index().url" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <Activity class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">All Incidents</p>
                            <p class="text-xs text-slate-500">Manage the full queue</p>
                        </div>
                    </div>
                </Link>
                <Link href="/admin/incident-verification" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">Verification</p>
                            <p class="text-xs text-slate-500">Check report authenticity</p>
                        </div>
                    </div>
                </Link>
                <Link href="/admin/dispatch-management" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-950/50 dark:text-orange-300">
                            <Navigation class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">Dispatch</p>
                            <p class="text-xs text-slate-500">Assign nearest rescuer</p>
                        </div>
                    </div>
                </Link>
                <Link href="/admin/responder-coordination" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                            <Users class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">Coordination</p>
                            <p class="text-xs text-slate-500">Track live field ops</p>
                        </div>
                    </div>
                </Link>
            </section>
        </div>
    </div>
</template>
