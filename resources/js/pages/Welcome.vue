<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { login, register } from '@/routes';
import {
    Activity,
    AlertCircle,
    ArrowRight,
    Bot,
    CheckCircle2,
    ChevronDown,
    Clock,
    MapPin,
    Menu,
    Mic,
    Moon,
    Radio,
    Shield,
    Sun,
    Users,
    X,
    Zap,
} from 'lucide-vue-next';

withDefaults(defineProps<{ canRegister: boolean }>(), { canRegister: true });

const { resolvedAppearance, updateAppearance } = useAppearance();
const isDark = computed(() => resolvedAppearance.value === 'dark');
const toggleDark = () => updateAppearance(isDark.value ? 'light' : 'dark');

const mobileMenuOpen = ref(false);
const scrollTo = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
    mobileMenuOpen.value = false;
};

const heroGradient = computed(() =>
    isDark.value
        ? 'radial-gradient(ellipse 70% 60% at 50% 50%, transparent 30%, #020617 75%)'
        : 'radial-gradient(ellipse 70% 60% at 50% 50%, transparent 30%, white 75%)',
);

const floatingIncidents = [
    { type: 'Fire Incident',   location: 'Brgy. Poblacion, Cebu',    time: '1 min ago',  color: 'border-red-500/60 bg-red-500/8',    dot: 'bg-red-500',    badge: 'bg-red-500/15 text-red-400' },
    { type: 'Flood Alert',     location: 'Brgy. San Jose, Davao',    time: '4 min ago',  color: 'border-amber-500/60 bg-amber-500/8', dot: 'bg-amber-400',  badge: 'bg-amber-500/15 text-amber-400' },
    { type: 'Medical Response',location: 'Brgy. Magsaysay, Iloilo',  time: '9 min ago',  color: 'border-blue-500/60 bg-blue-500/8',  dot: 'bg-blue-400',   badge: 'bg-blue-500/15 text-blue-400' },
    { type: 'Resolved ✓',     location: 'Brgy. Luna, CDO',          time: '18 min ago', color: 'border-green-500/60 bg-green-500/8', dot: 'bg-green-500',  badge: 'bg-green-500/15 text-green-400' },
];
</script>

