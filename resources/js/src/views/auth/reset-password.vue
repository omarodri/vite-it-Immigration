<template>
    <div>
        <div class="absolute inset-0">
            <img src="/assets/images/auth/bg-gradient.png" alt="image" class="h-full w-full object-cover" />
        </div>

        <div
            class="relative flex min-h-screen items-center justify-center bg-[url(/assets/images/auth/map.png)] bg-cover bg-center bg-no-repeat px-6 py-10 dark:bg-[#060818] sm:px-16"
        >
            <img src="/assets/images/auth/coming-soon-object1.png" alt="image" class="absolute left-0 top-1/2 h-full max-h-[893px] -translate-y-1/2" />
            <img src="/assets/images/auth/coming-soon-object2.png" alt="image" class="absolute left-24 top-0 h-40 md:left-[30%]" />
            <img src="/assets/images/auth/coming-soon-object3.png" alt="image" class="absolute right-0 top-0 h-[300px]" />
            <img src="/assets/images/auth/polygon-object.svg" alt="image" class="absolute bottom-0 end-[28%]" />
            <div
                class="relative w-full max-w-[870px] rounded-md bg-[linear-gradient(45deg,#fff9f9_0%,rgba(255,255,255,0)_25%,rgba(255,255,255,0)_75%,_#fff9f9_100%)] p-2 dark:bg-[linear-gradient(52.22deg,#0E1726_0%,rgba(14,23,38,0)_18.66%,rgba(14,23,38,0)_51.04%,rgba(14,23,38,0)_80.07%,#0E1726_100%)]"
            >
                <div class="relative flex flex-col justify-center rounded-md bg-white/60 backdrop-blur-lg dark:bg-black/50 px-6 lg:min-h-[758px] py-20">
                    <div class="absolute top-6 end-6">
                        <div class="dropdown">
                            <Popper :placement="store.rtlClass === 'rtl' ? 'bottom-start' : 'bottom-end'" offsetDistance="8">
                                <button
                                    type="button"
                                    class="flex items-center gap-2.5 rounded-lg border border-white-dark/30 bg-white px-2 py-1.5 text-white-dark hover:border-primary hover:text-primary dark:bg-black"
                                >
                                    <div>
                                        <img :src="currentFlag" alt="image" class="h-5 w-5 rounded-full object-cover" />
                                    </div>
                                    <div class="text-base font-bold uppercase">{{ store.locale }}</div>
                                    <span class="shrink-0">
                                        <icon-caret-down />
                                    </span>
                                </button>
                                <template #content="{ close }">
                                    <ul class="!px-2 text-dark dark:text-white-dark grid grid-cols-2 gap-2 font-semibold dark:text-white-light/90 w-[280px]">
                                        <template v-for="item in store.languageList" :key="item.code">
                                            <li>
                                                <button
                                                    type="button"
                                                    class="w-full hover:text-primary"
                                                    :class="{ 'bg-primary/10 text-primary': i18n.locale === item.code }"
                                                    @click="changeLanguage(item), close()"
                                                >
                                                    <img
                                                        class="w-5 h-5 object-cover rounded-full"
                                                        :src="`/assets/images/flags/${item.code.toUpperCase()}.svg`"
                                                        alt=""
                                                    />
                                                    <span class="ltr:ml-3 rtl:mr-3">{{ item.name }}</span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </Popper>
                        </div>
                    </div>
                    <div class="mx-auto w-full max-w-[440px]">
                        <div class="mb-7">
                            <h1 class="mb-3 text-2xl font-bold !leading-snug dark:text-white">Reset Your Password</h1>
                            <p>Enter your new password below</p>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isVerifying" class="flex justify-center py-10">
                            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></span>
                        </div>

                        <!-- Invalid Token -->
                        <div v-else-if="!isValidToken && !isVerifying" class="text-center">
                            <div class="mb-5 rounded-md bg-danger-light p-4 text-danger">
                                <p class="font-semibold">Invalid or Expired Link</p>
                                <p class="mt-2 text-sm">This password reset link is invalid or has expired. Please request a new one.</p>
                            </div>
                            <router-link
                                to="/auth/boxed-password-reset"
                                class="btn btn-gradient w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]"
                            >
                                Request New Link
                            </router-link>
                        </div>

                        <!-- Success Message -->
                        <div v-else-if="resetSuccess" class="text-center">
                            <div class="mb-5 rounded-md bg-success-light p-4 text-success">
                                <p class="font-semibold">Password Reset Successful!</p>
                                <p class="mt-2 text-sm">{{ authStore.passwordResetMessage }}</p>
                            </div>
                            <router-link
                                to="/auth/boxed-signin"
                                class="btn btn-gradient w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]"
                            >
                                Sign In
                            </router-link>
                        </div>

                        <!-- Reset Password Form -->
                        <template v-else>
                            <!-- Error Message -->
                            <div v-if="authStore.error" class="mb-5 rounded-md bg-danger-light p-4 text-danger">
                                {{ authStore.error }}
                            </div>

                            <form class="space-y-5" @submit.prevent="handleSubmit">
                                <div>
                                    <label for="Password" class="dark:text-white">New Password</label>
                                    <div class="relative text-white-dark">
                                        <input
                                            id="Password"
                                            v-model="form.password"
                                            type="password"
                                            placeholder="Enter New Password"
                                            class="form-input ps-10 placeholder:text-white-dark"
                                            :class="{ 'border-danger': v$.password.$error }"
                                        />
                                        <span class="absolute start-4 top-1/2 -translate-y-1/2">
                                            <icon-lock-dots :fill="true" />
                                        </span>
                                    </div>
                                    <template v-if="v$.password.$error">
                                        <p class="mt-1 text-danger text-sm" v-for="error in v$.password.$errors" :key="error.$uid">
                                            {{ error.$message }}
                                        </p>
                                    </template>
                                </div>

                                <div>
                                    <label for="PasswordConfirmation" class="dark:text-white">Confirm Password</label>
                                    <div class="relative text-white-dark">
                                        <input
                                            id="PasswordConfirmation"
                                            v-model="form.password_confirmation"
                                            type="password"
                                            placeholder="Confirm New Password"
                                            class="form-input ps-10 placeholder:text-white-dark"
                                            :class="{ 'border-danger': v$.password_confirmation.$error }"
                                        />
                                        <span class="absolute start-4 top-1/2 -translate-y-1/2">
                                            <icon-lock-dots :fill="true" />
                                        </span>
                                    </div>
                                    <template v-if="v$.password_confirmation.$error">
                                        <p class="mt-1 text-danger text-sm" v-for="error in v$.password_confirmation.$errors" :key="error.$uid">
                                            {{ error.$message }}
                                        </p>
                                    </template>
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]"
                                    :disabled="authStore.isLoading"
                                >
                                    <span v-if="authStore.isLoading" class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block align-middle mr-2"></span>
                                    {{ authStore.isLoading ? 'RESETTING...' : 'RESET PASSWORD' }}
                                </button>
                            </form>
                        </template>

                        <div class="mt-5 text-center dark:text-white">
                            Remember your password?
                            <router-link to="/auth/boxed-signin" class="uppercase text-primary underline transition hover:text-black dark:hover:text-white">
                                SIGN IN
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script lang="ts" setup>
    import { computed, reactive, ref, onMounted } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { useVuelidate } from '@vuelidate/core';
    import { required, minLength, sameAs, helpers } from '@vuelidate/validators';
    import appSetting from '@/app-setting';
    import { useAppStore } from '@/stores/index';
    import { useAuthStore } from '@/stores/auth';
    import { useRoute, useRouter } from 'vue-router';
    import { useMeta } from '@/composables/use-meta';

    import IconCaretDown from '@/components/icon/icon-caret-down.vue';
    import IconLockDots from '@/components/icon/icon-lock-dots.vue';

    useMeta({ title: 'Reset Password' });
    const route = useRoute();
    const router = useRouter();
    const store = useAppStore();
    const authStore = useAuthStore();

    // State
    const isVerifying = ref(true);
    const isValidToken = ref(false);
    const resetSuccess = ref(false);

    // Get token and email from URL
    const token = computed(() => route.query.token as string || '');
    const email = computed(() => route.query.email as string || '');

    // Form data
    const form = reactive({
        password: '',
        password_confirmation: '',
    });

    // Validation rules
    const rules = {
        password: {
            required,
            minLength: minLength(8),
        },
        password_confirmation: {
            required,
            sameAs: helpers.withMessage('Passwords must match', sameAs(computed(() => form.password))),
        },
    };

    const v$ = useVuelidate(rules, form);

    // Verify token on mount
    onMounted(async () => {
        authStore.clearError();
        authStore.clearPasswordResetMessage();

        if (!token.value || !email.value) {
            isVerifying.value = false;
            isValidToken.value = false;
            return;
        }

        try {
            const response = await authStore.verifyResetToken(token.value, email.value);
            isValidToken.value = response.valid;
        } catch (error) {
            isValidToken.value = false;
        } finally {
            isVerifying.value = false;
        }
    });

    // Handle form submission
    const handleSubmit = async () => {
        const isFormValid = await v$.value.$validate();
        if (!isFormValid) return;

        try {
            await authStore.resetPassword({
                token: token.value,
                email: email.value,
                password: form.password,
                password_confirmation: form.password_confirmation,
            });
            resetSuccess.value = true;
        } catch (error) {
            // Error is handled by the store
        }
    };

    // multi language
    const i18n = reactive(useI18n());
    const changeLanguage = (item: any) => {
        i18n.locale = item.code;
        appSetting.toggleLanguage(item);
    };
    const currentFlag = computed(() => {
        return `/assets/images/flags/${i18n.locale.toUpperCase()}.svg`;
    });
</script>
