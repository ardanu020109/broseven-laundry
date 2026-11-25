const LETTERS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; // tanpa huruf yang mirip (i, l, o)
const DIGITS = '23456789'; // tanpa 0 dan 1

/** Angka acak 0..max-1 dari sumber acak browser (tanpa bias modulo). */
function randomInt(max) {
    const buffer = new Uint32Array(1);
    const limit = Math.floor(0x100000000 / max) * max;

    do {
        crypto.getRandomValues(buffer);
    } while (buffer[0] >= limit);

    return buffer[0] % max;
}

function pick(chars) {
    return chars[randomInt(chars.length)];
}

/** Password acak berisi huruf dan angka (memenuhi aturan server). */
export function generatePassword(length = 12) {
    const all = LETTERS + DIGITS;
    const chars = [pick(LETTERS), pick(DIGITS)];

    while (chars.length < length) chars.push(pick(all));

    for (let index = chars.length - 1; index > 0; index--) {
        const swap = randomInt(index + 1);
        [chars[index], chars[swap]] = [chars[swap], chars[index]];
    }

    return chars.join('');
}