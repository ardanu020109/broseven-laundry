<script setup>
import { nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { CloudOff } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import AboutSection from '@/components/public/AboutSection.vue';
import FaqSection from '@/components/public/FaqSection.vue';
import HeroSection from '@/components/public/HeroSection.vue';
import PackagesSection from '@/components/public/PackagesSection.vue';
import ProceduresSection from '@/components/public/ProceduresSection.vue';
import StatusCheckSection from '@/components/public/StatusCheckSection.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { usePublicSiteStore } from '@/stores/publicSite';

const store = usePublicSiteStore();
const route = useRoute();

/** Menggulir ke bagian sesuai hash URL (mis. /#paket) setelah konten tersedia. */
function scrollToHash(hash) {
    if (!hash) return;

    let id = hash.slice(1);

    try {
        id = decodeURIComponent(id);
    } catch {
        return;
    }

    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

onMounted(() => {
    store.load();

    if (store.data) nextTick(() => scrollToHash(route.hash));
});

watch(
    () => store.data,
    async (data, previous) => {
        if (data && !previous) {
            await nextTick();
            scrollToHash(route.hash);
        }
    },
);
</script>

<template>
    <PublicLayout>
        <div v-if="store.loading && !store.data" class="state">
            <BaseLoading label="Memuat website..." />
        </div>

        <section v-else-if="store.error && !store.data" class="state container">
            <BaseEmptyState title="Website belum dapat dimuat" :description="store.error">
                <template #icon><CloudOff :size="32" /></template>
                <BaseButton @click="store.load({ force: true })">Coba lagi</BaseButton>
            </BaseEmptyState>
        </section>

        <template v-else-if="store.data">
            <HeroSection :business="store.data.business" :has-packages="store.data.packages.length > 0" />

            <StatusCheckSection :whatsapp-url="store.data.contact.whatsapp_url" />

            <AboutSection v-if="store.hasAbout" :business="store.data.business" :about="store.data.about" />

            <PackagesSection
                v-if="store.data.packages.length"
                :packages="store.data.packages"
                :contact="store.data.contact"
                :business-name="store.data.business.name"
            />

            <ProceduresSection v-if="store.data.procedures.length" :procedures="store.data.procedures" />

            <FaqSection v-if="store.data.faqs.length" :faqs="store.data.faqs" />
        </template>
    </PublicLayout>
</template>

<style scoped>
.state { display: grid; place-items: center; min-height: 60vh; }
</style>