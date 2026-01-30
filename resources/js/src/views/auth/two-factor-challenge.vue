<template>
    <div>
        <div class="absolute inset-0">
            <img src="/assets/images/auth/bg-gradient.png" alt="background" class="h-full w-full object-cover" />
        </div>

        <div
            class="relative flex min-h-screen items-center justify-center bg-[url(/assets/images/auth/map.png)] bg-cover bg-center bg-no-repeat px-6 py-10 dark:bg-[#060818] sm:px-16"
        >
            <img src="/assets/images/auth/coming-soon-object1.png" alt="deco" class="absolute left-0 top-1/2 h-full max-h-[893px] -translate-y-1/2" />
            <img src="/assets/images/auth/coming-soon-object3.png" alt="deco" class="absolute right-0 top-0 h-[300px]" />
            <img src="/assets/images/auth/polygon-object.svg" alt="deco" class="absolute bottom-0 end-[28%]" />
            <div
                class="relative w-full max-w-[870px] rounded-md bg-[linear-gradient(45deg,#fff9f9_0%,rgba(255,255,255,0)_25%,rgba(255,255,255,0)_75%,_#fff9f9_100%)] p-2 dark:bg-[linear-gradient(52.22deg,#0E1726_0%,rgba(14,23,38,0)_18.66%,rgba(14,23,38,0)_51.04%,rgba(14,23,38,0)_80.07%,#0E1726_100%)]"
            >
                <div class="relative flex flex-col justify-center rounded-md bg-white/60 backdrop-blur-lg dark:bg-black/50 px-6 lg:min-h-[600px] py-20">
                    <div class="mx-auto w-full max-w-[460px]">
                        <!-- Icon -->
                        <div class="mx-auto mb-7 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                            <icon-lock-dots class="h-8 w-8 text-primary" />
                        </div>

                        <!-- Title -->
                        <h2 class="mb-2 text-center text-2xl font-bold text-black dark:text-white">Two-Factor Authentication</h2>
                        <p class="mb-7 text-center text-base text-white-dark">
                            {{ useRecoveryCode ? 'Enter one of your recovery codes' : 'Enter the 6-digit code from your authenticator app' }}
                        </p>

                        <!-- Error Message -->
                        <div v-if="errorMessage" class="mb-5 flex items-center rounded bg-danger-light p-3.5 text-danger dark:bg-danger-dark-light">
                            <span class="ltr:pr-2 rtl:pl-2">
                                <icon-x class="w-5 h-5" />
                            </span>
                            <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                            <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" @click="errorMessage = ''">
                                <icon-x class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- TOTP Code Form (default) -->
                        <form v-if="!useRecoveryCode" @submit.prevent="handleSubmit" class="space-y-5 dark:text-white">
                            <div>
                                <label for="code" class="text-sm font-semibold dark:text-white-light">Authentication Code</label>
                                <div class="relative text-white-dark">
                                    <input
                                        id="code"
                                        v-model="code"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        maxlength="6"
                                        autocomplete="one-time-code"
                                        class="form-input ps-10 placeholder:text-white-dark text-center text-2xl tracking-[0.5em] font-mono"
                                        placeholder="000000"
                                        autofocus
                                        @input="handleCodeInput"
                                    />
                                    <span class="absolute start-4 top-1/2 -translate-y-1/2">
                                        <icon-lock-dots class="h-5 w-5 text-gray-500" />
                                    </span>
                                </div>
                            </div>
                            <button
                                type="submit"
                                class="btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]"
                                :disabled="isSubmitting || code.length !== 6"
                            >
                                <span v-if="!isSubmitting">Verify</span>
                                <span v-else class="flex items-center justify-center gap-2">
                                    <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block"></span>
                                    Verifying...
                                </span>
                            </button>
                        </form>

                        <!-- Recovery Code Form -->
                        <form v-else @submit.prevent="handleRecoverySubmit" class="space-y-5 dark:text-white">
                            <div>
                                <label for="recovery_code" class="text-sm font-semibold dark:text-white-light">Recovery Code</label>
                                <div class="relative text-white-dark">
                                    <input
                                        id="recovery_code"
                                        v-model="recoveryCode"
                                        type="text"
                                        class="form-input ps-10 placeholder:text-white-dark font-mono"
                                        placeholder="Enter recovery code"
                                        autofocus
                                    />
                                    <span class="absolute start-4 top-1/2 -translate-y-1/2">
                                        <icon-phone class="h-5 w-5 text-gray-500" />
                                    </span>
                                </div>
                            </div>
                            <button
                                type="submit"
                                class="btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]"
                                :disabled="isSubmitting || !recoveryCode.trim()"
                            >
                                <span v-if="!isSubmitting">Verify Recovery Code</span>
                                <span v-else class="flex items-center justify-center gap-2">
                                    <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block"></span>
                                    Verifying...
                                </span>
                            </button>
                        </form>

                        <!-- Toggle link -->
                        <div class="mt-5 text-center">
                            <button type="button" class="text-primary hover:underline text-sm font-semibold" @click="toggleMode">
                                {{ useRecoveryCode ? 'Use authenticator code instead' : 'Use a recovery code' }}
                            </button>
                        </div>

                        <!-- Back to login -->
                        <div class="mt-3 text-center">
                            <button type="button" class="text-white-dark hover:text-primary text-sm font-semibold" @click="backToLogin">
                                Back to login
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMeta } from '@/composables/use-meta';

// Icons
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconPhone from '@/components/icon/icon-phone.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Two-Factor Authentication' });

const router = useRouter();
const authStore = useAuthStore();

const code = ref('');
const recoveryCode = ref('');
const useRecoveryCode = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref('');

const handleCodeInput = () => {
    // Only allow digits
    code.value = code.value.replace(/\D/g, '');
    // Auto-submit when 6 digits entered
    if (code.value.length === 6) {
        handleSubmit();
    }
};

const handleSubmit = async () => {
    if (code.value.length !== 6 || isSubmitting.value) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        await authStore.verifyTwoFactor({ code: code.value });
        router.push('/');
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Invalid authentication code. Please try again.';
        code.value = '';
    } finally {
        isSubmitting.value = false;
    }
};

const handleRecoverySubmit = async () => {
    if (!recoveryCode.value.trim() || isSubmitting.value) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        await authStore.verifyTwoFactor({ recovery_code: recoveryCode.value.trim() });
        router.push('/');
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Invalid recovery code. Please try again.';
        recoveryCode.value = '';
    } finally {
        isSubmitting.value = false;
    }
};

const toggleMode = () => {
    useRecoveryCode.value = !useRecoveryCode.value;
    errorMessage.value = '';
    code.value = '';
    recoveryCode.value = '';
};

const backToLogin = () => {
    authStore.cancelTwoFactor();
    router.push({ name: 'boxed-signin' });
};
</script>
