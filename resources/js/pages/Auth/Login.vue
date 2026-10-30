<script>
export default { layout: null };
</script>

<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import { ensureCsrf, errorMessage } from '../../lib/api';

const form = reactive({ username: '', password: '', remember: false });
const error = ref('');
const busy = ref(false);
const demo = computed(() => usePage().props.demo);

// On the public demo, one click signs in with a shared login.
function signInAs(username) {
    Object.assign(form, { username, password: demo.value.password });
    submit();
}

async function submit() {
    busy.value = true;
    error.value = '';
    try {
        await ensureCsrf();
        await axios.post('/login', form, { withXSRFToken: true, headers: { Accept: 'application/json' } });
        router.visit('/');
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Sign in" />
    <div class="grid min-h-screen lg:grid-cols-2">
        <section class="hidden flex-col justify-between bg-sidebar p-12 text-sidebar-fg lg:flex">
            <div class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-lg bg-accent font-bold text-accent-ink">h</span>
                <span class="text-lg font-semibold tracking-tight text-white">hris</span>
            </div>
            <div class="max-w-md">
                <p class="text-3xl leading-tight font-semibold tracking-tight text-white">People, time and pay, computed the way Philippine law reads.</p>
                <ul class="mt-8 space-y-3 text-sm">
                    <li class="flex gap-3"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent" />SSS, PhilHealth, Pag-IBIG and BIR withholding on every payslip</li>
                    <li class="flex gap-3"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent" />Holiday and overtime premiums from the DOLE matrix</li>
                    <li class="flex gap-3"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-accent" />13th month, final pay, Form 2316 and the 1604-C alphalist</li>
                </ul>
            </div>
            <p class="text-xs text-sidebar-fg/60">Check figures against the current issuances before filing.</p>
        </section>

        <section class="flex items-center justify-center px-6 py-12">
            <form class="w-full max-w-sm" @submit.prevent="submit">
                <div class="mb-8 flex items-center gap-2.5 lg:hidden">
                    <span class="grid size-9 place-items-center rounded-lg bg-accent font-bold text-accent-ink">h</span>
                    <span class="text-lg font-semibold tracking-tight">hris</span>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">Sign in</h1>
                <p class="mt-1 text-sm text-muted">{{ demo ? 'This is a demo with a fictional company. Pick a role to look around.' : 'Use the username your administrator gave you.' }}</p>

                <div v-if="demo" class="mt-6 space-y-2">
                    <button v-for="l in demo.logins" :key="l.username" type="button" class="flex w-full items-center justify-between gap-3 rounded-lg border border-line px-3.5 py-3 text-left hover:border-accent hover:bg-accent-soft" :disabled="busy" @click="signInAs(l.username)">
                        <span><span class="block text-sm font-semibold">{{ l.role }}</span><span class="text-xs text-muted">{{ l.does }}</span></span>
                        <span class="text-xs font-medium text-accent">Sign in →</span>
                    </button>
                    <p class="pt-1 text-xs text-muted">Everything resets every night at {{ demo.resetAt }} (Manila time). Or sign in below with username <span class="font-mono">admin</span>, <span class="font-mono">approver</span> or <span class="font-mono">employee</span> and password <span class="font-mono">{{ demo.password }}</span>.</p>
                </div>

                <div v-if="error" class="mt-6 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ error }}</div>

                <label class="label mt-6" for="username">Username</label>
                <input id="username" v-model="form.username" class="input" autocomplete="username" required autofocus />
                <label class="label mt-4" for="password">Password</label>
                <input id="password" v-model="form.password" type="password" class="input" autocomplete="current-password" required />
                <label class="mt-4 flex items-center gap-2 text-sm text-muted"><input v-model="form.remember" type="checkbox" class="accent-[var(--accent)]" />Keep me signed in</label>

                <button class="btn-primary mt-6 w-full" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
            </form>
        </section>
    </div>
</template>
