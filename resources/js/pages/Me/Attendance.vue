<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import StatCard from '../../components/StatCard.vue';
import SkeletonRows from '../../components/SkeletonRows.vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';
import { api, errorMessage } from '../../lib/api';
import { toast } from '../../lib/feedback';
import { date, today } from '../../lib/format';
import { listOf } from '../../lib/hr';

const user = computed(() => usePage().props.auth.user);
const t = today();
const from = `${t.slice(0, 8)}01`;
const loading = ref(true);
const error = ref('');
const records = ref([]);
const summary = ref(null);
const busy = ref('');

async function load() {
    error.value = '';
    try {
        const [recs, sum] = await Promise.all([
            api('get', `/employee/${user.value.employeeId}/attendance/me?fromDate=${from}&toDate=${t}`),
            api('get', `/payroll/my/attendance?from=${from}&to=${t}`).catch(() => null),
        ]);
        records.value = listOf(recs).sort((a, b) => String(b.date).localeCompare(String(a.date)));
        summary.value = sum?.summary ?? null;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
onMounted(() => (user.value.employeeId ? load() : (loading.value = false)));

const todayRow = computed(() => records.value.find((r) => String(r.date).slice(0, 10) === t));
const clock = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : '—');
const nextAction = computed(() => {
    const r = todayRow.value;
    if (!r?.time_in_session1) return 'in';
    if (!r.time_out_session1) return 'out';
    if (!r.time_in_session2) return 'in';
    if (!r.time_out_session2) return 'out';
    return r.time_in_overtime && !r.time_out_overtime ? 'out' : 'in';
});
async function punch() {
    busy.value = nextAction.value;
    try {
        const res = await api('post', `/attendance/${nextAction.value === 'in' ? 'timeIn' : 'timeOut'}`);
        toast(res?.message ? (Array.isArray(res.message) ? res.message[0] : res.message) : nextAction.value === 'in' ? 'Clocked in.' : 'Clocked out.');
        await load();
    } catch (e) {
        toast(errorMessage(e), 'error');
    } finally {
        busy.value = '';
    }
}
</script>

<template>
    <Head title="My attendance" />
    <PageHeader title="My attendance" :subtitle="`${date(from)} to today`">
        <button v-if="user.employeeId" class="btn-primary" :disabled="!!busy || loading" @click="punch">
            <Icon :name="nextAction === 'in' ? 'login' : 'logout'" :size="16" />{{ busy ? 'Saving…' : nextAction === 'in' ? 'Clock in' : 'Clock out' }}
        </button>
    </PageHeader>

    <div v-if="!user.employeeId" class="card"><EmptyState icon="clock" title="Your login is not linked to an employee record" body="Ask an administrator to link it, then your time records appear here." /></div>
    <template v-else>
        <div v-if="error" class="card mb-4 border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Days present" :value="summary ? `${summary.daysPresent} / ${summary.scheduledDays}` : '—'" :loading="loading" />
            <StatCard label="Absences" :value="summary?.absentDays ?? '—'" :hint="summary?.leaveDays ? `${summary.leaveDays} day(s) on leave` : ''" :loading="loading" />
            <StatCard label="Late" :value="summary ? `${summary.lateMinutes} min` : '—'" :loading="loading" />
            <StatCard label="Approved overtime" :value="summary ? `${summary.overtimeHours} h` : '—'" :loading="loading" />
        </div>

        <section class="card mt-6 overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr><th class="th">Date</th><th class="th">Morning</th><th class="th">Afternoon</th><th class="th hidden sm:table-cell">Overtime</th></tr></thead>
                <tbody>
                    <SkeletonRows v-if="loading" :rows="6" :cols="4" />
                    <tr v-for="r in records" v-else :key="r.id">
                        <td class="td font-medium">{{ date(r.date) }}</td>
                        <td class="td num">{{ clock(r.time_in_session1) }} – {{ clock(r.time_out_session1) }}</td>
                        <td class="td num">{{ clock(r.time_in_session2) }} – {{ clock(r.time_out_session2) }}</td>
                        <td class="td num hidden sm:table-cell">{{ r.time_in_overtime ? `${clock(r.time_in_overtime)} – ${clock(r.time_out_overtime)}` : '' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!loading && !records.length" icon="clock" title="No time records this month" body="Clock in when you start work." />
        </section>
    </template>
</template>
