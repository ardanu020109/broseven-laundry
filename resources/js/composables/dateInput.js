const ISO = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/;

/**
 * Topeng ketikan: hanya angka, dengan pemisah otomatis.
 *   24112026     => 24/11/2026
 *   241120261030 => 24/11/2026 10:30   (withTime)
 * Teks ISO yang ditempel (2026-11-24) dikonversi ke urutan dd/mm/yyyy.
 */
export function maskDigits(raw, withTime = false) {
    let value = String(raw ?? '');
    const iso = ISO.exec(value.trim());

    if (iso) value = `${iso[3]}${iso[2]}${iso[1]}${withTime ? `${iso[4] ?? ''}${iso[5] ?? ''}` : ''}`;

    const digits = value.replace(/\D/g, '').slice(0, withTime ? 12 : 8);

    let out = digits.slice(0, 2);
    if (digits.length > 2) out += `/${digits.slice(2, 4)}`;
    if (digits.length > 4) out += `/${digits.slice(4, 8)}`;
    if (withTime && digits.length > 8) out += ` ${digits.slice(8, 10)}`;
    if (withTime && digits.length > 10) out += `:${digits.slice(10, 12)}`;

    return out;
}

/**
 * "24/11/2026" => "2026-11-24"; "24/11/2026 10:30" => "2026-11-24T10:30".
 * Mengembalikan null jika format salah atau tanggal/jam tidak ada (mis. 31/02/2026, 24:00).
 */
export function parseDisplay(text, withTime = false) {
    const pattern = withTime
        ? /^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/
        : /^(\d{2})\/(\d{2})\/(\d{4})$/;
    const match = pattern.exec(String(text ?? ''));

    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);

    if (year < 1900 || year > 2100 || month < 1 || month > 12) return null;

    const check = new Date(Date.UTC(year, month - 1, day));

    if (check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day) return null;

    const date = `${match[3]}-${match[2]}-${match[1]}`;

    if (!withTime) return date;

    if (Number(match[4]) > 23 || Number(match[5]) > 59) return null;

    return `${date}T${match[4]}:${match[5]}`;
}

/** "2026-11-24" => "24/11/2026"; "2026-11-24T10:30" => "24/11/2026 10:30". */
export function isoToDisplay(iso, withTime = false) {
    const match = ISO.exec(String(iso ?? ''));

    if (!match) return '';

    const date = `${match[3]}/${match[2]}/${match[1]}`;

    return withTime ? `${date} ${match[4] ?? '00'}:${match[5] ?? '00'}` : date;
}