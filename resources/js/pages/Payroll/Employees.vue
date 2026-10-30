<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import SkeletonRows from '../../components/SkeletonRows.vue';
import EmptyState from '../../components/EmptyState.vue';
import Drawer from '../../components/Drawer.vue';
import Icon from '../../components/Icon.vue';
import { api, errorMessage } from '../../lib/api';
import { confirm, toast } from '../../lib/feedback';
import { amount, date } from '../../lib/format';

const loading = ref(true);
const error = ref('');
const items = ref([]);
const options = ref({ payBases: [], regions: [], sectors: [], employmentStatuses: {} });
const query = ref('');

async function load() {
    error.value = '';
    try {
        const [p, o] = await Promise.all([api('get', '/payroll/profiles'), api('get', '/payroll/profiles/options')]);
        items.value = p.items;
        options.value = o;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(load);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return items.value.filter((p) => !q || p.name.toLowerCase().includes(q) || (p.region ?? '').toLowerCase().includes(q));
});
const rateOf = (p) => ({ monthly: [p.monthlySalary, '/ month'], daily: [p.dailyRate, '/ day'], hourly: [p.hourlyRate, '/ hour'] })[p.payBasis ?? 'monthly'];
const BASIS = { monthly: 'Monthly', daily: 'Daily', hourly: 'Hourly' };

// Edit drawer
const drawer = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const blank = () => ({ employeeId: '', name: '', lastName: '', firstName: '', middleName: '', payBasis: 'monthly', rate: '', region: 'NCR', sector: 'non_agri', minimumWageEarner: 'auto', hireDate: '', birthDate: '', employmentStatus: 'R', dailyRateFactor: 261, tin: '', sss: '', philhealth: '', pagibig: '' });
const form = reactive(blank());

function edit(p) {
    editingId.value = p?.employeeId ?? null;
    formError.value = '';
    Object.assign(form, blank(), p ? {
        employeeId: p.employeeId, name: p.name, lastName: p.lastName ?? '', firstName: p.firstName ?? '', middleName: p.middleName ?? '',
        payBasis: p.payBasis ?? 'monthly', rate: rateOf(p)[0] ?? '', region: p.region ?? '', sector: p.sector ?? 'non_agri',
        minimumWageEarner: p.minimumWageEarner === true ? 'yes' : p.minimumWageEarner === 'auto' ? 'auto' : 'no',
        hireDate: p.hireDate ?? '', birthDate: p.birthDate ?? '', employmentStatus: p.employmentStatus ?? 'R', dailyRateFactor: p.dailyRateFactor ?? 261, ...(p.ids ?? {}),
    } : {});
    drawer.value = true;
}

