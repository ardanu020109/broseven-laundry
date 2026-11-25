<script setup>
import { computed } from 'vue';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicNavbar from '@/components/public/PublicNavbar.vue';
import { usePublicSiteStore } from '@/stores/publicSite';
import { useSiteStore } from '@/stores/site';

const content = usePublicSiteStore();
const site = useSiteStore();

// Sebelum data tiba, navbar memakai identitas dari server (tanpa kedip).
const name = computed(() => content.data?.business?.name || site.name);
const logoUrl = computed(() => (content.data ? content.data.business.logo_url : site.logoUrl));

function focusMain() {
    document.getElementById('konten')?.focus();
}
</script>

<template>
    <div class="public">
        <a href="#konten" class="skip" @click.prevent="focusMain">Lewati ke konten utama</a>

        <PublicNavbar :name="name" :logo-url="logoUrl" :links="content.navLinks" />

        <main id="konten" class="public__main" tabindex="-1">
            <slot />
        </main>

        <PublicFooter
            v-if="content.data"
            :business="content.data.business"
            :contact="content.data.contact"
            :links="content.navLinks"
        />
    </div>
</template>

<style scoped>
/* Jangan beri overflow pada .public: navbar sticky tidak akan berfungsi. */
.public { display: flex; flex-direction: column; min-height: 100vh; background: var(--color-background); }
.public__main { flex: 1; outline: none; }

.skip {
    position: absolute;
    left: -9999px;
    top: var(--space-3);
}
.skip:focus {
    left: var(--space-4);
    z-index: var(--z-toast);
    padding: 0.7rem 1.1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-weight: 600;
}
</style>