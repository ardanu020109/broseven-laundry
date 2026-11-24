<script setup>
import { AlertCircle, CheckCircle2, X } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="toasts" aria-live="polite">
            <TransitionGroup name="toast">
                <div v-for="toast in toasts" :key="toast.id" :class="['toast', `toast--${toast.type}`]" role="status">
                    <component :is="toast.type === 'success' ? CheckCircle2 : AlertCircle" :size="18" class="toast__icon" />
                    <span class="toast__text">{{ toast.message }}</span>
                    <button type="button" class="toast__close" aria-label="Tutup" @click="remove(toast.id)">
                        <X :size="14" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toasts {
    position: fixed;
    right: var(--space-4);
    bottom: var(--space-4);
    z-index: var(--z-toast);
    display: grid;
    gap: var(--space-2);
    width: min(380px, calc(100vw - 2 * var(--space-4)));
    pointer-events: none;
}

.toast {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-left-width: 4px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-size: var(--text-sm);
    pointer-events: auto;
}
.toast--success { border-left-color: var(--color-success); }
.toast--success .toast__icon { color: var(--color-success); }
.toast--error { border-left-color: var(--color-danger); }
.toast--error .toast__icon { color: var(--color-danger); }

.toast__icon { flex: none; margin-top: 1px; }
.toast__text { flex: 1; overflow-wrap: anywhere; }
.toast__close { display: grid; place-items: center; background: transparent; border: 0; color: var(--color-muted); }

.toast-enter-active,
.toast-leave-active { transition: opacity var(--transition-base), transform var(--transition-base); }
.toast-enter-from,
.toast-leave-to { opacity: 0; transform: translateY(12px); }
</style>