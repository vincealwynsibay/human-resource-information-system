<script setup>
import { onBeforeUnmount, watch } from 'vue';
import Icon from './Icon.vue';

// A panel that slides in from the right for create and edit forms; Escape or the backdrop closes it.
const props = defineProps({ open: Boolean, title: String, subtitle: String, wide: Boolean });
const emit = defineEmits(['close']);
const onKey = (e) => e.key === 'Escape' && emit('close');
watch(() => props.open, (o) => (o ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey)));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-150">
            <div v-if="open" class="fixed inset-0 z-40 bg-black/30" @click="emit('close')" />
        </Transition>
        <Transition enter-from-class="translate-x-full" leave-to-class="translate-x-full" enter-active-class="transition-transform duration-200" leave-active-class="transition-transform duration-150">
            <aside v-if="open" class="fixed inset-y-0 right-0 z-50 flex w-full flex-col border-l border-line bg-surface shadow-xl" :class="wide ? 'max-w-2xl' : 'max-w-md'" role="dialog" aria-modal="true" :aria-label="title">
                <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold">{{ title }}</h2>
                        <p v-if="subtitle" class="mt-0.5 text-sm text-muted">{{ subtitle }}</p>
                    </div>
                    <button class="rounded-md p-1 text-muted hover:bg-surface-2" aria-label="Close" @click="emit('close')"><Icon name="x" /></button>
                </header>
                <div class="flex-1 overflow-y-auto px-5 py-5"><slot /></div>
                <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-5 py-3"><slot name="footer" /></footer>
            </aside>
        </Transition>
    </Teleport>
</template>
