import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import { useAppStore } from '@/stores/index';
import { useAuthStore } from '@/stores/auth';
import appSetting from '@/app-setting';

import HomeView from '../views/index.vue';

// Public routes that don't require authentication
const publicRoutes = [
    'boxed-signin',
    'boxed-signup',
    'cover-login',
    'cover-register',
    'boxed-password-reset',
    'cover-password-reset',
    'reset-password',
    'boxed-lockscreen',
    'cover-lockscreen',
    'error404',
    'error500',
    'error503',
    'maintenence',
    'coming-soon-boxed',
    'coming-soon-cover',
];

const routes: RouteRecordRaw[] = [
    // dashboard
    { path: '/', name: 'home', component: HomeView },
    {
        path: '/analytics',
        name: 'analytics',
        component: () => import('../views/analytics.vue'),
    },
    {
        path: '/finance',
        name: 'finance',
        component: () => import('../views/finance.vue'),
    },
    {
        path: '/crypto',
        name: 'crypto',
        component: () => import('../views/crypto.vue'),
    },

    // apps
    {
        path: '/apps/chat',
        name: 'chat',
        component: () => import('../views/apps/chat.vue'),
    },
    {
        path: '/apps/mailbox',
        name: 'mailbox',
        component: () => import('../views/apps/mailbox.vue'),
    },
    {
        path: '/apps/todolist',
        name: 'todolist',
        component: () => import('../views/apps/todolist.vue'),
    },
    {
        path: '/apps/notes',
        name: 'notes',
        component: () => import('../views/apps/notes.vue'),
    },
    {
        path: '/apps/scrumboard',
        name: 'scrumboard',
        component: () => import('../views/apps/scrumboard.vue'),
    },
    {
        path: '/apps/contacts',
        name: 'contacts',
        component: () => import('../views/apps/contacts.vue'),
    },
    // invoice
    {
        path: '/apps/invoice/list',
        name: 'invoice-list',
        component: () => import('../views/apps/invoice/list.vue'),
    },
    {
        path: '/apps/invoice/preview',
        name: 'invoice-preview',
        component: () => import('../views/apps/invoice/preview.vue'),
    },
    {
        path: '/apps/invoice/add',
        name: 'invoice-add',
        component: () => import('../views/apps/invoice/add.vue'),
    },
    {
        path: '/apps/invoice/edit',
        name: 'invoice-edit',
        component: () => import('../views/apps/invoice/edit.vue'),
    },
    {
        path: '/apps/calendar',
        name: 'calendar',
        component: () => import('../views/apps/calendar.vue'),
    },

    // components
    {
        path: '/components/tabs',
        name: 'tabs',
        component: () => import('../views/components/tabs.vue'),
    },
    {
        path: '/components/accordions',
        name: 'accordions',
        component: () => import('../views/components/accordions.vue'),
    },
    {
        path: '/components/modals',
        name: 'modals',
        component: () => import('../views/components/modals.vue'),
    },
    {
        path: '/components/cards',
        name: 'cards',
        component: () => import('../views/components/cards.vue'),
    },
    {
        path: '/components/carousel',
        name: 'carousel',
        component: () => import('../views/components/carousel.vue'),
    },
    {
        path: '/components/countdown',
        name: 'countdown',
        component: () => import('../views/components/countdown.vue'),
    },
    {
        path: '/components/counter',
        name: 'counter',
        component: () => import('../views/components/counter.vue'),
    },
    {
        path: '/components/sweetalert',
        name: 'sweetalert',
        component: () => import('../views/components/sweetalert.vue'),
    },
    {
        path: '/components/timeline',
        name: 'timeline',
        component: () => import('../views/components/timeline.vue'),
    },
    {
        path: '/components/notifications',
        name: 'notifications',
        component: () => import('../views/components/notifications.vue'),
    },
    {
        path: '/components/media-object',
        name: 'media-object',
        component: () => import('../views/components/media-object.vue'),
    },
    {
        path: '/components/list-group',
        name: 'list-group',
        component: () => import('../views/components/list-group.vue'),
    },
    {
        path: '/components/pricing-table',
        name: 'pricing-table',
        component: () => import('../views/components/pricing-table.vue'),
    },
    {
        path: '/components/lightbox',
        name: 'lightbox',
        component: () => import('../views/components/lightbox.vue'),
    },

    //elements
    {
        path: '/elements/alerts',
        name: 'alerts',
        component: () => import('../views/elements/alerts.vue'),
    },
    {
        path: '/elements/avatar',
        name: 'avatar',
        component: () => import('../views/elements/avatar.vue'),
    },
    {
        path: '/elements/badges',
        name: 'badges',
        component: () => import('../views/elements/badges.vue'),
    },
    {
        path: '/elements/breadcrumbs',
        name: 'breadcrumbs',
        component: () => import('../views/elements/breadcrumbs.vue'),
    },
    {
        path: '/elements/buttons',
        name: 'buttons',
        component: () => import('../views/elements/buttons.vue'),
    },
    {
        path: '/elements/buttons-group',
        name: 'buttons-group',
        component: () => import('../views/elements/buttons-group.vue'),
    },
    {
        path: '/elements/color-library',
        name: 'color-library',
        component: () => import('../views/elements/color-library.vue'),
    },
    {
        path: '/elements/dropdown',
        name: 'dropdown',
        component: () => import('../views/elements/dropdown.vue'),
    },
    {
        path: '/elements/infobox',
        name: 'infobox',
        component: () => import('../views/elements/infobox.vue'),
    },
    {
        path: '/elements/jumbotron',
        name: 'jumbotron',
        component: () => import('../views/elements/jumbotron.vue'),
    },
    {
        path: '/elements/loader',
        name: 'loader',
        component: () => import('../views/elements/loader.vue'),
    },
    {
        path: '/elements/pagination',
        name: 'pagination',
        component: () => import('../views/elements/pagination.vue'),
    },
    {
        path: '/elements/popovers',
        name: 'popovers',
        component: () => import('../views/elements/popovers.vue'),
    },
    {
        path: '/elements/progress-bar',
        name: 'progress-bar',
        component: () => import('../views/elements/progress-bar.vue'),
    },
    {
        path: '/elements/search',
        name: 'search',
        component: () => import('../views/elements/search.vue'),
    },
    {
        path: '/elements/tooltips',
        name: 'tooltips',
        component: () => import('../views/elements/tooltips.vue'),
    },
    {
        path: '/elements/treeview',
        name: 'treeview',
        component: () => import('../views/elements/treeview.vue'),
    },
    {
        path: '/elements/typography',
        name: 'typography',
        component: () => import('../views/elements/typography.vue'),
    },

    //charts
    {
        path: '/charts',
        name: 'charts',
        component: () => import('../views/charts.vue'),
    },

    //widgets
    {
        path: '/widgets',
        name: 'widgets',
        component: () => import('../views/widgets.vue'),
    },

    //font-icons
    {
        path: '/font-icons',
        name: 'font-icons',
        component: () => import('../views/font-icons.vue'),
    },

    //dragndrop
    {
        path: '/dragndrop',
        name: 'dragndrop',
        component: () => import('../views/dragndrop.vue'),
    },

    //tables
    {
        path: '/tables',
        name: 'tables',
        component: () => import('../views/tables.vue'),
    },

    //datatables
    {
        path: '/datatables/basic',
        name: 'datatables-basic',
        component: () => import('../views/datatables/basic.vue'),
    },
    {
        path: '/datatables/advanced',
        name: 'datatables-advanced',
        component: () => import('../views/datatables/advanced.vue'),
    },
    {
        path: '/datatables/skin',
        name: 'skin',
        component: () => import('../views/datatables/skin.vue'),
    },
    {
        path: '/datatables/order-sorting',
        name: 'order-sorting',
        component: () => import('../views/datatables/order-sorting.vue'),
    },
    {
        path: '/datatables/columns-filter',
        name: 'columns-filter',
        component: () => import('../views/datatables/columns-filter.vue'),
    },
    {
        path: '/datatables/multi-column',
        name: 'multi-column',
        component: () => import('../views/datatables/multi-column.vue'),
    },
    {
        path: '/datatables/multiple-tables',
        name: 'multiple-tables',
        component: () => import('../views/datatables/multiple-tables.vue'),
    },
    {
        path: '/datatables/alt-pagination',
        name: 'alt-pagination',
        component: () => import('../views/datatables/alt-pagination.vue'),
    },
    {
        path: '/datatables/checkbox',
        name: 'checkbox',
        component: () => import('../views/datatables/checkbox.vue'),
    },
    {
        path: '/datatables/range-search',
        name: 'range-search',
        component: () => import('../views/datatables/range-search.vue'),
    },
    {
        path: '/datatables/export',
        name: 'export',
        component: () => import('../views/datatables/export.vue'),
    },
    {
        path: '/datatables/sticky-header',
        name: 'sticky-header',
        component: () => import('../views/datatables/sticky-header.vue'),
    },
    {
        path: '/datatables/clone-header',
        name: 'clone-header',
        component: () => import('../views/datatables/clone-header.vue'),
    },
    {
        path: '/datatables/column-chooser',
        name: 'column-chooser',
        component: () => import('../views/datatables/column-chooser.vue'),
    },

    //forms
    {
        path: '/forms/basic',
        name: 'basic',
        component: () => import('../views/forms/basic.vue'),
    },
    {
        path: '/forms/input-group',
        name: 'input-group',
        component: () => import('../views/forms/input-group.vue'),
    },
    {
        path: '/forms/layouts',
        name: 'layouts',
        component: () => import('../views/forms/layouts.vue'),
    },
    {
        path: '/forms/validation',
        name: 'validation',
        component: () => import('../views/forms/validation.vue'),
    },
    {
        path: '/forms/input-mask',
        name: 'input-mask',
        component: () => import('../views/forms/input-mask.vue'),
    },
    {
        path: '/forms/select2',
        name: 'select2',
        component: () => import('../views/forms/select2.vue'),
    },
    {
        path: '/forms/touchspin',
        name: 'touchspin',
        component: () => import('../views/forms/touchspin.vue'),
    },
    {
        path: '/forms/checkbox-radio',
        name: 'checkbox-radio',
        component: () => import('../views/forms/checkbox-radio.vue'),
    },
    {
        path: '/forms/switches',
        name: 'switches',
        component: () => import('../views/forms/switches.vue'),
    },
    {
        path: '/forms/wizards',
        name: 'wizards',
        component: () => import('../views/forms/wizards.vue'),
    },
    {
        path: '/forms/file-upload',
        name: 'file-upload',
        component: () => import('../views/forms/file-upload.vue'),
    },
    {
        path: '/forms/quill-editor',
        name: 'quill-editor',
        component: () => import('../views/forms/quill-editor.vue'),
    },
    {
        path: '/forms/markdown-editor',
        name: 'markdown-editor',
        component: () => import('../views/forms/markdown-editor.vue'),
    },
    {
        path: '/forms/date-picker',
        name: 'date-picker',
        component: () => import('../views/forms/date-picker.vue'),
    },
    {
        path: '/forms/clipboard',
        name: 'clipboard',
        component: () => import('../views/forms/clipboard.vue'),
    },

    // users
    {
        path: '/users/profile',
        name: 'profile',
        component: () => import('../views/users/profile.vue'),
    },
    {
        path: '/users/user-account-settings',
        name: 'user-account-settings',
        component: () => import('../views/users/user-account-settings.vue'),
    },

    // pages
    {
        path: '/pages/knowledge-base',
        name: 'knowledge-base',
        component: () => import('../views/pages/knowledge-base.vue'),
    },
    {
        path: '/pages/contact-us-boxed',
        name: 'contact-us-boxed',
        component: () => import('../views/pages/contact-us-boxed.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/contact-us-cover',
        name: 'contact-us-cover',
        component: () => import('../views/pages/contact-us-cover.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/faq',
        name: 'faq',
        component: () => import('../views/pages/faq.vue'),
    },
    {
        path: '/pages/coming-soon-boxed',
        name: 'coming-soon-boxed',
        component: () => import('../views/pages/coming-soon-boxed.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/coming-soon-cover',
        name: 'coming-soon-cover',
        component: () => import('../views/pages/coming-soon-cover.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/error404',
        name: 'error404',
        component: () => import('../views/pages/error404.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/error500',
        name: 'error500',
        component: () => import('../views/pages/error500.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/error503',
        name: 'error503',
        component: () => import('../views/pages/error503.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/maintenence',
        name: 'maintenence',
        component: () => import('../views/pages/maintenence.vue'),
        meta: { layout: 'auth' },
    },

    // authentication
    {
        path: '/auth/boxed-signin',
        name: 'boxed-signin',
        component: () => import('../views/auth/boxed-signin.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/boxed-signup',
        name: 'boxed-signup',
        component: () => import('../views/auth/boxed-signup.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/boxed-lockscreen',
        name: 'boxed-lockscreen',
        component: () => import('../views/auth/boxed-lockscreen.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/boxed-password-reset',
        name: 'boxed-password-reset',
        component: () => import('../views/auth/boxed-password-reset.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/cover-login',
        name: 'cover-login',
        component: () => import('../views/auth/cover-login.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/cover-register',
        name: 'cover-register',
        component: () => import('../views/auth/cover-register.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/cover-lockscreen',
        name: 'cover-lockscreen',
        component: () => import('../views/auth/cover-lockscreen.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/cover-password-reset',
        name: 'cover-password-reset',
        component: () => import('../views/auth/cover-password-reset.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/reset-password',
        name: 'reset-password',
        component: () => import('../views/auth/reset-password.vue'),
        meta: { layout: 'auth' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    linkExactActiveClass: 'active',
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { left: 0, top: 0 };
        }
    },
});

// Flag to track if we've checked auth on initial load
let authChecked = false;

router.beforeEach(async (to, from, next) => {
    const store = useAppStore();
    const authStore = useAuthStore();

    // Set layout based on route meta
    if (to?.meta?.layout == 'auth') {
        store.setMainLayout('auth');
    } else {
        store.setMainLayout('app');
    }

    // Check if route is public
    const isPublicRoute = publicRoutes.includes(to.name as string);

    // Try to fetch user on first navigation if not authenticated
    if (!authChecked && !authStore.isAuthenticated) {
        authChecked = true;
        try {
            await authStore.fetchUser();
        } catch (error) {
            // User is not authenticated, continue
        }
    }

    // Redirect logic
    if (!authStore.isAuthenticated && !isPublicRoute) {
        // Not authenticated, trying to access protected route
        next({ name: 'boxed-signin' });
    } else if (authStore.isAuthenticated && isPublicRoute && to.name !== 'error404' && to.name !== 'error500' && to.name !== 'error503') {
        // Authenticated user trying to access login/register pages
        next({ name: 'home' });
    } else {
        next();
    }
});

router.afterEach((to, from, next) => {
    appSetting.changeAnimation();
});

export default router;
