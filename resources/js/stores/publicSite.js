import { defineStore } from 'pinia';
import http from '@/services/http';
import { useSiteStore } from '@/stores/site';

const STALE_MS = 5 * 60 * 1000;

function hasAboutContent(data) {
    return !!(data && (data.business.description || data.about.vision || data.about.missions?.length));
}

/**
 * Konten website publik dari GET /api/public/site.
 * Data dipakai ulang selama 5 menit; kegagalan menyegarkan tidak menghapus data yang sudah tampil.
 */
export const usePublicSiteStore = defineStore('publicSite', {
    state: () => ({
        data: null,
        loading: false,
        error: '',
        loadedAt: 0,
    }),

    getters: {
        hasAbout: (state) => hasAboutContent(state.data),

        /** Menu navbar: hanya bagian yang memiliki isi. */
        navLinks: (state) => {
            const data = state.data;
            if (!data) return [];

            const links = [{ id: 'beranda', label: 'Beranda' }];

            if (hasAboutContent(data)) links.push({ id: 'tentang', label: 'Tentang Kami' });
            if (data.packages.length) links.push({ id: 'paket', label: 'Paket Laundry' });
            if (data.procedures.length) links.push({ id: 'prosedur', label: 'Prosedur' });
            if (data.faqs.length) links.push({ id: 'faq', label: 'FAQ' });

            return links;
        },
    },

    actions: {
        async load({ force = false } = {}) {
            if (this.loading) return;

            const fresh = this.data && Date.now() - this.loadedAt < STALE_MS;
            if (fresh && !force) return;

            this.loading = true;
            this.error = '';

            try {
                const { data } = await http.get('/api/public/site');

                this.data = data.data;
                this.loadedAt = Date.now();
                this.sync();
            } catch (error) {
                // Data lama tetap ditampilkan jika penyegaran gagal.
                if (!this.data) {
                    this.error = error.response?.status === 429
                        ? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'
                        : 'Konten website tidak dapat dimuat. Periksa koneksi internet Anda lalu coba lagi.';
                }
            } finally {
                this.loading = false;
            }
        },

        /** Menyelaraskan warna, nama, logo, dan favicon dengan data terbaru. */
        sync() {
            const site = useSiteStore();

            if (this.data.colors) site.applyColors(this.data.colors);

            site.applyBranding({
                name: this.data.business.name,
                logo_url: this.data.business.logo_url,
                favicon_url: this.data.business.favicon_url,
            });
        },
    },
});