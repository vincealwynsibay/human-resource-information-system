<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import StatCard from '../../components/StatCard.vue';
import SkeletonRows from '../../components/SkeletonRows.vue';
import EmptyState from '../../components/EmptyState.vue';
import Drawer from '../../components/Drawer.vue';
import Icon from '../../components/Icon.vue';
import { api, blobErrorMessage, download, errorMessage } from '../../lib/api';
import { confirm, toast } from '../../lib/feedback';
import { amount, peso, today } from '../../lib/format';

const thisYear = Number(today().slice(0, 4));
const year = ref(thisYear);
const loading = ref(true);
const error = ref('');
const annual = ref({ rows: [], unpaidRuns: 0 });
const busy = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        annual.value = await api('get', `/payroll/year-end/${year.value}/annual`);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
watch(year, () => {
    alpha.value = null;
    load();
});
onMounted(load);

const totals = computed(() => {
    const r = annual.value.rows;
    const s = (f) => r.reduce((a, x) => a + x[f], 0);
    return { gross: s('grossCompensation'), taxable: s('taxableCompensation'), due: s('taxDue'), withheld: s('taxWithheld') };
});
const offRows = computed(() => annual.value.rows.filter((r) => !r.mwe && !r.separation && Math.abs(r.overWithheld) >= 0.01));

async function createRun(kind) {
    const what = kind === 'thirteenth'
        ? { title: `Compute 13th-month pay for ${year.value}?`, body: 'One twelfth of each employee\'s basic pay for the year, saved as a draft run. The part above the ₱90,000 ceiling is taxable.', label: 'Compute' }
        : { title: `Compute the ${year.value} year-end adjustment?`, body: 'Refunds over-withheld tax and collects tax still due, so each employee\'s withholding matches the annual tax table. Run it after every other run of the year is in.', label: 'Compute' };
    if (!(await confirm({ title: what.title, body: what.body, confirmLabel: what.label }))) return;
    busy.value = kind;
    try {
        const run = await api('post', `/payroll/year-end/${year.value}/${kind}`);
        toast('Draft run created.');
        router.visit(`/payroll/runs/${run.id}`);
    } catch (e) {
        toast(errorMessage(e), 'error', 6000);
    } finally {
        busy.value = '';
    }
}

async function csv(path, label) {
    busy.value = label;
    try {
        await download(`/payroll/year-end/${year.value}/${path}?format=csv`);
    } catch (e) {
        toast(await blobErrorMessage(e), 'error');
    } finally {
        busy.value = '';
    }
}

// Alphalist
const employer = reactive({ employerTin: '', branch: '0000', regionNumber: '' });
const alpha = ref(null);
const checking = ref(false);
const alphaError = ref('');
const alphaQuery = () => new URLSearchParams(Object.fromEntries(Object.entries(employer).filter(([, v]) => v)));
async function checkAlphalist() {
    checking.value = true;
    alphaError.value = '';
    try {
        alpha.value = await api('get', `/payroll/year-end/${year.value}/alphalist?${alphaQuery()}`);
    } catch (e) {
        alphaError.value = errorMessage(e);
    } finally {
        checking.value = false;
    }
}
async function downloadAlphalist() {
    busy.value = 'dat';
    try {
        await download(`/payroll/year-end/${year.value}/alphalist/download?${alphaQuery()}`);
        toast('Alphalist downloaded. Validate it with the BIR module before filing.');
    } catch (e) {
        toast(await blobErrorMessage(e), 'error');
    } finally {
        busy.value = '';
    }
}

// Final pay
const finalDrawer = ref(false);
const people = ref([]);
const fp = reactive({ employeeId: '', separationDate: today(), cause: 'resignation', unpaidDays: 0, unusedSilDays: 0 });
const fpError = ref('');
const CAUSES = { resignation: 'Resignation', just_cause: 'Termination for just cause', redundancy: 'Redundancy', retrenchment: 'Retrenchment', closure_not_due_to_losses: 'Closure (not due to losses)', closure_serious_losses: 'Closure due to serious losses', labor_saving_device: 'Labor-saving device', disease: 'Disease', retirement: 'Retirement (RA 7641)' };
async function openFinalPay() {
    fpError.value = '';
    finalDrawer.value = true;
    if (!people.value.length) people.value = (await api('get', '/payroll/profiles')).items;
    fp.employeeId ||= people.value[0]?.employeeId ?? '';
}
async function computeFinalPay() {
    busy.value = 'final';
    fpError.value = '';
    try {
        const run = await api('post', `/payroll/final-pay/${encodeURIComponent(fp.employeeId)}`, { separationDate: fp.separationDate, cause: fp.cause, unpaidDays: fp.unpaidDays, unusedSilDays: fp.unusedSilDays });
        toast('Final pay saved as a draft run.');
        router.visit(`/payroll/runs/${run.id}`);
    } catch (e) {
        fpError.value = errorMessage(e);
    } finally {
        busy.value = '';
    }
}
</script>

