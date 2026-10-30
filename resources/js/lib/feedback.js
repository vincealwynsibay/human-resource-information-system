import { reactive } from 'vue';

// App-wide toasts and a single confirmation dialog, rendered once by the layout.
export const toasts = reactive([]);
let nextId = 1;

export function toast(message, tone = 'success', ms = 4000) {
    const id = nextId++;
    toasts.push({ id, message, tone });
    setTimeout(() => dismiss(id), ms);
}

export function dismiss(id) {
    const i = toasts.findIndex((t) => t.id === id);
    if (i >= 0) toasts.splice(i, 1);
}

export const confirmState = reactive({ open: false, title: '', body: '', confirmLabel: 'Confirm', tone: 'primary', resolve: null });

/** Ask before an action that changes data. Resolves true when the person confirms. */
export function confirm({ title, body = '', confirmLabel = 'Confirm', tone = 'primary' }) {
    return new Promise((resolve) => Object.assign(confirmState, { open: true, title, body, confirmLabel, tone, resolve }));
}

export function closeConfirm(answer) {
    confirmState.resolve?.(answer);
    Object.assign(confirmState, { open: false, resolve: null });
}
