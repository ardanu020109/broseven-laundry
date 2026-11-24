import { reactive } from 'vue';

/** Menampung pesan validasi 422 dari Laravel: errors['nama_field'] => 'pesan'. */
export function useFormErrors() {
    const errors = reactive({});

    function clear() {
        Object.keys(errors).forEach((key) => delete errors[key]);
    }

    /** Mengembalikan true jika error berupa validasi (422) dan sudah ditampung. */
    function setFrom(error) {
        clear();

        const details = error?.response?.data?.errors;

        if (error?.response?.status === 422 && details) {
            Object.entries(details).forEach(([key, messages]) => {
                errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
            });
            return true;
        }

        return false;
    }

    return { errors, clear, setFrom };
}