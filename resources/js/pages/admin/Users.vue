<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    CheckCircle2,
    Mail,
    RefreshCw,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    UserPlus,
    Users as UsersIcon,
    UserCheck,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'rescuer' | 'resident';
    email_verified: boolean;
    created_at?: string | null;
    last_seen_at?: string | null;
    is_online?: boolean;
    agency?: string | null;
    specialization?: string | null;
    barangay?: string | null;
    active_assignments?: number;
    reported_incidents?: number;
};

const props = defineProps<{
    users: UserRow[];
    metrics: {
        total: number;
        admins: number;
        rescuers: number;
        residents: number;
        verified: number;
        unverified: number;
        online_rescuers: number;
    };
}>();

const search = ref('');
const roleFilter = ref<'all' | 'admin' | 'rescuer' | 'resident'>('all');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.users.filter((u) => {
        const okRole = roleFilter.value === 'all' || u.role === roleFilter.value;
        if (!okRole) return false;
        if (!q) return true;
        return [u.name, u.email, u.agency, u.barangay].filter(Boolean).some((f) => (f as string).toLowerCase().includes(q));
    });
});

const roleTone: Record<string, string> = {
    admin: 'bg-gradient-to-br from-indigo-500 to-violet-600',
    rescuer: 'bg-gradient-to-br from-emerald-500 to-teal-600',
    resident: 'bg-gradient-to-br from-sky-500 to-blue-600',
};

const rolePill: Record<string, string> = {
    admin: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    rescuer: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    resident: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
};

const roleIcon: Record<string, typeof Shield> = {
    admin: Shield,
    rescuer: ShieldCheck,
    resident: UsersIcon,
};

function initials(name: string): string {
    return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

function refresh(): void {
    router.reload({ only: ['users', 'metrics'] });
}
</script>

<template>
    <Head title="ResQMap · Users &amp; Roles" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <!-- Top Bar -->
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/85">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/30">
                        <UsersIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Users &amp; Roles</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Manage admins, rescuers, and residents</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="refresh">
                        <RefreshCw class="mr-2 h-3.5 w-3.5" />Refresh
                    </Button>
                    <Button size="sm" disabled>
                        <UserPlus class="mr-2 h-3.5 w-3.5" />Invite user
                    </Button>
                </div>
            </div>

            <!-- KPI chips -->
            <div class="grid grid-cols-2 gap-3 px-4 pb-3 md:grid-cols-6 md:px-6">
                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total users</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white">{{ metrics.total }}</p>
                </div>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Admins</p>
                    <p class="text-xl font-bold text-indigo-900 dark:text-indigo-200">{{ metrics.admins }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Rescuers</p>
                    <p class="text-xl font-bold text-emerald-900 dark:text-emerald-200">
                        {{ metrics.rescuers }}<span class="text-xs text-emerald-600 dark:text-emerald-400"> ({{ metrics.online_rescuers }} online)</span>
                    </p>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-900/50 dark:bg-sky-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Residents</p>
                    <p class="text-xl font-bold text-sky-900 dark:text-sky-200">{{ metrics.residents }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Email verified</p>
                    <p class="text-xl font-bold text-emerald-900 dark:text-emerald-200">{{ metrics.verified }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Pending verification</p>
                    <p class="text-xl font-bold text-amber-900 dark:text-amber-200">{{ metrics.unverified }}</p>
                </div>
            </div>
        </header>

        <!-- Body -->
        <div class="space-y-4 p-4 md:p-6">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="relative min-w-[240px] flex-1">
                    <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                    <Input v-model="search" placeholder="Search by name, email, agency, barangay…" class="h-9 pl-8" />
                </div>
                <div class="flex gap-1 rounded-full bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        v-for="role in [
                            { key: 'all', label: 'All', count: metrics.total },
                            { key: 'admin', label: 'Admins', count: metrics.admins },
                            { key: 'rescuer', label: 'Rescuers', count: metrics.rescuers },
                            { key: 'resident', label: 'Residents', count: metrics.residents },
                        ]"
                        :key="role.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="roleFilter === role.key
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'"
                        @click="roleFilter = role.key as typeof roleFilter"
                    >
                        {{ role.label }}
                        <span class="rounded-full bg-slate-200 px-1.5 text-[10px] dark:bg-slate-700">{{ role.count }}</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/60 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left">User</th>
                                <th class="px-4 py-3 text-left">Role</th>
                                <th class="px-4 py-3 text-left">Details</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="user in filtered" :key="user.id" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white" :class="roleTone[user.role]">
                                            {{ initials(user.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900 dark:text-white">{{ user.name }}</p>
                                            <p class="truncate text-xs text-slate-500">
                                                <Mail class="mr-0.5 inline h-3 w-3" />{{ user.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="rolePill[user.role]">
                                        <component :is="roleIcon[user.role]" class="h-2.5 w-2.5" />{{ user.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                    <template v-if="user.role === 'rescuer'">
                                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ user.agency ?? '—' }}</p>
                                        <p class="text-slate-500">{{ user.specialization ?? 'Unassigned' }}</p>
                                    </template>
                                    <template v-else-if="user.role === 'resident'">
                                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ user.barangay ?? '—' }}</p>
                                        <p class="text-slate-500">{{ user.reported_incidents ?? 0 }} reports</p>
                                    </template>
                                    <template v-else>
                                        <p class="text-slate-500">System administrator</p>
                                    </template>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span v-if="user.email_verified" class="inline-flex items-center gap-0.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                            <CheckCircle2 class="h-2.5 w-2.5" />Verified
                                        </span>
                                        <span v-else class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                            <ShieldAlert class="h-2.5 w-2.5" />Unverified
                                        </span>
                                        <span v-if="user.role === 'rescuer' && user.is_online" class="inline-flex items-center gap-0.5 rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />online
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-slate-500">
                                    <template v-if="user.role === 'rescuer'">
                                        <p><UserCheck class="inline h-3 w-3" /> {{ user.active_assignments ?? 0 }} active</p>
                                    </template>
                                    <template v-else-if="user.role === 'resident'">
                                        <p>{{ user.reported_incidents ?? 0 }} reports</p>
                                    </template>
                                    <template v-else>
                                        <p>—</p>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="filtered.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">
                                    No users match your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