async function save() {
    saving.value = true;
    formError.value = '';
    const id = (editingId.value ?? form.employeeId).trim();
    try {
        if (!id) throw new Error('Enter the employee ID used in the HR records.');
        await api('put', `/payroll/profiles/${encodeURIComponent(id)}`, {
            name: form.name, lastName: form.lastName, firstName: form.firstName, middleName: form.middleName,
            payBasis: form.payBasis, rate: form.rate, region: form.region, sector: form.sector, minimumWageEarner: form.minimumWageEarner,
            hireDate: form.hireDate, birthDate: form.birthDate, employmentStatus: form.employmentStatus, dailyRateFactor: form.dailyRateFactor,
            ids: { tin: form.tin, sss: form.sss, philhealth: form.philhealth, pagibig: form.pagibig },
        });
        toast(`${form.name}'s pay profile is saved.`);
        drawer.value = false;
        await load();
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function remove() {
    if (!(await confirm({ title: `Remove ${form.name}'s pay profile?`, body: 'Saved payslips stay; the employee is left out of new runs.', confirmLabel: 'Remove', tone: 'danger' }))) return;
    try {
        await api('delete', `/payroll/profiles/${encodeURIComponent(editingId.value)}`);
        toast('Pay profile removed.');
        drawer.value = false;
        await load();
    } catch (e) {
        formError.value = errorMessage(e);
    }
}

// CSV import
const importer = ref(false);
const csv = ref('');
const report = ref(null);
const importing = ref(false);
const importError = ref('');
function openImport() {
    report.value = null;
    csv.value = '';
    importError.value = '';
    importer.value = true;
}
async function readFile(ev) {
    const f = ev.target.files?.[0];
    if (f) csv.value = await f.text();
}
async function runImport(dryRun) {
    importing.value = true;
    importError.value = '';
    try {
        const out = await api('post', '/payroll/profiles/import', { csv: csv.value, dryRun });
        report.value = out.report;
        if (!dryRun) {
            toast(`${out.report.updated.length} pay profile(s) updated.`);
            importer.value = false;
            await load();
        }
    } catch (e) {
        importError.value = errorMessage(e);
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <Head title="Pay profiles" />
    <PageHeader title="Pay profiles" subtitle="What each employee is paid, and what the BIR alphalist needs to know about them.">
        <button class="btn-ghost" @click="openImport"><Icon name="upload" :size="16" />Import CSV</button>
        <button class="btn-primary" @click="edit(null)"><Icon name="plus" :size="16" />Add profile</button>
    </PageHeader>

    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="card overflow-hidden">
        <header class="flex items-center justify-between gap-3 px-4 py-3">
            <p class="text-sm text-muted">{{ loading ? 'Loading…' : `${filtered.length} of ${items.length} employees` }}</p>
            <input v-model="query" class="input max-w-60 py-1.5" placeholder="Search name or region" aria-label="Search" />
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead><tr><th class="th">Employee</th><th class="th">Pay</th><th class="th">Region</th><th class="th">Tax status</th><th class="th">Hired</th><th class="th">TIN</th></tr></thead>
                <tbody>
                    <SkeletonRows v-if="loading" :rows="8" :cols="6" />
                    <tr v-for="p in filtered" v-else :key="p.employeeId" class="cursor-pointer hover:bg-surface-2" @click="edit(p)">
                        <td class="td"><p class="font-medium">{{ p.name }}</p><p class="text-xs text-muted">ID {{ p.employeeId }}</p></td>
                        <td class="td"><span class="num">₱{{ amount(rateOf(p)[0]) }}</span> <span class="text-muted">{{ rateOf(p)[1] }}</span><p class="text-xs text-muted">{{ BASIS[p.payBasis ?? 'monthly'] }}</p></td>
                        <td class="td">{{ p.region ?? '—' }}</td>
                        <td class="td">
                            <span v-if="p.minimumWage?.earner" class="rounded-full bg-info-soft px-2 py-0.5 text-xs font-semibold text-info" :title="p.minimumWage.reason">Minimum wage · exempt</span>
                            <span v-else class="text-muted">Taxable</span>
                        </td>
                        <td class="td text-muted">{{ date(p.hireDate) }}</td>
                        <td class="td">
                            <span v-if="p.ids?.tin" class="num">{{ p.ids.tin }}</span>
                            <span v-else class="inline-flex items-center gap-1 text-xs text-warn"><Icon name="alert" :size="13" />missing</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-if="!loading && !items.length" icon="people" title="No pay profiles yet" body="Add each employee's pay, or import them from a spreadsheet saved as CSV.">
            <button class="btn-primary" @click="edit(null)"><Icon name="plus" :size="16" />Add profile</button>
        </EmptyState>
    </div>

    <Drawer :open="drawer" :title="editingId ? form.name : 'New pay profile'" subtitle="Checked against the payroll rules when you save." wide @close="drawer = false">
        <div class="grid gap-4 sm:grid-cols-2">
            <div v-if="!editingId"><label class="label" for="eid">Employee ID</label><input id="eid" v-model="form.employeeId" class="input" placeholder="as in the HR records" /></div>
            <div :class="editingId ? 'sm:col-span-2' : ''"><label class="label" for="nm">Display name</label><input id="nm" v-model="form.name" class="input" /></div>
            <div><label class="label" for="ln">Last name</label><input id="ln" v-model="form.lastName" class="input" /></div>
            <div><label class="label" for="fn">First name</label><input id="fn" v-model="form.firstName" class="input" /></div>
            <div><label class="label" for="mn">Middle name</label><input id="mn" v-model="form.middleName" class="input" /></div>
            <div>
                <label class="label" for="es">Employment status</label>
                <select id="es" v-model="form.employmentStatus" class="input"><option v-for="(label, code) in options.employmentStatuses" :key="code" :value="code">{{ label }}</option></select>
            </div>

            <p class="mt-2 text-xs font-semibold tracking-wide text-muted uppercase sm:col-span-2">Pay</p>
            <div>
                <label class="label" for="pb">Paid</label>
                <select id="pb" v-model="form.payBasis" class="input"><option value="monthly">Monthly</option><option value="daily">Daily</option><option value="hourly">Hourly</option></select>
            </div>
            <div><label class="label" for="rt">Rate (₱ {{ { monthly: 'a month', daily: 'a day', hourly: 'an hour' }[form.payBasis] }})</label><input id="rt" v-model="form.rate" type="number" min="0" step="0.01" class="input num" /></div>
            <div>
                <label class="label" for="rg">Region</label>
                <select id="rg" v-model="form.region" class="input"><option value="">—</option><option v-for="r in options.regions" :key="r" :value="r">{{ r }}</option></select>
            </div>
            <div>
                <label class="label" for="sc">Sector</label>
                <select id="sc" v-model="form.sector" class="input"><option value="non_agri">Non-agriculture</option><option value="agri_small">Agriculture / small establishment</option></select>
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="mw">Minimum-wage earner (tax-exempt)</label>
                <select id="mw" v-model="form.minimumWageEarner" class="input"><option value="auto">Decide from the regional minimum wage</option><option value="yes">Yes</option><option value="no">No</option></select>
            </div>
            <div class="sm:col-span-2">
                <label class="label" for="fac">Days paid per year</label>
                <select id="fac" v-model.number="form.dailyRateFactor" class="input">
                    <option :value="261">261 — five-day week</option><option :value="262">262 — five-day week, leap year</option>
                    <option :value="313">313 — six-day week</option><option :value="365">365 — rest days and holidays paid</option>
                </select>
                <p class="mt-1 text-xs text-muted">Turns a monthly salary into the daily rate used for absences, overtime and premiums.</p>
            </div>
            <div><label class="label" for="hd">Hire date</label><input id="hd" v-model="form.hireDate" type="date" class="input" /></div>
            <div><label class="label" for="bd">Birth date</label><input id="bd" v-model="form.birthDate" type="date" class="input" /></div>

            <p class="mt-2 text-xs font-semibold tracking-wide text-muted uppercase sm:col-span-2">Government numbers</p>
            <div><label class="label" for="tin">TIN</label><input id="tin" v-model="form.tin" class="input num" placeholder="123-456-789" /></div>
            <div><label class="label" for="sss">SSS</label><input id="sss" v-model="form.sss" class="input num" /></div>
            <div><label class="label" for="ph">PhilHealth</label><input id="ph" v-model="form.philhealth" class="input num" /></div>
            <div><label class="label" for="pi">Pag-IBIG</label><input id="pi" v-model="form.pagibig" class="input num" /></div>
        </div>
        <div v-if="formError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ formError }}</div>
        <template #footer>
            <button v-if="editingId" class="btn-danger mr-auto" @click="remove"><Icon name="trash" :size="16" />Remove</button>
            <button class="btn-ghost" @click="drawer = false">Cancel</button>
            <button class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save profile' }}</button>
        </template>
    </Drawer>

    <Drawer :open="importer" title="Import pay data" subtitle="Rows are matched to existing profiles by ID or name. Nothing is saved until you apply." wide @close="importer = false">
        <p class="text-sm text-muted">Columns, in any order: <span class="font-mono text-xs">Employee, Pay basis, Rate, Hire date, Birth date, Region, Sector, Min wage, SSS no., PhilHealth no., Pag-IBIG no., TIN</span></p>
        <input type="file" accept=".csv,text/csv" class="mt-4 block text-sm" @change="readFile" />
        <textarea v-model="csv" class="input mt-3 h-40 font-mono text-xs" placeholder="…or paste the CSV here" />
        <div v-if="importError" class="mt-4 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger">{{ importError }}</div>
        <div v-if="report" class="mt-4 rounded-lg border border-line bg-surface-2 p-4 text-sm">
            <p><span class="font-semibold text-accent">{{ report.updated.length }}</span> profile(s) will be updated<span v-if="report.updated.length">: {{ report.updated.join(', ') }}</span></p>
            <p v-if="report.skipped.length" class="mt-2 font-medium text-warn">{{ report.skipped.length }} row(s) skipped</p>
            <ul class="mt-1 space-y-0.5 text-xs text-muted"><li v-for="s in report.skipped" :key="s.row">Row {{ s.row }}: {{ s.reason }}</li></ul>
        </div>
        <template #footer>
            <button class="btn-ghost" :disabled="importing || !csv" @click="runImport(true)">Check</button>
            <button class="btn-primary" :disabled="importing || !report || !report.updated.length" @click="runImport(false)">Apply {{ report?.updated.length ?? '' }}</button>
        </template>
    </Drawer>
</template>
