<script setup>
import Icon from './Icon.vue';
defineProps({ icon: { type: String, default: 'file' }, title: String, body: String });
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <div class="mb-3 grid size-11 place-items-center rounded-full bg-accent-soft text-accent"><Icon :name="icon" :size="20" /></div>
        <p class="font-semibold">{{ title }}</p>
        <p v-if="body" class="mt-1 max-w-sm text-sm text-muted">{{ body }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
