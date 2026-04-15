<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthSplitResqmapLayout from '@/layouts/auth/AuthSplitResqmapLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { ShieldCheck } from 'lucide-vue-next';

defineOptions({ layout: AuthSplitResqmapLayout });
</script>

<template>
    <Head title="Register" />

    <!-- Page heading -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Create your account
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Join ResQMap as a Resident — free, fast, and always available.
        </p>
    </div>

    <!-- Resident notice -->
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-blue-200/60 bg-blue-50 px-4 py-3 dark:border-blue-900/40 dark:bg-blue-950/25">
        <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
        <p class="text-xs leading-relaxed text-blue-700 dark:text-blue-300">
            You are registering as a <strong>Resident</strong>. Rescuer and Admin accounts are
            provisioned by system administrators.
        </p>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <!-- Full name -->
        <div class="grid gap-1.5">
            <Label for="name" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                Full name
            </Label>
            <Input
                id="name"
                type="text"
                name="name"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Juan dela Cruz"
                class="h-11 rounded-xl border-slate-200 bg-slate-50 px-4 text-sm transition-all focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-red-500 dark:focus:bg-slate-800"
            />
            <InputError :message="errors.name" />
        </div>

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
                :tabindex="2"
                autocomplete="email"
                placeholder="email@example.com"
                class="h-11 rounded-xl border-slate-200 bg-slate-50 px-4 text-sm transition-all focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-red-500 dark:focus:bg-slate-800"
            />
            <InputError :message="errors.email" />
        </div>

        <!-- Password row -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    placeholder="Create password"
                    class="h-11 rounded-xl border-slate-200 bg-slate-50 px-4 text-sm transition-all focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-red-500 dark:focus:bg-slate-800"
                />
                <InputError :message="errors.password" />
            </div>
            <div class="grid gap-1.5">
                <Label for="password_confirmation" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Confirm
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    placeholder="Repeat password"
                    class="h-11 rounded-xl border-slate-200 bg-slate-50 px-4 text-sm transition-all focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-500/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-red-500 dark:focus:bg-slate-800"
                />
                <InputError :message="errors.password_confirmation" />
            </div>
        </div>

        <!-- Terms -->
        <p class="text-xs text-slate-400 dark:text-slate-500">
            By creating an account you agree to our
            <span class="font-medium text-slate-600 dark:text-slate-400">Terms of Service</span>
            and
            <span class="font-medium text-slate-600 dark:text-slate-400">Privacy Policy</span>.
        </p>

        <!-- Submit -->
        <Button
            type="submit"
            :tabindex="5"
            :disabled="processing"
            class="h-11 w-full rounded-xl bg-red-600 text-sm font-semibold text-white shadow-md shadow-red-500/20 transition-all duration-200 hover:bg-red-700 hover:shadow-lg hover:shadow-red-500/30 disabled:opacity-60"
            data-test="register-user-button"
        >
            <Spinner v-if="processing" class="mr-2" />
            {{ processing ? 'Creating account…' : 'Create free account' }}
        </Button>

        <!-- Divider + sign in link -->
        <div class="relative flex items-center gap-3">
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800" />
            <span class="text-xs text-slate-400">or</span>
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800" />
        </div>

        <div class="text-center text-sm text-slate-600 dark:text-slate-400">
            Already have an account?
            <TextLink
                :href="login()"
                class="font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                :tabindex="6"
            >
                Sign in instead
            </TextLink>
        </div>
    </Form>
</template>
