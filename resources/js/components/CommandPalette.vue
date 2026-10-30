<script setup>
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { api } from '../lib/api';
import { listOf } from '../lib/hr';

// Ctrl+K: jump to a page or an employee by typing part of the name.
const props = defineProps({ open: Boolean, pages: { type: Array, default: () => [] }, admin: Boolean });
const emit = defineEmits(['update:open']);
const q = ref('');
const input = ref(null);
const active = ref(0);
const people = ref(null);

watch(() => props.open, async (o) => {
    if (!o) return;
    q.value = '';
    active.value = 0;
    await nextTick();
    input.value?.focus();
    if (props.admin && people.value === null) {
        people.value = [];
        try {
            people.value = listOf(await api('get', '/employee?sortField=lastname&sortOrder=asc'), 'employees');
        } catch {
            /* pages still work */
        }
    }
});

const results = computed(() => {
    const s = q.value.trim().toLowerCase();
    const pages = props.pages.filter((p) => !s || p.label.toLowerCase().includes(s) || (p.group ?? '').toLowerCase().includes(s))
        .map((p) => ({ key: p.href, label: p.label, hint: p.group || 'Page', icon: p.icon, href: p.href }));
    const found = s ? (people.value ?? []).filter((e) => `${e.firstname} ${e.middlename ?? ''} ${e.lastname}`.toLowerCase().includes(s) || String(e.id) === s)
        .slice(0, 8).map((e) => ({ key: `e${e.id}`, label: `${e.firstname} ${e.lastname}`, hint: `Employee ${e.id}`, icon: 'user', href: `/people/${e.id}` })) : [];
    return [...found, ...pages].slice(0, 12);
});
watch(results, () => (active.value = 0));

function go(r) {
    if (!r) return;
    emit('update:open', false);
    router.visit(r.href);
}
function key(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        active.value = Math.min(active.value + 1, results.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (e.key === 'Enter') {
        go(results.value[active.value]);
    } else if (e.key === 'Escape') {
        emit('update:open', false);
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[80] flex items-start justify-center bg-black/40 px-4 pt-[12vh]" @mousedown.self="emit('update:open', false)">
            <div class="card w-full max-w-xl overflow-hidden" role="dialog" aria-label="Search">
                <div class="flex items-center gap-3 border-b border-line px-4">
                    <Icon name="search" class="text-muted" />
                    <input ref="input" v-model="q" class="h-12 flex-1 bg-transparent text-sm outline-none placeholder:text-muted" placeholder="Search people and pages…" role="combobox" aria-expanded="true" aria-controls="palette-results" @keydown="key" />
                    <kbd class="rounded border border-line px-1.5 text-[10px] text-muted">Esc</kbd>
                </div>
                <ul id="palette-results" class="max-h-80 overflow-y-auto p-1.5" role="listbox">
                    <li v-for="(r, i) in results" :key="r.key" role="option" :aria-selected="i === active">
                        <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm" :class="i === active ? 'bg-accent-soft' : 'hover:bg-surface-2'" @mouseenter="active = i" @click="go(r)">
                            <Icon :name="r.icon" :size="16" class="text-muted" /><span class="flex-1">{{ r.label }}</span><span class="text-xs text-muted">{{ r.hint }}</span>
                        </button>
                    </li>
                    <li v-if="!results.length" class="px-3 py-8 text-center text-sm text-muted">Nothing matches “{{ q }}”.</li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>
