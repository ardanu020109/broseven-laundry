import http from '@/services/http';

export async function fetchTracking(token) {
    const { data } = await http.get(`/api/public/tracking/${encodeURIComponent(token)}`);
    return data.data;
}