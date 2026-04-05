/**
 * useFormValidation
 *
 * Composable for standardized Vuelidate-based form validation helpers.
 *
 * Usage:
 *   const { inputClass, errorMsg, setApiErrors, clearApiErrors } = useFormValidation(v$);
 *
 * Template:
 *   <input :class="inputClass('field_name')" ... />
 *   <span v-if="v$.field_name.$error" class="text-danger text-xs mt-1">{{ errorMsg('field_name') }}</span>
 */

import { ref } from 'vue';

export function useFormValidation(v$: any) {
    /**
     * External (API 422) errors — keyed by field name.
     */
    const apiErrors = ref<Record<string, string[]>>({});

    /**
     * Returns input CSS classes including error state.
     * Adds `!border-danger` when the field has a Vuelidate or API error.
     */
    const inputClass = (field: string, extraClasses = 'form-input'): string => {
        const hasVuelidateError = v$?.[field]?.$error === true;
        const hasApiError = !!(apiErrors.value[field]?.length);
        if (hasVuelidateError || hasApiError) {
            return `${extraClasses} !border-danger`;
        }
        return extraClasses;
    };

    /**
     * Returns the first error message for a field.
     * Prefers Vuelidate errors, falls back to API errors.
     */
    const errorMsg = (field: string): string => {
        const vErrors = v$?.[field]?.$errors;
        if (vErrors?.length) {
            return String(vErrors[0]?.$message ?? '');
        }
        const aErrors = apiErrors.value[field];
        if (aErrors?.length) {
            return aErrors[0];
        }
        return '';
    };

    /**
     * Populate apiErrors from a 422 response's errors object.
     * Call this in the catch block of your API request.
     *
     * Example:
     *   } catch (err: any) {
     *       if (err?.response?.status === 422) {
     *           setApiErrors(err.response.data.errors);
     *       }
     *   }
     */
    const setApiErrors = (errors: Record<string, string[]>): void => {
        apiErrors.value = errors ?? {};
    };

    /**
     * Clear all API errors (e.g. on new form submission).
     */
    const clearApiErrors = (): void => {
        apiErrors.value = {};
    };

    /**
     * Returns true if either Vuelidate or API shows an error for the field.
     */
    const hasError = (field: string): boolean => {
        return v$?.[field]?.$error === true || !!(apiErrors.value[field]?.length);
    };

    return {
        apiErrors,
        inputClass,
        errorMsg,
        setApiErrors,
        clearApiErrors,
        hasError,
    };
}
