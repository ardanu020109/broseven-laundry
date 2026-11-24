import { reactive } from 'vue';

const toasts = reactive([]);
let counter = 0;

function remove(id) {
    const index = toasts.findIndex((toast) => toast.id === id);
    if (index >= 0) toasts.splice(index, 1);
}

function push(type, message, timeout) {
    const id = ++counter;
    toasts.push({ id, type, message });

    if (timeout) setTimeout(() => remove(id), timeout);
}

/** Notifikasi singkat. Dirender oleh <ToastHost /> di App.vue. */
export function useToast() {
    return {
        toasts,
        remove,
        success: (message) => push('success', message, 4000),
        error: (message) => push('error', message, 6500),
    };
}