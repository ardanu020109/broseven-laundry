<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BannerExporter from '@/components/website/BannerExporter.vue';
import ColorSettings from '@/components/website/ColorSettings.vue';
import FaqManager from '@/components/website/FaqManager.vue';
import IdentityForm from '@/components/website/IdentityForm.vue';
import PackageManager from '@/components/website/PackageManager.vue';
import ProcedureManager from '@/components/website/ProcedureManager.vue';
import { extractErrorMessage } from '@/services/http';
import { fetchWebsite } from '@/services/websiteService';

const TABS = [
    { key: 'identity', label: 'Identitas dan Konten' },
    { key: 'packages', label: 'Paket Laundry' },
    { key: 'procedures', label: 'Prosedur' },
    { key: 'faqs', label: 'FAQ' },
    { key: 'appearance', label: 'Warna Website' },
    { key: 'banner', label: 'Banner Promosi' },
];

const route = useRoute();
const router = useRouter();

const settings = ref(null);
const colors = ref([]);
const loading = ref(true);
const error = ref('');

const tab = computed(() => {
    const requested = String(route.query.tab ?? '');
    return TABS.some((item) => item.key === requested) ? requested : 'identity';
});

function selectTab(key) {
    router.replace({ query: { ...route.query, tab: key } });
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const data = await fetchWebsite();
        settings.value = data.settings;
        colors.value = data.colors;
    } catch (err) {
        error.value = extractErrorMessage(err, 'Pengaturan website tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <main class="ws">
        <div class="container ws__inner">
            <header class="ws__header">
                <BaseButton variant="ghost" size="sm" @click="router.push('/dashboard')">
                    <ArrowLeft :size="16" />
                    Dashboard
                </BaseButton>
                <h1>Pengaturan Website</h1>
                <p class="text-muted">Kelola isi, paket, prosedur, FAQ, dan tampilan website publik.</p>
            </header>

            <div class="ws__tabs" role="tablist" aria-label="Bagian pengaturan">
                <button
                    v-for="item in TABS"
                    :key="item.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.key"
                    :class="['ws__tab', { 'ws__tab--active': tab === item.key }]"
                    @click="selectTab(item.key)"
                >
                    {{ item.label }}
                </button>
            </div>

            <BaseLoading v-if="loading" label="Memuat pengaturan..." />

            <div v-else-if="error" class="ws__alert" role="alert">
                {{ error }}
                <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
            </div>

            <template v-else>
                <IdentityForm v-if="tab === 'identity'" :settings="settings" @updated="(value) => (settings = value)" />
                <PackageManager v-else-if="tab === 'packages'" />
                <ProcedureManager v-else-if="tab === 'procedures'" />
                <FaqManager v-else-if="tab === 'faqs'" />
                <BannerExporter v-else-if="tab === 'banner'" />
                <ColorSettings v-else :items="colors" @updated="(value) => (colors = value)" />
            </template>
        </div>
    </main>
</template>

<style scoped>
.ws { min-height: 100vh; background: var(--gradient-hero); padding-block: var(--space-6) var(--space-10); }
.ws__inner { display: grid; gap: var(--space-5); max-width: 1080px; }
.ws__header h1 { margin: var(--space-3) 0 var(--space-2); font-size: var(--text-2xl); }
.ws__header p { margin: 0; }

.ws__tabs { display: flex; gap: var(--space-2); overflow-x: auto; padding-bottom: var(--space-1); }
.ws__tab {
    flex: none;
    padding: 0.6rem 1.15rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 600;
    white-space: nowrap;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.ws__tab:hover { border-color: var(--color-primary); }
.ws__tab--active { background: var(--gradient-brand); border-color: transparent; box-shadow: var(--shadow-md); }

.ws__alert {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);
    padding: 0.85rem 1rem; background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md); color: var(--color-danger); font-weight: 500;
}
</style>