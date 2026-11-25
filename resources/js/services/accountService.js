import http from '@/services/http';

export async function updateProfile(payload) {
    const { data } = await http.put('/api/account/profile', payload);
    return data;
}

export async function updatePassword(payload) {
    const { data } = await http.put('/api/account/password', payload);
    return data;
}

export async function uploadAvatar(file) {
    const form = new FormData();
    form.append('photo', file);

    const { data } = await http.post('/api/account/avatar', form);
    return data;
}

export async function deleteAvatar() {
    const { data } = await http.delete('/api/account/avatar');
    return data;
}