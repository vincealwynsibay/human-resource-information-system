<script setup>
import RequestsPage from '../components/RequestsPage.vue';
import { date, today } from '../lib/format';

const kind = {
    title: 'Overtime',
    noun: 'overtime',
    Noun: 'Overtime',
    icon: 'clock',
    api: '/overtime',
    idField: 'overtimeId',
    dateField: 'requested_date',
    newLabel: 'Request overtime',
    adminSubtitle: 'Only approved overtime is paid: up to the approved hours, and rest-day or holiday work only when approved.',
    employeeSubtitle: 'Ask before you work extra hours, a rest day or a holiday: only approved overtime is paid.',
    formSubtitle: 'Hours past your shift, or work on a rest day or holiday, are paid only once this is approved.',
    emptyBody: 'Request overtime from the button above.',
    blank: () => ({ requestedDate: today(), duration: 2, reason: '' }),
    describe: (r) => `${Number(r.duration)} hour(s) on ${date(r.requested_date)}${r.reason ? `: “${r.reason}”` : ''}`,
    columns: [
        { label: 'Date', value: (r) => date(r.requested_date) },
        { label: 'Hours', class: 'num', value: (r) => Number(r.duration) },
        { label: 'Reason', class: 'hidden max-w-72 truncate text-muted sm:table-cell', value: (r) => r.reason ?? '' },
    ],
};
</script>

<template>
    <RequestsPage :kind="kind">
        <template #form="{ form }">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label" for="od">Date</label><input id="od" v-model="form.requestedDate" type="date" class="input" /></div>
                <div><label class="label" for="oh">Hours</label><input id="oh" v-model.number="form.duration" type="number" min="0.5" step="0.5" class="input num" /></div>
            </div>
            <label class="label mt-4" for="or">Reason</label>
            <textarea id="or" v-model="form.reason" class="input h-24" />
        </template>
    </RequestsPage>
</template>
