/**
 * Menggulir halus ke bagian tertentu dan memperbarui hash URL tanpa memicu router.
 * Offset navbar sticky ditangani oleh CSS (.section { scroll-margin-top }).
 */
export function scrollToSection(id) {
    if (id === 'beranda') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    const base = `${window.location.pathname}${window.location.search}`;

    try {
        window.history.replaceState(window.history.state, '', id === 'beranda' ? base : `${base}#${id}`);
    } catch {
        // Pembaruan URL hanya kosmetik.
    }
}