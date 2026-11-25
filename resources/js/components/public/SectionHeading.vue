<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    align: {
        type: String,
        default: 'center',
        validator: (value) => ['center', 'left'].includes(value),
    },
});
</script>

<template>
    <header :class="['heading', `heading--${align}`]">
        <span v-if="eyebrow" class="heading__eyebrow">{{ eyebrow }}</span>
        <h2 class="heading__title">{{ title }}</h2>
        <p v-if="description" class="heading__description">{{ description }}</p>
    </header>
</template>

<style scoped>
.heading { display: grid; gap: var(--space-3); max-width: 680px; margin-bottom: var(--space-8); }
.heading--center { margin-inline: auto; text-align: center; }

.heading__eyebrow {
    justify-self: inherit;
    color: var(--color-primary-strong);
    font-size: var(--text-sm);
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.heading--center .heading__eyebrow { justify-self: center; }

.heading__title { margin: 0; font-size: var(--text-3xl); }
.heading__description { margin: 0; color: var(--color-muted); font-size: var(--text-lg); }
</style>