<template>
    <Head title="Year-end & BIR" />
    <PageHeader title="Year-end & BIR" subtitle="13th month, final pay, annualization, Form 2316 and the 1604-C alphalist.">
        <label class="sr-only" for="yr">Year</label>
        <select id="yr" v-model.number="year" class="input w-28"><option v-for="y in [thisYear + 1, thisYear, thisYear - 1, thisYear - 2]" :key="y" :value="y">{{ y }}</option></select>
    </PageHeader>

    <div v-if="annual.unpaidRuns" class="card mb-4 flex items-start gap-3 border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn">
        <Icon name="alert" :size="18" class="mt-0.5 shrink-0" />
        <p>{{ annual.unpaidRuns }} run(s) for {{ year }} are not paid yet. They are included in these figures; pay or cancel them before filing.</p>
    </div>
    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Gross compensation" :value="peso(totals.gross)" :loading="loading" />
        <StatCard label="Taxable compensation" :value="peso(totals.taxable)" :loading="loading" />
        <StatCard label="Annual tax due" :value="peso(totals.due)" :loading="loading" />
        <StatCard label="Tax withheld" :value="peso(totals.withheld)" :hint="offRows.length ? `${offRows.length} employee(s) to adjust` : 'matches tax due'" :loading="loading" />
    </div>

    <div class="mt-6 grid gap-3 md:grid-cols-3">
        <button class="card flex items-start gap-3 p-4 text-left transition-colors hover:border-accent disabled:opacity-60" :disabled="!!busy" @click="createRun('thirteenth')">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-soft text-accent"><Icon name="calendar" /></span>
            <span><span class="block font-medium">13th-month pay</span><span class="text-sm text-muted">Due on or before December 24.</span></span>
        </button>
        <button class="card flex items-start gap-3 p-4 text-left transition-colors hover:border-accent disabled:opacity-60" :disabled="!!busy" @click="openFinalPay">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-soft text-accent"><Icon name="people" /></span>
            <span><span class="block font-medium">Final pay</span><span class="text-sm text-muted">Separation or retirement, annualized.</span></span>
        </button>
        <button class="card flex items-start gap-3 p-4 text-left transition-colors hover:border-accent disabled:opacity-60" :disabled="!!busy" @click="createRun('adjustment')">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-accent-soft text-accent"><Icon name="refresh" /></span>
            <span><span class="block font-medium">Year-end adjustment</span><span class="text-sm text-muted">Refund or collect the difference.</span></span>
        </button>
    </div>

    <section class="card mt-6 overflow-hidden">
        <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <h2 class="font-semibold">Annual summary</h2>
            <div class="flex gap-2">
                <button class="btn-ghost px-3 py-1.5" :disabled="!!busy || !annual.rows.length" @click="csv('annual', 'annual')"><Icon name="download" :size="15" />Summary CSV</button>
                <button class="btn-ghost px-3 py-1.5" :disabled="!!busy || !annual.rows.length" @click="csv('2316', '2316')"><Icon name="download" :size="15" />Form 2316 items</button>
            </div>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-sm">
                <thead><tr><th class="th">Employee</th><th class="th text-right">Gross</th><th class="th text-right">Non-taxable</th><th class="th text-right">Taxable</th><th class="th text-right">Tax due</th><th class="th text-right">Withheld</th><th class="th text-right">Over / (under)</th></tr></thead>
                <tbody>
                    <SkeletonRows v-if="loading" :rows="6" :cols="7" />
                    <tr v-for="r in annual.rows" v-else :key="r.employeeId">
                        <td class="td"><span class="font-medium">{{ r.name }}</span><span v-if="r.mwe" class="ml-2 rounded-full bg-info-soft px-2 py-0.5 text-xs text-info">MWE</span><span v-if="r.separation" class="ml-2 rounded-full bg-surface-2 px-2 py-0.5 text-xs text-muted">separated</span></td>
                        <td class="td num text-right">{{ amount(r.grossCompensation) }}</td>
                        <td class="td num text-right">{{ amount(r.nonTaxable + r.contributions) }}</td>
                        <td class="td num text-right">{{ amount(r.taxableCompensation) }}</td>
                        <td class="td num text-right">{{ amount(r.taxDue) }}</td>
                        <td class="td num text-right">{{ amount(r.taxWithheld) }}</td>
                        <td class="td num text-right" :class="Math.abs(r.overWithheld) >= 0.01 ? 'font-medium text-warn' : 'text-muted'">{{ r.overWithheld < 0 ? `(${amount(-r.overWithheld)})` : amount(r.overWithheld) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-if="!loading && !annual.rows.length" icon="calendar" :title="`No pay for ${year} yet`" body="The summary fills in as payroll runs are created for the year." />
    </section>

    <section class="card mt-6 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold">BIR 1604-C alphalist</h2>
                <p class="mt-1 max-w-xl text-sm text-muted">The annual alphalist of employees as a .DAT file, in the format the BIR Alphalist Validation Module accepts. Run it through the module before you file.</p>
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_8rem_10rem_auto] sm:items-end">
            <div><label class="label" for="etin">Employer TIN</label><input id="etin" v-model="employer.employerTin" class="input num" placeholder="123-456-789 or 12 digits" /></div>
            <div><label class="label" for="br">Branch code</label><input id="br" v-model="employer.branch" class="input num" maxlength="4" /></div>
            <div><label class="label" for="rn">Default region code</label><input id="rn" v-model="employer.regionNumber" class="input" placeholder="optional" /></div>
            <button class="btn-ghost" :disabled="checking || !employer.employerTin" @click="checkAlphalist">{{ checking ? 'Checking…' : 'Check' }}</button>
        </div>
        <div v-if="alphaError" class="mt-4 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger">{{ alphaError }}</div>
        <div v-if="checking" class="mt-4 space-y-2"><div class="skeleton h-4 w-1/3" /><div class="skeleton h-4 w-1/2" /></div>
        <div v-else-if="alpha" class="mt-5 rounded-lg border border-line">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <div class="text-sm">
                    <p class="font-mono text-xs">{{ alpha.fileName ?? '—' }}</p>
                    <p class="mt-0.5 text-muted">Schedule 1: {{ alpha.counts.schedule1 }} employee(s) · Schedule 2 (minimum wage): {{ alpha.counts.schedule2 }}</p>
                </div>
                <button class="btn-primary" :disabled="!!alpha.errors.length || busy === 'dat'" @click="downloadAlphalist"><Icon name="download" :size="16" />Download .DAT</button>
            </div>
            <div class="px-4 py-3 text-sm">
                <p v-if="!alpha.problems.length" class="flex items-center gap-2 text-accent"><Icon name="check" :size="16" />No problems found. The file passed the validator's rules.</p>
                <ul v-else class="space-y-1.5">
                    <li v-for="(p, i) in alpha.problems" :key="i" class="flex gap-2" :class="p.severity === 'error' ? 'text-danger' : 'text-warn'">
                        <Icon name="alert" :size="15" class="mt-0.5 shrink-0" /><span><span v-if="p.employee" class="font-medium">{{ p.employee }}: </span>{{ p.message }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <Drawer :open="finalDrawer" title="Final pay" subtitle="Unpaid salary, unused leave, pro-rated 13th month, separation or retirement pay, and the tax true-up." @close="finalDrawer = false">
        <label class="label" for="fe">Employee</label>
        <select id="fe" v-model="fp.employeeId" class="input"><option v-for="p in people" :key="p.employeeId" :value="p.employeeId">{{ p.name }}</option></select>
        <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="col-span-2"><label class="label" for="fc">Reason</label><select id="fc" v-model="fp.cause" class="input"><option v-for="(label, k) in CAUSES" :key="k" :value="k">{{ label }}</option></select></div>
            <div class="col-span-2"><label class="label" for="fd">Last day</label><input id="fd" v-model="fp.separationDate" type="date" class="input" /></div>
            <div><label class="label" for="fu">Unpaid days</label><input id="fu" v-model.number="fp.unpaidDays" type="number" min="0" step="0.5" class="input num" /></div>
            <div><label class="label" for="fs">Unused SIL days</label><input id="fs" v-model.number="fp.unusedSilDays" type="number" min="0" step="0.5" class="input num" /></div>
        </div>
        <div v-if="fpError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ fpError }}</div>
        <template #footer>
            <button class="btn-ghost" @click="finalDrawer = false">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'final' || !fp.employeeId" @click="computeFinalPay">{{ busy === 'final' ? 'Computing…' : 'Compute final pay' }}</button>
        </template>
    </Drawer>
</template>
