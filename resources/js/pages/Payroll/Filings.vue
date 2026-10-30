<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import StatCard from '../../components/StatCard.vue';
import Icon from '../../components/Icon.vue';
import { api, blobErrorMessage, download, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { KIND_LABEL, amount, date, peso, period, today } from '../../lib/format';

const TABS = [
    { key: 'bir', label: 'BIR 1601-C' },
    { key: 'sss', label: 'SSS', title: 'SSS contributions (R-3)', number: 'SS number', portal: 'My.SSS' },
    { key: 'philhealth', label: 'PhilHealth', title: 'PhilHealth premiums (RF-1)', number: 'PhilHealth no.', portal: 'the EPRS portal' },
    { key: 'pagibig', label: 'Pag-IBIG', title: 'Pag-IBIG contributions', number: 'Pag-IBIG MID', portal: 'Virtual Pag-IBIG' },
];

// The return filed now is last month's: open on that one.
const lastMonth = () => {
    const [y, m] = today().split('-').map(Number);
    return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`;
};
const params = new URLSearchParams(window.location.search);
const month = ref(params.get('month') ?? lastMonth());
const tab = ref(TABS.some((t) => t.key === params.get('tab')) ? params.get('tab') : 'bir');
const sheet = ref(null);
const contrib = ref(null);
const loading = ref(true);
const error = ref('');
const saving = ref('');
const ym = () => month.value.split('-').map(Number);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [y, m] = ym();
        [sheet.value, contrib.value] = await Promise.all([api('get', `/payroll/filings/1601c/${y}/${m}`), api('get', `/payroll/filings/contributions/${y}/${m}`)]);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(load);
const remember = () => history.replaceState(history.state, '', `?month=${month.value}${tab.value === 'bir' ? '' : `&tab=${tab.value}`}`);
watch(month, (v) => {
    if (/^\d{4}-\d{2}$/.test(v ?? '')) {
        remember();
        load();
    }
});
watch(tab, remember);

async function save(key, url) {
    saving.value = key;
    try {
        await download(url);
    } catch (e) {
        toast(await blobErrorMessage(e), 'error');
    } finally {
        saving.value = '';
    }
}
const pdfUrl = computed(() => {
    const [y, m] = ym();
    return tab.value === 'bir' ? `/payroll/filings/1601c/${y}/${m}/pdf` : `/payroll/filings/contributions/${y}/${m}/pdf`;
});

const item = computed(() => Object.fromEntries((sheet.value?.items ?? []).map((i) => [i.item, i.amount])));
const monthLabel = computed(() => {
    const [y, m] = ym();
    return new Date(Date.UTC(y, m - 1, 1)).toLocaleString('en-PH', { month: 'long', year: 'numeric', timeZone: 'UTC' });
});
const overdue = computed(() => sheet.value && sheet.value.runs.length && today() > sheet.value.dueDate);
const TOTALS = [21, 22, 24, 25, 27];
const signed = (n) => (n < 0 ? `(${amount(-n)})` : amount(n));

const agency = computed(() => TABS.find((t) => t.key === tab.value));
const list = computed(() => (tab.value === 'bir' ? null : contrib.value?.[tab.value]));
const openRuns = computed(() => (tab.value === 'bir' ? sheet.value?.openRuns : contrib.value?.openRuns) ?? 0);
const person = (r) => (r.lastName ? `${r.lastName}, ${r.firstName ?? ''} ${r.middleName ?? ''}`.trim() : r.name);
</script>

<template>
    <Head title="Remittances & filings" />
    <PageHeader title="Remittances & filings" subtitle="Monthly figures for the government returns and contribution reports, from approved payroll runs." :crumbs="[['Payroll', '/payroll/runs'], ['Remittances & filings']]">
        <label class="sr-only" for="month">Month</label>
        <input id="month" v-model="month" type="month" class="input w-44 py-1.5" />
        <button class="btn-primary" :disabled="!!saving || loading || !!error" @click="save('pdf', pdfUrl)"><Icon name="download" :size="16" />{{ saving === 'pdf' ? 'Preparing…' : tab === 'bir' ? 'Worksheet PDF' : 'Contributions PDF' }}</button>
    </PageHeader>

    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        <button v-for="t in TABS" :key="t.key" role="tab" :aria-selected="tab === t.key" class="-mb-px border-b-2 px-3.5 py-2 text-sm font-medium whitespace-nowrap transition-colors" :class="tab === t.key ? 'border-accent text-fg' : 'border-transparent text-muted hover:text-fg'" @click="tab = t.key">
            {{ t.label }}
            <span v-if="t.key !== 'bir' && contrib?.[t.key]?.missingNumbers.length" class="ml-1 inline-block size-1.5 rounded-full bg-warn align-middle" :title="`${contrib[t.key].missingNumbers.length} without a number`" />
        </button>
    </div>

    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <template v-else>
        <div v-if="openRuns" class="card mb-4 flex items-start gap-2 border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="status">
            <Icon name="alert" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ openRuns }} {{ openRuns === 1 ? 'run' : 'runs' }} {{ tab === 'bir' ? 'paying in' : 'for' }} {{ monthLabel }} {{ openRuns === 1 ? 'is' : 'are' }} not approved yet and left out of these figures. <Link href="/payroll/runs" class="font-medium underline">Review runs</Link></span>
        </div>

        <!-- BIR 1601-C: runs count in the month they are paid -->
        <template v-if="tab === 'bir'">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard label="Tax to remit" :value="peso(item[27])" :hint="sheet ? `1601-C · ${monthLabel}` : ''" :loading="loading" />
                <StatCard label="Due" :value="sheet ? date(sheet.dueDate) : ''" :hint="overdue ? 'Past due if not filed yet' : 'eBIRForms; eFPS filers by industry group'" :loading="loading" />
                <StatCard label="Taxable compensation" :value="peso(item[22])" :loading="loading" />
                <StatCard label="Employees paid" :value="sheet?.employees" :hint="sheet ? `${sheet.runs.length} run(s)` : ''" :loading="loading" />
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

                <section class="card h-fit overflow-hidden">
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
            </div>
        </template>

        <!-- SSS, PhilHealth, Pag-IBIG: contributions belong to the month the pay is for -->
        <template v-else>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard label="Total to remit" :value="peso(list?.totals.total)" :hint="agency.title" :loading="loading" />
                <StatCard :label="tab === 'philhealth' ? 'Personal share' : 'Employee share'" :value="peso(list?.totals.employee)" hint="Deducted from pay" :loading="loading" />
                <StatCard label="Employer share" :value="peso(list ? list.totals.employer + (list.totals.ec ?? 0) : null)" :hint="tab === 'sss' ? `Including EC ${amount(list?.totals.ec)}` : 'On top of pay'" :loading="loading" />
                <StatCard label="Members" :value="list?.rows.length" :hint="contrib ? `${contrib.runs.length} run(s) for ${monthLabel}` : ''" :loading="loading" />
            </div>

            <div v-if="list?.missingNumbers.length" class="card mt-4 flex items-start gap-2 border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="status">
                <Icon name="alert" :size="16" class="mt-0.5 shrink-0" />
                <span>No {{ agency.number }} on file for {{ list.missingNumbers.join(', ') }}. <Link href="/payroll/employees" class="font-medium underline">Add it to the pay profile</Link></span>
            </div>

            <section class="card mt-6 overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <h2 class="font-semibold">{{ agency.title }} <span class="font-normal text-muted">· {{ monthLabel }}</span></h2>
                        <p class="text-xs text-muted">What the approved payslips deducted, for entering in {{ agency.portal }}</p>
                    </div>
                    <button class="btn-ghost" :disabled="!!saving || loading || !list?.rows.length" @click="save('csv', `/payroll/filings/contributions/${ym()[0]}/${ym()[1]}/${tab}.csv`)"><Icon name="download" :size="16" />{{ saving === 'csv' ? 'Preparing…' : 'CSV' }}</button>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr>
                                <th class="th">Member</th><th class="th">{{ agency.number }}</th>
                                <th class="th text-right">{{ { sss: 'Salary credit', philhealth: 'Monthly basic', pagibig: 'Compensation (capped)' }[tab] }}</th>
                                <th class="th text-right">{{ tab === 'philhealth' ? 'Personal' : 'Employee' }}</th><th class="th text-right">Employer</th>
                                <th v-if="tab === 'sss'" class="th text-right">EC</th>
                                <th class="th text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="loading">
                                <tr v-for="i in 8" :key="i"><td colspan="7" class="td"><div class="skeleton h-4" /></td></tr>
                            </template>
                            <tr v-for="r in list.rows" v-else :key="r.employeeId" class="hover:bg-surface-2">
                                <td class="td"><span class="font-medium">{{ person(r) }}</span></td>
                                <td class="td num"><span v-if="r.number">{{ r.number }}</span><span v-else class="inline-flex items-center gap-1 text-xs text-warn"><Icon name="alert" :size="13" />missing</span></td>
                                <td class="td num text-right">
                                    <template v-if="tab === 'sss'">
                                        <span v-if="r.msc">{{ amount(r.msc) }}</span><span v-else class="text-xs text-warn" title="The deductions do not match one salary credit: the month may not be complete yet.">incomplete month</span>
                                        <span v-if="r.employeeMpf > 0" class="block text-xs text-muted">MPF {{ amount(r.employeeMpf) }} / {{ amount(r.employerMpf) }}</span>
                                    </template>
                                    <template v-else>{{ amount(r.base) }}</template>
                                </td>
                                <td class="td num text-right">{{ amount(r.employee) }}</td>
                                <td class="td num text-right">{{ amount(r.employer) }}</td>
                                <td v-if="tab === 'sss'" class="td num text-right">{{ amount(r.ec) }}</td>
                                <td class="td num text-right font-medium">{{ amount(r.total) }}</td>
                            </tr>
                            <tr v-if="!loading && list.rows.length" class="font-semibold">
                                <td class="td" colspan="3">Total to remit</td>
                                <td class="td num text-right">{{ amount(list.totals.employee) }}</td>
                                <td class="td num text-right">{{ amount(list.totals.employer) }}</td>
                                <td v-if="tab === 'sss'" class="td num text-right">{{ amount(list.totals.ec) }}</td>
                                <td class="td num text-right">{{ amount(list.totals.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!loading && !list.rows.length" class="px-4 py-8 text-center text-sm text-muted">No approved or paid runs for {{ monthLabel }}.</p>
                <p class="border-t border-line px-4 py-3 text-xs text-muted">Contributions count in the month the pay is for. Payment deadlines depend on your employer number; check {{ agency.portal }} for yours.</p>
            </section>
        </template>
    </template>
</template>
