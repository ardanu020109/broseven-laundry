import { createApp } from 'vue';
import { createPinia } from 'pinia';
import '@fontsource-variable/plus-jakarta-sans';

import '@/styles/main.css';
import App from '@/App.vue';
import router from '@/router';
import http, { setUnauthorizedHandler } from '@/services/http';
import { useAuthStore } from '@/stores/auth';

const app = createApp(App);

app.use(createPinia());
app.use(router);

const auth = useAuthStore();
let verifying = false;

// Server membalas 401 pada sebuah endpoint. Sebelum mengeluarkan pengguna, pastikan dulu
// bahwa sesi benar-benar berakhir (bukan 401 sesaat dari satu request).
setUnauthorizedHandler(async () => {
    if (!auth.isAuthenticated || verifying) return;

    verifying = true;

    try {
        await http.get('/api/auth/me');
    } catch (error) {
        if (error.response?.status === 401) {
            auth.clear();
            router.replace({
                name: 'login',
                query: { redirect: router.currentRoute.value.fullPath },
            });
        }
    } finally {
        verifying = false;
    }
});

app.mount('#app');