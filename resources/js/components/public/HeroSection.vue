<script setup>
import { computed } from 'vue';
import { ArrowRight, MessageCircle, QrCode, Search, Sparkles } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import HeroIllustration from '@/components/public/HeroIllustration.vue';
import { scrollToSection } from '@/composables/useSectionScroll';

const props = defineProps({
    business: { type: Object, required: true },
    hasPackages: Boolean,
});

const title = computed(() => props.business.hero_title || 'Laundry bersih dan wangi, tanpa repot.');

const subtitle = computed(
    () =>
        props.business.hero_subtitle ||
        props.business.description ||
        'Serahkan cucian Anda dan pantau setiap tahap prosesnya langsung dari ponsel.',
);

const points = [
    { icon: Search, text: 'Pantau status cucian secara langsung' },
    { icon: QrCode, text: 'QR Code sebagai bukti pengambilan' },
    { icon: MessageCircle, text: 'Informasi status lewat WhatsApp' },
];
</script>

<template>
    <section id="beranda" class="hero">
        <div class="container hero__inner">
            <div class="hero__copy">
                <BaseBadge tone="info" :dot="false" class="anim-fade-up">
                    <Sparkles :size="14" aria-hidden="true" />
                    {{ business.tagline || 'Laundry modern dan terpercaya' }}
                </BaseBadge>

                <h1 class="hero__title anim-fade-up" style="animation-delay: 0.08s">{{ title }}</h1>
                <p class="hero__lead anim-fade-up" style="animation-delay: 0.16s">{{ subtitle }}</p>

                <div class="hero__cta anim-fade-up" style="animation-delay: 0.24s">
                    <BaseButton v-if="hasPackages" size="lg" @click="scrollToSection('paket')">
                        Lihat Paket
                        <ArrowRight :size="18" />
                    </BaseButton>
                    <BaseButton variant="secondary" size="lg" @click="scrollToSection('cek-status')">
                        <Search :size="18" />
                        Cek Status Cucian
                    </BaseButton>
                </div>

                <ul class="hero__points anim-fade-up" style="animation-delay: 0.32s">
                    <li v-for="point in points" :key="point.text">
                        <span class="hero__point-icon"><component :is="point.icon" :size="16" aria-hidden="true" /></span>
                        {{ point.text }}
                    </li>
                </ul>
            </div>

            <div class="hero__art anim-fade-up" style="animation-delay: 0.2s">
                <HeroIllustration />
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero {
    position: relative;
    overflow: hidden;
    padding-block: var(--space-10) calc(var(--space-12) + var(--space-5));
    background: var(--gradient-hero);
}

.hero__inner {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
    gap: var(--space-8);
    align-items: center;
}

.hero__copy { display: grid; justify-items: start; gap: var(--space-5); }

.hero__title { margin: 0; font-size: var(--text-4xl); }
.hero__lead { max-width: 52ch; margin: 0; color: var(--color-muted); font-size: var(--text-lg); white-space: pre-line; }

.hero__cta { display: flex; flex-wrap: wrap; gap: var(--space-3); }

.hero__points { display: grid; gap: var(--space-3); margin-top: var(--space-2); }
.hero__points li { display: flex; align-items: center; gap: var(--space-3); font-size: var(--text-sm); font-weight: 500; }

.hero__point-icon {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    background: var(--color-surface);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
    color: var(--color-primary-strong);
}

@media (max-width: 900px) {
    .hero { padding-block: var(--space-8) calc(var(--space-12) + var(--space-3)); }
    .hero__inner { grid-template-columns: 1fr; gap: var(--space-6); }
    .hero__art { order: -1; max-width: 340px; margin-inline: auto; }
}
</style>