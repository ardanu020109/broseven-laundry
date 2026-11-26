const SIGNATURE = [137, 80, 78, 71, 13, 10, 26, 10];

let table = null;

function crc32(bytes) {
    if (!table) {
        table = new Uint32Array(256);

        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
            table[n] = c >>> 0;
        }
    }

    let crc = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) crc = table[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);

    return (crc ^ 0xffffffff) >>> 0;
}

/**
 * Menanamkan ukuran fisik (piksel per meter) ke PNG lewat chunk pHYs, sehingga
 * Photoshop/percetakan membaca ukuran cetak sebenarnya. Gagal membaca = mengembalikan blob asli.
 */
export async function setPngDpi(blob, pixelsPerMeter) {
    const bytes = new Uint8Array(await blob.arrayBuffer());

    if (SIGNATURE.some((value, index) => bytes[index] !== value)) return blob;

    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const chunks = [];
    let offset = 8;

    while (offset + 12 <= bytes.length) {
        const length = view.getUint32(offset);
        const type = String.fromCharCode(bytes[offset + 4], bytes[offset + 5], bytes[offset + 6], bytes[offset + 7]);
        const end = offset + 12 + length;

        if (end > bytes.length) return blob;

        if (type !== 'pHYs') chunks.push({ type, data: bytes.subarray(offset, end) });

        offset = end;
    }

    if (!chunks.length || chunks[0].type !== 'IHDR') return blob;

    const phys = new Uint8Array(21);
    const pv = new DataView(phys.buffer);

    pv.setUint32(0, 9);
    phys.set([0x70, 0x48, 0x59, 0x73], 4); // "pHYs"
    pv.setUint32(8, pixelsPerMeter);
    pv.setUint32(12, pixelsPerMeter);
    phys[16] = 1; // satuan: meter
    pv.setUint32(17, crc32(phys.subarray(4, 17)));

    return new Blob([bytes.subarray(0, 8), chunks[0].data, phys, ...chunks.slice(1).map((chunk) => chunk.data)], {
        type: 'image/png',
    });
}