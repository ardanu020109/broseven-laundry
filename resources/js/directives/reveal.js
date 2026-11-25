/**
 * v-reveal: elemen muncul dengan transisi halus saat masuk ke layar.
 * Nilai opsional = jeda dalam milidetik, mis. v-reveal="index * 70".
 * Jangan dipasang pada elemen yang memakai :class dinamis (kelas bisa tertimpa);
 * bungkus dengan <div v-reveal> terpisah.
 */
let shared = null;

function observer() {
    if (shared || typeof IntersectionObserver === 'undefined') return shared;

    shared = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;

                entry.target.classList.add('reveal--in');
                shared.unobserve(entry.target);
            }
        },
        { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
    );

    return shared;
}

export const vReveal = {
    mounted(el, binding) {
        el.classList.add('reveal');

        const delay = Math.min(Number(binding.value) || 0, 600);
        if (delay > 0) el.style.transitionDelay = `${delay}ms`;

        const io = observer();

        if (!io) {
            el.classList.add('reveal--in');
            return;
        }

        io.observe(el);
    },

    unmounted(el) {
        shared?.unobserve(el);
    },
};