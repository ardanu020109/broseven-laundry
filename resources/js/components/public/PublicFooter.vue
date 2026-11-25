<script setup>
import { computed } from 'vue';
import { Clock, MapPin, MessageCircle } from 'lucide-vue-next';
import BrandLogo from '@/components/public/BrandLogo.vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
import { scrollToSection } from '@/composables/useSectionScroll';

const props = defineProps({
    business: { type: Object, required: true },
    contact: { type: Object, required: true },
    links: { type: Array, default: () => [] },
});

const SOCIAL_LABELS = { instagram: 'Instagram', facebook: 'Facebook', tiktok: 'TikTok', youtube: 'YouTube' };

const socials = computed(() =>
    Object.entries(props.contact.social_links ?? {})
        .filter(([key, url]) => SOCIAL_LABELS[key] && url)
        .map(([key, url]) => ({ key, url, label: SOCIAL_LABELS[key] })),
);

const hasContact = computed(
    () => !!(props.contact.address || props.contact.whatsapp || props.contact.operating_hours),
);

const year = new Date().getFullYear();
</script>

<template>
    <footer class="footer">
        <div class="container footer__grid">
            <div class="footer__brand">
                <div class="footer__logo">
                    <BrandLogo :url="business.logo_url" :name="business.name" :size="44" />
                    <strong>{{ business.name }}</strong>
                </div>
                <p v-if="business.tagline || business.description" class="footer__about">
                    {{ business.tagline || business.description }}
                </p>

                <ul v-if="socials.length" class="footer__social" aria-label="Media sosial">
                    <li v-for="social in socials" :key="social.key">
                        <a :href="social.url" target="_blank" rel="noopener noreferrer" :aria-label="social.label" :title="social.label">
                            <SocialIcon :name="social.key" :size="18" />
                        </a>
                    </li>
                </ul>
            </div>

            <nav class="footer__col" aria-label="Navigasi footer">
                <h3>Menu</h3>
                <ul>
                    <li v-for="link in links" :key="link.id">
                        <a :href="`#${link.id}`" @click.prevent="scrollToSection(link.id)">{{ link.label }}</a>
                    </li>
                    <li><a href="#cek-status" @click.prevent="scrollToSection('cek-status')">Cek Status Cucian</a></li>
                </ul>
            </nav>

            <div v-if="hasContact" class="footer__col">
                <h3>Kontak</h3>
                <ul class="footer__contact">
                    <li v-if="contact.address">
                        <MapPin :size="18" aria-hidden="true" />
                        <span class="footer__address">{{ contact.address }}</span>
                    </li>
                    <li v-if="contact.whatsapp">
                        <MessageCircle :size="18" aria-hidden="true" />
                        <a :href="contact.whatsapp_url" target="_blank" rel="noopener noreferrer">+{{ contact.whatsapp }}</a>
                    </li>
                    <li v-if="contact.operating_hours">
                        <Clock :size="18" aria-hidden="true" />
                        <span>{{ contact.operating_hours }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer__bar">
            <div class="container footer__bar-inner">
                <small>&copy; {{ year }} {{ business.name }}. Semua hak dilindungi.</small>
                <RouterLink to="/login" class="footer__staff">Masuk Staff</RouterLink>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.footer { background: var(--color-surface); border-top: 1px solid var(--color-border); }

.footer__grid {
    display: grid;
    grid-template-columns: 1.4fr 0.8fr 1.2fr;
    gap: var(--space-8);
    padding-block: var(--space-10) var(--space-8);
}

.footer__logo { display: flex; align-items: center; gap: var(--space-3); font-size: var(--text-lg); }

.footer__about {
    display: -webkit-box;
    max-width: 38ch;
    margin: var(--space-4) 0 0;
    overflow: hidden;
    color: var(--color-muted);
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
}

.footer__social { display: flex; gap: var(--space-2); margin-top: var(--space-4); }
.footer__social a {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--color-border);
    border-radius: 50%;
    color: var(--color-text);
    transition: background var(--transition-fast), transform var(--transition-fast), border-color var(--transition-fast);
}
.footer__social a:hover { background: var(--color-primary-soft); border-color: var(--color-primary); transform: translateY(-2px); }

.footer__col h3 { margin: 0 0 var(--space-4); font-size: var(--text-base); }
.footer__col ul { display: grid; gap: var(--space-3); }
.footer__col a { color: var(--color-muted); transition: color var(--transition-fast); }
.footer__col a:hover { color: var(--color-text); }

.footer__contact li { display: flex; align-items: flex-start; gap: var(--space-3); color: var(--color-muted); }
.footer__contact svg { flex: none; margin-top: 3px; color: var(--color-primary-strong); }
.footer__address { white-space: pre-line; }

.footer__bar { border-top: 1px solid var(--color-border); }
.footer__bar-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    padding-block: var(--space-4);
    color: var(--color-muted);
}
.footer__staff { font-size: var(--text-sm); font-weight: 600; transition: color var(--transition-fast); }
.footer__staff:hover { color: var(--color-text); }

@media (max-width: 860px) {
    .footer__grid { grid-template-columns: 1fr 1fr; }
    .footer__brand { grid-column: 1 / -1; }
}

@media (max-width: 560px) {
    .footer__grid { grid-template-columns: 1fr; }
}
</style>