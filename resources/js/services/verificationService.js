import http from '@/services/http';

/**
 * Memeriksa hasil scan QR atau kode transaksi.
 * Untuk 404 (tidak ditemukan), server tetap mengirim hasil terstruktur (reason: NOT_FOUND).
 */
export async function lookupVerification(code) {
    try {
        const { data } = await http.post('/api/verifications/lookup', { code });
        return data.data;
    } catch (error) {
        if (error.response?.status === 404 && error.response.data?.data) {
            return error.response.data.data;
        }
        throw error;
    }
}

export async function completeVerification(code) {
    const { data } = await http.post('/api/verifications/complete', { code });
    return { message: data.message, result: data.data };
}