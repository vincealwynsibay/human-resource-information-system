<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import Icon from '../components/Icon.vue';
import Feedback from '../components/Feedback.vue';
import CommandPalette from '../components/CommandPalette.vue';
import Popover from '../components/Popover.vue';
import { api, ensureCsrf } from '../lib/api';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const company = computed(() => page.props.app?.company ?? 'hris');
const menuOpen = ref(false);

const read = (k, fallback) => {
    try {
        return JSON.parse(localStorage.getItem(k)) ?? fallback;
    } catch {
        return fallback;
    }
};
const write = (k, v) => {
    try {
        localStorage.setItem(k, JSON.stringify(v));
    } catch {
        /* private window: lasts for this visit */
    }
};
const collapsed = ref(read('hris-sidebar-collapsed', false));
const closedGroups = reactive(read('hris-nav-closed', {}));
function toggleSidebar() {
    collapsed.value = !collapsed.value;
    write('hris-sidebar-collapsed', collapsed.value);
}
function toggleGroup(name) {
    closedGroups[name] = !closedGroups[name];
    write('hris-nav-closed', closedGroups);
}

const groups = computed(() => {
    const admin = user.value?.isAdmin;
    const out = [];
    if (admin) {
        out.push({ name: '', items: [{ label: 'Dashboard', href: '/dashboard', icon: 'home' }] });
        out.push({ name: 'People', icon: 'people', items: [
            { label: 'Employees', href: '/people', icon: 'people', add: '/people?new=1' },
            { label: 'Leave', href: '/leave', icon: 'calendar' },
            { label: 'Overtime', href: '/overtime', icon: 'clock' },
        ] });
        out.push({ name: 'Payroll', icon: 'runs', items: [
            { label: 'Payroll runs', href: '/payroll/runs', icon: 'runs', add: '/payroll/runs?new=1' },
            { label: 'Pay profiles', href: '/payroll/employees', icon: 'slip' },
            { label: 'Loans', href: '/payroll/loans', icon: 'loan' },
            { label: 'Remittances & filings', href: '/payroll/filings', icon: 'file' },
            { label: 'Year-end & BIR', href: '/payroll/year-end', icon: 'calendar' },
        ] });
        out.push({ name: 'Admin', icon: 'shield', items: [
            { label: 'Access & roles', href: '/admin/access', icon: 'shield' },
            { label: 'Activity log', href: '/admin/activity', icon: 'list' },
            { label: 'Reference data', href: '/admin/reference', icon: 'book' },
        ] });
    }
    out.push({ name: 'Me', icon: 'user', items: [
        { label: 'My attendance', href: '/me/attendance', icon: 'clock' },
        ...(admin ? [] : [{ label: 'My leave', href: '/leave', icon: 'calendar' }, { label: 'My overtime', href: '/overtime', icon: 'clock' }]),
        { label: 'My payslips', href: '/me/payslips', icon: 'slip' },
    ] });
    return out;
});
const isActive = (href) => {
    const path = page.url.split('?')[0];
    return path === href || path.startsWith(`${href}/`);
};

const createItems = computed(() => (user.value?.isAdmin
    ? [
          { label: 'Payroll run', hint: 'Compute a pay period', href: '/payroll/runs?new=1', icon: 'runs' },
          { label: 'Employee', hint: 'Add someone to the company', href: '/people?new=1', icon: 'people' },
          { label: 'Loan', hint: 'SSS, Pag-IBIG or company loan', href: '/payroll/loans?new=1', icon: 'loan' },
          { label: 'Final pay', hint: 'Separation or retirement', href: '/payroll/year-end?finalPay=1', icon: 'slip' },
      ]
    : [])
    .concat(user.value?.employeeId ? [
        { label: 'Leave request', hint: 'Ask for time off', href: '/leave?new=1', icon: 'calendar' },
        { label: 'Overtime request', hint: 'Before you work extra hours', href: '/overtime?new=1', icon: 'clock' },
    ] : []));

// inbox: what waits on this administrator
const inbox = ref({ items: [], total: 0 });
async function loadInbox() {
    if (!user.value?.isAdmin) return;
    try {
        inbox.value = await api('get', '/payroll/inbox');
    } catch {
        /* the bell just stays empty */
    }
}

