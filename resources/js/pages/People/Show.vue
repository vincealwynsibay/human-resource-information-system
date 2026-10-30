<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import StatCard from '../../components/StatCard.vue';
import Drawer from '../../components/Drawer.vue';
import Icon from '../../components/Icon.vue';
import { api, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { amount, date, today } from '../../lib/format';
import { useLookups } from '../../lib/hr';

const props = defineProps({ id: { type: String, required: true } });
const loading = ref(true);
const error = ref('');
const emp = ref(null);
const pay = ref(null);
const { lookups, loadLookups, nameOf } = useLookups();

// attendance as payroll counts it: this month so far by default
const t = today();
const range = reactive({ from: `${t.slice(0, 8)}01`, to: t });
const att = ref(null);
const attLoading = ref(false);
const attError = ref('');

async function load() {
    error.value = '';
    try {
        const res = await api('get', `/employee/${props.id}`);
        emp.value = res.employee ?? res;
        pay.value = await api('get', `/payroll/profiles/${props.id}`).catch(() => null);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
async function loadAttendance() {
    attLoading.value = true;
    attError.value = '';
    try {
        att.value = await api('get', `/payroll/attendance/${props.id}?from=${range.from}&to=${range.to}`);
    } catch (e) {
        attError.value = errorMessage(e);
    } finally {
        attLoading.value = false;
    }
}
onMounted(() => Promise.all([load(), loadLookups(['departments', 'jobTitles', 'statuses']), loadAttendance()]));

const fullName = computed(() => (emp.value ? [emp.value.firstname, emp.value.middlename, emp.value.lastname].filter(Boolean).join(' ') : ''));
const rate = computed(() => {
    const p = pay.value;
    if (!p) return null;
    return { monthly: [p.monthlySalary, 'a month'], daily: [p.dailyRate, 'a day'], hourly: [p.hourlyRate, 'an hour'] }[p.payBasis ?? 'monthly'];
});

// edit drawers
const editing = ref('');
const saving = ref(false);
const formError = ref('');
const form = reactive({});
const SECTIONS = {
    personal: { title: 'Personal details', url: 'personalDetails', fields: [['firstname', 'First name'], ['middlename', 'Middle name'], ['lastname', 'Last name'], ['birth_date', 'Birth date', 'date'], ['gender', 'Gender'], ['marital_status', 'Civil status']] },
    contact: { title: 'Contact details', url: 'contactDetails', fields: [['email', 'Email'], ['mobile_no', 'Mobile number'], ['address', 'Address'], ['city', 'City'], ['province', 'Province'], ['zip_code', 'ZIP code']] },
    job: { title: 'Job', url: 'jobDetails', fields: [] },
};
function openEdit(section) {
    editing.value = section;
    formError.value = '';
    for (const k of Object.keys(form)) delete form[k];
    for (const [k] of SECTIONS[section].fields) form[k] = emp.value?.[k] ?? '';
    if (section === 'job') Object.assign(form, { DepartmentID: emp.value.DepartmentID ?? '', JobTitleID: emp.value.JobTitleID ?? '', EmploymentStatusID: emp.value.EmploymentStatusID ?? '' });
}
async function save() {
    saving.value = true;
    formError.value = '';
    try {
        const body = editing.value === 'job'
            ? { department: form.DepartmentID || null, jobTitle: form.JobTitleID || null, employmentStatus: form.EmploymentStatusID || null }
            : Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]));
        if (editing.value === 'personal') Object.assign(body, { firstname: form.firstname, lastname: form.lastname });
        await api('put', `/employee/${props.id}/${SECTIONS[editing.value].url}`, body);
        toast(`${SECTIONS[editing.value].title} saved.`);
        editing.value = '';
        await load();
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head :title="fullName || 'Employee'" />
    <Link href="/people" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-fg"><Icon name="back" :size="16" />Employees</Link>
    <div v-if="error" class="card border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <template v-else>
        <div class="mb-6 flex flex-wrap items-center gap-4">
            <span class="grid size-14 place-items-center rounded-full bg-accent-soft text-lg font-semibold text-accent uppercase">{{ (emp?.firstname?.[0] ?? '') + (emp?.lastname?.[0] ?? '') }}</span>
            <div class="min-w-0 flex-1">
                <div v-if="loading" class="skeleton h-7 w-56" />
                <template v-else>
                    <h1 class="text-xl font-semibold tracking-tight">{{ fullName }}</h1>
                    <p class="text-sm text-muted">{{ nameOf('jobTitles', emp.JobTitleID) }} · {{ nameOf('departments', emp.DepartmentID) }} · joined {{ date(emp.joined_date) }}</p>
                </template>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <section v-for="key in ['personal', 'contact']" :key="key" class="card p-5">
                    <header class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">{{ SECTIONS[key].title }}</h2>
                        <button class="text-sm text-accent hover:underline" :disabled="loading" @click="openEdit(key)">Edit</button>
                    </header>
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div v-for="[field, label, kind] in SECTIONS[key].fields" :key="field">
                            <dt class="text-xs text-muted">{{ label }}</dt>
                            <dd v-if="loading" class="skeleton mt-1 h-4 w-2/3" />
                            <dd v-else>{{ (kind === 'date' ? date(emp[field]) : emp[field]) || '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="card p-5">
                    <header class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Attendance</h2>
                            <p class="text-xs text-muted">As payroll counts it, from the time records.</p>
                        </div>
                        <div class="flex items-end gap-2">
                            <div><label class="label" for="af">From</label><input id="af" v-model="range.from" type="date" class="input py-1.5" /></div>
                            <div><label class="label" for="at">To</label><input id="at" v-model="range.to" type="date" class="input py-1.5" /></div>
                            <button class="btn-ghost py-1.5" :disabled="attLoading" @click="loadAttendance">Show</button>
                        </div>
                    </header>
                    <p v-if="attError" class="text-sm text-muted">{{ attError }}</p>
                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <StatCard label="Present" :value="att ? `${att.summary.daysPresent} / ${att.summary.scheduledDays}` : ''" hint="work days" :loading="attLoading || !att" />
                        <StatCard label="Absent" :value="att?.summary.absentDays" :hint="att?.summary.leaveDays ? `${att.summary.leaveDays} on leave` : 'days'" :loading="attLoading || !att" />
                        <StatCard label="Late · undertime" :value="att ? `${att.summary.lateMinutes} · ${att.summary.undertimeMinutes}` : ''" hint="minutes" :loading="attLoading || !att" />
                        <StatCard label="Overtime" :value="att ? `${att.summary.overtimeHours} h` : ''" :hint="att && (att.summary.restDayHours || att.summary.holidayHours) ? `+ ${att.summary.restDayHours + att.summary.holidayHours} h rest day / holiday` : 'approved'" :loading="attLoading || !att" />
                    </div>
                    <p v-if="att?.summary.missingSchedule" class="mt-3 text-xs text-warn">No work shift is assigned, so lateness is not computed.</p>
                    <p v-if="att?.summary.unapprovedHours" class="mt-3 text-xs text-warn">{{ att.summary.unapprovedHours }} hour(s) worked on rest days or holidays without approved overtime are not paid.</p>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-5">
                    <header class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Job</h2>
                        <button class="text-sm text-accent hover:underline" :disabled="loading" @click="openEdit('job')">Edit</button>
                    </header>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Department</dt><dd>{{ loading ? '…' : nameOf('departments', emp.DepartmentID) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Position</dt><dd>{{ loading ? '…' : nameOf('jobTitles', emp.JobTitleID) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Status</dt><dd>{{ loading ? '…' : nameOf('statuses', emp.EmploymentStatusID) }}</dd></div>
                    </dl>
                </section>
                <section class="card p-5">
                    <h2 class="font-semibold">Pay</h2>
                    <template v-if="pay">
                        <p class="num mt-2 text-xl font-semibold">₱{{ amount(rate[0]) }} <span class="text-sm font-normal text-muted">{{ rate[1] }}</span></p>
                        <p class="mt-1 text-sm text-muted">{{ pay.region ?? 'No region' }} · {{ pay.minimumWage?.earner ? 'minimum-wage earner (tax-exempt)' : 'taxable' }}</p>
                        <p class="mt-1 text-sm" :class="pay.ids?.tin ? 'text-muted' : 'text-warn'">{{ pay.ids?.tin ? `TIN ${pay.ids.tin}` : 'TIN missing: needed for the BIR alphalist' }}</p>
                        <Link href="/payroll/employees" class="btn-ghost mt-4 w-full">Edit pay profile</Link>
                    </template>
                    <template v-else-if="!loading">
                        <p class="mt-2 text-sm text-muted">Not set up for payroll yet. Add their pay profile with employee ID {{ id }}.</p>
                        <Link href="/payroll/employees" class="btn-primary mt-4 w-full">Set up pay</Link>
                    </template>
                </section>
            </aside>
        </div>
    </template>

    <Drawer :open="!!editing" :title="SECTIONS[editing]?.title" @close="editing = ''">
        <template v-if="editing === 'job'">
            <label class="label" for="dp">Department</label>
            <select id="dp" v-model="form.DepartmentID" class="input"><option value="">—</option><option v-for="d in lookups.departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
            <label class="label mt-4" for="jt">Position</label>
            <select id="jt" v-model="form.JobTitleID" class="input"><option value="">—</option><option v-for="d in lookups.jobTitles" :key="d.id" :value="d.id">{{ d.name }}</option></select>
            <label class="label mt-4" for="es">Employment status</label>
            <select id="es" v-model="form.EmploymentStatusID" class="input"><option value="">—</option><option v-for="d in lookups.statuses" :key="d.id" :value="d.id">{{ d.name }}</option></select>
        </template>
        <div v-else class="grid gap-4 sm:grid-cols-2">
            <div v-for="[field, label, kind] in SECTIONS[editing]?.fields ?? []" :key="field">
                <label class="label" :for="field">{{ label }}</label>
                <input :id="field" v-model="form[field]" :type="kind === 'date' ? 'date' : 'text'" class="input" />
            </div>
        </div>
        <div v-if="formError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ formError }}</div>
        <template #footer>
            <button class="btn-ghost" @click="editing = ''">Cancel</button>
            <button class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
        </template>
    </Drawer>
</template>