<template>
    <Head title="ResQMap — Disaster & Incident Reporting Platform" />

    <div class="min-h-screen scroll-smooth bg-white text-slate-900 antialiased dark:bg-[#020617] dark:text-slate-100">

        <!-- ══════════════════════════ NAVBAR ══════════════════════════ -->
        <header class="fixed top-0 z-50 w-full">
            <!-- Frosted glass bar -->
            <div class="border-b border-slate-200/60 bg-white/75 backdrop-blur-xl dark:border-white/6 dark:bg-slate-950/75">
                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-2.5">
                        <img src="/images/logo/resqmap.png" alt="ResQMap" class="h-8 w-auto sm:h-9" />
                        <span class="text-lg font-extrabold tracking-tight">ResQMap</span>
                    </div>

                    <nav class="hidden items-center gap-1 md:flex">
                        <button @click="scrollTo('features')" class="nav-link">Features</button>
                        <button @click="scrollTo('how-it-works')" class="nav-link">How It Works</button>
                        <button @click="scrollTo('roles')" class="nav-link">User Roles</button>
                    </nav>

                    <div class="flex items-center gap-2">
                        <button
                            @click="toggleDark"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/8 dark:hover:text-white"
                            :aria-label="isDark ? 'Light mode' : 'Dark mode'"
                        >
                            <Sun v-if="isDark" class="h-4.5 w-4.5" />
                            <Moon v-else class="h-4.5 w-4.5" />
                        </button>
                        <div class="hidden items-center gap-2 sm:flex">
                            <Link :href="login()" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/8">
                                Log in
                            </Link>
                            <Link v-if="canRegister" :href="register()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:bg-red-700 hover:shadow-lg hover:shadow-red-500/25">
                                Get started
                            </Link>
                        </div>
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 md:hidden dark:text-slate-400"
                            aria-label="Menu"
                        >
                            <X v-if="mobileMenuOpen" class="h-5 w-5" />
                            <Menu v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div v-if="mobileMenuOpen" class="border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/95 md:hidden">
                    <nav class="flex flex-col gap-1">
                        <button @click="scrollTo('features')" class="rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/6">Features</button>
                        <button @click="scrollTo('how-it-works')" class="rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/6">How It Works</button>
                        <button @click="scrollTo('roles')" class="rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/6">User Roles</button>
                        <div class="mt-2 flex gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                            <Link :href="login()" class="flex-1 rounded-lg border border-slate-300 py-2.5 text-center text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-300">Log in</Link>
                            <Link v-if="canRegister" :href="register()" class="flex-1 rounded-lg bg-red-600 py-2.5 text-center text-sm font-semibold text-white">Get started</Link>
                        </div>
                    </nav>
                </div>
            </Transition>
        </header>

        <!-- ══════════════════════════ HERO ══════════════════════════ -->
        <section class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden pt-16">

            <!-- Gradient mesh background -->
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.1)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.1)_1px,transparent_1px)] bg-[size:52px_52px] dark:bg-[linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)]" />
                <!-- Color orbs -->
                <div class="absolute top-1/4 left-1/4 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-red-500/8 blur-3xl dark:bg-red-500/12" />
                <div class="absolute bottom-1/4 right-1/4 h-80 w-80 translate-x-1/2 translate-y-1/2 rounded-full bg-blue-500/6 blur-3xl dark:bg-indigo-500/10" />
                <div class="absolute top-1/2 left-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-red-500/5 blur-2xl dark:bg-red-600/8" />
            </div>

            <!-- Radar rings -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="radar-ring absolute h-[180px] w-[180px] rounded-full border border-red-500/25" style="animation-delay:0s" />
                <div class="radar-ring absolute h-[340px] w-[340px] rounded-full border border-red-500/16" style="animation-delay:1s" />
                <div class="radar-ring absolute h-[500px] w-[500px] rounded-full border border-red-500/10" style="animation-delay:2s" />
                <div class="radar-ring absolute h-[660px] w-[660px] rounded-full border border-red-500/6" style="animation-delay:3s" />
                <div class="radar-ring absolute h-[820px] w-[820px] rounded-full border border-red-500/3" style="animation-delay:4s" />
            </div>

            <!-- Vignette overlay -->
            <div class="pointer-events-none absolute inset-0" :style="{ background: heroGradient }" />

            <!-- Floating incident cards — left (desktop only) -->
            <div class="absolute left-4 top-1/2 hidden -translate-y-1/2 space-y-3 xl:block" style="margin-left: 2rem">
                <div
                    v-for="(inc, i) in floatingIncidents.slice(0, 2)"
                    :key="i"
                    :class="['float-card rounded-2xl border p-3 backdrop-blur-md', inc.color]"
                    :style="{ animationDelay: `${i * 0.4}s` }"
                >
                    <div class="mb-1.5 flex items-center gap-2">
                        <span :class="['h-2 w-2 rounded-full', inc.dot]" />
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ inc.type }}</span>
                        <span :class="['ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-semibold', inc.badge]">LIVE</span>
                    </div>
                    <div class="text-[11px] text-slate-600 dark:text-slate-400">{{ inc.location }}</div>
                    <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-600">{{ inc.time }}</div>
                </div>
            </div>

            <!-- Floating incident cards — right (desktop only) -->
            <div class="absolute right-4 top-1/2 hidden -translate-y-1/2 space-y-3 xl:block" style="margin-right: 2rem">
                <div
                    v-for="(inc, i) in floatingIncidents.slice(2)"
                    :key="i"
                    :class="['float-card rounded-2xl border p-3 backdrop-blur-md', inc.color]"
                    :style="{ animationDelay: `${(i + 2) * 0.4}s` }"
                >
                    <div class="mb-1.5 flex items-center gap-2">
                        <span :class="['h-2 w-2 rounded-full', inc.dot]" />
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ inc.type }}</span>
                    </div>
                    <div class="text-[11px] text-slate-600 dark:text-slate-400">{{ inc.location }}</div>
                    <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-600">{{ inc.time }}</div>
                </div>
            </div>

            <!-- Center content -->
            <div class="relative z-10 mx-auto max-w-3xl px-4 text-center sm:px-6">
                <!-- Live badge -->
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-red-300/60 bg-red-50/80 px-4 py-1.5 text-sm font-semibold text-red-700 backdrop-blur-sm dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-400">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500" />
                    </span>
                    Emergency Response Platform · Philippines
                </div>

                <!-- Logo -->
                <div class="mb-6 flex justify-center">
                    <img src="/images/logo/resqmap.png" alt="ResQMap" class="h-20 w-auto drop-shadow-xl sm:h-24 lg:h-28" />
                </div>

                <!-- Headline with gradient -->
                <h1 class="mb-5 text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
                    Respond Faster.<br />
                    <span class="hero-gradient-text">Save Lives.</span>
                </h1>

                <p class="mx-auto mb-10 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-400 sm:text-xl">
                    A geotagged disaster and incident reporting platform uniting
                    <strong class="font-semibold text-slate-900 dark:text-slate-200">residents</strong>,
                    <strong class="font-semibold text-slate-900 dark:text-slate-200">rescuers</strong>,
                    and <strong class="font-semibold text-slate-900 dark:text-slate-200">administrators</strong>
                    for faster emergency response with
                    <strong class="font-semibold text-red-600 dark:text-red-400">AI-assisted triage</strong>
                    and
                    <strong class="font-semibold text-red-600 dark:text-red-400">voice-powered reporting</strong>.
                </p>

                <!-- CTA -->
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <!-- Pulsing Report button -->
                    <div class="relative">
                        <span class="btn-beacon btn-beacon-1" />
                        <span class="btn-beacon btn-beacon-2" />
                        <Link
                            :href="register()"
                            class="report-btn relative flex items-center gap-3 rounded-full bg-gradient-to-r from-red-600 to-red-700 px-8 py-4 text-base font-bold text-white shadow-2xl shadow-red-500/40 transition-all duration-300 hover:scale-105 hover:shadow-red-500/60 sm:text-lg"
                        >
                            <MapPin class="h-5 w-5 sm:h-6 sm:w-6" />
                            Report an Incident
                        </Link>
                    </div>

                    <Link
                        :href="login()"
                        class="flex items-center gap-2 rounded-full border border-slate-300 bg-white/70 px-8 py-4 text-base font-semibold text-slate-700 shadow-sm backdrop-blur-sm transition-all duration-300 hover:border-slate-400 hover:bg-white hover:shadow-md dark:border-white/15 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10 sm:text-lg"
                    >
                        Log in to Dashboard
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>

                <!-- Scroll hint -->
                <button
                    @click="scrollTo('features')"
                    class="mt-14 flex animate-bounce flex-col items-center gap-1.5 text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-300"
                    aria-label="Scroll down"
                >
                    <span class="text-[11px] font-semibold uppercase tracking-widest">Explore</span>
                    <ChevronDown class="h-5 w-5" />
                </button>
            </div>
        </section>

        <!-- ══════════════════════════ STATS ══════════════════════════ -->
        <section class="border-y border-slate-200 bg-slate-50/80 py-8 dark:border-white/6 dark:bg-white/2">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-6 text-center md:grid-cols-4">
                    <div v-for="stat in [
                        { value: '3', label: 'Dedicated Roles', icon: Users },
                        { value: 'GPS', label: 'Pinpoint Accuracy', icon: MapPin },
                        { value: 'AI', label: 'Assisted Triage', icon: Bot },
                        { value: '24/7', label: 'Always Available', icon: Clock },
                    ]" :key="stat.label" class="stat-card rounded-2xl px-4 py-5">
                        <component :is="stat.icon" class="mx-auto mb-2 h-5 w-5 text-red-500" />
                        <div class="text-2xl font-extrabold text-red-600 sm:text-3xl">{{ stat.value }}</div>
                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════ FEATURES ══════════════════════════ -->
        <section id="features" class="py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto mb-14 max-w-2xl text-center">
                    <div class="section-eyebrow">Platform Features</div>
                    <h2 class="section-heading">Everything you need for emergency management</h2>
                    <p class="section-sub">A comprehensive platform for rapid incident reporting, real-time coordination, and efficient response management.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="feat in [
                        { icon: MapPin,      color: 'red',    title: 'Geotagged Reporting',     desc: 'Pin exact incident locations on an interactive map with precise GPS coordinates for instant dispatching.' },
                        { icon: Bot,        color: 'amber',  title: 'AI-Assisted Reporting',   desc: 'ResQBot guides residents with smart follow-up questions so reports are complete, clear, and actionable.' },
                        { icon: Shield,     color: 'blue',   title: 'Multi-role Access',       desc: 'Purpose-built dashboards for Residents, Rescuers, and Administrators with exactly the tools each needs.' },
                        { icon: Users,      color: 'green',  title: 'Rescuer Dispatch',        desc: 'Assign and track rescuers to active incidents with full operational visibility across all teams.' },
                        { icon: Mic,        color: 'purple', title: 'Voice-Powered Input',     desc: 'Residents can speak incident details hands-free while AI captures and structures critical information.' },
                        { icon: Clock,      color: 'slate',  title: 'Response Timeline',       desc: 'Track every step from report submission to on-scene arrival and final resolution.' },
                    ]" :key="feat.title"
                        class="feature-card group relative rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-white/6 dark:bg-white/3"
                    >
                        <!-- Glow on hover -->
                        <div :class="[
                            'absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-300 group-hover:opacity-100',
                            feat.color === 'red'    ? 'bg-red-500/4'    : '',
                            feat.color === 'amber'  ? 'bg-amber-500/4'  : '',
                            feat.color === 'blue'   ? 'bg-blue-500/4'   : '',
                            feat.color === 'green'  ? 'bg-green-500/4'  : '',
                            feat.color === 'purple' ? 'bg-purple-500/4' : '',
                            feat.color === 'slate'  ? 'bg-slate-500/3'  : '',
                        ]" />
                        <div :class="[
                            'relative mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl',
                            feat.color === 'red'    ? 'bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-400'       : '',
                            feat.color === 'amber'  ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400': '',
                            feat.color === 'blue'   ? 'bg-blue-100 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400'   : '',
                            feat.color === 'green'  ? 'bg-green-100 text-green-600 dark:bg-green-950/60 dark:text-green-400': '',
                            feat.color === 'purple' ? 'bg-purple-100 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400': '',
                            feat.color === 'slate'  ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'  : '',
                        ]">
                            <component :is="feat.icon" class="h-5 w-5" />
                        </div>
                        <h3 class="relative mb-2 font-semibold text-slate-900 dark:text-slate-100">{{ feat.title }}</h3>
                        <p class="relative text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════ HOW IT WORKS ══════════════════════════ -->
        <section id="how-it-works" class="relative overflow-hidden py-20 lg:py-28">
            <!-- Section bg -->
            <div class="absolute inset-0 bg-slate-50/80 dark:bg-white/2" />
            <div class="absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.07)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.07)_1px,transparent_1px)] bg-[size:40px_40px] dark:bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)]" />

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto mb-14 max-w-2xl text-center">
                    <div class="section-eyebrow">The Process</div>
                    <h2 class="section-heading">How ResQMap Works</h2>
                    <p class="section-sub">From incident to resolution — a clear, coordinated process every step of the way.</p>
                </div>

                <div class="relative">
                    <!-- Connector -->
                    <div class="absolute top-11 left-1/2 hidden h-0.5 w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-red-400/40 to-transparent lg:block dark:via-red-600/30" />

                    <div class="grid gap-10 md:grid-cols-3 md:gap-6">
                        <div v-for="(step, i) in [
                            { icon: MapPin, color: 'red',   title: 'Resident Reports',          desc: 'A resident spots an emergency and submits a geotagged report with details and photos through ResQMap.' },
                            { icon: Shield, color: 'amber', title: 'Admin Verifies & Dispatches',desc: 'Administrator reviews, validates, and dispatches the nearest available rescuers immediately.' },
                            { icon: CheckCircle2, color: 'green', title: 'Rescuers Respond',     desc: 'Rescuers receive the assignment, navigate to location, and update status live until resolved.' },
                        ]" :key="step.title"
                            class="flex flex-col items-center text-center"
                        >
                            <div class="relative mb-6">
                                <div :class="[
                                    'step-orb flex h-24 w-24 items-center justify-center rounded-full border-4 text-white shadow-xl',
                                    step.color === 'red'   ? 'border-red-200/60 bg-red-600 shadow-red-500/30 dark:border-red-900/50'    : '',
                                    step.color === 'amber' ? 'border-amber-200/60 bg-amber-500 shadow-amber-500/30 dark:border-amber-900/50' : '',
                                    step.color === 'green' ? 'border-green-200/60 bg-green-600 shadow-green-500/30 dark:border-green-900/50' : '',
                                ]">
                                    <component :is="step.icon" class="h-10 w-10" />
                                </div>
                                <span class="absolute -top-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white shadow-md dark:bg-white dark:text-slate-900">
                                    {{ i + 1 }}
                                </span>
                            </div>
                            <h3 class="mb-3 text-lg font-bold">{{ step.title }}</h3>
                            <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════ USER ROLES ══════════════════════════ -->
        <section id="roles" class="py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto mb-14 max-w-2xl text-center">
                    <div class="section-eyebrow">Who It's For</div>
                    <h2 class="section-heading">Three Roles, One Mission</h2>
                    <p class="section-sub">ResQMap provides dedicated tools for every stakeholder in the emergency response chain.</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Resident -->
                    <div class="role-card rounded-2xl border border-slate-200 bg-white p-8 dark:border-white/8 dark:bg-white/3">
                        <div class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-500/25">
                            <Users class="h-7 w-7" />
                        </div>
                        <div class="mb-1 text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Community</div>
                        <h3 class="mb-2 text-xl font-bold">Resident</h3>
                        <p class="mb-5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Community members who report incidents and stay informed about nearby emergencies.</p>
                        <ul class="space-y-2.5 text-sm">
                            <li v-for="item in ['Submit geotagged incident reports','Track report status in real-time','View active incidents on the map','Receive emergency notifications']" :key="item" class="flex items-center gap-2.5 text-slate-700 dark:text-slate-300">
                                <CheckCircle2 class="h-4 w-4 shrink-0 text-green-500" />{{ item }}
                            </li>
                        </ul>
                    </div>

                    <!-- Rescuer (featured) -->
                    <div class="role-card relative rounded-2xl border-2 border-red-500 bg-white p-8 shadow-xl shadow-red-500/12 dark:bg-white/3">
                        <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-red-600 to-red-700 px-4 py-1 text-xs font-bold tracking-wide text-white shadow-md shadow-red-500/30">
                            Emergency Responder
                        </div>
                        <div class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-red-500 to-red-700 text-white shadow-lg shadow-red-500/30">
                            <Shield class="h-7 w-7" />
                        </div>
                        <div class="mb-1 text-xs font-bold uppercase tracking-widest text-red-600 dark:text-red-400">Field Response</div>
                        <h3 class="mb-2 text-xl font-bold">Rescuer</h3>
                        <p class="mb-5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Trained responders dispatched to active incidents with real-time navigation and tools.</p>
                        <ul class="space-y-2.5 text-sm">
                            <li v-for="item in ['Receive dispatched assignments','Navigate to incident location','Update incident status live','Log detailed response reports']" :key="item" class="flex items-center gap-2.5 text-slate-700 dark:text-slate-300">
                                <CheckCircle2 class="h-4 w-4 shrink-0 text-green-500" />{{ item }}
                            </li>
                        </ul>
                    </div>

                    <!-- Admin -->
                    <div class="role-card rounded-2xl border border-slate-200 bg-white p-8 dark:border-white/8 dark:bg-white/3">
                        <div class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 text-white shadow-lg shadow-slate-900/25">
                            <Activity class="h-7 w-7" />
                        </div>
                        <div class="mb-1 text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Operations</div>
                        <h3 class="mb-2 text-xl font-bold">Administrator</h3>
                        <p class="mb-5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">System managers overseeing all incidents, user management, and platform analytics.</p>
                        <ul class="space-y-2.5 text-sm">
                            <li v-for="item in ['Monitor all active incidents','Manage users & rescuer teams','Verify & dispatch responses','Generate analytics reports']" :key="item" class="flex items-center gap-2.5 text-slate-700 dark:text-slate-300">
                                <CheckCircle2 class="h-4 w-4 shrink-0 text-green-500" />{{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════ ALERT BANNER ══════════════════════════ -->
        <div class="border-y border-amber-200 bg-amber-50 py-4 dark:border-amber-900/30 dark:bg-amber-900/10">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-3 px-4 text-center sm:px-6">
                <AlertCircle class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                    In an emergency, use ResQMap to instantly report your location and incident details. Every second counts.
                </p>
            </div>
        </div>

        <!-- ══════════════════════════ CTA ══════════════════════════ -->
        <section class="relative overflow-hidden bg-red-600 py-20 dark:bg-gradient-to-br dark:from-red-950 dark:via-red-900 dark:to-slate-950">
            <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:48px_48px]" />
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="radar-ring absolute h-[250px] w-[250px] rounded-full border border-white/12" style="animation-delay:0s" />
                <div class="radar-ring absolute h-[450px] w-[450px] rounded-full border border-white/6" style="animation-delay:1.5s" />
                <div class="radar-ring absolute h-[650px] w-[650px] rounded-full border border-white/3" style="animation-delay:3s" />
            </div>
            <div class="absolute -top-32 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-white/5 blur-3xl" />

            <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-white/80 backdrop-blur-sm">
                    <Zap class="h-3.5 w-3.5" />
                    Ready when you need it
                </div>
                <h2 class="mb-4 text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    Be Prepared.<br class="sm:hidden" /> Be Responsive.
                </h2>
                <p class="mx-auto mb-10 max-w-xl text-lg leading-relaxed text-red-100">
                    Join ResQMap today and become part of a smarter, faster, and more coordinated emergency response network.
                </p>
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link v-if="canRegister" :href="register()"
                        class="rounded-full bg-white px-9 py-4 text-base font-bold text-red-600 shadow-xl shadow-black/20 transition-all duration-300 hover:scale-105 hover:bg-red-50 sm:text-lg">
                        Create Your Account
                    </Link>
                    <Link :href="login()"
                        class="flex items-center gap-2 rounded-full border-2 border-white/30 px-9 py-4 text-base font-semibold text-white transition-all duration-300 hover:border-white hover:bg-white/10 sm:text-lg">
                        Log in to ResQMap
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════ FOOTER ══════════════════════════ -->
        <footer class="border-t border-slate-200 bg-white py-8 dark:border-white/6 dark:bg-[#020617]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
                    <div class="flex items-center gap-2.5">
                        <img src="/images/logo/resqmap.png" alt="ResQMap" class="h-7 w-auto" />
                        <span class="font-bold">ResQMap</span>
                    </div>
                    <p class="text-center text-xs text-slate-400 sm:text-sm">
                        © {{ new Date().getFullYear() }} ResQMap · Geotagged Disaster &amp; Incident Reporting Platform
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* ── Utility classes ───────────────────────────────────── */
.nav-link {
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #475569;
    transition: color 0.15s, background 0.15s;
}
.nav-link:hover {
    background: #f1f5f9;
    color: #0f172a;
}
:global(.dark) .nav-link {
    color: #94a3b8;
}
:global(.dark) .nav-link:hover {
    background: rgba(255,255,255,0.06);
    color: #f1f5f9;
}
.section-eyebrow {
    margin-bottom: 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #dc2626;
}
:global(.dark) .section-eyebrow { color: #ef4444; }

.section-heading {
    margin-bottom: 1rem;
    font-size: clamp(1.75rem, 4vw, 2.25rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    color: #0f172a;
}
:global(.dark) .section-heading { color: #fff; }

.section-sub {
    font-size: 1.125rem;
    color: #64748b;
}
:global(.dark) .section-sub { color: #94a3b8; }

/* ── Hero gradient text ────────────────────────────────── */
.hero-gradient-text {
    background: linear-gradient(135deg, #dc2626 0%, #f97316 60%, #dc2626 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    background-size: 200% auto;
    animation: gradient-shift 4s linear infinite;
}

@keyframes gradient-shift {
    0%   { background-position: 0% center; }
    100% { background-position: 200% center; }
}

/* ── Radar rings ────────────────────────────────────────── */
.radar-ring {
    animation: radar-expand 5s ease-out infinite;
}

@keyframes radar-expand {
    0%   { opacity: 0.9; transform: scale(0.3); }
    75%  { opacity: 0; }
    100% { opacity: 0; transform: scale(1.8); }
}

/* ── CTA beacon rings ───────────────────────────────────── */
.btn-beacon {
    position: absolute;
    border-radius: 9999px;
    background: rgba(220, 38, 38, 0.4);
    animation: beacon-pulse 2.4s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.btn-beacon-1 { inset: -12px; animation-delay: 0s; }
.btn-beacon-2 { inset: -22px; background: rgba(220,38,38,0.2); animation-delay: 0.8s; }

@keyframes beacon-pulse {
    0%   { opacity: 0.85; transform: scale(0.88); }
    100% { opacity: 0;    transform: scale(1.45); }
}

/* ── Report button shine ────────────────────────────────── */
.report-btn::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 50%);
    opacity: 0.6;
}

/* ── Floating incident cards ────────────────────────────── */
.float-card {
    width: 200px;
    animation: float-drift 6s ease-in-out infinite;
}

@keyframes float-drift {
    0%, 100% { transform: translateY(0px); }
    50%       { transform: translateY(-10px); }
}

/* ── Feature card hover ─────────────────────────────────── */
.feature-card {
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 40px -12px rgba(0,0,0,0.12);
    border-color: rgba(220,38,38,0.3);
}
:global(.dark) .feature-card:hover {
    box-shadow: 0 16px 40px -12px rgba(0,0,0,0.5);
    border-color: rgba(220,38,38,0.25);
}

/* ── Step orb pulse ─────────────────────────────────────── */
.step-orb {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.step-orb:hover {
    transform: scale(1.08);
}

/* ── Stat card ──────────────────────────────────────────── */
.stat-card {
    transition: background 0.2s;
}
.stat-card:hover {
    background: rgba(239, 68, 68, 0.04);
}
:global(.dark) .stat-card:hover {
    background: rgba(239, 68, 68, 0.06);
}

/* ── Role card ──────────────────────────────────────────── */
.role-card {
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.role-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 48px -16px rgba(0,0,0,0.15);
}
:global(.dark) .role-card:hover {
    box-shadow: 0 20px 48px -16px rgba(0,0,0,0.6);
}
</style>
