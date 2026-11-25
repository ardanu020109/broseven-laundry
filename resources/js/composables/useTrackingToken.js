/**
 * Mengambil token tracking dari link atau token yang ditempel pengunjung.
 * Kode transaksi (BS-yymmdd-0001) sengaja TIDAK diterima: kode mudah ditebak
 * dan akan membuka data customer.
 *
 * @returns {{ token: string|null, reason: null|'empty'|'code'|'invalid' }}
 */
export function extractTrackingToken(raw) {
    const text = String(raw ?? '').trim();

    if (!text) return { token: null, reason: 'empty' };

    const fromUrl = /\/tracking\/([A-Za-z0-9]{40})(?![A-Za-z0-9])/.exec(text);
    if (fromUrl) return { token: fromUrl[1], reason: null };

    if (/^[A-Za-z0-9]{40}$/.test(text)) return { token: text, reason: null };

    if (/^BS-\d{6}-\d{4}$/i.test(text)) return { token: null, reason: 'code' };

    return { token: null, reason: 'invalid' };
}