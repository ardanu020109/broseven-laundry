import http from '@/services/http';

export async function fetchDashboard() {
    const { data } = await http.get('/api/dashboard');
    return data.data;
}

export async function fetchNotifications() {
    const { data } = await http.get('/api/notifications');
    return data.data;
}