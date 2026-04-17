<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Bell,
    Bot,
    Cog,
    Database,
    Globe,
    KeyRound,
    Languages,
    MapPinned,
    Mic,
    Navigation,
    Radio,
    Save,
    ShieldCheck,
    Sparkles,
    Zap,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    settings: {
        app_name: string;
        app_env: string;
        timezone: string;
        ai: {
            truth_verifier_enabled: boolean;
            operations_agent_enabled: boolean;
            tts_enabled: boolean;
            openai_model: string;
            openai_configured: boolean;
        };
        dispatch: {
            search_radius_km: number;
            max_candidates: number;
            fresh_window_minutes: number;
        };
        map: {
            default_latitude: number;
            default_longitude: number;
            default_zoom: number;
        };
        counts: {
            incidents: number;
            users: number;
            rescuers: number;
        };
    };
}>();
</script>

<template>
    <Head title="ResQMap · System Settings" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <!-- Top Bar -->
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/85">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 text-white shadow-lg">
                        <Cog class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">System Settings</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Platform configuration &amp; AI agents</p>
                    </div>
                </div>
                <Button size="sm" disabled>
                    <Save class="mr-2 h-3.5 w-3.5" />Save changes
                </Button>
            </div>
        </header>

        <div class="space-y-4 p-4 md:p-6">
            <!-- Environment info -->
            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <Globe class="h-3.5 w-3.5" />Application
                    </div>
                    <p class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ settings.app_name }}</p>
                    <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-600">{{ settings.app_env }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <Database class="h-3.5 w-3.5" />Data volume
                    </div>
                    <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                        <span class="font-bold text-slate-900 dark:text-white">{{ settings.counts.incidents }}</span> incidents ·
                        <span class="font-bold text-slate-900 dark:text-white">{{ settings.counts.users }}</span> users
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ settings.counts.rescuers }} rescuers active</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <Languages class="h-3.5 w-3.5" />Timezone
                    </div>
                    <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ settings.timezone }}</p>
                </div>
                <div class="rounded-2xl border p-4 shadow-sm" :class="settings.ai.openai_configured
                    ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/30'
                    : 'border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30'">
                    <div class="flex items-center gap-2 text-xs" :class="settings.ai.openai_configured ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                        <KeyRound class="h-3.5 w-3.5" />OpenAI
                    </div>
                    <p class="mt-1 text-sm font-bold" :class="settings.ai.openai_configured ? 'text-emerald-900 dark:text-emerald-100' : 'text-amber-900 dark:text-amber-100'">
                        {{ settings.ai.openai_configured ? 'Connected' : 'API key missing' }}
                    </p>
                    <p class="mt-0.5 text-[11px] font-mono" :class="settings.ai.openai_configured ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                        {{ settings.ai.openai_model }}
                    </p>
                </div>
            </section>

            <!-- AI Agents -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="mb-4 flex items-center gap-2">
                    <Sparkles class="h-4 w-4 text-indigo-500" />
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">AI Agents</h2>
                    <span class="ml-auto rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                        3 agents
                    </span>
                </header>
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 p-4 dark:border-emerald-900/50 dark:from-emerald-950/40 dark:to-teal-950/40">
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="h-4 w-4 text-emerald-600" />
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Truth Verifier</p>
                        </div>
                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">OpenAI vision authenticates incident photos.</p>
                        <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {{ settings.ai.truth_verifier_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-violet-50 p-4 dark:border-indigo-900/50 dark:from-indigo-950/40 dark:to-violet-950/40">
                        <div class="flex items-center gap-2">
                            <Bot class="h-4 w-4 text-indigo-600" />
                            <p class="text-xs font-bold uppercase tracking-wide text-indigo-800 dark:text-indigo-200">Operations Agent</p>
                        </div>
                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">LLM generates urgency, plan, and Cebuano updates.</p>
                        <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-indigo-500/15 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500" />
                            {{ settings.ai.operations_agent_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50 p-4 dark:border-orange-900/50 dark:from-orange-950/40 dark:to-amber-950/40">
                        <div class="flex items-center gap-2">
                            <Navigation class="h-4 w-4 text-orange-600" />
                            <p class="text-xs font-bold uppercase tracking-wide text-orange-800 dark:text-orange-200">Dispatch Agent</p>
                        </div>
                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">Algorithmic nearest-rescuer ranking (Haversine).</p>
                        <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-orange-500/15 px-2 py-0.5 text-[10px] font-bold text-orange-700 dark:text-orange-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-orange-500" />Always on
                        </span>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
                        <div class="flex items-center gap-2">
                            <Mic class="h-4 w-4 text-slate-500" />
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Voice assistant (TTS)</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="settings.ai.tts_enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'">
                            {{ settings.ai.tts_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
                        <div class="flex items-center gap-2">
                            <Bell class="h-4 w-4 text-slate-500" />
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Incident notifications</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            Enabled
                        </span>
                    </div>
                </div>
            </section>

            <!-- Dispatch config -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="mb-4 flex items-center gap-2">
                    <Radio class="h-4 w-4 text-orange-500" />
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Dispatch Parameters</h2>
                </header>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <Label for="radius" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Search radius (km)</Label>
                        <Input id="radius" type="number" :model-value="settings.dispatch.search_radius_km" disabled class="mt-1" />
                        <p class="mt-1 text-[11px] text-slate-500">Maximum distance to consider nearby rescuers.</p>
                    </div>
                    <div>
                        <Label for="candidates" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Max candidates</Label>
                        <Input id="candidates" type="number" :model-value="settings.dispatch.max_candidates" disabled class="mt-1" />
                        <p class="mt-1 text-[11px] text-slate-500">Top-N rescuers returned per incident.</p>
                    </div>
                    <div>
                        <Label for="fresh" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Online window (min)</Label>
                        <Input id="fresh" type="number" :model-value="settings.dispatch.fresh_window_minutes" disabled class="mt-1" />
                        <p class="mt-1 text-[11px] text-slate-500">How recent a GPS ping counts as "online".</p>
                    </div>
                </div>
            </section>

            <!-- Map defaults -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="mb-4 flex items-center gap-2">
                    <MapPinned class="h-4 w-4 text-emerald-500" />
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Map &amp; Geofence</h2>
                </header>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <Label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Default latitude</Label>
                        <Input :model-value="settings.map.default_latitude" disabled class="mt-1 font-mono" />
                    </div>
                    <div>
                        <Label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Default longitude</Label>
                        <Input :model-value="settings.map.default_longitude" disabled class="mt-1 font-mono" />
                    </div>
                    <div>
                        <Label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Default zoom</Label>
                        <Input :model-value="settings.map.default_zoom" disabled class="mt-1 font-mono" />
                    </div>
                </div>
            </section>

            <!-- Footer notice -->
            <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
                <Zap class="mt-0.5 h-5 w-5 text-amber-600" />
                <div>
                    <p class="text-sm font-bold text-amber-900 dark:text-amber-100">Read-only configuration view</p>
                    <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-200">
                        Values are loaded from <code class="rounded bg-amber-100 px-1 py-0.5 font-mono dark:bg-amber-900/50">.env</code> and config files.
                        Live editing will be wired up in a future release.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
