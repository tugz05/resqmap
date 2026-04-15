<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthSplitResqmapLayout from '@/layouts/auth/AuthSplitResqmapLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({ layout: AuthSplitResqmapLayout });

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <!-- Page heading -->
    <div class="mb-7">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Welcome back
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Log in to access your ResQMap dashboard
        </p>
    </div>

    <!-- Success status (e.g. after password reset) -->
    <div
        v-if="status"
        class="mb-5 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-950/40 dark:text-green-400"
    >
        <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <!-- Email -->
        <div class="grid gap-1.5">
            <Label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                Email address
            </Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="email@example.com"
                class="h-11 rounded-xl border-slate-200 bg-slate-50 px-4 text-sm transition-all focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-red-500 dark:focus:bg-slate-800"
            />
            <InputError :message="errors.email" />
        </div>

        <!-- Password -->
        <div class="grid gap-1.5">
            <div class="flex items-center justify-between">
                <Label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Password
                </Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                    :tabindex="5"
                >
                    Forgot password?
                </TextLink>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Enter your password"
                class="h-11 rounded-xl border-slate-200 bg-slate-50 px-4 text-sm transition-all focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-red-500 dark:focus:bg-slate-800"
            />
            <InputError :message="errors.password" />
        </div>

        <!-- Remember me -->
        <div class="flex items-center gap-2.5">
            <Checkbox id="remember" name="remember" :tabindex="3" />
            <Label for="remember" class="cursor-pointer text-sm text-slate-600 dark:text-slate-400">
                Keep me signed in
            </Label>
        </div>

        <!-- Submit -->
        <Button
            type="submit"
            :tabindex="4"
            :disabled="processing"
            class="h-11 w-full rounded-xl bg-red-600 text-sm font-semibold text-white shadow-md shadow-red-500/20 transition-all duration-200 hover:bg-red-700 hover:shadow-lg hover:shadow-red-500/30 disabled:opacity-60"
            data-test="login-button"
        >
            <Spinner v-if="processing" class="mr-2" />
            {{ processing ? 'Signing in…' : 'Sign in to ResQMap' }}
        </Button>

        <!-- Divider -->
        <div class="relative flex items-center gap-3">
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800" />
            <span class="text-xs text-slate-400">or</span>
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800" />
        </div>

        <!-- Sign up link -->
        <div v-if="canRegister" class="text-center text-sm text-slate-600 dark:text-slate-400">
            Don't have an account?
            <TextLink
                :href="register()"
                class="font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                :tabindex="6"
            >
                Create a free account
            </TextLink>
        </div>
    </Form>
</template>
