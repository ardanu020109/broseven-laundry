<script setup>
import { formatDateTime } from '@/composables/useFormat';

defineProps({
    histories: { type: Array, default: () => [] },
});
</script>

<template>
    <ol class="hist">
        <li v-for="item in histories" :key="item.id" class="hist__item">
            <span class="hist__dot" aria-hidden="true"></span>

            <div class="hist__body">
                <strong>{{ item.to_label }}</strong>
                <small>
                    {{ formatDateTime(item.created_at) }}
                    <template v-if="item.changed_by"> &middot; {{ item.changed_by.name }}</template>
                </small>
                <p v-if="item.note" class="hist__note">{{ item.note }}</p>
            </div>
        </li>
    </ol>
</template>

<style scoped>
.hist { display: grid; }

.hist__item { position: relative; display: flex; gap: var(--space-4); padding-bottom: var(--space-4); }
.hist__item:last-child { padding-bottom: 0; }

.hist__item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 6px;
    top: 18px;
    bottom: 0;
    width: 2px;
    background: var(--color-border);
}

.hist__dot { flex: none; width: 14px; height: 14px; margin-top: 4px; background: var(--gradient-brand); border: 2px solid var(--color-surface); border-radius: 50%; box-shadow: 0 0 0 2px var(--color-primary); }

.hist__body { display: grid; gap: 2px; }
.hist__body small { color: var(--color-muted); }
.hist__note { margin: var(--space-1) 0 0; padding: var(--space-2) var(--space-3); background: var(--color-primary-soft); border-radius: var(--radius-sm); font-size: var(--text-sm); }
</style>