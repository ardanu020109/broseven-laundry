<script setup>
defineProps({
    /** [{ label, to? }] */
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length" class="crumbs" aria-label="Breadcrumb">
        <ol>
            <li v-for="(item, index) in items" :key="index" :class="{ 'crumbs__last': index === items.length - 1 }">
                <RouterLink v-if="item.to && index < items.length - 1" :to="item.to">{{ item.label }}</RouterLink>
                <span v-else :aria-current="index === items.length - 1 ? 'page' : undefined">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.crumbs { min-width: 0; }
.crumbs ol { display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-1); font-size: var(--text-sm); }

.crumbs li { display: inline-flex; align-items: center; gap: var(--space-1); color: var(--color-muted); }
.crumbs li + li::before { content: '/'; opacity: 0.5; margin-right: var(--space-1); }

.crumbs a { transition: color var(--transition-fast); }
.crumbs a:hover { color: var(--color-text); text-decoration: underline; }

.crumbs__last { color: var(--color-text); font-weight: 600; }

@media (max-width: 640px) {
    .crumbs li:not(.crumbs__last) { display: none; }
    .crumbs li + li::before { display: none; }
}
</style>