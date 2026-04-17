<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Activity,
    Badge,
    Briefcase,
    Clock,
    Heart,
    MapPin,
    Phone,
    RefreshCw,
    Search,
    ShieldCheck,
    Siren,
    Truck,
    Users as UsersIcon,
    Wrench,
    Zap,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Responder = {
    id: number;
    name: string;
    email: string;
    agency: string | null;
    unit: string | null;
    badge: string | null;
    contact: string | null;
    specialization: string | null;
    is_active: boolean;
    is_online: boolean;
    last_seen_at: string | null;
    active_assignments: number;
    completed_assignments: number;
    latitude?: number | null;
    longitude?: number | null;
};

type AgencyStat = {
    agency: string;
    count: number;
    online: number;
};

const props = defineProps<{
    responders: Responder[];
    metrics: {
        total: number;
        active: number;
        online: number;
        busy: number;
        available: number;
        avg_load: number;
    };
    agencies: AgencyStat[];
    specializations: Array<{ name: string; count: number }>;
}>();

const search = ref('');
const statusFilter = ref<'all' | 'available' | 'busy' | 'offline'>('all');
const agencyFilter = ref<string>('all');

const statusOf = (r: Responder): 'available' | 'busy' | 'offline' => {
    if (!r.is_online) return 'offline';
    if (r.active_assignments > 0) return 'busy';
    return 'available';
};

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.responders.filter((r) => {
        if (statusFilter.value !== 'all' && statusOf(r) !== statusFilter.value) return false;
        if (agencyFilter.value !== 'all' && r.agency !== agencyFilter.value) return false;
        if (!q) return true;
        return [r.name, r.email, r.agency, r.unit, r.specialization, r.badge]
            .filter(Boolean)
            .some((f) => (f as string).toLowerCase().includes(q));
    });
});

const statusTheme: Record<string, { bg: string; ring: string; label: string; text: string; dot: string }> = {
    available: { bg: 'bg-emerald-500', ring: 'ring-emerald-300 dark:ring-emerald-700', label: 'Available', text: 'text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500' },
    busy: { bg: 'bg-amber-500', ring: 'ring-amber-300 dark:ring-amber-700', label: 'On Mission', text: 'text-amber-700 dark:text-amber-300', dot: 'bg-amber-500' },
    offline: { bg: 'bg-slate-400', ring: 'ring-slate-300 dark:ring-slate-700', label: 'Offline', text: 'text-slate-600 dark:text-slate-300', dot: 'bg-slate-400' },
};

