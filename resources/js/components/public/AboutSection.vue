<script setup>
import { Check, Sparkles, Target } from 'lucide-vue-next';
import BaseCard from '@/components/common/BaseCard.vue';
import BrandLogo from '@/components/public/BrandLogo.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { vReveal } from '@/directives/reveal';

defineProps({
    business: { type: Object, required: true },
    about: { type: Object, required: true },
});
</script>

<template>
    <section id="tentang" class="section section--surface">
        <div class="container">
            <div v-reveal>
                <SectionHeading eyebrow="Tentang Kami" :title="`Mengenal ${business.name}`" />
            </div>

            <div class="about">
                <div v-reveal class="about__intro">
                    <BrandLogo :url="business.logo_url" :name="business.name" :size="76" />
                    <h3>{{ business.name }}</h3>
                    <p v-if="business.description" class="about__text">{{ business.description }}</p>
                </div>

                <div class="about__cards">
                    <div v-if="about.vision" v-reveal="80">
                        <BaseCard class="about__card">
                            <span class="about__icon"><Target :size="22" aria-hidden="true" /></span>
                            <h3>Visi</h3>
                            <p>{{ about.vision }}</p>
                        </BaseCard>
                    </div>

                    <div v-if="about.missions?.length" v-reveal="160">
                        <BaseCard class="about__card">
                            <span class="about__icon"><Sparkles :size="22" aria-hidden="true" /></span>
                            <h3>Misi</h3>
                            <ul class="about__missions">
                                <li v-for="(mission, index) in about.missions" :key="index">
                                    <Check :size="18" aria-hidden="true" />
                                    <span>{{ mission }}</span>
                                </li>
                            </ul>
                        </BaseCard>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.about { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); gap: var(--space-8); align-items: start; }

.about__intro { display: grid; justify-items: start; gap: var(--space-4); }
.about__intro h3 { margin: 0; font-size: var(--text-2xl); }
.about__text { margin: 0; color: var(--color-muted); font-size: var(--text-lg); white-space: pre-line; }

.about__cards { display: grid; gap: var(--space-5); }
.about__card h3 { margin: var(--space-3) 0 var(--space-2); font-size: var(--text-xl); }
.about__card p { margin: 0; color: var(--color-muted); white-space: pre-line; }

.about__icon {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    background: var(--gradient-brand);
    border-radius: var(--radius-md);
}

.about__missions { display: grid; gap: var(--space-3); }
.about__missions li { display: flex; align-items: flex-start; gap: var(--space-3); color: var(--color-muted); }
.about__missions svg { flex: none; margin-top: 3px; color: var(--color-success); }

@media (max-width: 860px) {
    .about { grid-template-columns: 1fr; gap: var(--space-6); }
}
</style>