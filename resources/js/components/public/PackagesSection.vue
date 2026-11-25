<script setup>
import { Check, Clock, MessageCircle } from 'lucide-vue-next';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { formatCurrency } from '@/composables/useFormat';
import { vReveal } from '@/directives/reveal';

const props = defineProps({
    packages: { type: Array, default: () => [] },
    contact: { type: Object, default: () => ({}) },
    businessName: { type: String, default: '' },
});

/** Tautan pesan WhatsApp berisi nama paket. Hanya ada jika admin sudah mengisi nomor WhatsApp. */
function orderLink(pkg) {
    const base = props.contact?.whatsapp_url;
    if (!base) return null;

    const text = `Halo ${props.businessName}, saya ingin memesan layanan ${pkg.name}.`;

    return `${base}?text=${encodeURIComponent(text)}`;
}
</script>

<template>
    <section id="paket" class="section">
        <div class="container">
            <div v-reveal>
                <SectionHeading
                    eyebrow="Paket Laundry"
                    title="Pilih paket yang sesuai kebutuhan Anda"
                    description="Harga transparan, estimasi jelas, dan setiap prosesnya bisa dipantau."
                />
            </div>

            <div class="packages">
                <div v-for="(pkg, index) in packages" :key="pkg.id" v-reveal="index * 70" class="packages__cell">
                    <article class="pkg">
                        <header>
                            <h3 class="pkg__name">{{ pkg.name }}</h3>
                            <p v-if="pkg.description" class="pkg__desc">{{ pkg.description }}</p>
                        </header>

                        <p class="pkg__price">
                            <strong>{{ formatCurrency(pkg.price) }}</strong>
                            <span>{{ pkg.price_label }}</span>
                        </p>

                        <p class="pkg__estimate">
                            <Clock :size="16" aria-hidden="true" />
                            Estimasi pengerjaan {{ pkg.estimated_label }}
                        </p>

                        <ul v-if="pkg.features.length" class="pkg__features">
                            <li v-for="(feature, featureIndex) in pkg.features" :key="featureIndex">
                                <Check :size="16" aria-hidden="true" />
                                <span>{{ feature }}</span>
                            </li>
                        </ul>

                        <a
                            v-if="orderLink(pkg)"
                            class="pkg__order"
                            :href="orderLink(pkg)"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <MessageCircle :size="16" aria-hidden="true" />
                            Pesan via WhatsApp
                        </a>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.packages { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-5); }
.packages__cell { display: flex; }

.pkg {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--space-4);
    padding: var(--space-6);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
}
.pkg:hover { transform: translateY(-6px); border-color: var(--color-primary); box-shadow: var(--shadow-lg); }

.pkg__name { margin: 0; font-size: var(--text-xl); }
.pkg__desc { margin: var(--space-2) 0 0; color: var(--color-muted); font-size: var(--text-sm); }

.pkg__price { display: flex; align-items: baseline; flex-wrap: wrap; gap: var(--space-2); margin: 0; }
.pkg__price strong { font-size: var(--text-3xl); line-height: 1.1; letter-spacing: -0.02em; }
.pkg__price span { color: var(--color-muted); font-weight: 500; }

.pkg__estimate {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    align-self: flex-start;
    margin: 0;
    padding: 0.35rem 0.85rem;
    background: var(--color-primary-soft);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 600;
}

.pkg__features { display: grid; gap: var(--space-3); flex: 1; align-content: start; }
.pkg__features li { display: flex; align-items: flex-start; gap: var(--space-3); font-size: var(--text-sm); }
.pkg__features svg { flex: none; margin-top: 2px; color: var(--color-success); }

.pkg__order {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: 0.8rem 1.2rem;
    background: var(--gradient-brand);
    border-radius: var(--radius-full);
    box-shadow: var(--shadow-sm);
    font-weight: 600;
    transition: transform var(--transition-fast), box-shadow var(--transition-base);
}
.pkg__order:hover { transform: translateY(-2px); box-shadow: var(--shadow-glow); }
.pkg__order:active { transform: translateY(0) scale(0.98); }
</style>