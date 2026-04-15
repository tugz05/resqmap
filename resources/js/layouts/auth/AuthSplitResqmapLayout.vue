<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bot, MapPin, Mic, Shield } from 'lucide-vue-next';
import { home } from '@/routes';

const incidents = [
    { type: 'Fire', location: 'Brgy. Poblacion, Cebu City', time: '1 min ago', color: 'bg-red-500', dot: 'bg-red-400' },
    { type: 'Flood Alert', location: 'Brgy. San Jose, Davao City', time: '4 min ago', color: 'bg-amber-500', dot: 'bg-amber-400' },
    { type: 'Medical', location: 'Brgy. Magsaysay, Iloilo', time: '9 min ago', color: 'bg-blue-500', dot: 'bg-blue-400' },
    { type: 'Landslide', location: 'Brgy. Santa Cruz, CDO', time: '18 min ago', color: 'bg-orange-500', dot: 'bg-orange-400' },
];
</script>

<template>
    <div class="relative min-h-svh overflow-hidden bg-white dark:bg-slate-950 lg:grid lg:grid-cols-2">

        <!-- ═══ LEFT PANEL — Branding (hidden on mobile) ═══ -->
        <div class="relative hidden flex-col overflow-hidden bg-slate-950 lg:flex">
            <!-- Animated grid -->
            <div
                class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:40px_40px]"
            />

            <!-- Radar rings -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="panel-ring absolute h-[150px] w-[150px] rounded-full border border-red-500/20" style="animation-delay:0s" />
                <div class="panel-ring absolute h-[280px] w-[280px] rounded-full border border-red-500/14" style="animation-delay:0.9s" />
                <div class="panel-ring absolute h-[410px] w-[410px] rounded-full border border-red-500/8" style="animation-delay:1.8s" />
                <div class="panel-ring absolute h-[540px] w-[540px] rounded-full border border-red-500/5" style="animation-delay:2.7s" />
            </div>

            <!-- Red glow at center -->
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_50%,rgba(220,38,38,0.12),transparent)]" />

            <!-- Top corner accent -->
            <div class="absolute -top-24 -left-24 h-64 w-64 rounded-full bg-red-600/10 blur-3xl" />
            <div class="absolute -bottom-24 -right-24 h-64 w-64 rounded-full bg-red-900/20 blur-3xl" />

            <!-- Panel content -->
            <div class="relative z-10 flex h-full flex-col px-10 py-10">
                <!-- Logo -->
                <Link :href="home()" class="flex items-center gap-3 group">
                    <img
                        src="/images/logo/resqmap.png"
                        alt="ResQMap"
                        class="h-10 w-auto drop-shadow-lg transition-transform duration-300 group-hover:scale-105"
                    />
                    <span class="text-xl font-extrabold tracking-tight text-white">ResQMap</span>
                </Link>

                <!-- Center block -->
                <div class="my-auto">
                    <!-- Live badge -->
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-semibold text-red-400">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75" />
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-500" />
                        </span>
                        Live Emergency Response Platform
                    </div>

                    <h2 class="mb-4 text-3xl font-extrabold leading-tight tracking-tight text-white xl:text-4xl">
                        Emergency Response<br />
                        <span class="text-red-400">for the Modern</span><br />
                        Community
                    </h2>
                    <p class="mb-8 max-w-sm text-sm leading-relaxed text-slate-400">
                        ResQMap connects residents, rescuers, and administrators on a single platform for faster,
                        more coordinated disaster response.
                    </p>

                    <!-- Features -->
                    <ul class="mb-10 space-y-4">
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-500/15 text-red-400">
                                <MapPin class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white">Geotagged Incident Reports</div>
                                <div class="text-xs text-slate-500">Exact GPS pinpointing for every emergency</div>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-400">
                                <Bot class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white">AI-Assisted Incident Triage</div>
                                <div class="text-xs text-slate-500">ResQBot asks smart follow-up questions for complete reports</div>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400">
                                <Mic class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white">Voice-Powered Reporting</div>
                                <div class="text-xs text-slate-500">Speak naturally while AI converts details into structured reports</div>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400">
                                <Shield class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-white">Instant Rescuer Dispatch</div>
                                <div class="text-xs text-slate-500">Verified reports routed to nearest teams in real time</div>
                            </div>
                        </li>
                    </ul>

                    <!-- Live incident ticker -->
                    <div class="rounded-xl border border-white/8 bg-white/4 p-4 backdrop-blur-sm">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-green-500" />
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-widest text-slate-400">
                                Recent Incidents
                            </span>
                        </div>
                        <ul class="ticker-list space-y-2 overflow-hidden" style="max-height: 120px">
                            <li
                                v-for="(inc, i) in incidents"
                                :key="i"
                                class="ticker-item flex items-center gap-2.5"
                                :style="{ animationDelay: `${i * 1.5}s` }"
                            >
                                <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', inc.dot]" />
                                <span class="text-xs font-medium text-slate-300">{{ inc.type }}</span>
                                <span class="min-w-0 truncate text-xs text-slate-500">{{ inc.location }}</span>
                                <span class="ml-auto shrink-0 text-xs text-slate-600">{{ inc.time }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Footer credit -->
                <p class="text-xs text-slate-700">
                    © {{ new Date().getFullYear() }} ResQMap · All rights reserved
                </p>
            </div>
        </div>

        <!-- ═══ RIGHT PANEL — Form ═══ -->
        <div class="flex min-h-svh flex-col items-center justify-center p-6 lg:min-h-0 lg:p-10">
            <!-- Mobile-only logo -->
            <Link
                :href="home()"
                class="mb-8 flex flex-col items-center gap-2 lg:hidden"
            >
                <img src="/images/logo/resqmap.png" alt="ResQMap" class="h-12 w-auto" />
                <span class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">ResQMap</span>
            </Link>

            <div class="w-full max-w-sm">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.panel-ring {
    animation: panel-radar 5s ease-out infinite;
}

@keyframes panel-radar {
    0%   { opacity: 0.8; transform: scale(0.3); }
    80%  { opacity: 0; }
    100% { opacity: 0; transform: scale(1.8); }
}

.ticker-item {
    animation: ticker-fade-in 0.6s ease forwards;
    opacity: 0;
}

@keyframes ticker-fade-in {
    from { opacity: 0; transform: translateX(-8px); }
    to   { opacity: 1; transform: translateX(0); }
}
</style>
