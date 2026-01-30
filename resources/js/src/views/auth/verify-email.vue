<template>
    <div class="flex min-h-screen items-center justify-center bg-[url('/assets/images/map.svg')] bg-cover bg-center dark:bg-[url('/assets/images/map-dark.svg')]">
        <div class="panel m-6 w-full max-w-lg sm:w-[480px]">
            <div class="flex flex-col items-center justify-center">
                <!-- Loading State -->
                <template v-if="isLoading">
                    <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
                        <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-12 h-12"></span>
                    </div>
                    <h2 class="mb-2 text-2xl font-bold">Verifying Email</h2>
                    <p class="text-center text-gray-500 dark:text-gray-400">
                        Please wait while we verify your email address...
                    </p>
                </template>

                <!-- Success State -->
                <template v-else-if="isVerified">
                    <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-success/10">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <h2 class="mb-2 text-2xl font-bold text-success">Email Verified!</h2>
                    <p class="mb-6 text-center text-gray-500 dark:text-gray-400">
                        Your email has been successfully verified. You can now access all features of your account.
                    </p>
                    <button
                        type="button"
                        class="btn btn-success w-full"
                        @click="goToDashboard"
                    >
                        Go to Dashboard
                    </button>
                </template>

                <!-- Error State -->
                <template v-else>
                    <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-danger/10">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-danger">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                    </div>
                    <h2 class="mb-2 text-2xl font-bold text-danger">Verification Failed</h2>
                    <p class="mb-6 text-center text-gray-500 dark:text-gray-400">
                        {{ errorMessage }}
                    </p>
                    <div class="flex w-full flex-col gap-3">
                        <button
                            type="button"
                            class="btn btn-primary w-full"
                            @click="requestNewLink"
                        >
                            Request New Verification Link
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-dark w-full"
                            @click="goToLogin"
                        >
                            Back to Login
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const isLoading = ref(true);
const isVerified = ref(false);
const errorMessage = ref('');

const verifyEmail = async () => {
    // Get parameters from URL
    const id = route.query.id as string;
    const hash = route.query.hash as string;
    const expires = route.query.expires as string;
    const signature = route.query.signature as string;

    // Validate required parameters
    if (!id || !hash || !expires || !signature) {
        isLoading.value = false;
        errorMessage.value = 'Invalid verification link. Please request a new one.';
        return;
    }

    try {
        const response = await authStore.verifyEmail({ id, hash, expires, signature });
        isVerified.value = response.verified;

        if (!response.verified) {
            errorMessage.value = response.message || 'Could not verify email. Please try again.';
        }
    } catch (error: any) {
        if (error.response?.status === 403) {
            errorMessage.value = 'This verification link is invalid or has expired. Please request a new one.';
        } else {
            errorMessage.value = error.response?.data?.message || 'An error occurred while verifying your email.';
        }
    } finally {
        isLoading.value = false;
    }
};

const goToDashboard = () => {
    router.push({ name: 'home' });
};

const goToLogin = () => {
    router.push({ name: 'boxed-signin' });
};

const requestNewLink = () => {
    if (authStore.isAuthenticated) {
        router.push({ name: 'email-verification-notice' });
    } else {
        router.push({ name: 'boxed-signin' });
    }
};

onMounted(() => {
    verifyEmail();
});
</script>
