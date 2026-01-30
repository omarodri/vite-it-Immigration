<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-[#fef3f3] via-white to-[#fef5f5] dark:from-[#060818] dark:via-black dark:to-[#0e1726]">
        <div class="p-5 text-center font-semibold">
            <div class="flex items-center justify-center">
                <div class="relative">
                    <div class="flex h-32 w-32 items-center justify-center rounded-full bg-danger/20">
                        <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-danger">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <h2 class="mt-8 text-4xl font-bold text-danger md:text-6xl">403</h2>
            <h4 class="mt-4 text-xl font-semibold sm:text-3xl">Access Forbidden</h4>
            <p class="mx-auto mt-4 max-w-md text-base text-gray-500 dark:text-gray-400">
                You don't have permission to access this page. If you believe this is an error, please contact your administrator.
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <router-link to="/" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ltr:mr-2 rtl:ml-2">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Go to Dashboard
                </router-link>
                <button type="button" class="btn btn-outline-dark" @click="goBack">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ltr:mr-2 rtl:ml-2">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Go Back
                </button>
            </div>

            <div class="mt-12 text-sm text-gray-400">
                <p>Error Code: PERMISSION_DENIED</p>
                <p v-if="requiredPermission" class="mt-1">
                    Required: <code class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ requiredPermission }}</code>
                </p>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const requiredPermission = computed(() => route.query.permission as string || null);

const goBack = () => {
    if (window.history.length > 2) {
        router.back();
    } else {
        router.push({ name: 'home' });
    }
};
</script>
