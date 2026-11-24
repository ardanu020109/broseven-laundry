<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { X } from 'lucide-vue-next';

const open = defineModel({ type: Boolean, default: false });

const props = defineProps({
    title: { type: String, required: true },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const titleId = useId();
const dialog = ref(null);
let previouslyFocused = null;

function close() {
    open.value = false;
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.stopPropagation();
        close();
    }
}

function onBackdrop() {
    if (props.closeOnBackdrop) close();
}

function lock() {
    previouslyFocused = document.activeElement;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
}

function unlock() {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
    previouslyFocused?.focus?.();
    previouslyFocused = null;
}

watch(
    open,
    async (isOpen) => {
        if (isOpen) {
            lock();
            await nextTick();
            dialog.value?.focus();
        } else {
            unlock();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (open.value) unlock();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="modal" @mousedown.self="onBackdrop">
                <div
                    ref="dialog"
                    :class="['modal__dialog', `modal__dialog--${size}`]"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    tabindex="-1"
                >
                    <header class="modal__header">
                        <h3 :id="titleId" class="modal__title">{{ title }}</h3>
                        <button type="button" class="modal__close" aria-label="Tutup" @click="close">
                            <X :size="18" />
                        </button>
                    </header>

                    <div class="modal__body"><slot /></div>

                    <footer v-if="$slots.footer" class="modal__footer"><slot name="footer" /></footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal {
    position: fixed;
    inset: 0;
    z-index: var(--z-modal);
    display: grid;
    place-items: center;
    padding: var(--space-4);
    background: color-mix(in srgb, #0f1f29 45%, transparent);
    backdrop-filter: blur(4px);
}

.modal__dialog {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-height: calc(100vh - 2 * var(--space-4));
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    outline: none;
}

.modal__dialog--sm { max-width: 420px; }
.modal__dialog--md { max-width: 560px; }
.modal__dialog--lg { max-width: 760px; }

.modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid var(--color-border);
}

.modal__title { margin: 0; font-size: var(--text-lg); }

.modal__close {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    background: transparent;
    border: 0;
    border-radius: var(--radius-full);
    color: var(--color-muted);
    transition: background var(--transition-fast), color var(--transition-fast);
}
.modal__close:hover { background: var(--color-primary-soft); color: var(--color-text); }

.modal__body { padding: var(--space-5); overflow-y: auto; }

.modal__footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-3);
    padding: var(--space-4) var(--space-5);
    border-top: 1px solid var(--color-border);
}

.modal-enter-active,
.modal-leave-active { transition: opacity var(--transition-base); }
.modal-enter-active .modal__dialog,
.modal-leave-active .modal__dialog { transition: transform var(--transition-base); }

.modal-enter-from,
.modal-leave-to { opacity: 0; }
.modal-enter-from .modal__dialog,
.modal-leave-to .modal__dialog { transform: translateY(16px) scale(0.98); }
</style>