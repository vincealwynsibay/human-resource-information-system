<script setup>
import { computed } from 'vue';
import { STATUS_LABEL } from '../lib/format';

const props = defineProps({ status: { type: String, required: true } });
const tone = computed(() => ({
    DRAFT: 'bg-surface-2 text-muted',
    PENDING_APPROVAL: 'bg-warn-soft text-warn',
    APPROVED: 'bg-info-soft text-info',
    PAID: 'bg-accent-soft text-accent',
    CANCELLED: 'bg-danger-soft text-danger',
})[props.status] ?? 'bg-surface-2 text-muted');
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap" :class="tone">
        <span class="size-1.5 rounded-full bg-current" />{{ STATUS_LABEL[status] ?? status }}
    </span>
</template>
