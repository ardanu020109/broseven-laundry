import { CheckCircle2, Droplets, PackageCheck, Shirt, Wind, XCircle } from 'lucide-vue-next';

/** Pilihan filter status pada daftar transaksi. */
export const STATUS_FILTERS = [
    { value: '', label: 'Semua status' },
    { value: 'IN_PROGRESS', label: 'Dalam proses' },
    { value: 'RECEIVED', label: 'Diterima' },
    { value: 'WASHING', label: 'Sedang dicuci' },
    { value: 'DRYING', label: 'Sedang dikeringkan' },
    { value: 'IRONING', label: 'Sedang disetrika' },
    { value: 'READY', label: 'Siap diambil' },
    { value: 'COMPLETED', label: 'Selesai' },
    { value: 'CANCELLED', label: 'Dibatalkan' },
];

export const IN_PROGRESS_STATUSES = ['RECEIVED', 'WASHING', 'DRYING', 'IRONING'];

/** Kalimat tindakan pada tombol perubahan status. */
export const STATUS_ACTIONS = {
    WASHING: { label: 'Mulai Cuci', icon: Droplets },
    DRYING: { label: 'Mulai Keringkan', icon: Wind },
    IRONING: { label: 'Mulai Setrika', icon: Shirt },
    READY: { label: 'Tandai Siap Diambil', icon: PackageCheck },
    COMPLETED: { label: 'Serahkan Cucian', icon: CheckCircle2 },
    CANCELLED: { label: 'Batalkan Transaksi', icon: XCircle },
};

/** True jika cucian masih diproses dan sudah melewati estimasi selesai. */
export function isOverdue(transaction) {
    return (
        IN_PROGRESS_STATUSES.includes(transaction.status) &&
        !!transaction.estimated_completion_at &&
        new Date(transaction.estimated_completion_at) < new Date()
    );
}