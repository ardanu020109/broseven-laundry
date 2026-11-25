import http from '@/services/http';

function clean(params = {}) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

/** Mengembalikan { data, meta, links } (format pagination Laravel). */
export async function fetchEmployees(params) {
    const { data } = await http.get('/api/admin/employees', { params: clean(params) });
    return data;
}

export async function createEmployee(payload) {
    const { data } = await http.post('/api/admin/employees', payload);
    return data;
}

export async function updateEmployee(id, payload) {
    const { data } = await http.put(`/api/admin/employees/${id}`, payload);
    return data;
}

export async function setEmployeeStatus(id, isActive) {
    const { data } = await http.patch(`/api/admin/employees/${id}/status`, { is_active: isActive });
    return data;
}

export async function resetEmployeePassword(id, password) {
    const { data } = await http.post(`/api/admin/employees/${id}/password`, { password });
    return data;
}

export async function fetchEmployeeActivities(id, params) {
    const { data } = await http.get(`/api/admin/employees/${id}/activities`, { params: clean(params) });
    return data;
}