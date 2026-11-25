<script setup>
import { useRoute } from 'vue-router';

defineProps({
    items: { type: Array, required: true },
});

const emit = defineEmits(['navigate']);

const route = useRoute();

function isActive(item) {
    return item.match.some((base) => route.path === base || route.path.startsWith(`${base}/`));
}
</script>

<template>
    <nav class="menu" aria-label="Menu utama">
        <RouterLink
            v-for="item in items"
            :key="item.key"
            :to="item.to"
            :class="['menu__item', { 'menu__item--active': isActive(item) }]"
            :aria-current="isActive(item) ? 'page' : undefined"
            @click="emit('navigate')"
        >
            <component :is="item.icon" :size="20" aria-hidden="true" />
            <span>{{ item.label }}</span>
        </RouterLink>
    </nav>
</template>

<style scoped>
.menu { display: grid; gap: var(--space-1); }

.menu__item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    color: var(--color-muted);
    font-weight: 600;
    transition: background var(--transition-fast), color var(--transition-fast), transform var(--transition-fast);
}

.menu__item:hover { background: var(--color-primary-soft); color: var(--color-text); transform: translateX(2px); }

.menu__item--active {
    background: var(--gradient-brand);
    box-shadow: var(--shadow-sm);
    color: var(--color-text);
}
.menu__item--active:hover { transform: none; background: var(--gradient-brand); }
</style>