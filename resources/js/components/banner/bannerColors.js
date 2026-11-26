function parseHex(hex) {
    const match = /^#?([0-9a-f]{6})$/i.exec(String(hex || ''));
    const n = match ? parseInt(match[1], 16) : 0;

    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

/** Campuran dua warna HEX; t = 0 menghasilkan a, t = 1 menghasilkan b. */
export function mix(a, b, t) {
    const A = parseHex(a);
    const B = parseHex(b);

    return `#${A.map((v, i) => Math.round(v + (B[i] - v) * t).toString(16).padStart(2, '0')).join('')}`;
}

export function alpha(hex, a) {
    const [r, g, b] = parseHex(hex);

    return `rgba(${r}, ${g}, ${b}, ${a})`;
}

/** Palette banner: turunan warna sama dengan CSS Variables website (color-mix). */
export function buildPalette(colors = {}) {
    const c = {
        primary: '#ADD8E6',
        secondary: '#ADBCE6',
        accent: '#ADE6D8',
        background: '#F8FCFD',
        text: '#243746',
        ...colors,
    };

    return {
        ...c,
        surface: '#FFFFFF',
        primarySoft: mix('#FFFFFF', c.primary, 0.22),
        accentSoft: mix('#FFFFFF', c.accent, 0.25),
        muted: mix(c.text, '#FFFFFF', 0.4),
    };
}