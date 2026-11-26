import { alpha, buildPalette, mix } from '@/components/banner/bannerColors';

export const FONT_FAMILY = 'Plus Jakarta Sans Variable';
const STACK = `"${FONT_FAMILY}", system-ui, "Segoe UI", Roboto, Arial, sans-serif`;
const GREEN = '#48B89A';

function setFont(ctx, weight, size) {
    ctx.font = `${weight} ${Math.max(1, Math.round(size * 2) / 2)}px ${STACK}`;
}

export function formatRupiah(value) {
    const n = Number(value) || 0;
    const text = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: Number.isInteger(n) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(n);

    return `Rp${text}`;
}

function formatPhone(digits) {
    const d = String(digits || '');

    return d.startsWith('62') && d.length >= 10 ? `+62 ${d.slice(2, 5)}-${d.slice(5, 9)}-${d.slice(9)}` : `+${d}`;
}

function roundedRect(ctx, x, y, w, h, r) {
    const radius = Math.max(0, Math.min(r, w / 2, h / 2));

    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius);
    ctx.closePath();
}

/** Pembangkit angka acak deterministik: gelembung selalu di tempat yang sama. */
function seeded(seed) {
    let a = seed;

    return () => {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/* ------------------------------- Teks ------------------------------- */

function wrapLines(ctx, text, maxWidth) {
    const lines = [];
    let line = '';

    for (const word of String(text).split(/\s+/).filter(Boolean)) {
        const test = line ? `${line} ${word}` : word;

        if (!line || ctx.measureText(test).width <= maxWidth) {
            line = test;
        } else {
            lines.push(line);
            line = word;
        }
    }

    if (line) lines.push(line);

    return lines;
}

function ellipsize(ctx, text, maxWidth) {
    if (ctx.measureText(text).width <= maxWidth) return text;

    let t = text;
    while (t.length > 1 && ctx.measureText(`${t}…`).width > maxWidth) t = t.slice(0, -1);

    return `${t.trimEnd()}…`;
}

/** Ukuran huruf terbesar (<= start) agar teks muat dalam maxWidth, maxLines, dan maxHeight. */
function fitText(ctx, text, o) {
    const weight = o.weight ?? 600;
    const lh = o.lineHeight ?? 1.18;
    const maxLines = o.maxLines ?? 1;
    const floor = Math.max(1, o.min ?? o.start * 0.4);

    if (!text) return { lines: [], size: o.start, weight, lineHeight: o.start * lh, height: 0 };

    let size = Math.max(floor, o.start);
    let lines = [];

    for (;;) {
        setFont(ctx, weight, size);
        lines = wrapLines(ctx, text, o.maxWidth);

        const widest = lines.reduce((max, l) => Math.max(max, ctx.measureText(l).width), 0);
        const tooTall = lines.length * size * lh > (o.maxHeight ?? Infinity);

        if ((lines.length <= maxLines && widest <= o.maxWidth && !tooTall) || size <= floor) break;

        size = Math.max(floor, size * 0.93);
    }

    if (lines.length > maxLines) {
        lines = lines.slice(0, maxLines);
        lines[maxLines - 1] = `${lines[maxLines - 1]}…`;
    }

    lines = lines.map((l) => ellipsize(ctx, l, o.maxWidth));

    return { lines, size, weight, lineHeight: size * lh, height: lines.length * size * lh };
}

function drawLines(ctx, layout, x, y, color, align = 'left') {
    setFont(ctx, layout.weight, layout.size);
    ctx.fillStyle = color;
    ctx.textAlign = align;
    ctx.textBaseline = 'top';

    layout.lines.forEach((line, index) => ctx.fillText(line, x, y + index * layout.lineHeight));

    return y + layout.height;
}

/* ------------------------------ Elemen ------------------------------ */

function drawLogo(ctx, img, x, y, size, P) {
    ctx.save();
    ctx.shadowColor = alpha(P.text, 0.2);
    ctx.shadowBlur = size * 0.12;
    ctx.shadowOffsetY = size * 0.04;
    roundedRect(ctx, x, y, size, size, size * 0.22);
    ctx.fillStyle = '#FFFFFF';
    ctx.fill();
    ctx.restore();

    if (img && img.naturalWidth) {
        const box = size * 0.84;
        const scale = Math.min(box / img.naturalWidth, box / img.naturalHeight);
        const w = img.naturalWidth * scale;
        const h = img.naturalHeight * scale;

        ctx.save();
        roundedRect(ctx, x, y, size, size, size * 0.22);
        ctx.clip();
        ctx.drawImage(img, x + (size - w) / 2, y + (size - h) / 2, w, h);
        ctx.restore();

        return;
    }

    // Tanpa logo: ikon tetesan air.
    const cx = x + size / 2;
    const cy = y + size / 2;
    const s = size;

    ctx.beginPath();
    ctx.moveTo(cx, cy - 0.36 * s);
    ctx.bezierCurveTo(cx + 0.1 * s, cy - 0.18 * s, cx + 0.28 * s, cy - 0.02 * s, cx + 0.28 * s, cy + 0.12 * s);
    ctx.bezierCurveTo(cx + 0.28 * s, cy + 0.3 * s, cx + 0.15 * s, cy + 0.38 * s, cx, cy + 0.38 * s);
    ctx.bezierCurveTo(cx - 0.15 * s, cy + 0.38 * s, cx - 0.28 * s, cy + 0.3 * s, cx - 0.28 * s, cy + 0.12 * s);
    ctx.bezierCurveTo(cx - 0.28 * s, cy - 0.02 * s, cx - 0.1 * s, cy - 0.18 * s, cx, cy - 0.36 * s);
    ctx.closePath();
    ctx.fillStyle = mix(P.primary, P.text, 0.35);
    ctx.fill();
}

function drawCheck(ctx, cx, cy, r) {
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.fillStyle = GREEN;
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(cx - r * 0.45, cy);
    ctx.lineTo(cx - r * 0.1, cy + r * 0.35);
    ctx.lineTo(cx + r * 0.5, cy - r * 0.32);
    ctx.lineWidth = Math.max(1, r * 0.28);
    ctx.strokeStyle = '#FFFFFF';
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();
}

function drawBackground(ctx, W, H, P, short) {
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, mix(P.background, P.primary, 0.28));
    g.addColorStop(0.55, P.background);
    g.addColorStop(1, mix(P.background, P.accent, 0.32));
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    const blob = (x, y, r, color) => {
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
    };

    blob(W * 0.03, H * 0.04, short * 0.9, alpha(P.primary, 0.28));
    blob(W * 0.98, H * 0.96, short * 0.8, alpha(P.accent, 0.3));
    blob(W * 0.55, H * 1.03, short * 0.5, alpha(P.secondary, 0.2));

    const rnd = seeded(7);
    ctx.lineWidth = Math.max(1, short * 0.003);

    for (let i = 0; i < 26; i++) {
        const r = short * (0.012 + rnd() * 0.03);

        ctx.beginPath();
        ctx.arc(rnd() * W, rnd() * H, r, 0, Math.PI * 2);
        ctx.fillStyle = alpha(P.primary, 0.12);
        ctx.fill();
        ctx.strokeStyle = alpha(P.secondary, 0.35);
        ctx.stroke();
    }
}

/* ------------------------------ Header ------------------------------ */

function headerPanel(ctx, r, P, radius) {
    ctx.save();
    roundedRect(ctx, r.x, r.y, r.w, r.h, radius);
    const g = ctx.createLinearGradient(r.x, r.y, r.x + r.w, r.y + r.h);
    g.addColorStop(0, P.primary);
    g.addColorStop(1, P.secondary);
    ctx.fillStyle = g;
    ctx.fill();
    ctx.clip();
    ctx.fillStyle = alpha('#FFFFFF', 0.18);
    ctx.beginPath();
    ctx.arc(r.x + r.w, r.y, Math.min(r.w, r.h) * 0.7, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(r.x, r.y + r.h, Math.min(r.w, r.h) * 0.5, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}

/** Logo dan nama berdampingan (mode sedang). */
function drawHeaderRow(ctx, r, model, P) {
    headerPanel(ctx, r, P, r.h * 0.28);

    const pad = r.h * 0.14;
    const logo = r.h - pad * 2;
    drawLogo(ctx, model.logo, r.x + pad, r.y + pad, logo, P);

    const tx = r.x + pad * 2 + logo;
    const tw = r.w - (tx - r.x) - pad;
    const name = fitText(ctx, model.name, { weight: 800, maxWidth: tw, maxLines: 1, start: r.h * 0.46, min: r.h * 0.2, maxHeight: r.h * 0.6 });
    const tag = model.tagline
        ? fitText(ctx, model.tagline, { weight: 500, maxWidth: tw, maxLines: 1, start: name.size * 0.5, min: r.h * 0.1 })
        : null;
    const gap = r.h * 0.04;
    const total = name.height + (tag ? tag.height + gap : 0);

    const y = drawLines(ctx, name, tx, r.y + (r.h - total) / 2, P.text);
    if (tag) drawLines(ctx, tag, tx, y + gap, alpha(P.text, 0.8));
}

/** Logo di atas, nama di bawah, rata tengah (mode sangat lebar dan mode tinggi). */
function drawHeaderStack(ctx, r, model, P) {
    headerPanel(ctx, r, P, Math.min(r.w, r.h) * 0.1);

    const pad = Math.min(r.w, r.h) * 0.09;
    const iw = r.w - pad * 2;
    const logo = Math.min(iw * 0.62, r.h * 0.4);
    const gap = pad * 0.8;
    const room = Math.max(0, r.h - pad * 2 - logo - gap);

    const name = fitText(ctx, model.name, {
        weight: 800, maxWidth: iw, maxLines: 3, start: Math.min(iw * 0.2, room * 0.4), min: iw * 0.06, maxHeight: room * (model.tagline ? 0.65 : 1),
    });
    const tag = model.tagline
        ? fitText(ctx, model.tagline, { weight: 500, maxWidth: iw, maxLines: 3, start: name.size * 0.55, min: iw * 0.04, maxHeight: Math.max(0, room - name.height - gap) })
        : null;

    const total = logo + gap + name.height + (tag ? tag.height + gap * 0.6 : 0);
    let y = r.y + Math.max(pad, (r.h - total) / 2);
    const cx = r.x + r.w / 2;

    drawLogo(ctx, model.logo, cx - logo / 2, y, logo, P);
    y = drawLines(ctx, name, cx, y + logo + gap, P.text, 'center');
    if (tag) drawLines(ctx, tag, cx, y + gap * 0.6, alpha(P.text, 0.8), 'center');
}

/* ------------------------------ Paket ------------------------------ */

/** Mencari jumlah kolom terbaik agar kartu mendekati proporsi yang enak dilihat. */
function bestGrid(n, w, h, gap) {
    let best = null;

    for (let c = 1; c <= n; c++) {
        const r = Math.ceil(n / c);
        const cw = (w - gap * (c - 1)) / c;
        const ch = (h - gap * (r - 1)) / r;

        if (cw < 10 || ch < 10) continue;

        const score = (cw * ch) / (1 + Math.abs(Math.log(cw / ch / 0.95)) * 1.4);

        if (!best || score > best.score) best = { c, r, cw, ch, score };
    }

    return best ?? { c: n, r: 1, cw: Math.max(10, (w - gap * (n - 1)) / n), ch: h, score: 0 };
}

function cardShell(ctx, x, y, w, h, r, P, short) {
    ctx.save();
    ctx.shadowColor = alpha(P.text, 0.22);
    ctx.shadowBlur = short * 0.018;
    ctx.shadowOffsetY = short * 0.008;
    roundedRect(ctx, x, y, w, h, r);
    ctx.fillStyle = P.surface;
    ctx.fill();
    ctx.restore();
}

function drawCard(ctx, x, y, w, h, pkg, model, P, short) {
    const r = Math.min(w, h) * 0.09;
    cardShell(ctx, x, y, w, h, r, P, short);

    ctx.save();
    roundedRect(ctx, x, y, w, h, r);
    ctx.clip();
    const bar = ctx.createLinearGradient(x, 0, x + w, 0);
    bar.addColorStop(0, P.primary);
    bar.addColorStop(1, P.secondary);
    ctx.fillStyle = bar;
    ctx.fillRect(x, y, w, h * 0.07);
    ctx.restore();

    roundedRect(ctx, x, y, w, h, r);
    ctx.lineWidth = Math.max(1, short * 0.0022);
    ctx.strokeStyle = alpha(P.primary, 0.9);
    ctx.stroke();

    const o = model.options;
    const pad = w * 0.09;
    const iw = w - pad * 2;
    const top = y + h * 0.07 + pad * 0.5;
    const innerH = y + h - pad * 0.7 - top;
    const cx = x + w / 2;
    const gap = h * 0.03;

    const name = fitText(ctx, pkg.name, {
        weight: 800, maxWidth: iw, maxLines: 2, start: Math.min(h * 0.095, iw * 0.17), min: Math.min(h * 0.04, iw * 0.07), maxHeight: h * 0.24,
    });
    const price = fitText(ctx, formatRupiah(pkg.price), {
        weight: 800, maxWidth: iw, maxLines: 1, start: Math.min(h * 0.15, iw * 0.26), min: Math.min(h * 0.06, iw * 0.1),
    });
    const unitSize = price.size * 0.42;

    const parts = [
        { h: name.height, draw: (yy) => drawLines(ctx, name, cx, yy, P.text, 'center') },
        {
            h: price.height + unitSize * 1.3,
            draw: (yy) => {
                const next = drawLines(ctx, price, cx, yy, P.text, 'center');

                if (pkg.priceLabel) {
                    setFont(ctx, 500, unitSize);
                    ctx.fillStyle = P.muted;
                    ctx.textAlign = 'center';
                    ctx.fillText(pkg.priceLabel, cx, next + unitSize * 0.1);
                }
            },
        },
    ];

    if (o.showEstimate && pkg.estimateLabel) {
        const fs = Math.min(h * 0.048, iw * 0.075);
        setFont(ctx, 600, fs);
        const label = ellipsize(ctx, `Estimasi ${pkg.estimateLabel}`, iw - fs * 1.6);
        const pillW = ctx.measureText(label).width + fs * 1.6;
        const pillH = fs * 1.9;

        parts.push({
            h: pillH,
            draw: (yy) => {
                roundedRect(ctx, cx - pillW / 2, yy, pillW, pillH, pillH / 2);
                ctx.fillStyle = P.accentSoft;
                ctx.fill();
                ctx.lineWidth = Math.max(1, fs * 0.08);
                ctx.strokeStyle = P.accent;
                ctx.stroke();
                setFont(ctx, 600, fs);
                ctx.fillStyle = P.text;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText(label, cx, yy + (pillH - fs) / 2);
            },
        });
    }

    if (o.showFeatures && pkg.features.length) {
        const fs = Math.max(5, Math.min(h * 0.042, iw * 0.065));
        const lineH = fs * 1.6;
        const used = parts.reduce((sum, p) => sum + p.h, 0) + gap * parts.length;
        const count = Math.max(0, Math.min(pkg.features.length, 5, Math.floor((innerH - used) / lineH)));

        if (count > 0) {
            parts.push({
                h: count * lineH,
                draw: (yy) => {
                    setFont(ctx, 500, fs);
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'top';

                    for (let i = 0; i < count; i++) {
                        const text = ellipsize(ctx, pkg.features[i], iw - fs * 1.7);
                        const sx = cx - (fs * 1.3 + ctx.measureText(text).width) / 2;
                        const ty = yy + i * lineH;

                        drawCheck(ctx, sx + fs * 0.45, ty + lineH / 2, fs * 0.42);
                        ctx.fillStyle = P.text;
                        ctx.fillText(text, sx + fs * 1.3, ty + (lineH - fs) / 2);
                    }
                },
            });
        }
    }

    const total = parts.reduce((sum, p) => sum + p.h, 0) + gap * (parts.length - 1);
    let yy = top + Math.max(0, (innerH - total) / 2);

    for (const part of parts) {
        part.draw(yy);
        yy += part.h + gap;
    }
}

function drawGrid(ctx, area, model, P, short) {
    const items = model.packages;
    const gap = Math.round(short * 0.035);
    const grid = bestGrid(items.length, area.w, area.h, gap);

    let cw = grid.cw;
    let ch = grid.ch;
    cw = Math.min(cw, ch * 1.8);
    ch = Math.min(ch, cw * 1.55);

    const rows = Math.ceil(items.length / grid.c);
    const y0 = area.y + Math.max(0, (area.h - (rows * ch + gap * (rows - 1))) / 2);

    items.forEach((pkg, index) => {
        const row = Math.floor(index / grid.c);
        const col = index % grid.c;
        const inRow = Math.min(grid.c, items.length - row * grid.c);
        const rowW = inRow * cw + gap * (inRow - 1);

        drawCard(ctx, area.x + (area.w - rowW) / 2 + col * (cw + gap), y0 + row * (ch + gap), cw, ch, pkg, model, P, short);
    });
}

/** Baris daftar harga (banner tinggi): nama di kiri, harga di kanan. */
function drawRow(ctx, x, y, w, h, pkg, model, P, short) {
    const r = h * 0.22;
    cardShell(ctx, x, y, w, h, r, P, short);

    ctx.save();
    roundedRect(ctx, x, y, w, h, r);
    ctx.clip();
    const bar = ctx.createLinearGradient(0, y, 0, y + h);
    bar.addColorStop(0, P.primary);
    bar.addColorStop(1, P.secondary);
    ctx.fillStyle = bar;
    ctx.fillRect(x, y, w * 0.022, h);
    ctx.restore();

    const pad = h * 0.18;
    const priceW = w * 0.34;
    const price = fitText(ctx, formatRupiah(pkg.price), { weight: 800, maxWidth: priceW, maxLines: 1, start: h * 0.36, min: h * 0.14 });
    const unit = pkg.priceLabel
        ? fitText(ctx, pkg.priceLabel, { weight: 500, maxWidth: priceW, maxLines: 1, start: price.size * 0.42, min: h * 0.08 })
        : null;

    const px = x + w - pad;
    let py = y + (h - price.height - (unit ? unit.height : 0)) / 2;
    py = drawLines(ctx, price, px, py, P.text, 'right');
    if (unit) drawLines(ctx, unit, px, py, P.muted, 'right');

    const lx = x + pad + w * 0.02;
    const lw = Math.max(10, w - (pad + w * 0.02) - priceW - pad * 1.5);
    const name = fitText(ctx, pkg.name, { weight: 800, maxWidth: lw, maxLines: 2, start: h * 0.3, min: h * 0.12, maxHeight: h * 0.55 });

    const sub = [];
    if (model.options.showEstimate && pkg.estimateLabel) sub.push(`Estimasi ${pkg.estimateLabel}`);
    if (model.options.showFeatures && pkg.features.length) sub.push(pkg.features.slice(0, 3).join(' • '));

    const line = sub.length
        ? fitText(ctx, sub.join('  |  '), { weight: 500, maxWidth: lw, maxLines: 1, start: h * 0.17, min: h * 0.08 })
        : null;
    const gap = h * 0.04;
    const total = name.height + (line ? line.height + gap : 0);

    const ty = drawLines(ctx, name, lx, y + (h - total) / 2, P.text);
    if (line) drawLines(ctx, line, lx, ty + gap, P.muted);
}

function drawRows(ctx, area, model, P, short) {
    const items = model.packages;
    const gap = Math.round(area.w * 0.035);
    const rh = Math.min((area.h - gap * (items.length - 1)) / items.length, area.w * 0.3);

    if (rh < 8) return;

    let y = area.y + Math.max(0, (area.h - (items.length * rh + gap * (items.length - 1))) / 2);

    items.forEach((pkg) => {
        drawRow(ctx, area.x, y, area.w, rh, pkg, model, P, short);
        y += rh + gap;
    });
}

function drawBody(ctx, body, model, P, mode, short) {
    let top = body.y;

    if (model.headline) {
        const head = fitText(ctx, model.headline, {
            weight: 800, maxWidth: body.w, maxLines: 1, start: Math.min(body.h * 0.085, body.w * 0.075), min: short * 0.025,
        });
        const center = mode === 'tall';
        const hx = center ? body.x + body.w / 2 : body.x;
        const by = drawLines(ctx, head, hx, top, P.text, center ? 'center' : 'left') + head.size * 0.12;
        const barW = Math.min(body.w * 0.18, head.size * 3);
        const barH = Math.max(2, head.size * 0.14);

        roundedRect(ctx, center ? body.x + (body.w - barW) / 2 : body.x, by, barW, barH, barH / 2);
        ctx.fillStyle = P.secondary;
        ctx.fill();

        top = by + barH + head.size * 0.5;
    }

    const area = { x: body.x, y: top, w: body.w, h: body.y + body.h - top };

    if (!model.packages.length || area.h < short * 0.05) return;

    if (mode === 'tall') drawRows(ctx, area, model, P, short);
    else drawGrid(ctx, area, model, P, short);
}

function drawFooter(ctx, f, model, P, mode) {
    roundedRect(ctx, f.x, f.y, f.w, f.h, f.h * 0.4);
    ctx.fillStyle = P.text;
    ctx.fill();

    const c = model.contact;
    const parts = [];
    if (c.whatsapp) parts.push(`WhatsApp ${formatPhone(c.whatsapp)}`);
    if (c.hours) parts.push(c.hours);
    if (c.address) parts.push(String(c.address).replace(/\s+/g, ' '));

    const pad = f.h * 0.5;
    const text = fitText(ctx, parts.join('   •   '), {
        weight: 600, maxWidth: f.w - pad * 2, maxLines: mode === 'tall' ? 3 : 2, start: f.h * 0.42, min: f.h * 0.12, maxHeight: f.h * 0.8,
    });

    drawLines(ctx, text, f.x + f.w / 2, f.y + (f.h - text.height) / 2, '#FFFFFF', 'center');
}

/* ------------------------------- Utama ------------------------------ */

/**
 * Menggambar banner pada ukuran piksel W x H. Semua ukuran dihitung relatif, sehingga
 * pratinjau kecil dan hasil ekspor besar tampil sama.
 *
 * model: { name, tagline, logo (HTMLImageElement|null), colors, headline,
 *          packages: [{ name, price, priceLabel, estimateLabel, features[] }],
 *          contact: { whatsapp, address, hours },
 *          options: { showFeatures, showEstimate, showContact } }
 */
export function drawBanner(ctx, W, H, model) {
    const P = buildPalette(model.colors);
    const short = Math.min(W, H);
    const ratio = W / H;
    const m = Math.round(short * 0.04);
    const c = model.contact;
    const hasContact = !!(model.options.showContact && (c.whatsapp || c.address || c.hours));
    const mode = ratio >= 2.1 ? 'side' : ratio >= 0.8 ? 'top' : 'tall';

    ctx.save();
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.clearRect(0, 0, W, H);
    drawBackground(ctx, W, H, P, short);

    const footerH = hasContact ? Math.round(H * { side: 0.13, top: 0.09, tall: 0.07 }[mode]) : 0;
    const footer = footerH ? { x: m, y: H - m - footerH, w: W - 2 * m, h: footerH } : null;
    const bottom = footer ? footer.y - m * 0.7 : H - m;

    let header;
    let body;

    if (mode === 'side') {
        const hw = Math.round(W * 0.27);
        header = { x: m, y: m, w: hw, h: bottom - m };
        body = { x: m * 2 + hw, y: m, w: W - hw - m * 3, h: bottom - m };
    } else if (mode === 'top') {
        const hh = Math.round(H * (ratio < 1.3 ? 0.15 : 0.2));
        header = { x: m, y: m, w: W - 2 * m, h: hh };
        body = { x: m, y: m + hh + m * 0.8, w: W - 2 * m, h: bottom - (m + hh + m * 0.8) };
    } else {
        const hh = Math.round(Math.min(Math.max(H * 0.2, W * 0.75), H * 0.3));
        header = { x: m, y: m, w: W - 2 * m, h: hh };
        body = { x: m, y: m + hh + m * 0.6, w: W - 2 * m, h: bottom - (m + hh + m * 0.6) };
    }

    if (mode === 'top') drawHeaderRow(ctx, header, model, P);
    else drawHeaderStack(ctx, header, model, P);

    drawBody(ctx, body, model, P, mode, short);
    if (footer) drawFooter(ctx, footer, model, P, mode);

    ctx.restore();
}

export function renderToCanvas(canvas, W, H, model) {
    canvas.width = W;
    canvas.height = H;
    drawBanner(canvas.getContext('2d'), W, H, model);
}

/** Memuat logo (satu origin, canvas tidak ter-taint). Gagal = null (dipakai ikon bawaan). */
export async function loadImage(url) {
    if (!url) return null;

    try {
        const img = new Image();
        img.src = url;
        await img.decode();

        return img;
    } catch {
        return null;
    }
}

/** Memastikan huruf web termuat sebelum menggambar. */
export async function ensureFonts(texts) {
    if (!document.fonts?.load) return;

    const sample = Array.from(new Set(`${texts.join(' ')} Rp0123456789.,+-•`)).join('');

    await Promise.all(
        [500, 600, 800].map((weight) => document.fonts.load(`${weight} 40px "${FONT_FAMILY}"`, sample).catch(() => null)),
    );
}