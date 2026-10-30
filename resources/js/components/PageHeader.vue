<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from './Icon.vue';

// Page title with an optional "for <company>" suffix and breadcrumbs ([[label, href], ...], the last one is the page).
const props = defineProps({ title: String, subtitle: String, crumbs: { type: Array, default: null }, company: Boolean });
const companyName = computed(() => usePage().props.app?.company);
const trail = computed(() => props.crumbs ?? []);
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <nav v-if="trail.length" class="mb-1.5 flex flex-wrap items-center gap-1 text-xs text-muted" aria-label="Breadcrumb">
                <Link href="/" class="hover:text-fg" aria-label="Home"><Icon name="home" :size="13" /></Link>
                <template v-for="([label, href], i) in trail" :key="label">
                    <Icon name="chevron" :size="12" />
                    <Link v-if="href && i < trail.length - 1" :href="href" class="hover:text-fg">{{ label }}</Link>
                    <span v-else class="text-fg">{{ label }}</span>
                </template>
            </nav>
            <slot name="eyebrow" />
            <h1 class="text-xl font-semibold tracking-tight">{{ title }}<span v-if="company && companyName" class="font-normal text-muted"> for {{ companyName }}</span></h1>
            <p v-if="subtitle" class="mt-1 text-sm text-muted">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
