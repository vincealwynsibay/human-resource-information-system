<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import PageHeader from '../components/PageHeader.vue';
import StatusChip from '../components/StatusChip.vue';
import Icon from '../components/Icon.vue';
import CostChart from '../components/charts/CostChart.vue';
import BarList from '../components/charts/BarList.vue';
import { api, errorMessage } from '../lib/api';
import { KIND_LABEL, date, peso, period } from '../lib/format';

const user = computed(() => usePage().props.auth.user);
const loading = ref(true);
const error = ref('');
const d = ref(null);
const inbox = ref({ items: [] });

onMounted(async () => {
    try {
        [d.value, inbox.value] = await Promise.all([api('get', '/payroll/dashboard'), api('get', '/payroll/inbox')]);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

const month = computed(() => Number((d.value?.today ?? '2000-01').slice(5, 7)));
const cost = (m) => (m ? m.net + m.deductions + m.employer : 0);
const thisMonth = computed(() => d.value?.payrollByMonth[month.value - 1]);
const lastMonth = computed(() => d.value?.payrollByMonth[month.value - 2]);
const change = computed(() => (lastMonth.value && cost(lastMonth.value) ? ((cost(thisMonth.value) - cost(lastMonth.value)) / cost(lastMonth.value)) * 100 : null));
const ytd = computed(() => (d.value?.payrollByMonth ?? []).reduce((a, m) => ({ gross: a.gross + m.gross, employer: a.employer + m.employer }), { gross: 0, employer: 0 }));
const att = computed(() => d.value?.attendanceToday);
const ATT = [
    ['present', 'On time', 'bg-accent', 'check'],
    ['late', 'Late', 'bg-warn', 'clock'],
    ['onLeave', 'On leave', 'bg-info', 'calendar'],
    ['absent', 'Absent', 'bg-danger', 'alert'],
    ['notYet', 'Not in yet', 'bg-line', 'clock'],
];
const greeting = computed(() => {
    const h = Number(new Intl.DateTimeFormat('en-PH', { hour: 'numeric', hour12: false, timeZone: 'Asia/Manila' }).format(new Date()));
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
});
const EVENT = {
    birthday: ['cake', 'bg-warn-soft text-warn', (e) => `${e.name}'s birthday`],
    anniversary: ['star', 'bg-info-soft text-info', (e) => `${e.name}: ${e.years} year${e.years > 1 ? 's' : ''} with the company`],
    holiday: ['calendar', 'bg-danger-soft text-danger', (e) => `${e.name} (${e.type === 'regular' ? 'regular holiday' : 'special day'})`],
    payday: ['peso', 'bg-accent-soft text-accent', () => 'Pay day'],
};
const relative = (iso) => {
    const days = Math.round((Date.parse(`${iso}T00:00:00Z`) - Date.parse(`${d.value.today}T00:00:00Z`)) / 864e5);
    return days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : `In ${days} days`;
};
</script>

<template>
    <Head title="Dashboard" />
    <PageHeader :title="`${greeting}, ${user.username}`" :subtitle="d ? `${date(d.today)} · here is where things stand` : ' '" :crumbs="[['Dashboard']]">
        <Link href="/payroll/runs?new=1" class="btn-primary"><Icon name="plus" :size="16" />New payroll run</Link>
    </PageHeader>

    <div v-if="error" class="card mb-6 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div v-for="(t, i) in [
            { icon: 'people', label: 'Headcount', value: d?.people?.headcount ?? d?.profiles.count, hint: d?.people ? `${d.people.byDepartment.length} departments · ${d.profiles.count} on payroll` : 'on payroll' },
            { icon: 'peso', label: 'Payroll cost this month', value: peso(cost(thisMonth)), hint: change === null ? 'pay, deductions and employer share' : `${change >= 0 ? '▲' : '▼'} ${Math.abs(change).toFixed(1)}% from last month`, tone: change > 0 ? 'text-warn' : '' },
            { icon: 'trend', label: `Gross pay ${d?.year ?? ''} to date`, value: peso(ytd.gross), hint: `${peso(ytd.employer)} employer contributions` },
            { icon: 'bell', label: 'Waiting on you', value: inbox.items.reduce((a, x) => a + x.count, 0), hint: inbox.items.length ? inbox.items.map((x) => `${x.count} ${x.kind}`).join(' · ') : 'all caught up' },
        ]" :key="i" class="card flex items-start gap-3 px-4 py-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent"><Icon :name="t.icon" :size="19" /></span>
            <div class="min-w-0">
                <p class="text-xs font-medium text-muted">{{ t.label }}</p>
                <div v-if="loading" class="skeleton mt-1.5 h-6 w-24" />
                <p v-else class="num mt-0.5 truncate text-xl font-semibold tracking-tight">{{ t.value }}</p>
                <p v-if="!loading" class="mt-0.5 truncate text-xs" :class="t.tone || 'text-muted'">{{ t.hint }}</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="card p-5">
            <header class="mb-4 flex items-baseline justify-between">
                <div><h2 class="font-semibold">Payroll cost</h2><p class="text-xs text-muted">By month of the pay period, {{ d?.year }} · runs not cancelled</p></div>
                <Link href="/payroll/runs" class="text-sm text-accent hover:underline">Runs</Link>
            </header>
            <div v-if="loading" class="skeleton h-60" />
            <CostChart v-else :months="d.payrollByMonth" :up-to="month" />
        </section>

        <section class="card p-5">
            <header class="mb-1 flex items-baseline justify-between"><h2 class="font-semibold">Today</h2><Link href="/people" class="text-sm text-accent hover:underline">People</Link></header>
            <div v-if="loading" class="mt-4 space-y-3"><div class="skeleton h-3" /><div class="skeleton h-24" /></div>
            <template v-else-if="att?.workDay">
                <p class="text-xs text-muted">{{ att.present + att.late }} of {{ att.scheduled }} scheduled are in</p>
                <div class="mt-4 flex h-3 gap-0.5 overflow-hidden rounded-full" role="img" :aria-label="ATT.map(([k, l]) => `${l} ${att[k]}`).join(', ')">
                    <div v-for="[k, l, c] in ATT.filter(([k]) => att[k])" :key="k" :class="c" :style="{ flex: att[k] }" :title="`${l}: ${att[k]}`" />
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    <li v-for="[k, l, c, icon] in ATT" :key="k" class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-full" :class="c" />{{ l }}</span><span class="num font-medium">{{ att[k] }}</span>
                    </li>
                </ul>
            </template>
            <p v-else-if="att" class="mt-3 text-sm text-muted">Not a work day: a rest day or a holiday.</p>
            <p v-else class="mt-3 text-sm text-muted">Time records appear here once the HR side is connected.</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between px-5 pt-4 pb-2"><h2 class="font-semibold">Payroll in progress</h2><Link href="/payroll/runs" class="text-sm text-accent hover:underline">All</Link></header>
            <div v-if="loading" class="space-y-2 px-5 pb-5"><div v-for="i in 3" :key="i" class="skeleton h-10" /></div>
            <ul v-else class="divide-y divide-line">
                <li v-for="r in d.runs.waiting" :key="r.id">
                    <Link :href="`/payroll/runs/${r.id}`" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-surface-2">
                        <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ period(r.period_start, r.period_end) }}</span><span class="text-xs text-muted">{{ KIND_LABEL[r.kind] }} · {{ r.payslips_count }} people · {{ peso(r.total_net) }}</span></span>
                        <StatusChip :status="r.status" />
                    </Link>
                </li>
                <li v-if="!d.runs.waiting.length" class="px-5 py-8 text-center text-sm text-muted">No run in progress.<template v-if="d.runs.lastPaid"> Last paid: {{ period(d.runs.lastPaid.period_start, d.runs.lastPaid.period_end) }}.</template></li>
            </ul>
            <div v-if="!loading && (d.pending?.leave || d.pending?.overtime)" class="flex gap-2 border-t border-line px-5 py-3 text-sm">
                <Link v-if="d.pending.leave" href="/leave" class="rounded-full bg-warn-soft px-2.5 py-0.5 text-xs font-semibold text-warn">{{ d.pending.leave }} leave to decide</Link>
                <Link v-if="d.pending.overtime" href="/overtime" class="rounded-full bg-warn-soft px-2.5 py-0.5 text-xs font-semibold text-warn">{{ d.pending.overtime }} overtime to decide</Link>
            </div>
        </section>

        <section class="card overflow-hidden">
            <header class="px-5 pt-4 pb-2"><h2 class="font-semibold">Coming up</h2></header>
            <div v-if="loading" class="space-y-2 px-5 pb-5"><div v-for="i in 4" :key="i" class="skeleton h-10" /></div>
            <ul v-else class="divide-y divide-line">
                <li v-for="(e, i) in d.upcoming.slice(0, 7)" :key="i" class="flex items-center gap-3 px-5 py-2.5">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="EVENT[e.kind][1]"><Icon :name="EVENT[e.kind][0]" :size="15" /></span>
                    <span class="min-w-0 flex-1">
                        <Link v-if="e.employeeId" :href="`/people/${e.employeeId}`" class="block truncate text-sm hover:underline">{{ EVENT[e.kind][2](e) }}</Link>
                        <span v-else class="block truncate text-sm">{{ EVENT[e.kind][2](e) }}</span>
                        <span class="text-xs text-muted">{{ relative(e.date) }} · {{ date(e.date) }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <section class="card p-5">
            <header class="mb-4 flex items-baseline justify-between"><h2 class="font-semibold">Headcount by department</h2><Link href="/people" class="text-sm text-accent hover:underline">People</Link></header>
            <div v-if="loading" class="space-y-3"><div v-for="i in 4" :key="i" class="skeleton h-6" /></div>
            <BarList v-else-if="d.people" :rows="d.people.byDepartment" unit="people" />
            <p v-else class="text-sm text-muted">Departments appear once the HR side is connected.</p>
        </section>
    </div>
</template>
