<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowRight, Link2, MessageCircle } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import { extractTrackingToken } from '@/composables/useTrackingToken';
import { vReveal } from '@/directives/reveal';

defineProps({
    whatsappUrl: { type: String, default: null },
});

const router = useRouter();

const input = ref('');
const error = ref('');

const MESSAGES = {
    empty: 'Tempel link tracking dari WhatsApp terlebih dahulu.',
    code: 'Demi menjaga privasi, status cucian hanya bisa dibuka lewat link tracking yang dikirim ke WhatsApp Anda, bukan dengan kode transaksi.',
    invalid: 'Link tidak valid. Salin seluruh link tracking yang dikirim lewat WhatsApp.',
};

function submit() {
    const { token, reason } = extractTrackingToken(input.value);

    if (!token) {
        error.value = MESSAGES[reason] ?? MESSAGES.invalid;
        return;
    }

    error.value = '';
    router.push({ name: 'tracking', params: { token } });
}
</script>

<template>
    <section id="cek-status" class="status">
        <div class="container">
            <div v-reveal class="status__card">
                <div class="status__text">
                    <h2>Cek Status Cucian</h2>
                    <p>
                        Setelah cucian diterima, Anda mendapat link tracking lewat WhatsApp. Tempel link tersebut di
                        sini untuk melihat status dan QR Code pengambilan.
                    </p>
                </div>

                <div class="status__action">
                    <form class="status__form" novalidate @submit.prevent="submit">
                        <BaseInput
                            v-model="input"
                            type="text"
                            placeholder="Tempel link tracking dari WhatsApp"
                            aria-label="Link tracking cucian"
                            autocomplete="off"
                            spellcheck="false"
                            :error="error"
                        >
                            <template #icon><Link2 :size="18" /></template>
                        </BaseInput>

                        <BaseButton type="submit" size="lg">
                            Lihat Status
                            <ArrowRight :size="18" />
                        </BaseButton>
                    </form>

                    <p v-if="whatsappUrl" class="status__help">
                        Link hilang?
                        <a :href="whatsappUrl" target="_blank" rel="noopener noreferrer">
                            <MessageCircle :size="14" aria-hidden="true" />
                            Hubungi kami lewat WhatsApp
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.status {
    position: relative;
    z-index: 2;
    margin-top: calc(-1 * var(--space-10));
    padding-bottom: var(--space-4);
    scroll-margin-top: calc(var(--topbar-height) + 8px);
}

.status__card {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: var(--space-6);
    align-items: center;
    padding: var(--space-6);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
}

.status__text h2 { margin: 0 0 var(--space-2); font-size: var(--text-2xl); }
.status__text p { margin: 0; color: var(--color-muted); }

.status__form { display: flex; align-items: flex-start; gap: var(--space-3); }
.status__form > :first-child { flex: 1; }

.status__help { margin: var(--space-3) 0 0; font-size: var(--text-sm); color: var(--color-muted); }
.status__help a {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--color-primary-strong);
    font-weight: 600;
    text-decoration: underline;
}

@media (max-width: 860px) {
    .status__card { grid-template-columns: 1fr; }
}

@media (max-width: 560px) {
    .status__form { flex-direction: column; }
    .status__form > * { width: 100%; }
}
</style>