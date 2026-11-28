import assert from 'node:assert/strict';
import { isoToDisplay, maskDigits, parseDisplay } from '../resources/js/composables/dateInput.js';

// Topeng
assert.equal(maskDigits('24112026'), '24/11/2026');
assert.equal(maskDigits('2411'), '24/11');
assert.equal(maskDigits('24'), '24');
assert.equal(maskDigits('24a1b1c2026'), '24/11/2026');
assert.equal(maskDigits('241120261030', true), '24/11/2026 10:30');
assert.equal(maskDigits('2026-11-24'), '24/11/2026');            // hasil paste ISO
assert.equal(maskDigits('2026-11-24T10:30', true), '24/11/2026 10:30');
assert.equal(maskDigits('999999999999999'), '99/99/9999');

// Tanggal valid
assert.equal(parseDisplay('24/11/2026'), '2026-11-24');
assert.equal(parseDisplay('29/02/2028'), '2028-02-29');          // tahun kabisat
assert.equal(parseDisplay('24/11/2026 10:30', true), '2026-11-24T10:30');
assert.equal(parseDisplay('01/01/2026 00:00', true), '2026-01-01T00:00');

// Tanggal tidak valid
for (const bad of ['31/02/2026', '29/02/2026', '32/01/2026', '10/13/2026', '00/01/2026', '24/11/26', '24/11', '', null]) {
    assert.equal(parseDisplay(bad), null, `seharusnya tidak valid: ${bad}`);
}
for (const bad of ['24/11/2026 24:00', '24/11/2026 10:60', '24/11/2026']) {
    assert.equal(parseDisplay(bad, true), null, `seharusnya tidak valid: ${bad}`);
}

// ISO ke tampilan
assert.equal(isoToDisplay('2026-11-24'), '24/11/2026');
assert.equal(isoToDisplay('2026-11-24T10:30', true), '24/11/2026 10:30');
assert.equal(isoToDisplay('2026-11-24', true), '24/11/2026 00:00');
assert.equal(isoToDisplay(''), '');
assert.equal(isoToDisplay('bukan-tanggal'), '');

console.log('Semua pemeriksaan format tanggal berhasil.');