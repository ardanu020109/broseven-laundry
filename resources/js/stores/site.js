import { defineStore } from 'pinia';

/**
 * Identitas dan warna website. Nilai awal datang dari server (window.__SITE__),
 * sehingga tidak ada kedip warna saat halaman dimuat.
 */
export const useSiteStore = defineStore('site', {
    state: () => {
        const boot = window.__SITE__ ?? {};

        return {
            name: boot.name || 'BroSeven! Laundry',
            logoUrl: boot.logo_url || null,
            faviconUrl: boot.favicon_url || null,
            colors: { ...(boot.colors ?? {}) },
        };
    },

    actions: {
        /** Menerapkan warna ke seluruh halaman tanpa memuat ulang. */
        applyColors(colors) {
            const root = document.documentElement;

            Object.entries(colors).forEach(([key, value]) => {
                root.style.setProperty(`--color-${key}`, value);
            });

            this.colors = { ...this.colors, ...colors };

            if (colors.primary) {
                document.querySelector('meta[name="theme-color"]')?.setAttribute('content', colors.primary);
            }
        },

        applyBranding({ name, logo_url: logoUrl, favicon_url: faviconUrl }) {
            if (name) this.name = name;
            this.logoUrl = logoUrl ?? null;
            this.faviconUrl = faviconUrl ?? null;

            let link = document.querySelector('link[rel="icon"]');

            if (faviconUrl) {
                if (!link) {
                    link = document.createElement('link');
                    link.rel = 'icon';
                    document.head.appendChild(link);
                }
                link.href = faviconUrl;
            } else {
                link?.remove();
            }
        },
    },
});