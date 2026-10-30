<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import SkeletonRows from '../../components/SkeletonRows.vue';
import EmptyState from '../../components/EmptyState.vue';
import Drawer from '../../components/Drawer.vue';
import Icon from '../../components/Icon.vue';
import { api, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { date } from '../../lib/format';
import { listOf, useLookups } from '../../lib/hr';

const loading = ref(true);
const error = ref('');
const employees = ref([]);
const pay = ref({});
const query = ref('');
const { lookups, loadLookups, nameOf } = useLookups();

let timer = null;
async function load() {
    error.value = '';
    try {
        const q = query.value.trim();
        const res = await api('get', `/employee?sortField=lastname&sortOrder=asc${q ? `&name=${encodeURIComponent(q)}` : ''}`);
        employees.value = listOf(res, 'employees');
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
watch(query, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 250);
});
onMounted(async () => {
    await Promise.all([load(), loadLookups(), api('get', '/payroll/profiles').then((p) => (pay.value = Object.fromEntries(p.items.map((x) => [x.employeeId, x])))).catch(() => {})]);
});

const withoutPay = computed(() => employees.value.filter((e) => !pay.value[String(e.id)]).length);

const drawer = ref(false);
const saving = ref(false);
const formError = ref('');
const form = reactive({ firstname: '', middlename: '', lastname: '' });
async function create() {
    saving.value = true;
    formError.value = '';
    try {
        await api('post', '/employee', form);
        toast(`${form.firstname} ${form.lastname} added.`);
        drawer.value = false;
        Object.assign(form, { firstname: '', middlename: '', lastname: '' });
        await load();
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Employees" />
    <PageHeader title="Employees" subtitle="Everyone in the company, their placement, and whether they are set up for payroll.">
        <button class="btn-primary" @click="drawer = true"><Icon name="plus" :size="16" />Add employee</button>
    </PageHeader>

    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="card overflow-hidden">
        <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <p class="text-sm text-muted">
                <template v-if="!loading">{{ employees.length }} employee(s)<span v-if="withoutPay" class="text-warn"> · {{ withoutPay }} without a pay profile</span></template>
            </p>
            <input v-model="query" class="input max-w-64 py-1.5" placeholder="Search by name" aria-label="Search by name" />
        </header>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr><th class="th">Employee</th><th class="th hidden md:table-cell">Department</th><th class="th hidden md:table-cell">Position</th><th class="th hidden sm:table-cell">Joined</th><th class="th">Payroll</th></tr></thead>
                <tbody>
                    <SkeletonRows v-if="loading" :rows="8" :cols="5" />
                    <tr v-for="e in employees" v-else :key="e.id" class="cursor-pointer hover:bg-surface-2" @click="router.visit(`/people/${e.id}`)">
                        <td class="td">
                            <div class="flex items-center gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-accent-soft text-xs font-semibold text-accent uppercase">{{ (e.firstname?.[0] ?? '') + (e.lastname?.[0] ?? '') }}</span>
                                <span><span class="block font-medium">{{ e.lastname }}, {{ e.firstname }}</span><span class="text-xs text-muted">ID {{ e.id }}<template v-if="e.email"> · {{ e.email }}</template></span></span>
                            </div>
                        </td>
                        <td class="td hidden md:table-cell">{{ nameOf('departments', e.DepartmentID) }}</td>
                        <td class="td hidden md:table-cell">{{ nameOf('jobTitles', e.JobTitleID) }}</td>
                        <td class="td hidden text-muted sm:table-cell">{{ date(e.joined_date) }}</td>
                        <td class="td">
                            <span v-if="pay[String(e.id)]" class="rounded-full bg-accent-soft px-2 py-0.5 text-xs font-semibold text-accent">Set up</span>
                            <span v-else class="rounded-full bg-warn-soft px-2 py-0.5 text-xs font-semibold text-warn">No pay profile</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-if="!loading && !employees.length" icon="people" :title="query ? 'Nobody matches that name' : 'No employees yet'" :body="query ? '' : 'Add your first employee to start.'" />
    </div>

    <Drawer :open="drawer" title="Add employee" subtitle="Their details, job and pay can be completed on their page." @close="drawer = false">
        <label class="label" for="fn">First name</label><input id="fn" v-model="form.firstname" class="input" autofocus />
        <label class="label mt-4" for="mn">Middle name</label><input id="mn" v-model="form.middlename" class="input" />
        <label class="label mt-4" for="ln">Last name</label><input id="ln" v-model="form.lastname" class="input" />
        <div v-if="formError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ formError }}</div>
        <template #footer>
            <button class="btn-ghost" @click="drawer = false">Cancel</button>
            <button class="btn-primary" :disabled="saving || !form.firstname || !form.lastname" @click="create">{{ saving ? 'Adding…' : 'Add employee' }}</button>
        </template>
    </Drawer>
</template>
