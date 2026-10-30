<script setup>
import RequestsPage from '../components/RequestsPage.vue';
import { api } from '../lib/api';
import { date, today } from '../lib/format';
import { listOf } from '../lib/hr';

const typeName = (extra, id) => (id == null ? 'Unpaid leave' : extra.types?.find((t) => String(t.id) === String(id))?.name ?? `Type #${id}`);
const days = (r) => r.no_of_days ?? '—';

const kind = {
    title: 'Leave',
    noun: 'leave',
    Noun: 'Leave',
    icon: 'calendar',
    api: '/leave',
    idField: 'leaveId',
    dateField: 'start_date',
    newLabel: 'Request leave',
    adminSubtitle: 'Approve or reject leave. Approved leave is paid in payroll runs that use time records.',
    employeeSubtitle: 'Your leave requests and how many days you have left.',
    formSubtitle: 'Your approver is notified on their Leave page.',
    emptyBody: 'Request leave from the button above.',
    loadExtra: async () => {
        const [types, mine] = await Promise.all([api('get', '/leavetype').then(listOf).catch(() => []), api('get', '/leaveentitlement/me').then(listOf).catch(() => [])]);
        return { types, entitlements: mine };
    },
    blank: () => ({ leaveType: '', startDate: today(), endDate: today(), reason: '' }),
    describe: (r, extra) => `${typeName(extra, r.LeaveTypeID)}, ${date(r.start_date)} to ${date(r.end_date)}${r.reason ? `: “${r.reason}”` : ''}`,
    columns: [
        { label: 'Type', value: (r, extra) => typeName(extra, r.LeaveTypeID) },
        { label: 'Dates', value: (r) => (String(r.start_date).slice(0, 10) === String(r.end_date).slice(0, 10) ? date(r.start_date) : `${date(r.start_date)} – ${date(r.end_date)}`) },
        { label: 'Days', class: 'num hidden sm:table-cell', value: days },
        { label: 'Reason', class: 'hidden max-w-64 truncate text-muted md:table-cell', value: (r) => r.reason ?? '' },
    ],
};
</script>

<template>
    <RequestsPage :kind="kind">
        <template #top="{ extra }">
            <div v-if="extra.entitlements?.length && !$page.props.auth.user.isAdmin" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="e in extra.entitlements" :key="e.id" class="card px-4 py-3">
                    <p class="text-xs text-muted">{{ typeName(extra, e.LeaveTypeID) }}</p>
                    <p class="num mt-1 text-xl font-semibold">{{ Number(e.entitlement) - Number(e.days_used ?? 0) }} <span class="text-sm font-normal text-muted">of {{ Number(e.entitlement) }} days left</span></p>
                </div>
            </div>
        </template>
        <template #form="{ form, extra }">
            <label class="label" for="lt">Type</label>
            <select id="lt" v-model="form.leaveType" class="input">
                <option value="">Unpaid leave</option>
                <option v-for="t in extra.types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div><label class="label" for="sd">First day</label><input id="sd" v-model="form.startDate" type="date" class="input" /></div>
                <div><label class="label" for="ed">Last day</label><input id="ed" v-model="form.endDate" type="date" class="input" /></div>
            </div>
            <label class="label mt-4" for="rs">Reason</label>
            <textarea id="rs" v-model="form.reason" class="input h-24" />
        </template>
    </RequestsPage>
</template>
