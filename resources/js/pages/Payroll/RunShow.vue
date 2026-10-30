<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import StatusChip from '../../components/StatusChip.vue';
import Drawer from '../../components/Drawer.vue';
import StatCard from '../../components/StatCard.vue';
import SkeletonRows from '../../components/SkeletonRows.vue';
import Icon from '../../components/Icon.vue';
import { api, blobErrorMessage, download, errorMessage } from '../../lib/api';
import { confirm, toast } from '../../lib/feedback';
import { FREQUENCY_LABEL, KIND_LABEL, amount, date, peso, period } from '../../lib/format';

const props = defineProps({ id: { type: String, required: true } });
const run = ref(null);
const loading = ref(true);
const error = ref('');
const busy = ref('');
const open = ref(new Set());
const query = ref('');

async function load() {
    error.value = '';
    try {
        run.value = await api('get', `/payroll/runs/${props.id}`);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(load);

const ACTIONS = {
    recompute: { label: 'Recompute', icon: 'refresh', title: 'Recompute this draft?', body: 'Every payslip is computed again from the current pay profiles and loans.', done: 'Draft recomputed.' },
    submit: { label: 'Submit for approval', title: 'Submit for approval?', body: 'The payslips can no longer be recomputed once submitted.', done: 'Submitted for approval.' },
    approve: { label: 'Approve', title: 'Approve this run?', body: 'Approved payslips become visible to employees.', done: 'Run approved.' },
    pay: { label: 'Mark as paid', title: 'Mark this run as paid?', body: 'Loan balances are reduced by this run\'s deductions. A paid run cannot be cancelled.', done: 'Run marked as paid.' },
    cancel: { label: 'Cancel run', title: 'Cancel this run?', body: 'Its period becomes free to compute again. This cannot be undone.', done: 'Run cancelled.', tone: 'danger' },
};
const available = computed(() => ({
    DRAFT: { primary: 'submit', more: ['recompute', 'cancel'] },
    PENDING_APPROVAL: { primary: 'approve', more: ['cancel'] },
    APPROVED: { primary: 'pay', more: ['cancel'] },
})[run.value?.status] ?? { primary: null, more: [] });

async function act(action) {
    const a = ACTIONS[action];
    if (!(await confirm({ title: a.title, body: a.body, confirmLabel: a.label, tone: a.tone ?? 'primary' }))) return;
    busy.value = action;
    try {
        run.value = await api('put', `/payroll/runs/${props.id}/${action}`);
        toast(a.done);
    } catch (e) {
        toast(errorMessage(e), 'error', 6000);
        await load(); // someone else may have moved it: show where it is now
    } finally {
        busy.value = '';
    }
}

const slips = computed(() => {
    const q = query.value.trim().toLowerCase();
    return (run.value?.payslips ?? []).filter((s) => !q || (s.name ?? '').toLowerCase().includes(q));
});
const toggle = (id) => (open.value.has(id) ? open.value.delete(id) : open.value.add(id), (open.value = new Set(open.value)));
const line = (list, code) => (list ?? []).filter((x) => x.code === code).reduce((a, x) => a + x.amount, 0);
// Manual allowances and deductions on a draft: saved as inputs, then the run is recomputed.
const adjusting = ref(null);
const lines = ref({ allowances: [], otherDeductions: [] });
const adjError = ref('');
function openAdjust(s) {
    const own = run.value.inputs?.inputs?.[s.employee_id] ?? {};
    lines.value = {
        allowances: (own.allowances ?? []).map((l) => ({ ...l, taxable: l.taxable !== false })),
        otherDeductions: (own.otherDeductions ?? []).map((l) => ({ ...l })),
    };
    adjError.value = '';
    adjusting.value = s;
}
async function saveAdjust() {
    busy.value = 'adjust';
    adjError.value = '';
    try {
        run.value = await api('put', `/payroll/runs/${props.id}/employees/${encodeURIComponent(adjusting.value.employee_id)}/adjustments`, lines.value);
        toast(`${adjusting.value.name}'s payslip recomputed.`);
        adjusting.value = null;
    } catch (e) {
        adjError.value = errorMessage(e);
    } finally {
        busy.value = '';
    }
}

// Payslip PDFs and the bank credit file.
async function save(key, url, done) {
    busy.value = key;
    try {
        await download(url);
        if (done) toast(done);
    } catch (e) {
        toast(await blobErrorMessage(e), 'error', 6000);
    } finally {
        busy.value = '';
    }
}
const final = computed(() => ['APPROVED', 'PAID'].includes(run.value?.status));
const bank = ref(null);
const bankOpen = ref(false);
const bankError = ref('');
async function openBank() {
    bankOpen.value = true;
    bank.value = null;
    bankError.value = '';
    try {
        bank.value = await api('get', `/payroll/runs/${props.id}/bank`);
    } catch (e) {
        bankError.value = errorMessage(e);
    }
}

const timeline = computed(() => {
    const r = run.value;
    if (!r) return [];
    return [
        ['Created', r.created_at, r.created_by_name],
        ['Submitted', r.submitted_at, r.submitted_by_name],
        ['Approved', r.approved_at, r.approved_by_name],
        ['Paid', r.paid_at],
        ['Cancelled', r.cancelled_at],
    ].filter(([, t]) => t);
});
</script>

<template>
    <Head :title="run ? `Run ${period(run.period_start, run.period_end)}` : 'Payroll run'" />
    <Link href="/payroll/runs" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-fg"><Icon name="back" :size="16" />Payroll runs</Link>

    <div v-if="error" class="card border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <template v-else>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div v-if="loading" class="skeleton h-7 w-64" />
                <template v-else>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-semibold tracking-tight">{{ period(run.period_start, run.period_end) }}</h1>
                        <StatusChip :status="run.status" />
                    </div>
                    <p class="mt-1 text-sm text-muted">{{ KIND_LABEL[run.kind] }} · {{ FREQUENCY_LABEL[run.frequency] ?? run.frequency }} · pay date {{ date(run.pay_date) }}</p>
                </template>
            </div>
            <div v-if="run" class="flex flex-wrap gap-2">
                <button v-if="run.status !== 'CANCELLED'" class="btn-ghost" :disabled="!!busy" :title="final ? undefined : 'Not final yet: each page is marked as such'" @click="save('pdf', `/payroll/runs/${props.id}/payslips/pdf`)">
                    <Icon name="download" :size="16" />{{ busy === 'pdf' ? 'Preparing…' : 'Payslips (PDF)' }}
                </button>
                <button v-if="final" class="btn-ghost" :disabled="!!busy" @click="openBank"><Icon name="file" :size="16" />Bank file</button>
                <button v-for="a in available.more" :key="a" :class="ACTIONS[a].tone === 'danger' ? 'btn-danger' : 'btn-ghost'" :disabled="!!busy" @click="act(a)">
                    <Icon v-if="ACTIONS[a].icon" :name="ACTIONS[a].icon" :size="16" />{{ busy === a ? 'Working…' : ACTIONS[a].label }}
                </button>
                <button v-if="available.primary" class="btn-primary" :disabled="!!busy || (available.primary === 'approve' && !run.can_approve)" :title="available.primary === 'approve' && !run.can_approve ? 'You prepared or submitted this run: another administrator approves it.' : undefined" @click="act(available.primary)">{{ busy === available.primary ? 'Working…' : ACTIONS[available.primary].label }}</button>
                <p v-if="available.primary === 'approve' && !run.can_approve" class="w-full text-right text-xs text-muted">You prepared or submitted this run, so another administrator approves it.</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Gross pay" :value="peso(run?.total_gross)" :loading="loading" />
            <StatCard label="Deductions" :value="peso(run?.total_deductions)" :loading="loading" />
            <StatCard label="Net pay" :value="peso(run?.total_net)" :hint="run ? `${run.payslips.length} payslip(s)` : ''" :loading="loading" />
            <StatCard label="Employer share" :value="peso(run?.total_employer_cost)" hint="SSS, EC, PhilHealth, Pag-IBIG" :loading="loading" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_15rem]">
            <section class="card overflow-hidden">
                <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <h2 class="font-semibold">Payslips</h2>
                    <input v-model="query" class="input max-w-56 py-1.5" placeholder="Find an employee" aria-label="Find an employee" />
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead><tr><th class="th">Employee</th><th class="th text-right">Gross</th><th class="th text-right">Contributions</th><th class="th text-right">Tax</th><th class="th text-right">Net</th></tr></thead>
                        <tbody>
                            <SkeletonRows v-if="loading" :rows="6" :cols="5" />
                            <template v-for="s in slips" v-else :key="s.id">
                                <tr class="cursor-pointer hover:bg-surface-2" :aria-expanded="open.has(s.id)" @click="toggle(s.id)">
                                    <td class="td"><span class="inline-flex items-center gap-2 font-medium"><Icon name="chevron" :size="14" class="text-muted transition-transform" :class="{ 'rotate-90': open.has(s.id) }" />{{ s.name }}</span></td>
                                    <td class="td num text-right">{{ amount(s.gross) }}</td>
                                    <td class="td num text-right">{{ amount(line(s.data.deductions, 'SSS') + line(s.data.deductions, 'PHILHEALTH') + line(s.data.deductions, 'PAGIBIG')) }}</td>
                                    <td class="td num text-right">{{ amount(line(s.data.deductions, 'WITHHOLDING_TAX')) }}</td>
                                    <td class="td num text-right font-medium">{{ amount(s.net) }}</td>
                                </tr>
                                <tr v-if="open.has(s.id)" class="bg-surface-2/60">
                                    <td colspan="5" class="border-b border-line px-4 py-4">
                                        <div class="grid gap-6 md:grid-cols-2">
                                            <div>
                                                <p class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">Earnings</p>
                                                <div v-for="(e, i) in s.data.earnings" :key="i" class="flex justify-between gap-4 py-0.5"><span>{{ e.label }}<span v-if="!e.taxable" class="ml-1.5 text-xs text-muted">non-taxable</span></span><span class="num">{{ amount(e.amount) }}</span></div>
                                            </div>
                                            <div>
                                                <p class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">Deductions</p>
                                                <div v-for="(d, i) in s.data.deductions" :key="i" class="flex justify-between gap-4 py-0.5"><span>{{ d.label }}</span><span class="num">{{ amount(d.amount) }}</span></div>
                                                <p v-if="!s.data.deductions.length" class="text-muted">None</p>
                                            </div>
                                        </div>
                                        <div v-if="run.inputs?.attendance?.[s.employee_id]" class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-line pt-3 text-xs text-muted">
                                            <template v-for="a in [run.inputs.attendance[s.employee_id]]" :key="s.id">
                                                <span>Present <b class="text-fg">{{ a.daysPresent }}/{{ a.scheduledDays }}</b> days</span>
                                                <span v-if="a.absentDays">Absent <b class="text-danger">{{ a.absentDays }}</b></span>
                                                <span v-if="a.leaveDays">On leave <b class="text-fg">{{ a.leaveDays }}</b></span>
                                                <span v-if="a.lateMinutes">Late <b class="text-fg">{{ a.lateMinutes }} min</b></span>
                                                <span v-if="a.undertimeMinutes">Undertime <b class="text-fg">{{ a.undertimeMinutes }} min</b></span>
                                                <span v-if="a.overtimeHours">Overtime <b class="text-fg">{{ a.overtimeHours }} h</b></span>
                                                <span v-if="a.restDayHours || a.holidayHours">Rest day / holiday <b class="text-fg">{{ a.restDayHours + a.holidayHours }} h</b></span>
                                                <span v-if="a.unapprovedHours" class="text-warn">{{ a.unapprovedHours }} h worked without approved overtime (not paid)</span>
                                                <span v-if="a.missingSchedule" class="text-warn">No work shift assigned: late not computed</span>
                                            </template>
                                        </div>
                                        <p v-for="(n, i) in s.data.notes" :key="i" class="mt-3 text-xs text-muted">{{ n }}</p>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button class="btn-ghost px-3 py-1.5 text-xs" :disabled="!!busy" @click="save(`pdf-${s.id}`, `/payroll/runs/${props.id}/payslips/${encodeURIComponent(s.employee_id)}/pdf`)"><Icon name="download" :size="14" />{{ busy === `pdf-${s.id}` ? 'Preparing…' : 'Payslip PDF' }}</button>
                                            <button v-if="run.status === 'DRAFT' && run.kind === 'regular'" class="btn-ghost px-3 py-1.5 text-xs" @click="openAdjust(s)">Add allowance or deduction</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p v-if="!loading && !slips.length" class="px-4 py-8 text-center text-sm text-muted">No employee matches “{{ query }}”.</p>
            </section>

            <aside class="card h-fit p-4">
                <h2 class="text-sm font-semibold">History</h2>
                <ol v-if="!loading" class="mt-3 space-y-3 border-l border-line pl-4 text-sm">
                    <li v-for="[label, t, who] in timeline" :key="label" class="relative">
                        <span class="absolute top-1.5 -left-[1.31rem] size-2 rounded-full bg-accent" />
                        <p class="font-medium">{{ label }}<span v-if="who" class="font-normal text-muted"> by {{ who }}</span></p>
                        <p class="text-xs text-muted">{{ new Date(t).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) }}</p>
                    </li>
                </ol>
                <p v-if="run?.notes" class="mt-4 border-t border-line pt-3 text-xs text-muted">{{ run.notes }}</p>
            </aside>
        </div>
    </template>

    <Drawer :open="bankOpen" title="Bank file" :subtitle="run ? `Net pay for ${period(run.period_start, run.period_end)}, paid ${date(run.pay_date)}` : ''" @close="bankOpen = false">
        <div v-if="bankError" class="rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ bankError }}</div>
        <div v-else-if="!bank" class="space-y-3"><div class="skeleton h-16" /><div class="skeleton h-24" /></div>
        <template v-else>
            <div class="grid grid-cols-2 gap-3">
                <StatCard label="To credit" :value="peso(bank.meta.total)" :hint="`${bank.meta.count} account(s)`" />
                <StatCard label="Reference" :value="bank.meta.reference" />
            </div>
            <div v-if="bank.missing.length" class="mt-4 rounded-lg border border-warn/30 bg-warn-soft px-3 py-2.5 text-sm text-warn" role="status">
                <p class="font-medium">{{ bank.missing.length }} {{ bank.missing.length === 1 ? 'person has' : 'people have' }} no bank account on file ({{ peso(bank.missingTotal) }}). Pay them another way, or add an account to their pay profile.</p>
                <ul class="mt-1.5 space-y-0.5">
                    <li v-for="m in bank.missing" :key="m.employeeId" class="flex justify-between gap-4"><Link :href="`/payroll/employees?edit=${m.employeeId}`" class="hover:underline">{{ m.name }}</Link><span class="num">{{ amount(m.amount) }}</span></li>
                </ul>
            </div>
            <h3 class="mt-6 mb-2 text-sm font-semibold">Download</h3>
            <div class="space-y-2">
                <button v-for="f in bank.formats" :key="f.key" class="flex w-full items-center justify-between rounded-lg border border-line px-3 py-2.5 text-left text-sm hover:bg-surface-2" :disabled="!!busy || !bank.meta.count" @click="save(`bank-${f.key}`, `/payroll/runs/${props.id}/bank/${f.key}`, 'Bank file downloaded. Check the total against your bank portal before uploading.')">
                    <span><span class="font-medium">{{ f.label }}</span> <span class="text-muted">.{{ f.extension }}</span></span>
                    <Icon name="download" :size="16" class="text-muted" />
                </button>
            </div>
            <p class="mt-4 text-xs text-muted">These are generic layouts, not a particular bank's. Each bank gives its own upload format to corporate clients; it can be added once you have it.</p>
        </template>
    </Drawer>

    <Drawer :open="!!adjusting" :title="adjusting ? `Adjust ${adjusting.name}` : ''" subtitle="Tax and contributions are computed again with these lines." wide @close="adjusting = null">
        <section v-for="[key, title, withTax] in [['allowances', 'Allowances and other pay', true], ['otherDeductions', 'Other deductions', false]]" :key="key" class="mb-6">
            <h3 class="mb-2 text-sm font-semibold">{{ title }}</h3>
            <div v-for="(l, i) in lines[key]" :key="i" class="mb-2 flex flex-wrap items-center gap-2">
                <input v-model="l.name" class="input flex-1" placeholder="Description" :aria-label="`${title} description`" />
                <input v-model.number="l.amount" type="number" min="0" step="0.01" class="input num w-32" placeholder="Amount" :aria-label="`${title} amount`" />
                <label v-if="withTax" class="flex items-center gap-1.5 text-xs text-muted"><input v-model="l.taxable" type="checkbox" class="accent-[var(--accent)]" />Taxable</label>
                <button class="rounded-md p-1.5 text-muted hover:text-danger" aria-label="Remove line" @click="lines[key].splice(i, 1)"><Icon name="trash" :size="15" /></button>
            </div>
            <button class="text-sm text-accent hover:underline" @click="lines[key].push(withTax ? { name: '', amount: '', taxable: true } : { name: '', amount: '' })">+ Add line</button>
        </section>
        <p class="text-xs text-muted">De minimis benefits such as a rice subsidy are usually non-taxable; commissions and most allowances are taxable.</p>
        <div v-if="adjError" class="mt-4 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ adjError }}</div>
        <template #footer>
            <button class="btn-ghost" @click="adjusting = null">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'adjust'" @click="saveAdjust">{{ busy === 'adjust' ? 'Recomputing…' : 'Save and recompute' }}</button>
        </template>
    </Drawer>
</template>
