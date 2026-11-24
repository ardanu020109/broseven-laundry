import http from '@/services/http';

function clean(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

export async function fetchReport(params) {
    const { data } = await http.get('/api/admin/reports', { params: clean(params) });
    return data.data;
}

/** Mengembalikan { data, meta, links } (format pagination Laravel). */
export async function fetchReportTransactions(params) {
    const { data } = await http.get('/api/admin/reports/transactions', { params: clean(params) });
    return data;
}

export async function fetchReportPrint(params) {
    const { data } = await http.get('/api/admin/reports/print', { params: clean(params) });
    return data.data;
}

/** Mengunduh CSV lewat Axios (membawa sesi login), lalu memicu penyimpanan file. */
export async function downloadReportCsv(params) {
    try {
        const response = await http.get('/api/admin/reports/export', {
            params: clean(params),
            responseType: 'blob',
        });

        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : 'laporan-pemasukan.csv';

        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);

        return filename;
    } catch (error) {
        // Respons error berbentuk Blob: ubah kembali menjadi JSON agar pesannya terbaca.
        if (error.response?.data instanceof Blob) {
            try {
                error.response.data = JSON.parse(await error.response.data.text());
            } catch {
                error.response.data = {};
            }
        }
        throw error;
    }
}