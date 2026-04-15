import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';

export function useModalGuard(
    formGetter: () => object,
    onClose: () => void,
) {
    const { t } = useI18n();
    const initialSnapshot = ref('');

    function captureSnapshot() {
        initialSnapshot.value = JSON.stringify(formGetter());
    }

    const isDirty = computed(
        () => JSON.stringify(formGetter()) !== initialSnapshot.value,
    );

    async function handleClose() {
        if (!isDirty.value) {
            onClose();
            return;
        }

        const result = await Swal.fire({
            title: t('modal_guard.unsaved_title'),
            text: t('modal_guard.unsaved_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e7515a',
            cancelButtonColor: '#805dca',
            confirmButtonText: t('modal_guard.discard'),
            cancelButtonText: t('modal_guard.keep_editing'),
            reverseButtons: true,
            padding: '2em',
        });

        if (result.isConfirmed) {
            onClose();
        }
    }

    function forceClose() {
        // Equalize snapshot so isDirty becomes false, then close without confirmation
        initialSnapshot.value = JSON.stringify(formGetter());
        onClose();
    }

    return { captureSnapshot, isDirty, handleClose, forceClose };
}
