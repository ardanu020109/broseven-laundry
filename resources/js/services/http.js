import axios from 'axios';

/**
 * Instance Axios tunggal untuk seluruh aplikasi.
 * Memakai cookie sesi + token CSRF Laravel (XSRF-TOKEN) pada domain yang sama.
 */
const http = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let unauthorizedHandler = null;

/** Didaftarkan di app.js. Dipanggil saat server membalas 401 pada endpoint biasa. */
export function setUnauthorizedHandler(handler) {
    unauthorizedHandler = handler;
}

// 401 pada endpoint ini adalah hal normal dan ditangani langsung oleh pemanggilnya.
const AUTH_PROBE_URLS = ['/api/auth/me', '/api/auth/login', '/api/auth/logout'];

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const url = error.config?.url ?? '';

        if (status === 401 && !AUTH_PROBE_URLS.some((path) => url.startsWith(path))) {
            unauthorizedHandler?.(error);
        }

        if (status === 419) {
            console.warn('Sesi kedaluwarsa. Silakan muat ulang halaman.');
        }

        return Promise.reject(error);
    },
);

/**
 * Mengambil pesan error yang ramah dari respons Laravel.
 */
export function extractErrorMessage(error, fallback = 'Terjadi kesalahan. Silakan coba lagi.') {
    const data = error?.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
    }
    return data?.message || fallback;
}

export default http;