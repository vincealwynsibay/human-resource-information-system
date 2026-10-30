<script setup>
// One measure across categories as labelled horizontal bars (a single series: one colour, labels carry identity).
import { computed } from 'vue';

const props = defineProps({ rows: { type: Array, required: true }, unit: { type: String, default: '' } });
const max = computed(() => Math.max(1, ...props.rows.map((r) => Number(r.count))));
</script>

<template>
    <ul class="space-y-2.5">
        <li v-for="r in rows" :key="r.name" class="group" :title="`${r.name}: ${r.count} ${unit}`">
            <div class="mb-1 flex justify-between text-sm"><span>{{ r.name }}</span><span class="num text-muted">{{ r.count }}</span></div>
            <div class="h-2 rounded-full bg-surface-2"><div class="h-2 rounded-full bg-accent transition-[width] group-hover:bg-accent-hover" :style="{ width: `${(Number(r.count) / max) * 100}%` }" /></div>
        </li>
    </ul>
</template>