function initials(name: string): string {
    return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

function timeAgo(iso?: string | null): string {
    if (!iso) return 'never';
    const diff = Math.max(0, Date.now() - new Date(iso).getTime());
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function refresh(): void {
    router.reload({ only: ['responders', 'metrics', 'agencies', 'specializations'] });
}
</script>

<template>
    <Head title="ResQMap · Resources &amp; Responders" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <!-- Top Bar -->
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/85">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/30">
                        <Truck class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Resources &amp; Responders</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Rescuer fleet · Agencies · Specializations</p>
                    </div>
                </div>
                <Button variant="outline" size="sm" @click="refresh">
                    <RefreshCw class="mr-2 h-3.5 w-3.5" />Refresh
                </Button>
            </div>

            <!-- KPI chips -->
            <div class="grid grid-cols-2 gap-3 px-4 pb-3 md:grid-cols-6 md:px-6">
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total rescuers</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white">{{ metrics.total }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Online</p>
                    <p class="text-xl font-bold text-emerald-900 dark:text-emerald-200">{{ metrics.online }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Available</p>
                    <p class="text-xl font-bold text-emerald-900 dark:text-emerald-200">{{ metrics.available }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">On mission</p>
                    <p class="text-xl font-bold text-amber-900 dark:text-amber-200">{{ metrics.busy }}</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Active overall</p>
                    <p class="text-xl font-bold text-indigo-900 dark:text-indigo-200">{{ metrics.active }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Avg load</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white">{{ metrics.avg_load }}</p>
                </div>
            </div>
        </header>

        <!-- Body -->
        <div class="space-y-4 p-4 md:p-6">
            <!-- Agency + Specialization row -->
            <section class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2">
                        <Briefcase class="h-4 w-4 text-slate-500" />
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Agencies</h2>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="a in agencies" :key="a.agency" class="group">
                            <div class="flex items-center justify-between text-xs">
                                <span class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ a.agency }}</span>
                                <span class="shrink-0 text-slate-500">{{ a.online }}/{{ a.count }} online</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" :style="{ width: `${a.count > 0 ? (a.online / a.count) * 100 : 0}%` }" />
                            </div>
                        </div>
                        <p v-if="agencies.length === 0" class="text-center text-xs text-slate-500">No agencies registered.</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2">
                        <Wrench class="h-4 w-4 text-slate-500" />
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Specializations</h2>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-for="s in specializations" :key="s.name" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <Heart class="h-3 w-3 text-rose-500" />
                            {{ s.name }}
                            <span class="rounded-full bg-white px-1.5 text-[10px] font-bold text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ s.count }}</span>
                        </span>
                        <p v-if="specializations.length === 0" class="text-xs text-slate-500">No specializations set.</p>
                    </div>
                </div>
            </section>

            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="relative min-w-[240px] flex-1">
                    <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                    <Input v-model="search" placeholder="Search by name, agency, specialization…" class="h-9 pl-8" />
                </div>
                <div class="flex gap-1 rounded-full bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        v-for="s in [
                            { key: 'all', label: 'All', count: metrics.total },
                            { key: 'available', label: 'Available', count: metrics.available },
                            { key: 'busy', label: 'On Mission', count: metrics.busy },
                            { key: 'offline', label: 'Offline', count: metrics.total - metrics.online },
                        ]"
                        :key="s.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="statusFilter === s.key
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'"
                        @click="statusFilter = s.key as typeof statusFilter"
                    >
                        {{ s.label }}
                        <span class="rounded-full bg-slate-200 px-1.5 text-[10px] dark:bg-slate-700">{{ s.count }}</span>
                    </button>
                </div>
                <select v-model="agencyFilter" class="h-9 rounded-md border border-slate-200 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="all">All agencies</option>
                    <option v-for="a in agencies" :key="a.agency" :value="a.agency">{{ a.agency }}</option>
                </select>
            </div>

            <!-- Roster grid -->
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="r in filtered"
                    :key="r.id"
                    class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="relative bg-gradient-to-br from-emerald-500/10 via-transparent to-transparent p-4 dark:from-emerald-900/20">
                        <div class="flex items-start gap-3">
                            <div class="relative">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 ring-2 ring-white dark:ring-slate-900">
                                    {{ initials(r.name) }}
                                </div>
                                <span class="absolute -right-0.5 -bottom-0.5 h-3.5 w-3.5 rounded-full border-2 border-white dark:border-slate-900" :class="statusTheme[statusOf(r)].dot" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-bold text-slate-900 dark:text-white">{{ r.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ r.agency ?? 'Independent' }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                    <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase text-white" :class="statusTheme[statusOf(r)].bg">
                                        {{ statusTheme[statusOf(r)].label }}
                                    </span>
                                    <span v-if="r.is_active" class="inline-flex items-center gap-0.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        <ShieldCheck class="h-2.5 w-2.5" />Active
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                        <dl class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <Wrench class="mr-0.5 inline h-2.5 w-2.5" />Specialty
                                </dt>
                                <dd class="mt-0.5 truncate text-xs font-semibold text-slate-800 dark:text-slate-100">
                                    {{ r.specialization ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <Badge class="mr-0.5 inline h-2.5 w-2.5" />Badge
                                </dt>
                                <dd class="mt-0.5 truncate text-xs font-semibold text-slate-800 dark:text-slate-100">
                                    {{ r.badge ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <Siren class="mr-0.5 inline h-2.5 w-2.5" />Active ops
                                </dt>
                                <dd class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ r.active_assignments }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <Activity class="mr-0.5 inline h-2.5 w-2.5" />Completed
                                </dt>
                                <dd class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ r.completed_assignments }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500">
                            <span><Clock class="mr-0.5 inline h-3 w-3" />{{ timeAgo(r.last_seen_at) }}</span>
                            <span v-if="r.latitude && r.longitude" class="inline-flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400">
                                <MapPin class="h-3 w-3" />GPS
                            </span>
                        </div>

                        <div class="mt-3 flex gap-1.5">
                            <Button variant="outline" size="sm" class="flex-1">
                                <Phone class="mr-1 h-3 w-3" />Call
                            </Button>
                            <Button variant="outline" size="sm" class="flex-1">
                                <Zap class="mr-1 h-3 w-3" />Assign
                            </Button>
                        </div>
                    </div>
                </article>
                <div v-if="filtered.length === 0" class="col-span-full flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center dark:border-slate-700 dark:bg-slate-900">
                    <UsersIcon class="h-10 w-10 text-slate-400" />
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No responders match your filters</p>
                </div>
            </section>
        </div>
    </div>
</template>
