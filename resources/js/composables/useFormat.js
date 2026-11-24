const TIMEZONE = 'Asia/Makassar';

export function formatDateTime(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: TIMEZONE,
    }).format(new Date(value));
}

export function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeZone: TIMEZONE,
    }).format(new Date(value));
}

/** Rp20.000; jika ada sen ditampilkan 2 desimal: Rp2.566,66 */
export function formatCurrency(value) {
    const number = Number(value) || 0;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: Number.isInteger(number) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(number);
}

export function formatNumber(value, maximumFractionDigits = 2) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits }).format(Number(value) || 0);
}

export function formatQuantity(value, unit = '') {
    const number = formatNumber(value);
    return unit ? `${number} ${unit}` : number;
}

/** 1.250.000 => "1,3 jt" (untuk label sumbu grafik). */
export function formatCompact(value) {
    return new Intl.NumberFormat('id-ID', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(Number(value) || 0);
}

export function formatPercent(value) {
    return `${formatNumber(value, 1)}%`;
}

/** Tanggal hari ini (WITA) berformat YYYY-MM-DD. */
export function todayKey() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: TIMEZONE }).format(new Date());
}