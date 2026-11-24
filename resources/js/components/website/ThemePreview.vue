<script setup>
import { computed } from 'vue';
import { Droplets } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';

const props = defineProps({
    colors: { type: Object, required: true },
    name: { type: String, default: 'BroSeven! Laundry' },
});

const vars = computed(() => ({
    '--color-primary': props.colors.primary,
    '--color-secondary': props.colors.secondary,
    '--color-accent': props.colors.accent,
    '--color-background': props.colors.background,
    '--color-text': props.colors.text,
}));
</script>

<template>
    <div class="theme-scope preview" :style="vars" role="img" aria-label="Pratinjau tampilan website">
        <header class="preview__nav">
            <span class="preview__logo"><Droplets :size="16" /></span>
            <strong class="preview__name">{{ name }}</strong>
            <nav class="preview__links" aria-hidden="true">
                <span>Beranda</span><span>Paket</span><span>FAQ</span>
            </nav>
            <BaseButton size="sm">Cek Status</BaseButton>
        </header>

        <section class="preview__hero">
            <BaseBadge tone="info">Laundry modern</BaseBadge>
            <h3>Cucian bersih, wangi, dan rapi</h3>
            <p>Pantau setiap tahap proses cucian langsung dari ponsel Anda.</p>
            <div class="preview__buttons">
                <BaseButton>Lihat Paket</BaseButton>
                <BaseButton variant="secondary">Cek Status Cucian</BaseButton>
            </div>
        </section>

        <section class="preview__cards">
            <BaseCard v-for="item in [['Reguler', 'Rp6.000 / kg'], ['Express', 'Rp12.000 / kg'], ['Bedcover', 'Rp30.000 / item']]" :key="item[0]">
                <strong>{{ item[0] }}</strong>
                <p class="preview__price">{{ item[1] }}</p>
                <BaseBadge tone="success">Aktif</BaseBadge>
            </BaseCard>
        </section>
    </div>
</template>

<style scoped>
.preview { border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; }

.preview__nav {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
}

.preview__logo {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    background: var(--gradient-brand);
    border-radius: var(--radius-sm);
}

.preview__name { font-size: var(--text-sm); }
.preview__links { display: flex; gap: var(--space-3); margin-left: auto; font-size: var(--text-sm); color: var(--color-muted); }

.preview__hero {
    display: grid;
    justify-items: start;
    gap: var(--space-2);
    padding: var(--space-6) var(--space-5);
    background: var(--gradient-hero);
}
.preview__hero h3 { margin: 0; font-size: var(--text-xl); }
.preview__hero p { margin: 0; color: var(--color-muted); }
.preview__buttons { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-2); }

.preview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--space-3);
    padding: var(--space-4) var(--space-5) var(--space-5);
}
.preview__price { margin: var(--space-1) 0 var(--space-3); color: var(--color-muted); font-size: var(--text-sm); }

@media (max-width: 560px) {
    .preview__links { display: none; }
    .preview__nav :deep(.btn) { margin-left: auto; }
}
</style>