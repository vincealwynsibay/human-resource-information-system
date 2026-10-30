<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

// A button that opens a small panel below it; a click outside or Escape closes it.
defineProps({ align: { type: String, default: 'right' } });
const open = ref(false);
const root = ref(null);
const outside = (e) => root.value && !root.value.contains(e.target) && (open.value = false);
const esc = (e) => e.key === 'Escape' && (open.value = false);
watch(open, (o) => {
    if (o) {
        document.addEventListener('mousedown', outside);
        document.addEventListener('keydown', esc);
    } else {
        document.removeEventListener('mousedown', outside);
        document.removeEventListener('keydown', esc);
    }
});
onBeforeUnmount(() => (open.value = false));
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" :aria-expanded="open" class="block" @click="open = !open"><slot name="trigger" /></button>
        <Transition enter-from-class="opacity-0 -translate-y-1" leave-to-class="opacity-0" enter-active-class="transition duration-100" leave-active-class="transition duration-75">
            <div v-if="open" class="absolute top-full z-40 mt-2 overflow-hidden rounded-xl border border-line bg-surface shadow-xl" :class="align === 'left' ? 'left-0' : 'right-0'" @click="open = false">
                <slot />
            </div>
        </Transition>
    </div>
</template>
