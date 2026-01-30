<template>
    <div class="flex min-h-screen items-center justify-center bg-[url('/assets/images/map.svg')] bg-cover bg-center dark:bg-[url('/assets/images/map-dark.svg')]">
        <div class="panel m-6 w-full max-w-lg sm:w-[480px]">
            <div class="flex flex-col items-center justify-center">
                <!-- Email Icon -->
                <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                        <rect width="20" height="16" x="2" y="4" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                </div>

                <h2 class="mb-2 text-2xl font-bold">Verify Your Email</h2>
                <p class="mb-6 text-center text-gray-500 dark:text-gray-400">
                    We've sent a verification link to <strong>{{ authStore.user?.email }}</strong>.
                    Please check your inbox and click the link to verify your email address.
                </p>

                <!-- Success Message -->
                <div v-if="successMessage" class="mb-4 w-full rounded-lg bg-success/10 p-4 text-center text-success">
                    {{ successMessage }}
                </div>

                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 w-full rounded-lg bg-danger/10 p-4 text-center text-danger">
                    {{ errorMessage }}
                </div>

                <!-- Resend Button -->
                <button
                    type="button"
                    class="btn btn-primary w-full"
                    :disabled="isLoading || countdown > 0"
                    @click="resendVerification"
                >
                    <span v-if="isLoading" class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 ltr:mr-2 rtl:ml-2 inline-block align-middle"></span>
                    <span v-if="countdown > 0">
                        Resend in {{ countdown }}s
                    </span>
                    <span v-else>
                        Resend Verification Email
                    </span>
                </button>

                <div class="mt-6 flex w-full flex-col gap-3">
                    <!-- Check Status Button -->
                    <button
                        type="button"
                        class="btn btn-outline-primary w-full"
                        :disabled="isChecking"
                        @click="checkStatus"
                    >
                        <span v-if="isChecking" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-5 h-5 ltr:mr-2 rtl:ml-2 inline-block align-middle"></span>
                        I've Verified My Email
                    </button>

                    <!-- Logout Button -->
                    <button
                        type="button"
                        class="btn btn-outline-dark w-full"
                        @click="logout"
                    >
                        Sign Out
                    </button>
                </div>

                <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Didn't receive the email? Check your spam folder or
                    <a href="#" class="text-primary hover:underline" @click.prevent="resendVerification">click here to resend</a>.
                </p>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotification } from '@/composables/useNotification';

const router = useRouter();
const authStore = useAuthStore();
const notification = useNotification();

const isLoading = ref(false);
const isChecking = ref(false);
const countdown = ref(0);
const successMessage = ref('');
const errorMessage = ref('');

let countdownInterval: ReturnType<typeof setInterval> | null = null;

const startCountdown = (seconds: number = 60) => {
    countdown.value = seconds;
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(countdownInterval!);
            countdownInterval = null;
        }
    }, 1000);
};

const resendVerification = async () => {
    if (countdown.value > 0) return;

    isLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await authStore.sendVerificationEmail();
        successMessage.value = 'Verification email sent! Please check your inbox.';
        startCountdown(60);
    } catch (error: any) {
        if (error.response?.status === 429) {
            errorMessage.value = 'Too many requests. Please wait before trying again.';
            startCountdown(60);
        } else if (error.response?.status === 400) {
            // Email already verified
            successMessage.value = 'Your email is already verified!';
            setTimeout(() => {
                router.push({ name: 'home' });
            }, 2000);
        } else {
            errorMessage.value = error.response?.data?.message || 'Failed to send verification email.';
        }
    } finally {
        isLoading.value = false;
    }
};

const checkStatus = async () => {
    isChecking.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await authStore.checkVerificationStatus();
        if (response.verified) {
            notification.success('Email verified successfully!');
            router.push({ name: 'home' });
        } else {
            errorMessage.value = 'Your email is not yet verified. Please click the link in the email we sent you.';
        }
    } catch (error: any) {
        errorMessage.value = 'Could not check verification status. Please try again.';
    } finally {
        isChecking.value = false;
    }
};

const logout = async () => {
    await authStore.logout();
    router.push({ name: 'boxed-signin' });
};

onMounted(() => {
    // Check if user is already verified
    if (authStore.isEmailVerified) {
        router.push({ name: 'home' });
    }
});

onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});
</script>
