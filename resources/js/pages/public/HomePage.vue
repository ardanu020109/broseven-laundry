<script setup>
import { ref } from 'vue';
import { Droplets, Sparkles } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import http from '@/services/http';

const counter = ref(0);
const axiosStatus = ref('Belum dites');
const axiosLoading = ref(false);

const palette = [
    { name: 'Light Blue', hex: '#ADD8E6', variable: '--color-primary' },
    { name: 'Perano', hex: '#ADBCE6', variable: '--color-secondary' },
    { name: 'Ice Cold', hex: '#ADE6D8', variable: '--color-accent' },
];

async function testAxios() {
    axiosLoading.value = true;
    try {
        const response = await http.get('/up');
        axiosStatus.value = `Axios OK (status ${response.status})`;
    } catch (error) {
        axiosStatus.value = `Axios gagal: ${error.message}`;
    } finally {
        axiosLoading.value = false;
    }
}
</script>

<template>
    <main class="setup">
        <div class="container setup__inner">
            <div class="setup__badge anim-fade-up">
                <Droplets :size="18" />
                Fase 2 berhasil
            </div>

            <h1 class="anim-fade-up">
                <span class="text-gradient">BroSeven!</span> Laundry
            </h1>
            <p class="text-muted anim-fade-up">
                Vue 3, Vite, Vue Router, Pinia, Axios, dan CSS manual sudah berjalan.
            </p>

            <div class="setup__palette">
                <BaseCard v-for="color in palette" :key="color.hex" hoverable>
                    <div class="swatch" :style="{ background: `var(${color.variable})` }"></div>
                    <strong>{{ color.name }}</strong>
                    <div class="text-muted">{{ color.hex }}</div>
                </BaseCard>
            </div>

            <div class="row setup__actions">
                <BaseButton @click="counter++">
                    <Sparkles :size="18" />
                    Klik: {{ counter }}
                </BaseButton>
                <BaseButton variant="secondary" :loading="axiosLoading" @click="testAxios">
                    Tes Axios
                </BaseButton>
                <BaseButton variant="ghost" @click="$router.push('/halaman-tidak-ada')">
                    Tes halaman 404
                </BaseButton>
            </div>

            <p class="setup__status">{{ axiosStatus }}</p>
        </div>
    </main>
</template>

<style scoped>
.setup {
    min-height: 100vh;
    display: grid;
    place-items: center;
    background: var(--gradient-hero);
    padding-block: var(--space-10);
}

.setup__inner { text-align: center; }

.setup__badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: 0.45rem 1rem;
    margin-bottom: var(--space-4);
    background: var(--color-accent-soft);
    border: 1px solid var(--color-accent);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 600;
}

.setup__palette {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: var(--space-4);
    max-width: 640px;
    margin: var(--space-6) auto;
}

.swatch {
    height: 72px;
    border-radius: var(--radius-md);
    margin-bottom: var(--space-3);
}

.setup__actions { justify-content: center; }

.setup__status {
    margin-top: var(--space-5);
    font-weight: 600;
    color: var(--color-muted);
}
</style>