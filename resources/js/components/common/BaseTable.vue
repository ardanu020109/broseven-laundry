<script setup>
defineProps({
    caption: { type: String, default: '' },
});
</script>

<template>
    <div class="table-wrap" tabindex="0">
        <table class="table">
            <caption v-if="caption" class="sr-only">{{ caption }}</caption>
            <slot />
        </table>
    </div>
</template>

<style scoped>
.table-wrap {
    max-width: 100%;
    overflow-x: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
}

.table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }

.table :deep(th),
.table :deep(td) {
    padding: 0.8rem 1rem;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid var(--color-border);
}

.table :deep(th) {
    background: var(--color-primary-soft);
    color: var(--color-muted);
    font-size: var(--text-xs);
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.table :deep(tbody tr:last-child td) { border-bottom: 0; }
.table :deep(tbody tr:hover td) { background: color-mix(in srgb, var(--color-primary) 10%, white); }
.table :deep(.num) { text-align: right; font-variant-numeric: tabular-nums; }
</style>