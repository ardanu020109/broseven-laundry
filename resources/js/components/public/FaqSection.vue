<script setup>
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { vReveal } from '@/directives/reveal';

const props = defineProps({
    faqs: { type: Array, default: () => [] },
});

// Satu pertanyaan terbuka dalam satu waktu; yang pertama terbuka di awal.
const openId = ref(props.faqs[0]?.id ?? null);

function toggle(id) {
    openId.value = openId.value === id ? null : id;
}
</script>

<template>
    <section id="faq" class="section">
        <div class="container faq">
            <div v-reveal>
                <SectionHeading eyebrow="FAQ" title="Pertanyaan yang sering diajukan" />
            </div>

            <div class="faq__list">
                <div v-for="(faq, index) in faqs" :key="faq.id" v-reveal="index * 60">
                    <div :class="['faq__item', { 'faq__item--open': openId === faq.id }]">
                        <h3 class="faq__question">
                            <button
                                :id="`faq-q-${faq.id}`"
                                type="button"
                                class="faq__button"
                                :aria-expanded="openId === faq.id"
                                :aria-controls="`faq-a-${faq.id}`"
                                @click="toggle(faq.id)"
                            >
                                <span>{{ faq.question }}</span>
                                <ChevronDown :size="20" class="faq__chevron" aria-hidden="true" />
                            </button>
                        </h3>

                        <div
                            :id="`faq-a-${faq.id}`"
                            class="faq__panel"
                            role="region"
                            :aria-labelledby="`faq-q-${faq.id}`"
                            :inert="openId === faq.id ? undefined : ''"
                        >
                            <div><p class="faq__answer">{{ faq.answer }}</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.faq { max-width: 820px; }
.faq__list { display: grid; gap: var(--space-3); }

.faq__item {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.faq__item:hover { border-color: var(--color-primary); }
.faq__item--open { border-color: var(--color-primary); box-shadow: var(--shadow-md); }

.faq__question { margin: 0; font-size: var(--text-base); }

.faq__button {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    width: 100%;
    padding: var(--space-4) var(--space-5);
    background: transparent;
    border: 0;
    border-radius: var(--radius-lg);
    font-weight: 600;
    text-align: left;
}

.faq__chevron { flex: none; color: var(--color-primary-strong); transition: transform var(--transition-base); }
.faq__item--open .faq__chevron { transform: rotate(180deg); }

.faq__panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows var(--transition-base); }
.faq__item--open .faq__panel { grid-template-rows: 1fr; }
.faq__panel > div { overflow: hidden; }

.faq__answer { margin: 0; padding: 0 var(--space-5) var(--space-5); color: var(--color-muted); white-space: pre-line; }
</style>