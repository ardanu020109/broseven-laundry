<script setup>
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';
import { formatDateTime } from '@/composables/useFormat';

const props = defineProps({
    steps: { type: Array, default: () => [] },
});

// Tahap yang dilewati (mis. setrika pada paket tanpa setrika) tidak ditampilkan.
const visibleSteps = computed(() => props.steps.filter((step) => step.state !== 'skipped'));
</script>

<template>
    <ol class="timeline">
        <li
            v-for="step in visibleSteps"
            :key="step.status"
            :class="['timeline__item', `timeline__item--${step.state}`]"
        >
            <span class="timeline__marker" aria-hidden="true">
                <Check v-if="step.state === 'done'" :size="14" />
                <X v-else-if="step.state === 'cancelled'" :size="14" />
                <span v-else-if="step.state === 'current'" class="timeline__pulse"></span>
            </span>

            <div class="timeline__body">
                <strong>{{ step.label }}</strong>
                <small v-if="step.at">{{ formatDateTime(step.at) }}</small>
                <small v-else-if="step.state === 'current'">Sedang berlangsung</small>
            </div>
        </li>
    </ol>
</template>

<style scoped>
.timeline { display: flex; flex-direction: column; }

.timeline__item {
    position: relative;
    display: flex;
    gap: var(--space-4);
    padding-bottom: var(--space-5);
}

.timeline__item:last-child { padding-bottom: 0; }

.timeline__item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 13px;
    top: 28px;
    bottom: 0;
    width: 2px;
    background: var(--color-border);
}

.timeline__item--done:not(:last-child)::before {
    background: var(--color-success);
}

.timeline__marker {
    position: relative;
    z-index: 1;
    display: grid;
    place-items: center;
    flex: none;
    width: 28px;
    height: 28px;
    background: var(--color-surface);
    border: 2px solid var(--color-border);
    border-radius: 50%;
    color: #fff;
}

.timeline__body { display: flex; flex-direction: column; padding-top: 3px; }
.timeline__body strong { font-weight: 600; }
.timeline__body small { color: var(--color-muted); }

.timeline__item--pending .timeline__body strong,
.timeline__item--inactive .timeline__body strong { color: var(--color-muted); font-weight: 500; }

.timeline__item--done .timeline__marker {
    background: var(--color-success);
    border-color: var(--color-success);
}

.timeline__item--current .timeline__marker {
    border-color: var(--color-primary-strong);
    background: var(--color-primary-soft);
}

.timeline__pulse {
    width: 10px;
    height: 10px;
    background: var(--color-primary-strong);
    border-radius: 50%;
    animation: pulse 1.6s ease-in-out infinite;
}

.timeline__item--cancelled .timeline__marker {
    background: var(--color-danger);
    border-color: var(--color-danger);
}

.timeline__item--cancelled .timeline__body strong { color: var(--color-danger); }

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.6); opacity: 0.5; }
}
</style>