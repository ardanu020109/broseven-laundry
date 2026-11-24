<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertCircle, Droplets, Lock, MessageCircle, QrCode, TrendingUp, User } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import { useAuthStore } from '@/stores/auth';
import { extractErrorMessage } from '@/services/http';

const appName = import.meta.env.VITE_APP_NAME || 'BroSeven! Laundry';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ login: '', password: '', remember: false });
const errors = reactive({ login: '', password: '' });
const formError = ref('');
const loading = ref(false);

const features = [
    { icon: QrCode, text: 'Verifikasi pengambilan dengan QR Code' },
    { icon: MessageCircle, text: 'Kirim status cucian lewat WhatsApp' },
    { icon: TrendingUp, text: 'Pantau transaksi dan pemasukan' },
];

function safeRedirect() {
    const target = route.query.redirect;
    return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')
        ? target
        : '/dashboard';
}

async function submit() {
    errors.login = '';
    errors.password = '';
    formError.value = '';
    loading.value = true;

    try {
        await auth.login({
            login: form.login.trim(),
            password: form.password,
            remember: form.remember,
        });
        router.replace(safeRedirect());
    } catch (error) {
        const fieldErrors = error.response?.data?.errors;

        if (error.response?.status === 422 && fieldErrors) {
            errors.login = fieldErrors.login?.[0] ?? '';
            errors.password = fieldErrors.password?.[0] ?? '';
        } else {
            formError.value = extractErrorMessage(error);
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="login">
        <section class="login__brand">
            <div class="login__logo">
                <span class="login__logo-icon"><Droplets :size="24" /></span>
                <strong>{{ appName }}</strong>
            </div>

            <div class="login__pitch">
                <h2>Kelola laundry lebih rapi, cepat, dan transparan.</h2>
                <ul>
                    <li v-for="item in features" :key="item.text">
                        <span class="login__feature-icon"><component :is="item.icon" :size="18" /></span>
                        {{ item.text }}
                    </li>
                </ul>
            </div>

            <small>&copy; {{ new Date().getFullYear() }} {{ appName }}</small>
        </section>

        <section class="login__panel">
            <form class="login__form anim-fade-up" novalidate @submit.prevent="submit">
                <div>
                    <h1>Selamat datang</h1>
                    <p class="text-muted">Masuk untuk mengelola transaksi laundry.</p>
                </div>

                <div v-if="formError" class="login__alert" role="alert">
                    <AlertCircle :size="18" />
                    {{ formError }}
                </div>

                <BaseInput
                    v-model="form.login"
                    label="Email atau username"
                    placeholder="admin@broseven.test"
                    autocomplete="username"
                    name="login"
                    :error="errors.login"
                >
                    <template #icon><User :size="18" /></template>
                </BaseInput>

                <BaseInput
                    v-model="form.password"
                    type="password"
                    label="Password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    name="password"
                    :error="errors.password"
                >
                    <template #icon><Lock :size="18" /></template>
                </BaseInput>

                <label class="login__remember">
                    <input v-model="form.remember" type="checkbox" />
                    Ingat saya di perangkat ini
                </label>

                <BaseButton type="submit" size="lg" block :loading="loading">Masuk</BaseButton>

                <RouterLink to="/" class="login__back">&larr; Kembali ke beranda</RouterLink>
            </form>
        </section>
    </main>
</template>

<style scoped>
.login {
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    min-height: 100vh;
}

.login__brand {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: var(--space-8);
    padding: var(--space-8);
    background: var(--gradient-hero);
    border-right: 1px solid var(--color-border);
}

.login__logo { display: flex; align-items: center; gap: var(--space-3); font-size: var(--text-lg); }

.login__logo-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    background: var(--gradient-brand);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-glow);
}

.login__pitch h2 { font-size: var(--text-3xl); max-width: 16ch; margin-bottom: var(--space-5); }

.login__pitch ul { display: grid; gap: var(--space-3); }

.login__pitch li { display: flex; align-items: center; gap: var(--space-3); font-weight: 500; }

.login__feature-icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    background: var(--color-surface);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
}

.login__brand small { color: var(--color-muted); }

.login__panel { display: grid; place-items: center; padding: var(--space-6) var(--space-5); }

.login__form { display: grid; gap: var(--space-4); width: 100%; max-width: 400px; }
.login__form h1 { font-size: var(--text-2xl); margin-bottom: var(--space-1); }
.login__form p { margin: 0; }

.login__alert {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: 0.8rem 1rem;
    background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md);
    color: var(--color-danger);
    font-size: var(--text-sm);
    font-weight: 500;
}

.login__remember {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-sm);
    color: var(--color-muted);
    cursor: pointer;
}
.login__remember input { width: 16px; height: 16px; accent-color: var(--color-primary-strong); }

.login__back { text-align: center; font-size: var(--text-sm); color: var(--color-muted); transition: color var(--transition-fast); }
.login__back:hover { color: var(--color-text); }

@media (max-width: 860px) {
    .login { grid-template-columns: 1fr; }
    .login__brand { display: none; }
    .login__panel { min-height: 100vh; background: var(--gradient-hero); }
    .login__form {
        padding: var(--space-6);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-lg);
    }
}
</style>