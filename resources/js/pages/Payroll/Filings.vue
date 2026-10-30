<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import StatCard from '../../components/StatCard.vue';
import Icon from '../../components/Icon.vue';
import { api, blobErrorMessage, download, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { KIND_LABEL, amount, date, peso, period, today } from '../../lib/format';

// The return filed now is last month's: open on that one.
const lastMonth = () => {
    const [y, m] = today().split('-').map(Number);
    return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`;
};
const month = ref(new URLSearchParams(window.location.search).get('month') ?? lastMonth());
const sheet = ref(null);
const loading = ref(true);
const error = ref('');
const saving = ref(false);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [y, m] = month.value.split('-').map(Number);
        sheet.value = await api('get', `/payroll/filings/1601c/${y}/${m}`);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(load);
watch(month, (v) => {
    if (/^\d{4}-\d{2}$/.test(v ?? '')) {
        history.replaceState(history.state, '', `?month=${v}`);
        load();
    }
});

async function savePdf() {
    saving.value = true;
    try {
        const [y, m] = month.value.split('-').map(Number);
        await download(`/payroll/filings/1601c/${y}/${m}/pdf`);
    } catch (e) {
        toast(await blobErrorMessage(e), 'error');
    } finally {
        saving.value = false;
    }
}

const item = computed(() => Object.fromEntries((sheet.value?.items ?? []).map((i) => [i.item, i.amount])));
const monthLabel = computed(() => {
    const [y, m] = month.value.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, 1)).toLocaleString('en-PH', { month: 'long', year: 'numeric', timeZone: 'UTC' });
});
const overdue = computed(() => sheet.value && sheet.value.runs.length && today() > sheet.value.dueDate);
const TOTALS = [21, 22, 24, 25, 27];
const signed = (n) => (n < 0 ? `(${amount(-n)})` : amount(n));
</script>

<template>
    <Head title="Remittances & filings" />
    <PageHeader title="Remittances & filings" subtitle="Monthly figures for the government returns, from the payroll runs paid in the month." :crumbs="[['Payroll', '/payroll/runs'], ['Remittances & filings']]">
        <label class="sr-only" for="month">Month</label>
        <input id="month" v-model="month" type="month" class="input w-44 py-1.5" />
        <button class="btn-primary" :disabled="saving || loading || !!error" @click="savePdf"><Icon name="download" :size="16" />{{ saving ? 'Preparing…' : 'Worksheet PDF' }}</button>
    </PageHeader>

    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <template v-else>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Tax to remit" :value="peso(item[27])" :hint="sheet ? `1601-C · ${monthLabel}` : ''" :loading="loading" />
            <StatCard label="Due" :value="sheet ? date(sheet.dueDate) : ''" :hint="overdue ? 'Past due if not filed yet' : 'eBIRForms; eFPS filers by industry group'" :loading="loading" />
            <StatCard label="Taxable compensation" :value="peso(item[22])" :loading="loading" />
            <StatCard label="Employees paid" :value="sheet?.employees" :hint="sheet ? `${sheet.runs.length} run(s)` : ''" :loading="loading" />
        </div>

        <div v-if="sheet?.openRuns" class="card mt-4 flex items-start gap-2 border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="status">
            <Icon name="alert" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ sheet.openRuns }} {{ sheet.openRuns === 1 ? 'run paying' : 'runs paying' }} in {{ monthLabel }} {{ sheet.openRuns === 1 ? 'is' : 'are' }} not approved yet and left out of these figures. <Link href="/payroll/runs" class="font-medium underline">Review runs</Link></span>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_22rem]">
            <section class="card overflow-hidden">
                <header class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
                    <h2 class="font-semibold">BIR Form 1601-C <span class="font-normal text-muted">· {{ monthLabel }}</span></h2>
                    <p class="text-xs text-muted">Copy these into eBIRForms or eFPS</p>
                </header>
                <table class="w-full text-sm">
                    <thead><tr><th class="th w-14">Item</th><th class="th">Computation of tax</th><th class="th text-right">Amount</th></tr></thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="i in 8" :key="i"><td colspan="3" class="td"><div class="skeleton h-4" /></td></tr>
                        </template>
                        <tr v-for="i in sheet.items" v-else :key="i.item" :class="TOTALS.includes(i.item) ? 'font-semibold' : ''">
                            <td class="td text-muted">{{ i.item }}</td>
                            <td class="td">{{ i.label }}</td>
                            <td class="td num text-right">{{ signed(i.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="sheet?.notes.length" class="space-y-1 border-t border-line px-4 py-3 text-xs text-muted">
                    <p v-for="(n, i) in sheet.notes" :key="i">{{ n }}</p>
                </div>
            </section>

            <div class="space-y-6">
                <section class="card overflow-hidden">
                    <header class="px-4 py-3"><h2 class="font-semibold">Runs included</h2><p class="text-xs text-muted">Approved or paid, with a pay date in {{ monthLabel }}</p></header>
                    <div v-if="loading" class="space-y-2 px-4 pb-4"><div v-for="i in 3" :key="i" class="skeleton h-10" /></div>
                    <ul v-else-if="sheet.runs.length" class="divide-y divide-line border-t border-line">
                        <li v-for="r in sheet.runs" :key="r.id">
                            <Link :href="`/payroll/runs/${r.id}`" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-surface-2">
                                <span><span class="block font-medium">{{ period(r.period_start, r.period_end) }}</span><span class="text-xs text-muted">{{ KIND_LABEL[r.kind] }} · paid {{ date(r.pay_date) }} · {{ r.employees }} people</span></span>
                                <span class="num text-right text-xs text-muted">tax<br /><span class="text-sm text-fg">{{ amount(r.taxWithheld) }}</span></span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="border-t border-line px-4 py-6 text-center text-sm text-muted">No approved or paid runs in {{ monthLabel }}.</p>
                </section>

                <section class="card p-4 text-sm">
                    <h2 class="font-semibold">Also due every month</h2>
                    <p class="mt-1 text-xs text-muted">Contribution reports for SSS (R-3), PhilHealth (RF-1) and Pag-IBIG come next. The contributions themselves are already on every payslip.</p>
                </section>
            </div>
        </div>
    </template>
</template>
