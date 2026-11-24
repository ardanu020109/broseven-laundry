import http from '@/services/http';

/** Pratinjau pesan dan link WhatsApp. Tidak ada efek samping di server. */
export async function fetchWhatsAppPreview(transactionId, template = null) {
    const { data } = await http.get(`/api/transactions/${transactionId}/whatsapp`, {
        params: template ? { template } : {},
    });
    return data.data;
}

/** Mencatat bahwa petugas membuka WhatsApp (bukan bahwa pesan terkirim). */
export async function recordWhatsAppOpened(transactionId, template = null) {
    const { data } = await http.post(
        `/api/transactions/${transactionId}/whatsapp`,
        template ? { template } : {},
    );
    return data.data;
}