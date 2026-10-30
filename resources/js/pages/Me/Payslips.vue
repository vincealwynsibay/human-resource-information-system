<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import { api, blobErrorMessage, download, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { KIND_LABEL, amount, date, peso, period } from '../../lib/format';

const loading = ref(true);
const error = ref('');
const slips = ref([]);
const selected = ref(null);
const saving = ref(false);

onMounted(async () => {
    try {
        slips.value = (await api('get', '/payroll/my/payslips')).items;
        selected.value = slips.value[0] ?? null;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

async function savePdf() {
    saving.value = true;
    try {
        await download(`/payroll/my/payslips/${selected.value.id}/pdf`);
    } catch (e) {
        toast(await blobErrorMessage(e), 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="My payslips" />
    <PageHeader title="My payslips" subtitle="Payslips appear here once a payroll run is approved." />
    <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>

    <div v-if="loading" class="grid gap-6 md:grid-cols-[16rem_1fr]">
        <div class="card space-y-3 p-3"><div v-for="i in 4" :key="i" class="skeleton h-12" /></div>
        <div class="card space-y-3 p-6"><div class="skeleton h-6 w-1/3" /><div class="skeleton h-4 w-2/3" /><div class="skeleton h-40" /></div>
    </div>

    <div v-else-if="slips.length" class="grid gap-6 md:grid-cols-[16rem_1fr]">
        <nav class="card h-fit p-2" aria-label="Payslips">
            <button v-for="s in slips" :key="s.id" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition-colors" :class="selected?.id === s.id ? 'bg-accent-soft' : 'hover:bg-surface-2'" @click="selected = s">
                <span><span class="block font-medium">{{ period(s.run.period_start, s.run.period_end) }}</span><span class="text-xs text-muted">{{ KIND_LABEL[s.run.kind] }}</span></span>
                <span class="num text-sm">{{ amount(s.net) }}</span>
            </button>
        </nav>

        <article v-if="selected" class="card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted uppercase">Payslip</p>
                    <h2 class="mt-1 text-lg font-semibold">{{ period(selected.run.period_start, selected.run.period_end) }}</h2>
                    <p class="text-sm text-muted">{{ selected.name }} · paid {{ date(selected.run.pay_date) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-muted">Net pay</p>
                    <p class="num text-2xl font-semibold text-accent">{{ peso(selected.net) }}</p>
                    <button class="btn-ghost mt-2 px-3 py-1.5 text-xs" :disabled="saving" @click="savePdf"><Icon name="download" :size="14" />{{ saving ? 'Preparing…' : 'Download PDF' }}</button>
                </div>
            </div>
            <div class="mt-5 grid gap-8 sm:grid-cols-2">
                <section>
                    <h3 class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">Earnings</h3>
                    <div v-for="(e, i) in selected.data.earnings" :key="i" class="flex justify-between gap-4 py-1 text-sm"><span>{{ e.label }}</span><span class="num">{{ amount(e.amount) }}</span></div>
                    <div class="mt-2 flex justify-between border-t border-line pt-2 text-sm font-semibold"><span>Gross</span><span class="num">{{ amount(selected.gross) }}</span></div>
                </section>
                <section>
                    <h3 class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">Deductions</h3>
                    <div v-for="(d, i) in selected.data.deductions" :key="i" class="flex justify-between gap-4 py-1 text-sm"><span>{{ d.label }}</span><span class="num">{{ amount(d.amount) }}</span></div>
                    <div class="mt-2 flex justify-between border-t border-line pt-2 text-sm font-semibold"><span>Total</span><span class="num">{{ amount(selected.total_deductions) }}</span></div>
                </section>
            </div>
            <p v-for="(n, i) in selected.data.notes" :key="i" class="mt-4 text-xs text-muted">{{ n }}</p>
        </article>
    </div>

    <div v-else class="card"><EmptyState icon="slip" title="No payslips yet" body="Your payslips appear here once your employer approves a payroll run." /></div>
</template>
