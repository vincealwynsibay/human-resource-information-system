<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import PageHeader from '../components/PageHeader.vue';
import StatCard from '../components/StatCard.vue';
import StatusChip from '../components/StatusChip.vue';
import SkeletonRows from '../components/SkeletonRows.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import { api, errorMessage } from '../lib/api';
import { KIND_LABEL, peso, period, today } from '../lib/format';

const loading = ref(true);
const error = ref('');
const runs = ref([]);
const profiles = ref([]);
const annual = ref(null);
const year = Number(today().slice(0, 4));

onMounted(async () => {
    try {
        const [r, p, a] = await Promise.all([api('get', '/payroll/runs?limit=100'), api('get', '/payroll/profiles'), api('get', `/payroll/year-end/${year}/annual`)]);
        runs.value = r.items;
        profiles.value = p.items;
        annual.value = a;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

const waiting = computed(() => runs.value.filter((r) => ['DRAFT', 'PENDING_APPROVAL', 'APPROVED'].includes(r.status)));
const lastPaid = computed(() => runs.value.find((r) => r.status === 'PAID'));
const ytdGross = computed(() => (annual.value?.rows ?? []).reduce((a, r) => a + r.grossCompensation, 0));
const ytdTax = computed(() => (annual.value?.rows ?? []).reduce((a, r) => a + r.taxWithheld, 0));
const mwe = computed(() => profiles.value.filter((p) => p.minimumWage?.earner).length);

const nextStep = (s) => ({ DRAFT: 'Review and submit', PENDING_APPROVAL: 'Approve', APPROVED: 'Mark as paid' })[s];
</script>

<template>
    <Head title="Overview" />
    <PageHeader title="Overview" :subtitle="`Payroll for ${year} at a glance`">
        <Link href="/payroll/runs?new=1" class="btn-primary"><Icon name="plus" :size="16" />New payroll run</Link>
    </PageHeader>

    <div v-if="error" class="card mb-6 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="On payroll" :value="profiles.length" :hint="mwe ? `${mwe} minimum-wage earner${mwe > 1 ? 's' : ''}` : 'with pay profiles'" :loading="loading" />
        <StatCard label="Runs needing action" :value="waiting.length" hint="draft, awaiting approval or unpaid" :loading="loading" />
        <StatCard :label="`Gross pay ${year} to date`" :value="peso(ytdGross)" :hint="annual?.unpaidRuns ? `includes ${annual.unpaidRuns} unpaid run(s)` : 'all runs paid'" :loading="loading" />
        <StatCard :label="`Tax withheld ${year}`" :value="peso(ytdTax)" hint="BIR withholding on compensation" :loading="loading" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between px-4 py-3">
                <h2 class="font-semibold">Needs your attention</h2>
                <Link href="/payroll/runs" class="text-sm text-accent hover:underline">All runs</Link>
            </header>
            <table class="w-full text-sm">
                <tbody>
                    <SkeletonRows v-if="loading" :rows="3" :cols="3" />
                    <tr v-for="r in waiting.slice(0, 6)" v-else :key="r.id" class="cursor-pointer hover:bg-surface-2" @click="$inertia.visit(`/payroll/runs/${r.id}`)">
                        <td class="td">
                            <p class="font-medium">{{ period(r.period_start, r.period_end) }}</p>
                            <p class="text-xs text-muted">{{ KIND_LABEL[r.kind] }} · {{ r.payslips_count }} employee(s)</p>
                        </td>
                        <td class="td"><StatusChip :status="r.status" /></td>
                        <td class="td text-right text-sm text-accent">{{ nextStep(r.status) }} →</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!loading && !waiting.length" icon="check" title="Nothing waiting" body="Every payroll run is paid or cancelled." />
        </section>

        <section class="card p-4">
            <h2 class="font-semibold">Last paid run</h2>
            <template v-if="loading"><div class="skeleton mt-3 h-4 w-1/2" /><div class="skeleton mt-3 h-8 w-3/4" /></template>
            <template v-else-if="lastPaid">
                <p class="mt-1 text-sm text-muted">{{ period(lastPaid.period_start, lastPaid.period_end) }} · {{ KIND_LABEL[lastPaid.kind] }}</p>
                <p class="num mt-3 text-2xl font-semibold">{{ peso(lastPaid.total_net) }}</p>
                <p class="text-xs text-muted">net pay to {{ lastPaid.payslips_count }} employee(s)</p>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Gross</dt><dd class="num">{{ peso(lastPaid.total_gross) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Deductions</dt><dd class="num">{{ peso(lastPaid.total_deductions) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Employer share</dt><dd class="num">{{ peso(lastPaid.total_employer_cost) }}</dd></div>
                </dl>
                <Link :href="`/payroll/runs/${lastPaid.id}`" class="btn-ghost mt-4 w-full">Open run</Link>
            </template>
            <p v-else class="mt-2 text-sm text-muted">No run has been paid yet.</p>
        </section>
    </div>
</template>
