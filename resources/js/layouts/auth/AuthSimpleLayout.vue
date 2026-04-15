<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-white p-6 dark:bg-slate-950 md:p-10"
    >
        <!-- Card -->
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <!-- Logo + Title -->
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-3 transition-opacity hover:opacity-80"
                    >
                        <img
                            src="/images/logo/resqmap.png"
                            alt="ResQMap"
                            class="h-14 w-auto drop-shadow-sm"
                        />
                        <span
                            class="text-lg font-extrabold tracking-tight text-slate-900 dark:text-white"
                        >
                            ResQMap
                        </span>
                    </Link>

                    <div v-if="title || description" class="space-y-1.5 text-center">
                        <h1
                            v-if="title"
                            class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <!-- Form slot -->
                <slot />
            </div>
        </div>
    </div>
</template>
