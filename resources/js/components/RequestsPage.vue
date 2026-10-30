<script setup>
// Leave and overtime requests share one workflow: employees file and withdraw their own pending requests,
// administrators approve or reject them. `kind` holds what differs (endpoints, fields, columns).
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import PageHeader from './PageHeader.vue';
import SkeletonRows from './SkeletonRows.vue';
import EmptyState from './EmptyState.vue';
import Drawer from './Drawer.vue';
import Icon from './Icon.vue';
import { api, errorMessage } from '../lib/api';
import { confirm, toast } from '../lib/feedback';
import { REQUEST_STATUS, listOf } from '../lib/hr';

const props = defineProps({ kind: { type: Object, required: true } });
const page = usePage();
const user = computed(() => page.props.auth.user);
const admin = computed(() => user.value?.isAdmin);

const loading = ref(true);
const error = ref('');
const rows = ref([]);
const people = ref({});
const status = ref(admin.value ? 'PENDING' : '');
const extra = ref({});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const url = admin.value ? `${props.kind.api}${status.value ? `?status=${status.value}` : ''}` : `${props.kind.api}/me`;
        rows.value = listOf(await api('get', url)).sort((a, b) => String(b[props.kind.dateField]).localeCompare(String(a[props.kind.dateField])));
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(async () => {
    const tasks = [load(), props.kind.loadExtra?.().then((x) => (extra.value = x)).catch(() => {})];
    if (admin.value) tasks.push(api('get', '/employee').then((r) => (people.value = Object.fromEntries(listOf(r, 'employees').map((e) => [String(e.id), `${e.firstname} ${e.lastname}`])))).catch(() => {}));
    await Promise.all(tasks);
});
const shown = computed(() => (admin.value || !status.value ? rows.value : rows.value.filter((r) => r.status === status.value)));
const tabs = [['PENDING', 'Pending'], ['APPROVED', 'Approved'], ['REJECTED', 'Rejected'], ['', 'All']];

async function decide(row, approve) {
    const who = people.value[String(row.EmployeeID)] ?? `Employee ${row.EmployeeID}`;
    if (!(await confirm({ title: `${approve ? 'Approve' : 'Reject'} ${who}'s ${props.kind.noun}?`, body: props.kind.describe(row, extra.value), confirmLabel: approve ? 'Approve' : 'Reject', tone: approve ? 'primary' : 'danger' }))) return;
    try {
        await api('post', `${props.kind.api}/${approve ? 'approve' : 'reject'}`, { [props.kind.idField]: row.id });
        toast(`${props.kind.Noun} ${approve ? 'approved' : 'rejected'}.`);
        await load();
    } catch (e) {
        toast(errorMessage(e), 'error');
    }
}
async function withdraw(row) {
    if (!(await confirm({ title: `Withdraw this ${props.kind.noun} request?`, body: props.kind.describe(row, extra.value), confirmLabel: 'Withdraw', tone: 'danger' }))) return;
    try {
        await api('delete', `${props.kind.api}/${row.id}`);
        toast('Request withdrawn.');
        await load();
    } catch (e) {
        toast(errorMessage(e), 'error');
    }
}

const drawer = ref(false);
const saving = ref(false);
const formError = ref('');
const form = reactive({});
function openNew() {
    for (const k of Object.keys(form)) delete form[k];
    Object.assign(form, props.kind.blank());
    formError.value = '';
    drawer.value = true;
}
async function submit() {
    saving.value = true;
    formError.value = '';
    try {
        await api('post', props.kind.api, { ...form, employeeId: user.value.employeeId });
        toast(`${props.kind.Noun} request sent for approval.`);
        drawer.value = false;
        await load();
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head :title="kind.title" />
    <PageHeader :title="admin ? kind.title : `My ${kind.noun}`" :subtitle="admin ? kind.adminSubtitle : kind.employeeSubtitle">
        <button v-if="user.employeeId" class="btn-primary" @click="openNew"><Icon name="plus" :size="16" />{{ kind.newLabel }}</button>
    </PageHeader>

    <slot name="top" :extra="extra" />

    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        <button v-for="[key, label] in tabs" :key="key" role="tab" :aria-selected="status === key" class="-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap" :class="status === key ? 'border-accent font-medium' : 'border-transparent text-muted hover:text-fg'" @click="status = key; admin && load()">{{ label }}</button>
    </div>
    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr>
                <th v-if="admin" class="th">Employee</th>
                <th v-for="c in kind.columns" :key="c.label" class="th" :class="c.class">{{ c.label }}</th>
                <th class="th">Status</th><th class="th" />
            </tr></thead>
            <tbody>
                <SkeletonRows v-if="loading" :rows="5" :cols="kind.columns.length + (admin ? 3 : 2)" />
                <tr v-for="r in shown" v-else :key="r.id">
                    <td v-if="admin" class="td font-medium">{{ people[String(r.EmployeeID)] ?? `Employee ${r.EmployeeID}` }}</td>
                    <td v-for="c in kind.columns" :key="c.label" class="td" :class="c.class">{{ c.value(r, extra) }}</td>
                    <td class="td"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="REQUEST_STATUS[r.status] ?? 'bg-surface-2 text-muted'">{{ r.status }}</span></td>
                    <td class="td text-right whitespace-nowrap">
                        <template v-if="r.status === 'PENDING'">
                            <template v-if="admin">
                                <button class="btn-ghost px-2.5 py-1 text-xs" @click="decide(r, false)">Reject</button>
                                <button class="btn-primary ml-2 px-2.5 py-1 text-xs" @click="decide(r, true)">Approve</button>
                            </template>
                            <button v-else class="text-xs text-muted hover:text-danger" @click="withdraw(r)">Withdraw</button>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="!loading && !shown.length" :icon="kind.icon" :title="admin && status === 'PENDING' ? 'Nothing waiting for approval' : `No ${kind.noun} requests`" :body="admin ? '' : kind.emptyBody" />
    </div>

    <Drawer :open="drawer" :title="kind.newLabel" :subtitle="kind.formSubtitle" @close="drawer = false">
        <slot name="form" :form="form" :extra="extra" />
        <div v-if="formError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ formError }}</div>
        <template #footer>
            <button class="btn-ghost" @click="drawer = false">Cancel</button>
            <button class="btn-primary" :disabled="saving" @click="submit">{{ saving ? 'Sending…' : 'Send for approval' }}</button>
        </template>
    </Drawer>
</template>