const theme = ref(document.documentElement.dataset.theme ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme.value;
    try {
        localStorage.setItem('hris-theme', theme.value);
    } catch {
        /* lasts for this visit */
    }
}

async function signOut() {
    await ensureCsrf();
    await axios.post('/logout', {}, { withXSRFToken: true });
    router.visit('/login');
}

const palette = ref(false);
const onKey = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        palette.value = true;
    }
};
let stopListening;
onMounted(() => {
    window.addEventListener('keydown', onKey);
    loadInbox();
    stopListening = router.on('navigate', loadInbox);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    stopListening?.();
});
const navItems = computed(() => groups.value.flatMap((g) => g.items.map((i) => ({ ...i, group: g.name }))));
</script>

<template>
    <div class="min-h-screen lg:grid" :class="collapsed ? 'lg:grid-cols-[4.25rem_1fr]' : 'lg:grid-cols-[15.5rem_1fr]'">
        <aside class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col bg-sidebar text-sidebar-fg transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0" :class="{ 'translate-x-0': menuOpen }">
            <div class="flex items-center gap-2.5 px-4 pt-4 pb-3" :class="collapsed ? 'lg:justify-center lg:px-2' : ''">
                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent text-sm font-bold text-accent-ink">h</span>
                <div class="min-w-0" :class="collapsed ? 'lg:hidden' : ''">
                    <p class="truncate text-[15px] font-semibold tracking-tight text-white">hris</p>
                    <p class="truncate text-xs text-sidebar-fg/70" :title="company">{{ company }}</p>
                </div>
            </div>
            <button class="mx-3 mb-3 flex items-center gap-2 rounded-lg border border-white/10 px-3 py-1.5 text-left text-xs text-sidebar-fg/70 hover:bg-sidebar-active" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" @click="palette = true">
                <Icon name="search" :size="15" /><span :class="collapsed ? 'lg:hidden' : ''" class="flex-1">Search</span><kbd :class="collapsed ? 'lg:hidden' : ''" class="rounded border border-white/15 px-1 font-sans text-[10px]">Ctrl K</kbd>
            </button>
            <nav class="flex-1 overflow-y-auto px-3 pb-4">
                <div v-for="g in groups" :key="g.name" class="mb-1">
                    <button v-if="g.name" class="flex w-full items-center justify-between px-3 pt-3 pb-1 text-[11px] font-semibold tracking-wider text-sidebar-fg/50 uppercase hover:text-sidebar-fg" :class="collapsed ? 'lg:hidden' : ''" :aria-expanded="!closedGroups[g.name]" @click="toggleGroup(g.name)">
                        {{ g.name }}<Icon name="chevron" :size="13" class="transition-transform" :class="closedGroups[g.name] ? '' : 'rotate-90'" />
                    </button>
                    <div v-if="g.name && collapsed" class="mx-auto my-2 hidden h-px w-6 bg-white/10 lg:block" />
                    <template v-if="!closedGroups[g.name] || collapsed">
                        <div v-for="item in g.items" :key="item.href" class="group relative flex items-center">
                            <Link :href="item.href" class="flex flex-1 items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-sidebar-active hover:text-white" :class="[isActive(item.href) ? 'bg-sidebar-active font-medium text-white' : '', collapsed ? 'lg:justify-center lg:px-0' : '']" :title="collapsed ? item.label : undefined" @click="menuOpen = false">
                                <Icon :name="item.icon" :size="17" class="shrink-0" /><span :class="collapsed ? 'lg:hidden' : ''">{{ item.label }}</span>
                            </Link>
                            <Link v-if="item.add && !collapsed" :href="item.add" class="absolute right-1.5 hidden rounded p-1 text-sidebar-fg/60 group-hover:block hover:bg-white/10 hover:text-white" :aria-label="`New: ${item.label}`"><Icon name="plus" :size="14" /></Link>
                        </div>
                    </template>
                </div>
            </nav>
            <button class="hidden items-center gap-2 border-t border-white/10 px-5 py-3 text-xs text-sidebar-fg/60 hover:text-white lg:flex" :class="collapsed ? 'justify-center px-0' : ''" @click="toggleSidebar">
                <Icon :name="collapsed ? 'chevron' : 'back'" :size="15" /><span v-if="!collapsed">Collapse</span>
            </button>
        </aside>
        <div v-if="menuOpen" class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="menuOpen = false" />

        <div class="min-w-0">
            <header class="sticky top-0 z-10 flex h-14 items-center gap-2 border-b border-line bg-bg/85 px-4 backdrop-blur sm:px-6">
                <button class="rounded-md p-1.5 text-muted hover:bg-surface-2 lg:hidden" aria-label="Open menu" @click="menuOpen = true"><Icon name="menu" /></button>
                <Popover v-if="createItems.length" align="left">
                    <template #trigger><span class="btn-primary px-3 py-1.5"><Icon name="plus" :size="15" />Create</span></template>
                    <div class="w-72 p-1.5">
                        <Link v-for="c in createItems" :key="c.href" :href="c.href" class="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-surface-2">
                            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-accent-soft text-accent"><Icon :name="c.icon" :size="16" /></span>
                            <span><span class="block text-sm font-medium">{{ c.label }}</span><span class="text-xs text-muted">{{ c.hint }}</span></span>
                        </Link>
                    </div>
                </Popover>
                <div class="flex-1" />
                <button class="hidden items-center gap-2 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-muted hover:text-fg md:flex" @click="palette = true"><Icon name="search" :size="15" />Search people and pages<kbd class="ml-6 rounded border border-line px-1 text-[10px]">Ctrl K</kbd></button>
                <Popover v-if="user?.isAdmin" align="right">
                    <template #trigger>
                        <span class="relative grid size-9 place-items-center rounded-md text-muted hover:bg-surface-2" aria-label="Things waiting on you">
                            <Icon name="bell" :size="18" />
                            <span v-if="inbox.total" class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-danger px-1 text-[10px] leading-4 font-semibold text-white">{{ inbox.total > 99 ? '99+' : inbox.total }}</span>
                        </span>
                    </template>
                    <div class="w-80">
                        <p class="border-b border-line px-4 py-3 text-sm font-semibold">Waiting on you</p>
                        <Link v-for="i in inbox.items" :key="i.kind" :href="i.href" class="flex items-center justify-between px-4 py-2.5 text-sm hover:bg-surface-2">
                            <span>{{ i.label }}</span><span class="rounded-full bg-accent-soft px-2 text-xs font-semibold text-accent">{{ i.count }}</span>
                        </Link>
                        <p v-if="!inbox.items.length" class="px-4 py-6 text-center text-sm text-muted">You're all caught up.</p>
                    </div>
                </Popover>
                <button class="grid size-9 place-items-center rounded-md text-muted hover:bg-surface-2" :aria-label="theme === 'dark' ? 'Light theme' : 'Dark theme'" @click="toggleTheme"><Icon :name="theme === 'dark' ? 'sun' : 'moon'" :size="18" /></button>
                <Popover v-if="user" align="right">
                    <template #trigger>
                        <span class="flex items-center gap-2 rounded-lg py-1 pr-2 pl-1 hover:bg-surface-2">
                            <span class="grid size-7 place-items-center rounded-full bg-accent-soft text-xs font-semibold text-accent uppercase">{{ user.username?.[0] ?? '?' }}</span>
                            <span class="hidden text-sm sm:inline">{{ user.username }}</span>
                        </span>
                    </template>
                    <div class="w-56 p-1.5">
                        <p class="px-3 py-2 text-xs text-muted">Signed in as <span class="font-medium text-fg">{{ user.username }}</span><br />{{ user.isAdmin ? 'Administrator' : 'Employee' }}</p>
                        <Link v-if="user.employeeId && user.isAdmin" :href="`/people/${user.employeeId}`" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><Icon name="user" :size="16" />My profile</Link>
                        <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-2" @click="signOut"><Icon name="logout" :size="16" />Sign out</button>
                    </div>
                </Popover>
            </header>
            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-8"><slot /></main>
        </div>
        <CommandPalette v-model:open="palette" :pages="navItems" :admin="!!user?.isAdmin" />
        <Feedback />
    </div>
</template>
