import Swal, { SweetAlertIcon, SweetAlertResult } from 'sweetalert2';

export interface NotificationOptions {
    title?: string;
    text?: string;
    html?: string;
    timer?: number;
    showConfirmButton?: boolean;
    position?: 'top' | 'top-start' | 'top-end' | 'center' | 'center-start' | 'center-end' | 'bottom' | 'bottom-start' | 'bottom-end';
}

export interface ConfirmOptions {
    title?: string;
    text?: string;
    html?: string;
    confirmButtonText?: string;
    cancelButtonText?: string;
    icon?: SweetAlertIcon;
    reverseButtons?: boolean;
}

const defaultToastOptions = {
    toast: true,
    position: 'top-end' as const,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    padding: '10px 20px',
};

export function useNotification() {
    const success = (message: string, options: NotificationOptions = {}): Promise<SweetAlertResult> => {
        return Swal.fire({
            ...defaultToastOptions,
            icon: 'success',
            title: message,
            ...options,
        });
    };

    const error = (message: string, options: NotificationOptions = {}): Promise<SweetAlertResult> => {
        return Swal.fire({
            ...defaultToastOptions,
            icon: 'error',
            title: message,
            timer: 5000, // Longer for errors
            ...options,
        });
    };

    const warning = (message: string, options: NotificationOptions = {}): Promise<SweetAlertResult> => {
        return Swal.fire({
            ...defaultToastOptions,
            icon: 'warning',
            title: message,
            ...options,
        });
    };

    const info = (message: string, options: NotificationOptions = {}): Promise<SweetAlertResult> => {
        return Swal.fire({
            ...defaultToastOptions,
            icon: 'info',
            title: message,
            ...options,
        });
    };

    const confirm = async (options: ConfirmOptions = {}): Promise<boolean> => {
        const result = await Swal.fire({
            title: options.title || 'Are you sure?',
            text: options.text,
            html: options.html,
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: options.confirmButtonText || 'Yes',
            cancelButtonText: options.cancelButtonText || 'Cancel',
            reverseButtons: options.reverseButtons ?? true,
            padding: '2em',
        });

        return result.isConfirmed;
    };

    const confirmDelete = async (itemName: string = 'this item'): Promise<boolean> => {
        return confirm({
            title: 'Delete Confirmation',
            text: `Are you sure you want to delete ${itemName}? This action cannot be undone.`,
            icon: 'warning',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
        });
    };

    const showValidationErrors = (errors: Record<string, string[]>): void => {
        const errorMessages = Object.values(errors).flat().join('<br>');
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: errorMessages,
            padding: '2em',
        });
    };

    const showApiError = (error: any, defaultMessage: string = 'An error occurred'): void => {
        let message = defaultMessage;

        if (error.response?.data?.message) {
            message = error.response.data.message;
        } else if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            message = Object.values(errors).flat().join('<br>');
        } else if (error.message) {
            message = error.message;
        }

        Swal.fire({
            ...defaultToastOptions,
            icon: 'error',
            title: 'Error',
            html: message,
            timer: 5000,
        });
    };

    return {
        success,
        error,
        warning,
        info,
        confirm,
        confirmDelete,
        showValidationErrors,
        showApiError,
    };
}

export default useNotification;
