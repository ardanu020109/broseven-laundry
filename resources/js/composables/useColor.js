const SURFACE = '#FFFFFF';

/** "#abc" / "#AABBCC" => "#AABBCC", atau null jika tidak valid. */
export function normalizeHex(value) {
    const hex = String(value ?? '').trim();
    const short = /^#([0-9a-f]{3})$/i.exec(hex);
    const full = short ? `#${[...short[1]].map((c) => c + c).join('')}` : hex;

    return /^#[0-9a-f]{6}$/i.test(full) ? full.toUpperCase() : null;
}

export function luminance(hex) {
    const normalized = normalizeHex(hex) ?? '#000000';

    const [r, g, b] = [1, 3, 5]
        .map((index) => parseInt(normalized.slice(index, index + 2), 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Rasio kontras 1 sampai 21 (WCAG). */
export function contrastRatio(a, b) {
    const first = luminance(a);
    const second = luminance(b);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

/**
 * Peringatan kontras (hanya untuk UX; backend yang menegakkan aturan ini).
 * Mengembalikan { primary: '...', text: '...' } untuk warna yang bermasalah.
 */
export function contrastIssues(colors) {
    const issues = {};

    if (contrastRatio(colors.text, colors.background) < 4.5) {
        issues.text = 'Kontras dengan warna latar kurang dari 4,5:1, tulisan sulit dibaca.';
    } else if (contrastRatio(colors.text, SURFACE) < 4.5) {
        issues.text = 'Warna teks terlalu terang untuk kartu putih (kontras kurang dari 4,5:1).';
    }

    for (const key of ['primary', 'secondary', 'accent']) {
        if (contrastRatio(colors.text, colors[key]) < 3) {
            issues[key] = 'Kontras dengan warna teks kurang dari 3:1, tulisan pada tombol sulit dibaca.';
        }
    }

    return issues;
}