import http from '@/services/http';

/** Pengaturan dan warna (halaman admin). */
export async function fetchWebsite() {
    const { data } = await http.get('/api/admin/website');
    return data.data;
}

export async function saveSettings(payload) {
    const { data } = await http.put('/api/admin/website/settings', payload);
    return data;
}

export async function uploadImage(kind, file) {
    const form = new FormData();
    form.append('image', file);

    const { data } = await http.post(`/api/admin/website/images/${kind}`, form);
    return data;
}

export async function deleteImage(kind) {
    const { data } = await http.delete(`/api/admin/website/images/${kind}`);
    return data;
}

export async function saveColors(colors) {
    const { data } = await http.put('/api/admin/website/colors', { colors });
    return data;
}

export async function resetColors(keys = null) {
    const { data } = await http.post('/api/admin/website/colors/reset', keys ? { keys } : {});
    return data;
}

function crud(resource) {
    const base = `/api/admin/${resource}`;

    return {
        /** Mengembalikan seluruh body: { data: [...], ...tambahan }. */
        async list(params = {}) {
            const { data } = await http.get(base, { params });
            return data;
        },
        async create(payload) {
            const { data } = await http.post(base, payload);
            return data;
        },
        async update(id, payload) {
            const { data } = await http.put(`${base}/${id}`, payload);
            return data;
        },
        async remove(id) {
            const { data } = await http.delete(`${base}/${id}`);
            return data;
        },
        async reorder(ids) {
            const { data } = await http.post(`${base}/reorder`, { ids });
            return data;
        },
    };
}

export const packagesApi = {
    ...crud('packages'),
    async restore(id) {
        const { data } = await http.post(`/api/admin/packages/${id}/restore`);
        return data;
    },
};

export const proceduresApi = crud('procedures');
export const faqsApi = crud('faqs');