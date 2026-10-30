<script setup>
defineProps({ label: String, value: [String, Number], hint: String, loading: Boolean });
</script>

<template>
    <div class="card px-4 py-3.5">
        <p class="text-xs font-medium text-muted">{{ label }}</p>
        <div v-if="loading" class="skeleton mt-2 h-7 w-2/3" />
        <p v-else class="num mt-1 text-xl font-semibold tracking-tight">{{ value }}</p>
        <p v-if="hint && !loading" class="mt-0.5 text-xs text-muted">{{ hint }}</p>
    </div>
</template>
