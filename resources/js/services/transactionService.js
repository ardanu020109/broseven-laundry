import http from '@/services/http';

function clean(params = {}) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

/** Mengembalikan { data, meta, links } (format pagination Laravel). */
export async function fetchTransactions(params) {
    const { data } = await http.get('/api/transactions', { params: clean(params) });
    return data;
}

export async function fetchTransaction(id) {
    const { data } = await http.get(`/api/transactions/${id}`);
    return data.data;
}

export async function createTransaction(payload) {
    const { data } = await http.post('/api/transactions', payload);
    return data;
}

export async function updateTransaction(id, payload) {
    const { data } = await http.put(`/api/transactions/${id}`, payload);
    return data;
}

export async function deleteTransaction(id) {
    const { data } = await http.delete(`/api/transactions/${id}`);
    return data;
}

export async function changeTransactionStatus(id, status, note = null) {
    const { data } = await http.post(`/api/transactions/${id}/status`, { status, note });
    return data;
}

/** Paket aktif untuk form transaksi. */
export async function fetchActivePackages() {
    const { data } = await http.get('/api/packages');
    return data.data;
}