import { ClipboardList, LayoutDashboard, Settings, TrendingUp, User, Users } from 'lucide-vue-next';

/**
 * Menu sidebar. "ability" mengikuti Gate backend (dari /api/auth/me) dan hanya untuk UX;
 * keamanan sebenarnya ada di backend.
 */
export const NAV_ITEMS = [
    { key: 'dashboard', label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard, match: ['/dashboard'] },
    { key: 'transactions', label: 'Transaksi Laundry', to: '/transactions', icon: ClipboardList, match: ['/transactions', '/verify'] },
    { key: 'reports', label: 'Laporan Pemasukan', to: '/reports', icon: TrendingUp, ability: 'view-reports', match: ['/reports'] },
    { key: 'website', label: 'Pengaturan Website', to: '/website-settings', icon: Settings, ability: 'manage-website', match: ['/website-settings'] },
    { key: 'employees', label: 'Kelola Karyawan', to: '/employees', icon: Users, ability: 'manage-employees', match: ['/employees'] },
    { key: 'account', label: 'Pengaturan Akun', to: '/account', icon: User, match: ['/account'] },
];