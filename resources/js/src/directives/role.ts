import { Directive, DirectiveBinding } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * v-role directive for role-based element visibility
 *
 * Usage:
 * - v-role="'admin'" - Show element if user has role
 * - v-role="['admin', 'editor']" - Show if user has ANY of these roles
 * - v-role.all="['admin', 'editor']" - Show if user has ALL roles (rare use case)
 * - v-role.hide="'admin'" - Hide instead of remove from DOM
 */
export const vRole: Directive = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        updateVisibility(el, binding);
    },
    updated(el: HTMLElement, binding: DirectiveBinding) {
        updateVisibility(el, binding);
    },
};

function updateVisibility(el: HTMLElement, binding: DirectiveBinding): void {
    const authStore = useAuthStore();
    const roles = binding.value;
    const modifiers = binding.modifiers;

    let hasRole = false;

    if (Array.isArray(roles)) {
        if (modifiers.all) {
            // User must have ALL roles
            hasRole = roles.every(role => authStore.hasRole(role));
        } else {
            // User must have ANY of the roles
            hasRole = authStore.hasAnyRole(roles);
        }
    } else if (typeof roles === 'string') {
        hasRole = authStore.hasRole(roles);
    }

    if (!hasRole) {
        if (modifiers.hide) {
            // Hide the element but keep in DOM
            el.style.display = 'none';
        } else {
            // Remove from DOM
            const comment = document.createComment(' v-role ');
            el.parentNode?.replaceChild(comment, el);
            // Store original element for potential re-insertion
            (comment as any).__vRoleElement = el;
        }
    } else {
        // Show the element
        if (modifiers.hide) {
            el.style.display = '';
        }
    }
}

export default vRole;
