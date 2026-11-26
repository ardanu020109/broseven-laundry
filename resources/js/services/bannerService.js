import http from '@/services/http';

/** Ukuran, kualitas, dan batas rendering dari backend. */
export async function fetchBannerPresets() {
    const { data } = await http.get('/api/admin/website/banner/presets');
    return data.data;
}

/** Mencatat ekspor banner di log aktivitas. */
export async function logBannerExport(payload) {
    const { data } = await http.post('/api/admin/website/banner/exports', payload);
    return data;
}