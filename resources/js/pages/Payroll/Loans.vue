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
import { amount, period } from '../../lib/format';

const loading = ref(true);
const error = ref('');
const loans = ref([]);
const people = ref([]);
const open = ref(null);

async function load() {
    error.value = '';
    try {
        const [l, p] = await Promise.all([api('get', '/payroll/loans'), api('get', '/payroll/profiles')]);
        loans.value = l.items;
        people.value = p.items;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(load);

const nameOf = (id) => people.value.find((p) => p.employeeId === id)?.name ?? `Employee ${id}`;
const paidOff = (l) => l.balance <= 0;
const progress = (l) => Math.round(((l.principal - l.balance) / l.principal) * 100);
const outstanding = computed(() => loans.value.reduce((a, l) => a + l.balance, 0));

const drawer = ref(false);
const saving = ref(false);
const formError = ref('');
const form = reactive({ employeeId: '', name: 'SSS salary loan', principal: '', amortization: '' });
const periods = computed(() => (form.principal > 0 && form.amortization > 0 ? Math.ceil(form.principal / form.amortization) : null));

function openNew() {
    Object.assign(form, { employeeId: people.value[0]?.employeeId ?? '', name: 'SSS salary loan', principal: '', amortization: '' });
    formError.value = '';
    drawer.value = true;
}
async function save() {
    saving.value = true;
    formError.value = '';
    try {
        await api('post', '/payroll/loans', form);
        toast(`Loan added for ${nameOf(form.employeeId)}.`);
        drawer.value = false;
        await load();
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}
async function remove(l) {
    if (!(await confirm({ title: `Remove “${l.name}”?`, body: `For ${nameOf(l.employeeId)}. Only a loan with no payments yet can be removed.`, confirmLabel: 'Remove', tone: 'danger' }))) return;
    try {
        await api('delete', `/payroll/loans/${l.id}`);
        toast('Loan removed.');
        await load();
    } catch (e) {
        toast(errorMessage(e), 'error');
    }
}
</script>

<template>
    <Head title="Loans" />
    <PageHeader title="Loans" subtitle="Deducted from every pay run after tax, and only reduced once a run is paid.">
        <button class="btn-primary" :disabled="loading || !people.length" @click="openNew"><Icon name="plus" :size="16" />Add loan</button>
    </PageHeader>

    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div class="card overflow-hidden">
        <header class="px-4 py-3 text-sm text-muted">
            <template v-if="!loading">{{ loans.length }} loan(s) · ₱{{ amount(outstanding) }} outstanding</template>
            <div v-else class="skeleton h-4 w-48" />
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead><tr><th class="th">Employee</th><th class="th">Loan</th><th class="th text-right">Per period</th><th class="th">Repaid</th><th class="th text-right">Balance</th><th class="th" /></tr></thead>
                <tbody>
                    <SkeletonRows v-if="loading" :rows="4" :cols="6" />
                    <template v-for="l in loans" v-else :key="l.id">
                        <tr class="cursor-pointer hover:bg-surface-2" @click="open = open === l.id ? null : l.id">
                            <td class="td font-medium">{{ nameOf(l.employeeId) }}</td>
                            <td class="td">{{ l.name }}<p class="text-xs text-muted">₱{{ amount(l.principal) }} borrowed</p></td>
                            <td class="td num text-right">{{ amount(l.amortization) }}</td>
                            <td class="td">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-accent" :style="{ width: `${progress(l)}%` }" /></div>
                                    <span class="text-xs text-muted">{{ paidOff(l) ? 'Paid off' : `${progress(l)}%` }}</span>
                                </div>
                            </td>
                            <td class="td num text-right font-medium">{{ amount(l.balance) }}</td>
                            <td class="td text-right">
                                <button v-if="!l.history.length" class="text-muted hover:text-danger" :aria-label="`Remove ${l.name}`" @click.stop="remove(l)"><Icon name="trash" :size="16" /></button>
                            </td>
                        </tr>
                        <tr v-if="open === l.id" class="bg-surface-2/60">
                            <td colspan="6" class="border-b border-line px-4 py-3">
                                <p v-if="!l.history.length" class="text-sm text-muted">No payments yet. The first one is taken when a run that deducts it is paid.</p>
                                <div v-for="(h, i) in l.history" :key="i" class="flex justify-between py-0.5 text-sm">
                                    <span>{{ period(h.periodStart, h.periodEnd) }}</span><span class="num">−{{ amount(h.amount) }} → {{ amount(h.balanceAfter) }}</span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <EmptyState v-if="!loading && !loans.length" icon="loan" title="No loans" body="SSS and Pag-IBIG salary loans, cash advances and other amortized deductions go here." />
    </div>

    <Drawer :open="drawer" title="Add a loan" @close="drawer = false">
        <label class="label" for="emp">Employee</label>
        <select id="emp" v-model="form.employeeId" class="input"><option v-for="p in people" :key="p.employeeId" :value="p.employeeId">{{ p.name }}</option></select>
        <label class="label mt-4" for="nm">Description</label>
        <input id="nm" v-model="form.name" class="input" list="loan-kinds" />
        <datalist id="loan-kinds"><option value="SSS salary loan" /><option value="Pag-IBIG multi-purpose loan" /><option value="Cash advance" /><option value="Company loan" /></datalist>
        <div class="mt-4 grid grid-cols-2 gap-4">
            <div><label class="label" for="pr">Amount borrowed (₱)</label><input id="pr" v-model.number="form.principal" type="number" min="0" step="0.01" class="input num" /></div>
            <div><label class="label" for="am">Deduct per pay period (₱)</label><input id="am" v-model.number="form.amortization" type="number" min="0" step="0.01" class="input num" /></div>
        </div>
        <p v-if="periods" class="mt-3 text-sm text-muted">Paid off after {{ periods }} pay period(s).</p>
        <div v-if="formError" class="mt-5 rounded-lg border border-danger/30 bg-danger-soft px-3 py-2.5 text-sm text-danger" role="alert">{{ formError }}</div>
        <template #footer>
            <button class="btn-ghost" @click="drawer = false">Cancel</button>
            <button class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Adding…' : 'Add loan' }}</button>
        </template>
    </Drawer>
</template>
