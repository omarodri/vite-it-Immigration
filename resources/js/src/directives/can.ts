import { Directive, DirectiveBinding } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * v-can directive for permission-based element visibility
 *
 * Usage:
 * - v-can="'users.view'" - Show element if user has permission
 * - v-can="['users.view', 'users.create']" - Show if user has ANY of these permissions
 * - v-can.all="['users.view', 'users.create']" - Show if user has ALL permissions
 * - v-can.hide="'users.view'" - Hide instead of remove from DOM
 */
export const vCan: Directive = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        updateVisibility(el, binding);
    },
    updated(el: HTMLElement, binding: DirectiveBinding) {
        updateVisibility(el, binding);
    },
};

function updateVisibility(el: HTMLElement, binding: DirectiveBinding): void {
    const authStore = useAuthStore();
    const permissions = binding.value;
    const modifiers = binding.modifiers;

    let hasPermission = false;

    if (Array.isArray(permissions)) {
        if (modifiers.all) {
            // User must have ALL permissions
            hasPermission = authStore.hasAllPermissions(permissions);
        } else {
            // User must have ANY of the permissions
            hasPermission = authStore.hasAnyPermission(permissions);
        }
    } else if (typeof permissions === 'string') {
        hasPermission = authStore.hasPermission(permissions);
    }

    if (!hasPermission) {
        if (modifiers.hide) {
            // Hide the element but keep in DOM
            el.style.display = 'none';
        } else {
            // Remove from DOM
            const comment = document.createComment(' v-can ');
            el.parentNode?.replaceChild(comment, el);
            // Store original element for potential re-insertion
            (comment as any).__vCanElement = el;
        }
    } else {
        // Show the element
        if (modifiers.hide) {
            el.style.display = '';
        }
    }
}

export default vCan;
