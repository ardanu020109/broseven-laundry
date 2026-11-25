import { defineStore } from 'pinia';
import http from '@/services/http';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        initialized: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        isAdmin: (state) => state.user?.role === 'admin',
        isEmployee: (state) => state.user?.role === 'employee',

        /**
         * Cek kemampuan, contoh: auth.can('view-reports').
         * Hanya untuk UX (menu/tombol). Keamanan sebenarnya ada di backend.
         */
        can: (state) => (ability) => !!state.user?.abilities?.[ability],
    },

    actions: {
        /** Memeriksa sesi login sekali saja per pemuatan halaman. */
        async init() {
            if (this.initialized) return;
            await this.fetchUser();
        },

        async fetchUser() {
            try {
                const { data } = await http.get('/api/auth/me');
                this.user = data.data;
            } catch {
                this.user = null;
            } finally {
                this.initialized = true;
            }
        },

        async login(credentials) {
            await http.get('/sanctum/csrf-cookie');
            const { data } = await http.post('/api/auth/login', credentials);
            this.user = data.data;
            this.initialized = true;
            return this.user;
        },

        async logout() {
            try {
                await http.post('/api/auth/logout');
            } finally {
                this.user = null;
            }
        },

        /** Memperbarui data pengguna di state (mis. setelah mengubah profil atau foto). */
        setUser(user) {
            this.user = user;
        },

        /** Membersihkan state lokal tanpa memanggil server (sesi sudah berakhir). */
        clear() {
            this.user = null;
            this.initialized = true;
        },
    },
});