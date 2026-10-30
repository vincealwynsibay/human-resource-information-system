<script setup>
import { closeConfirm, confirmState, dismiss, toasts } from '../lib/feedback';
import Icon from './Icon.vue';
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2" aria-live="polite">
            <TransitionGroup enter-from-class="translate-y-2 opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
                <div v-for="t in toasts" :key="t.id" class="pointer-events-auto flex items-start gap-3 rounded-lg border border-line bg-surface px-4 py-3 shadow-lg">
                    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full" :class="t.tone === 'error' ? 'bg-danger-soft text-danger' : 'bg-accent-soft text-accent'">
                        <Icon :name="t.tone === 'error' ? 'alert' : 'check'" :size="13" />
                    </span>
                    <p class="flex-1 text-sm">{{ t.message }}</p>
                    <button class="text-muted hover:text-fg" aria-label="Dismiss" @click="dismiss(t.id)"><Icon name="x" :size="15" /></button>
                </div>
            </TransitionGroup>
        </div>

        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-100">
            <div v-if="confirmState.open" class="fixed inset-0 z-[70] grid place-items-center bg-black/40 p-4" @click.self="closeConfirm(false)" @keydown.esc="closeConfirm(false)">
                <div class="card w-full max-w-sm p-5" role="alertdialog" aria-modal="true" :aria-label="confirmState.title">
                    <h2 class="text-base font-semibold">{{ confirmState.title }}</h2>
                    <p v-if="confirmState.body" class="mt-2 text-sm text-muted">{{ confirmState.body }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button class="btn-ghost" @click="closeConfirm(false)">Back</button>
                        <button :class="confirmState.tone === 'danger' ? 'btn bg-danger text-white hover:opacity-90' : 'btn-primary'" autofocus @click="closeConfirm(true)">{{ confirmState.confirmLabel }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
