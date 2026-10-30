<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import Icon from '../components/Icon.vue';
import Feedback from '../components/Feedback.vue';
import { ensureCsrf } from '../lib/api';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const menuOpen = ref(false);

const nav = computed(() => [
    ...(user.value?.isAdmin
        ? [
              { label: 'Overview', href: '/dashboard', icon: 'home' },
              { section: 'People' },
              { label: 'Employees', href: '/people', icon: 'people' },
              { label: 'Leave', href: '/leave', icon: 'calendar' },
              { label: 'Overtime', href: '/overtime', icon: 'clock' },
              { section: 'Payroll' },
              { label: 'Payroll runs', href: '/payroll/runs', icon: 'runs' },
              { label: 'Pay profiles', href: '/payroll/employees', icon: 'slip' },
              { label: 'Loans', href: '/payroll/loans', icon: 'loan' },
              { label: 'Year-end & BIR', href: '/payroll/year-end', icon: 'calendar' },
          ]
        : []),
    { section: 'Me' },
    { label: 'My attendance', href: '/me/attendance', icon: 'clock' },
    ...(user.value?.isAdmin ? [] : [{ label: 'My leave', href: '/leave', icon: 'calendar' }, { label: 'My overtime', href: '/overtime', icon: 'clock' }]),
    { label: 'My payslips', href: '/me/payslips', icon: 'slip' },
]);
const isActive = (href) => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);

const theme = ref(document.documentElement.dataset.theme ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme.value;
    try {
        localStorage.setItem('hris-theme', theme.value);
    } catch {
        /* private window: the choice lasts for this visit */
    }
}

async function signOut() {
    await ensureCsrf();
    await axios.post('/logout', {}, { withXSRFToken: true });
    router.visit('/login');
}
</script>

<template>
    <div class="min-h-screen lg:grid lg:grid-cols-[15rem_1fr]">
        <aside class="fixed inset-y-0 left-0 z-30 flex w-60 -translate-x-full flex-col bg-sidebar text-sidebar-fg transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" :class="{ 'translate-x-0': menuOpen }">
            <div class="flex items-center gap-2.5 px-5 pt-5 pb-6">
                <span class="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-accent-ink">h</span>
                <span class="text-[15px] font-semibold tracking-tight text-white">hris</span>
            </div>
            <nav class="flex flex-1 flex-col gap-0.5 px-3">
                <template v-for="item in nav" :key="item.href ?? item.section">
                    <p v-if="item.section" class="px-3 pt-4 pb-1 text-[11px] font-semibold tracking-wider text-sidebar-fg/50 uppercase">{{ item.section }}</p>
                    <Link v-else :href="item.href" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-sidebar-active hover:text-white" :class="isActive(item.href) ? 'bg-sidebar-active font-medium text-white' : ''" @click="menuOpen = false">
                        <Icon :name="item.icon" :size="17" />{{ item.label }}
                    </Link>
                </template>
            </nav>
            <div class="border-t border-white/10 px-5 py-4 text-xs text-sidebar-fg/70">Philippine payroll · BIR 1604-C ready</div>
        </aside>
        <div v-if="menuOpen" class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="menuOpen = false" />

        <div class="min-w-0">
            <header class="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b border-line bg-bg/85 px-4 backdrop-blur sm:px-6">
                <button class="rounded-md p-1.5 text-muted hover:bg-surface-2 lg:hidden" aria-label="Open menu" @click="menuOpen = true"><Icon name="menu" /></button>
                <div class="flex-1" />
                <button class="rounded-md p-1.5 text-muted hover:bg-surface-2" :aria-label="theme === 'dark' ? 'Light theme' : 'Dark theme'" @click="toggleTheme"><Icon :name="theme === 'dark' ? 'sun' : 'moon'" /></button>
                <div v-if="user" class="flex items-center gap-2.5 border-l border-line pl-3">
                    <span class="grid size-7 place-items-center rounded-full bg-accent-soft text-xs font-semibold text-accent uppercase">{{ user.username?.[0] ?? '?' }}</span>
                    <span class="hidden text-sm sm:inline">{{ user.username }}</span>
                    <button class="rounded-md p-1.5 text-muted hover:bg-surface-2" aria-label="Sign out" @click="signOut"><Icon name="logout" :size="17" /></button>
                </div>
            </header>
            <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8"><slot /></main>
        </div>
        <Feedback />
    </div>
</template>
