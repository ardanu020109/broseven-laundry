import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSiteStore } from '@/stores/site';

const TRANSACTIONS_CRUMB = [{ label: 'Transaksi Laundry', to: '/transactions' }];
const STAFF = ['admin', 'employee'];

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('@/pages/public/HomePage.vue'),
        meta: { title: '' },
    },
    {
        path: '/tracking/:token',
        name: 'tracking',
        component: () => import('@/pages/public/TrackingPage.vue'),
        meta: { title: 'Status Cucian' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { title: 'Masuk', guestOnly: true },
    },

    /*
     * Halaman internal berada di bawah satu layout (sidebar dan topbar tidak dimuat ulang
     * saat berpindah halaman). Path induk "/panel" hanya pembungkus dan dialihkan ke
     * /dashboard; path anak absolut, sehingga alamat halaman tidak berubah.
     */
    {
        path: '/panel',
        component: () => import('@/layouts/DashboardLayout.vue'),
        redirect: '/dashboard',
        meta: { layout: 'dashboard', requiresAuth: true },
        children: [
            {
                path: '/dashboard',
                name: 'dashboard',
                component: () => import('@/pages/dashboard/DashboardPage.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: '/transactions',
                name: 'transactions',
                component: () => import('@/pages/transactions/TransactionsPage.vue'),
                meta: { title: 'Transaksi Laundry', roles: STAFF },
            },
            {
                path: '/transactions/new',
                name: 'transactions.create',
                component: () => import('@/pages/transactions/TransactionFormPage.vue'),
                meta: { title: 'Transaksi Baru', breadcrumb: TRANSACTIONS_CRUMB, roles: STAFF },
            },
            {
                path: '/transactions/:id(\\d+)',
                name: 'transactions.show',
                component: () => import('@/pages/transactions/TransactionDetailPage.vue'),
                meta: { title: 'Detail Transaksi', breadcrumb: TRANSACTIONS_CRUMB, roles: STAFF },
            },
            {
                path: '/transactions/:id(\\d+)/edit',
                name: 'transactions.edit',
                component: () => import('@/pages/transactions/TransactionFormPage.vue'),
                meta: { title: 'Ubah Transaksi', breadcrumb: TRANSACTIONS_CRUMB, roles: STAFF },
            },
            {
                path: '/verify/:token?',
                name: 'verify',
                component: () => import('@/pages/transactions/VerifyPage.vue'),
                meta: { title: 'Verifikasi Transaksi', breadcrumb: TRANSACTIONS_CRUMB, roles: STAFF },
            },
            {
                path: '/reports',
                name: 'reports',
                component: () => import('@/pages/reports/ReportPage.vue'),
                meta: { title: 'Laporan Pemasukan', roles: ['admin'], ability: 'view-reports' },
            },
            {
                path: '/website-settings',
                name: 'website-settings',
                component: () => import('@/pages/website-settings/WebsiteSettingsPage.vue'),
                meta: { title: 'Pengaturan Website', roles: ['admin'], ability: 'manage-website' },
            },
            {
                path: '/employees',
                name: 'employees',
                component: () => import('@/pages/employees/EmployeesPage.vue'),
                meta: { title: 'Kelola Karyawan', roles: ['admin'], ability: 'manage-employees' },
            },
            {
                path: '/account',
                name: 'account',
                component: () => import('@/pages/account/AccountPage.vue'),
                meta: { title: 'Pengaturan Akun', roles: STAFF },
            },
        ],
    },

    {
        path: '/403',
        name: 'forbidden',
        component: () => import('@/pages/ForbiddenPage.vue'),
        meta: { title: 'Akses ditolak' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
        meta: { title: 'Halaman tidak ditemukan' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        if (to.hash) return { el: to.hash, top: 84, behavior: 'smooth' };
        // Perubahan query saja (filter daftar) tidak menggulir halaman ke atas.
        if (to.path === from.path) return false;
        return { top: 0 };
    },
});

/**
 * Meta route yang didukung:
 *   requiresAuth : harus login (diwarisi dari route induk)
 *   guestOnly    : hanya untuk yang belum login
 *   roles        : ['admin'] atau ['admin', 'employee']
 *   ability      : 'view-reports' (dari Gate backend, lewat /api/auth/me)
 *   breadcrumb   : [{ label, to }] jejak sebelum judul halaman
 *
 * Pembatasan di frontend hanya untuk UX. Keamanan sebenarnya ada di backend.
 */
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth || to.meta.guestOnly) {
        await auth.init();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) {
        return { name: 'forbidden' };
    }

    if (to.meta.ability && !auth.can(to.meta.ability)) {
        return { name: 'forbidden' };
    }

    return true;
});

router.afterEach((to) => {
    const appName = useSiteStore().name;
    document.title = to.meta?.title ? `${to.meta.title} | ${appName}` : appName;
});

export default router;