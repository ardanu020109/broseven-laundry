import { onBeforeUnmount, onMounted } from 'vue';

/** Menutup dropdown saat klik di luar elemen atau menekan Escape. */
export function useDismiss(elementRef, isOpen, close) {
    function onPointer(event) {
        if (isOpen.value && elementRef.value && !elementRef.value.contains(event.target)) close();
    }

    function onKey(event) {
        if (isOpen.value && event.key === 'Escape') close();
    }

    onMounted(() => {
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('pointerdown', onPointer);
        document.removeEventListener('keydown', onKey);
    });
}