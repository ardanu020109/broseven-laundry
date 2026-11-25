<script setup>
import SectionHeading from '@/components/public/SectionHeading.vue';
import { procedureIcon } from '@/components/public/procedureIcons';
import { vReveal } from '@/directives/reveal';

defineProps({
    procedures: { type: Array, default: () => [] },
});
</script>

<template>
    <section id="prosedur" class="section section--surface">
        <div class="container">
            <div v-reveal>
                <SectionHeading
                    eyebrow="Prosedur"
                    title="Alur laundry yang mudah dan transparan"
                    description="Dari cucian diserahkan sampai diambil kembali, setiap tahap tercatat."
                />
            </div>

            <ol class="steps">
                <li v-for="(step, index) in procedures" :key="step.id" v-reveal="index * 70" class="step">
                    <div class="step__top">
                        <span class="step__number">{{ index + 1 }}</span>
                        <span class="step__icon">
                            <component :is="procedureIcon(step.icon)" :size="22" aria-hidden="true" />
                        </span>
                    </div>
                    <h3 class="step__title">{{ step.title }}</h3>
                    <p v-if="step.description" class="step__text">{{ step.description }}</p>
                </li>
            </ol>
        </div>
    </section>
</template>

<style scoped>
.steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: var(--space-5); }

.step {
    padding: var(--space-5);
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
}

.step__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); }

.step__number {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    background: var(--gradient-brand);
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
    font-weight: 700;
}

.step__icon {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    background: var(--color-surface);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    color: var(--color-primary-strong);
}

.step__title { margin: 0 0 var(--space-2); font-size: var(--text-lg); }
.step__text { margin: 0; color: var(--color-muted); font-size: var(--text-sm); white-space: pre-line; }
</style>