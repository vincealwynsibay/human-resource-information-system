<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import StatusChip from '../../components/StatusChip.vue';
import SkeletonRows from '../../components/SkeletonRows.vue';
import EmptyState from '../../components/EmptyState.vue';
import Drawer from '../../components/Drawer.vue';
import Icon from '../../components/Icon.vue';
import { api, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { FREQUENCY_LABEL, KIND_LABEL, date, peso, period, today } from '../../lib/format';

const tabs = [
    { key: '', label: 'All' },
    { key: 'DRAFT', label: 'Drafts' },
    { key: 'PENDING_APPROVAL', label: 'Awaiting approval' },
    { key: 'APPROVED', label: 'Approved' },
    { key: 'PAID', label: 'Paid' },
    { key: 'CANCELLED', label: 'Cancelled' },
];
const filter = reactive({ status: '', page: 1, limit: 15 });
const loading = ref(true);
const error = ref('');
const data = ref({ items: [], total: 0 });
const pages = computed(() => Math.max(1, Math.ceil(data.value.total / filter.limit)));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const q = new URLSearchParams({ page: filter.page, limit: filter.limit, ...(filter.status ? { status: filter.status } : {}) });
        data.value = await api('get', `/payroll/runs?${q}`);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
watch(() => [filter.status, filter.page], load);
onMounted(() => {
    load();
    if (new URLSearchParams(location.search).get('new')) openNew();
});

// New run
const drawer = ref(false);
const form = reactive({ frequency: 'SEMI_MONTHLY', periodStart: '', periodEnd: '', payDate: '', schedule: 'split', daysWorked: 11, hoursWorked: 88, useAttendance: true });
const preview = ref(null);
const previewing = ref(false);
const saving = ref(false);
const formError = ref('');

function presets() {
    const t = today();
    const y = Number(t.slice(0, 4)), m = Number(t.slice(5, 7)), d = Number(t.slice(8, 10));
    const mm = String(m).padStart(2, '0');
    const last = new Date(Date.UTC(y, m, 0)).getUTCDate();
    return { y, mm, last, firstHalf: d <= 15 };
}
function applyPreset(kind) {
    const { y, mm, last, firstHalf } = presets();
    if (kind === 'month') Object.assign(form, { frequency: 'MONTHLY', periodStart: `${y}-${mm}-01`, periodEnd: `${y}-${mm}-${last}`, daysWorked: 22, hoursWorked: 176 });
    if (kind === 'first') Object.assign(form, { frequency: 'SEMI_MONTHLY', periodStart: `${y}-${mm}-01`, periodEnd: `${y}-${mm}-15`, daysWorked: 11, hoursWorked: 88 });
    if (kind === 'second') Object.assign(form, { frequency: 'SEMI_MONTHLY', periodStart: `${y}-${mm}-16`, periodEnd: `${y}-${mm}-${last}`, daysWorked: 11, hoursWorked: 88 });
    form.payDate = form.periodEnd;
}
function openNew() {
    applyPreset(presets().firstHalf ? 'first' : 'second');
    preview.value = null;
    formError.value = '';
    drawer.value = true;
}
const body = () => ({
    periodStart: form.periodStart, periodEnd: form.periodEnd, frequency: form.frequency, payDate: form.payDate || null, schedule: form.schedule,
    defaults: { daysWorked: form.daysWorked, hoursWorked: form.hoursWorked },
    useAttendance: form.useAttendance,
});
watch(() => [form.periodStart, form.periodEnd, form.frequency, form.schedule, form.daysWorked, form.hoursWorked, form.useAttendance], () => (preview.value = null));

async function runPreview() {
    previewing.value = true;
    formError.value = '';
    try {
        preview.value = await api('post', '/payroll/runs/preview', body());
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        previewing.value = false;
    }
}
async function create() {
    saving.value = true;
    formError.value = '';
    try {
        const run = await api('post', '/payroll/runs', body());
        toast(`Draft run created for ${period(run.period_start, run.period_end)}.`);
        router.visit(`/payroll/runs/${run.id}`);
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Payroll runs" />
    <PageHeader title="Payroll runs" subtitle="Compute a period, review it, then submit, approve and pay.">
        <button class="btn-primary" @click="openNew"><Icon name="plus" :size="16" />New payroll run</button>
    </PageHeader>

    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        <button v-for="t in tabs" :key="t.key" role="tab" :aria-selected="filter.status === t.key" class="-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors" :class="filter.status === t.key ? 'border-accent font-medium text-fg' : 'border-transparent text-muted hover:text-fg'" @click="Object.assign(filter, { status: t.key, page: 1 })">{{ t.label }}</button>
    </div>

    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }} <button class="ml-2 underline" @click="load">Try again</button></div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr><th class="th">Period</th><th class="th hidden md:table-cell">Type</th><th class="th">Status</th><th class="th hidden text-right md:table-cell">Employees</th><th class="th hidden text-right sm:table-cell">Gross</th><th class="th text-right">Net pay</th></tr>
            </thead>
            <tbody>
                <SkeletonRows v-if="loading" :rows="6" :cols="6" />
                <tr v-for="r in data.items" v-else :key="r.id" class="cursor-pointer hover:bg-surface-2" @click="router.visit(`/payroll/runs/${r.id}`)">
                    <td class="td">
                        <p class="font-medium">{{ period(r.period_start, r.period_end) }}</p>
                        <p class="text-xs text-muted">{{ FREQUENCY_LABEL[r.frequency] ?? r.frequency }} · paid {{ date(r.pay_date) }}</p>
                    </td>
                    <td class="td hidden text-muted md:table-cell">{{ KIND_LABEL[r.kind] }}</td>
                    <td class="td"><StatusChip :status="r.status" /></td>
                    <td class="td num hidden text-right md:table-cell">{{ r.payslips_count }}</td>
                    <td class="td num hidden text-right sm:table-cell">{{ peso(r.total_gross) }}</td>
                    <td class="td num text-right font-medium">{{ peso(r.total_net) }}</td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="!loading && !data.items.length" icon="runs" :title="filter.status ? 'No runs with this status' : 'No payroll runs yet'" body="A run computes every employee's payslip for a period. It starts as a draft you can review.">
            <button v-if="!filter.status" class="btn-primary" @click="openNew"><Icon name="plus" :size="16" />New payroll run</button>
        </EmptyState>
        <div v-if="pages > 1" class="flex items-center justify-between px-4 py-3 text-sm text-muted">
            <span>{{ data.total }} runs</span>
            <div class="flex items-center gap-2">
                <button class="btn-ghost px-2.5 py-1" :disabled="filter.page <= 1 || loading" @click="filter.page--">Previous</button>
                <span>Page {{ filter.page }} of {{ pages }}</span>
                <button class="btn-ghost px-2.5 py-1" :disabled="filter.page >= pages || loading" @click="filter.page++">Next</button>
            </div>
        </div>
    </div>

    <Drawer :open="drawer" title="New payroll run" subtitle="It is saved as a draft; nothing is paid until you approve and pay it." @close="drawer = false">
        <div class="mb-5 flex flex-wrap gap-2">
            <button class="btn-ghost px-2.5 py-1 text-xs" @click="applyPreset('first')">1st–15th</button>
            <button class="btn-ghost px-2.5 py-1 text-xs" @click="applyPreset('second')">16th–end</button>
            <button class="btn-ghost px-2.5 py-1 text-xs" @click="applyPreset('month')">Whole month</button>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="label" for="freq">Pay frequency</label>
                <select id="freq" v-model="form.frequency" class="input">
                    <option value="SEMI_MONTHLY">Semi-monthly</option><option value="MONTHLY">Monthly</option><option value="WEEKLY">Weekly</option>
                </select>
            </div>
            <div><label class="label" for="ps">Period start</label><input id="ps" v-model="form.periodStart" type="date" class="input" /></div>
            <div><label class="label" for="pe">Period end</label><input id="pe" v-model="form.periodEnd" type="date" class="input" /></div>
            <div><label class="label" for="pd">Pay date</label><input id="pd" v-model="form.payDate" type="date" class="input" /></div>
            <div v-if="form.frequency === 'SEMI_MONTHLY'">
                <label class="label" for="sch">Contributions</label>
                <select id="sch" v-model="form.schedule" class="input"><option value="split">Split across both cutoffs</option><option value="second_cutoff">All on the 2nd cutoff</option></select>
            </div>
            <label class="col-span-2 flex items-start gap-3 rounded-lg border border-line p-3">
                <input v-model="form.useAttendance" type="checkbox" class="mt-0.5 accent-[var(--accent)]" />
                <span class="text-sm"><span class="font-medium">Use time records</span><span class="block text-muted">Absences, late, undertime, approved overtime and rest-day or holiday work come from attendance. Approved leave is paid.</span></span>
            </label>
            <template v-if="!form.useAttendance">
                <div><label class="label" for="dw">Days worked (daily-paid)</label><input id="dw" v-model.number="form.daysWorked" type="number" min="0" step="0.5" class="input num" /></div>
                <div><label class="label" for="hw">Hours worked (hourly-paid)</label><input id="hw" v-model.number="form.hoursWorked" type="number" min="0" step="0.5" class="input num" /></div>
            </template>
        </div>
        <p class="mt-3 text-xs text-muted">Monthly-paid staff get their salary for the period; open loans are deducted automatically.</p>

        <div v-if="formError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ formError }}</div>

        <div class="mt-5 rounded-lg border border-line bg-surface-2 p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium">Preview</p>
                <button class="text-sm text-accent hover:underline disabled:opacity-50" :disabled="previewing || !form.periodStart || !form.periodEnd" @click="runPreview">{{ previewing ? 'Computing…' : preview ? 'Recompute' : 'Compute totals' }}</button>
            </div>
            <div v-if="previewing" class="mt-3 space-y-2"><div class="skeleton h-4 w-2/3" /><div class="skeleton h-4 w-1/2" /></div>
            <dl v-else-if="preview" class="mt-3 grid grid-cols-2 gap-y-1.5 text-sm">
                <dt class="text-muted">Employees</dt><dd class="num text-right">{{ preview.slips.length }}</dd>
                <dt class="text-muted">Gross</dt><dd class="num text-right">{{ peso(preview.totals.gross) }}</dd>
                <dt class="text-muted">Deductions</dt><dd class="num text-right">{{ peso(preview.totals.deductions) }}</dd>
                <dt class="font-medium">Net pay</dt><dd class="num text-right font-semibold">{{ peso(preview.totals.net) }}</dd>
                <dt class="text-muted">Employer share</dt><dd class="num text-right">{{ peso(preview.totals.employerCost) }}</dd>
            </dl>
            <p v-else class="mt-2 text-sm text-muted">See the totals before you save the draft.</p>
            <p v-if="preview?.calendarMissing" class="mt-3 text-xs text-warn">There is no holiday calendar for this year: holidays are paid as ordinary days.</p>
        </div>

        <template #footer>
            <button class="btn-ghost" @click="drawer = false">Cancel</button>
            <button class="btn-primary" :disabled="saving || !form.periodStart || !form.periodEnd" @click="create">{{ saving ? 'Creating…' : 'Create draft' }}</button>
        </template>
    </Drawer>
</template>
