<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">Users</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('profile.account_settings') }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="profileStore.isLoading && !profileStore.hasProfile" class="pt-5">
            <div class="panel">
                <div class="flex items-center justify-center py-20">
                    <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></span>
                </div>
            </div>
        </div>

        <div v-else class="pt-5">
            <div class="flex items-center justify-between mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('profile.settings') }}</h5>
            </div>
            <TabGroup :selectedIndex="selectedTab" @change="changeTab">
                <TabList class="flex font-semibold border-b border-[#ebedf2] dark:border-[#191e3a] mb-5 whitespace-nowrap overflow-y-auto" aria-label="Account settings sections">
                    <Tab as="template" v-slot="{ selected }">
                        <a
                            href="javascript:;"
                            class="flex gap-2 p-4 border-b border-transparent hover:border-primary hover:text-primary !outline-none"
                            :class="{ '!border-primary text-primary': selected }"
                        >
                            <icon-user class="w-5 h-5" />
                            {{ $t('profile.general') }}
                        </a>
                    </Tab>
                    <Tab as="template" v-slot="{ selected }">
                        <a
                            href="javascript:;"
                            class="flex gap-2 p-4 border-b border-transparent hover:border-primary hover:text-primary !outline-none"
                            :class="{ '!border-primary text-primary': selected }"
                        >
                            <icon-lock-dots class="w-5 h-5" />
                            {{ $t('profile.security') }}
                        </a>
                    </Tab>
                    <Tab as="template" v-slot="{ selected }">
                        <a
                            href="javascript:;"
                            class="flex gap-2 p-4 border-b border-transparent hover:border-primary hover:text-primary !outline-none"
                            :class="{ '!border-primary text-primary': selected }"
                        >
                            <icon-settings class="w-5 h-5" />
                            {{ $t('profile.preferences') }}
                        </a>
                    </Tab>
                    <Tab as="template" v-slot="{ selected }">
                        <a
                            href="javascript:;"
                            class="flex gap-2 p-4 border-b border-transparent hover:border-primary hover:text-primary !outline-none"
                            :class="{ '!border-primary text-primary': selected }"
                        >
                            <icon-trash-lines class="w-5 h-5" />
                            {{ $t('profile.danger_zone') }}
                        </a>
                    </Tab>
                </TabList>
                <TabPanels>
                    <!-- General Tab -->
                    <TabPanel>
                        <div>
                            <form @submit.prevent="saveProfile" class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 mb-5 bg-white dark:bg-[#0e1726]">
                                <h6 class="text-lg font-bold mb-5">{{ $t('profile.general_information') }}</h6>
                                <div class="flex flex-col sm:flex-row">
                                    <!-- Avatar Section -->
                                    <div class="ltr:sm:mr-4 rtl:sm:ml-4 w-full sm:w-2/12 mb-5">
                                        <div class="flex flex-col items-center">
                                            <!-- Avatar Preview -->
                                            <div v-if="profileStore.avatarUrl" class="w-24 h-24 md:w-32 md:h-32 rounded-full overflow-hidden mb-3">
                                                <img :src="profileStore.avatarUrl" alt="Avatar" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-gradient-to-r from-primary to-secondary flex items-center justify-center text-white mb-3">
                                                <span class="text-3xl font-bold">{{ profileStore.initials }}</span>
                                            </div>

                                            <!-- Avatar Actions -->
                                            <div class="flex gap-2">
                                                <label class="btn btn-primary btn-sm cursor-pointer">
                                                    <icon-camera class="w-4 h-4 ltr:mr-1 rtl:ml-1" />
                                                    <span v-if="!profileStore.isUploadingAvatar">{{ $t('profile.upload') }}</span>
                                                    <span v-else class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4"></span>
                                                    <input
                                                        type="file"
                                                        class="hidden"
                                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                                        @change="handleAvatarUpload"
                                                        :disabled="profileStore.isUploadingAvatar"
                                                        aria-label="Upload profile photo"
                                                    />
                                                </label>
                                                <button
                                                    v-if="profileStore.avatarUrl"
                                                    type="button"
                                                    class="btn btn-outline-danger btn-sm"
                                                    @click="deleteAvatar"
                                                    :disabled="profileStore.isUploadingAvatar"
                                                    aria-label="Remove profile photo"
                                                >
                                                    <icon-trash-lines class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2 text-center">Max 2MB (JPG, PNG, GIF)</p>
                                        </div>
                                    </div>

                                    <!-- Form Fields -->
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-5">
                                        <div>
                                            <label for="name">{{ $t('profile.full_name') }} <span class="text-danger">*</span></label>
                                            <input
                                                id="name"
                                                v-model="profileForm.name"
                                                type="text"
                                                placeholder="Enter your name"
                                                class="form-input"
                                                :class="{ 'border-danger': v$.name.$error }"
                                                :aria-invalid="v$.name.$error"
                                                :aria-describedby="v$.name.$error ? 'name-error' : undefined"
                                            />
                                            <span v-if="v$.name.$error" id="name-error" role="alert" class="text-danger text-xs mt-1">{{ v$.name.$errors[0].$message }}</span>
                                        </div>
                                        <div>
                                            <label for="email">{{ $t('profile.email') }}</label>
                                            <input
                                                id="email"
                                                :value="profileStore.email"
                                                type="email"
                                                class="form-input bg-gray-100 dark:bg-gray-800"
                                                disabled
                                            />
                                            <span class="text-xs text-gray-500">{{ $t('profile.contact_admin_to_change_email') }}</span>
                                        </div>
                                        <div>
                                            <label for="phone">{{ $t('profile.phone') }}</label>
                                            <input
                                                id="phone"
                                                v-model="profileForm.phone"
                                                type="text"
                                                placeholder="+1 (555) 123-4567"
                                                class="form-input"
                                            />
                                        </div>
                                        <div>
                                            <label for="date_of_birth">{{ $t('profile.date_of_birth') }}</label>
                                            <flat-pickr
                                                id="date_of_birth"
                                                v-model="profileForm.date_of_birth as DateOption | DateOption[]"
                                                class="form-input"
                                                :config="datePickerConfig"
                                                :value="profileForm.date_of_birth ? new Date(profileForm.date_of_birth) : null"
                                                :placeholder="$t('profile.select_date')"
                                            />
                                        </div>
                                        <div>
                                            <label for="website">{{ $t('profile.website') }}</label>
                                            <input
                                                id="website"
                                                v-model="profileForm.website"
                                                type="url"
                                                placeholder="https://ejemplo.com"
                                                class="form-input"
                                                :class="{ 'border-danger': v$.website.$error }"
                                                :aria-invalid="v$.website.$error"
                                                :aria-describedby="v$.website.$error ? 'website-error' : undefined"
                                            />
                                            <span v-if="v$.website.$error" id="website-error" role="alert" class="text-danger text-xs mt-1">{{ v$.website.$errors[0].$message }}</span>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="bio">{{ $t('profile.bio') }}</label>
                                            <textarea
                                                id="bio"
                                                v-model="profileForm.bio"
                                                rows="3"
                                                placeholder="Cuéntanos sobre ti..."
                                                class="form-textarea"
                                                maxlength="500"
                                            ></textarea>
                                            <span class="text-xs text-gray-500">{{ (profileForm.bio?.length || 0) }}/500 characters</span>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <!-- Address Information -->
                            <form @submit.prevent="saveProfile" class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 mb-5 bg-white dark:bg-[#0e1726]">
                                <h6 class="text-lg font-bold mb-5">{{ $t('profile.address_information') }}</h6>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div class="sm:col-span-2">
                                        <label for="address">{{ $t('profile.address') }}</label>
                                        <input
                                            id="address"
                                            v-model="profileForm.address"
                                            type="text"
                                            placeholder="123 Main Street"
                                            class="form-input"
                                        />
                                    </div>
                                    <div>
                                        <label for="city">{{ $t('profile.city') }}</label>
                                        <input
                                            id="city"
                                            v-model="profileForm.city"
                                            type="text"
                                            placeholder="New York"
                                            class="form-input"
                                        />
                                    </div>
                                    <div>
                                        <label for="state">{{ $t('profile.state') }}</label>
                                        <input
                                            id="state"
                                            v-model="profileForm.state"
                                            type="text"
                                            placeholder="NY"
                                            class="form-input"
                                        />
                                    </div>
                                    <div>
                                        <label for="country">{{ $t('profile.country') }}</label>
                                        <input
                                            id="country"
                                            v-model="profileForm.country"
                                            type="text"
                                            placeholder="United States"
                                            class="form-input"
                                        />
                                    </div>
                                    <div>
                                        <label for="postal_code">{{ $t('profile.postal_code') }}</label>
                                        <input
                                            id="postal_code"
                                            v-model="profileForm.postal_code"
                                            type="text"
                                            placeholder="10001"
                                            class="form-input"
                                        />
                                    </div>
                                </div>
                            </form>

                            <!-- Social Links -->
                            <form @submit.prevent="saveProfile" class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 mb-5 bg-white dark:bg-[#0e1726]">
                                <h6 class="text-lg font-bold mb-5">Social Links</h6>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div class="flex">
                                        <div class="bg-[#eee] flex justify-center items-center rounded px-3 font-semibold dark:bg-[#1b2e4b] ltr:mr-2 rtl:ml-2">
                                            <icon-linkedin class="w-5 h-5" />
                                        </div>
                                        <input
                                            v-model="profileForm.social_links.linkedin"
                                            type="text"
                                            placeholder="linkedin-username"
                                            class="form-input"
                                        />
                                    </div>
                                    <div class="flex">
                                        <div class="bg-[#eee] flex justify-center items-center rounded px-3 font-semibold dark:bg-[#1b2e4b] ltr:mr-2 rtl:ml-2">
                                            <icon-twitter class="w-5 h-5" />
                                        </div>
                                        <input
                                            v-model="profileForm.social_links.twitter"
                                            type="text"
                                            placeholder="twitter-username"
                                            class="form-input"
                                        />
                                    </div>
                                    <div class="flex">
                                        <div class="bg-[#eee] flex justify-center items-center rounded px-3 font-semibold dark:bg-[#1b2e4b] ltr:mr-2 rtl:ml-2">
                                            <icon-facebook class="w-5 h-5" />
                                        </div>
                                        <input
                                            v-model="profileForm.social_links.facebook"
                                            type="text"
                                            placeholder="facebook-username"
                                            class="form-input"
                                        />
                                    </div>
                                    <div class="flex">
                                        <div class="bg-[#eee] flex justify-center items-center rounded px-3 font-semibold dark:bg-[#1b2e4b] ltr:mr-2 rtl:ml-2">
                                            <icon-github />
                                        </div>
                                        <input
                                            v-model="profileForm.social_links.github"
                                            type="text"
                                            placeholder="github-username"
                                            class="form-input"
                                        />
                                    </div>
                                </div>
                            </form>

                            <!-- Preferences -->
                            <form @submit.prevent="saveProfile" class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 mb-5 bg-white dark:bg-[#0e1726]">
                                <h6 class="text-lg font-bold mb-5">{{ $t('profile.preferences') }}</h6>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <label for="timezone">{{ $t('profile.timezone') }}</label>
                                        <select id="timezone" v-model="profileForm.timezone" class="form-select">
                                            <option value="">{{ $t('profile.select_timezone') }}</option>
                                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="language">{{ $t('profile.language') }}</label>
                                        <select id="language" v-model="profileForm.language" class="form-select">
                                            <option value="">{{ $t('profile.select_language') }}</option>
                                            <option value="en">{{ $t('profile.english') }}</option>
                                            <option value="es">{{ $t('profile.spanish') }}</option>
                                            <option value="fr">{{ $t('profile.french') }}</option>
                                            <!-- <option value="de">{{ $t('profile.german') }}</option>
                                            <option value="pt">{{ $t('profile.portuguese') }}</option> -->
                                        </select>
                                    </div>
                                </div>
                            </form>

                            <!-- Save Button -->
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    @click="saveProfile"
                                    :disabled="profileStore.isSaving"
                                >
                                    <span v-if="!profileStore.isSaving">{{ $t('profile.save_changes') }}</span>
                                    <span v-else class="flex items-center">
                                        <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 ltr:mr-2 rtl:ml-2"></span>
                                        {{ $t('profile.saving') }}...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </TabPanel>

                    <!-- Security Tab -->
                    <TabPanel>
                        <div>
                            <form @submit.prevent="changePassword" class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 mb-5 bg-white dark:bg-[#0e1726]">
                                <h6 class="text-lg font-bold mb-5">{{ $t('profile.change_password') }}</h6>
                                <div class="max-w-md space-y-5">
                                    <div>
                                        <label for="current_password">{{ $t('profile.current_password') }} <span class="text-danger">*</span></label>
                                        <div class="relative">
                                            <input
                                                id="current_password"
                                                v-model="passwordForm.current_password"
                                                :type="showCurrentPassword ? 'text' : 'password'"
                                                :placeholder="$t('profile.enter_current_password')"
                                                class="form-input pr-10"
                                                :class="{ 'border-danger': vPassword$.current_password.$error }"
                                                :aria-invalid="vPassword$.current_password.$error"
                                                :aria-describedby="vPassword$.current_password.$error ? 'current_password-error' : undefined"
                                            />
                                            <button
                                                type="button"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
                                                @click="showCurrentPassword = !showCurrentPassword"
                                                aria-label="Toggle password visibility"
                                            >
                                                <icon-eye v-if="!showCurrentPassword" class="w-5 h-5" />
                                                <icon-eye-off v-else class="w-5 h-5" />
                                            </button>
                                        </div>
                                        <span v-if="vPassword$.current_password.$error" id="current_password-error" role="alert" class="text-danger text-xs mt-1">{{ vPassword$.current_password.$errors[0].$message }}</span>
                                    </div>
                                    <div>
                                        <label for="new_password">{{ $t('profile.new_password') }} <span class="text-danger">*</span></label>
                                        <div class="relative">
                                            <input
                                                id="new_password"
                                                v-model="passwordForm.password"
                                                :type="showNewPassword ? 'text' : 'password'"
                                                :placeholder="$t('profile.enter_new_password')"
                                                class="form-input pr-10"
                                                :class="{ 'border-danger': vPassword$.password.$error }"
                                                :aria-invalid="vPassword$.password.$error"
                                                :aria-describedby="vPassword$.password.$error ? 'new_password-error' : 'new_password-hint'"
                                            />
                                            <button
                                                type="button"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
                                                @click="showNewPassword = !showNewPassword"
                                                aria-label="Toggle password visibility"
                                            >
                                                <icon-eye v-if="!showNewPassword" class="w-5 h-5" />
                                                <icon-eye-off v-else class="w-5 h-5" />
                                            </button>
                                        </div>
                                        <span v-if="vPassword$.password.$error" id="new_password-error" role="alert" class="text-danger text-xs mt-1">{{ vPassword$.password.$errors[0].$message }}</span>
                                        <p id="new_password-hint" class="text-xs text-gray-500 mt-1">{{ $t('profile.minimum_8_characters') }}</p>
                                    </div>
                                    <div>
                                        <label for="password_confirmation">{{ $t('profile.confirm_new_password') }} <span class="text-danger">*</span></label>
                                        <div class="relative">
                                            <input
                                                id="password_confirmation"
                                                v-model="passwordForm.password_confirmation"
                                                :type="showConfirmPassword ? 'text' : 'password'"
                                                :placeholder="$t('profile.confirm_new_password')"
                                                class="form-input pr-10"
                                                :class="{ 'border-danger': vPassword$.password_confirmation.$error }"
                                                :aria-invalid="vPassword$.password_confirmation.$error"
                                                :aria-describedby="vPassword$.password_confirmation.$error ? 'password_confirmation-error' : undefined"
                                            />
                                            <button
                                                type="button"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
                                                @click="showConfirmPassword = !showConfirmPassword"
                                                aria-label="Toggle password visibility"
                                            >
                                                <icon-eye v-if="!showConfirmPassword" class="w-5 h-5" />
                                                <icon-eye-off v-else class="w-5 h-5" />
                                            </button>
                                        </div>
                                        <span v-if="vPassword$.password_confirmation.$error" id="password_confirmation-error" role="alert" class="text-danger text-xs mt-1">{{ vPassword$.password_confirmation.$errors[0].$message }}</span>
                                    </div>
                                    <div class="pt-3">
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            :disabled="isChangingPassword"
                                        >
                                            <span v-if="!isChangingPassword">{{ $t('profile.update_password') }}</span>
                                            <span v-else class="flex items-center">
                                                <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 ltr:mr-2 rtl:ml-2"></span>
                                                {{ $t('profile.updating') }}...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Two-Factor Authentication -->
                            <div class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 mb-5 bg-white dark:bg-[#0e1726]">
                                <div class="flex items-center justify-between mb-5">
                                    <div>
                                        <h6 class="text-lg font-bold">{{ $t('profile.two_factor_authentication') }}</h6>
                                        <p class="text-sm text-gray-500">{{ $t('profile.add_additional_security_to_your_account_using_totp_authentication') }}</p>
                                    </div>
                                    <span v-if="isTwoFactorEnabled" class="badge badge-outline-success">{{ $t('profile.enabled') }}</span>
                                    <span v-else class="badge badge-outline-warning">{{ $t('profile.disabled') }}</span>
                                </div>

                                <!-- 2FA Not Enabled: Show Enable Button -->
                                <div v-if="!isTwoFactorEnabled && !twoFactorSetup.showSetup">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                        {{ $t('profile.two_factor_authentication_adds_an_extra_layer_of_security_by_requiring_a_code_from_your_authenticator_app_when_you_sign_in') }}
                                    </p>
                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        @click="enableTwoFactor"
                                        :disabled="twoFactorSetup.isLoading"
                                        aria-label="Enable two-factor authentication"
                                    >
                                        <span v-if="!twoFactorSetup.isLoading">{{ $t('profile.enable_two_factor_authentication') }}</span>
                                        <span v-else class="flex items-center gap-2">
                                            <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4"></span>
                                            {{ $t('profile.setting_up') }}...
                                        </span>
                                    </button>
                                </div>

                                <!-- 2FA Setup Flow: QR Code + Confirm -->
                                <div v-if="twoFactorSetup.showSetup && !isTwoFactorEnabled">
                                    <div class="space-y-5">
                                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900">
                                            <p class="text-sm font-semibold mb-3">{{ $t('profile.scan_this_qr_code_with_your_authenticator_app') }}:</p>
                                            <div class="flex justify-center mb-3" v-html="twoFactorSetup.qrCode"></div>
                                            <p class="text-xs text-gray-500 text-center">{{ $t('profile.google_authenticator_authy_or_any_totp_app') }}</p>
                                        </div>

                                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900">
                                            <p class="text-sm font-semibold mb-2">{{ $t('profile.or_enter_this_secret_manually') }}:</p>
                                            <code class="block text-center text-sm bg-white dark:bg-black p-2 rounded border font-mono select-all">
                                                {{ twoFactorSetup.secret }}
                                            </code>
                                        </div>

                                        <div>
                                            <p class="text-sm font-semibold mb-2">{{ $t('profile.enter_the_6_digit_code_from_your_app_to_confirm') }}:</p>
                                            <div class="flex gap-3 items-end">
                                                <input
                                                    v-model="twoFactorSetup.confirmCode"
                                                    type="text"
                                                    inputmode="numeric"
                                                    pattern="[0-9]*"
                                                    maxlength="6"
                                                    class="form-input w-60 text-center text-sm tracking-widest font-mono"
                                                    :placeholder="$t('profile.enter_the_6_digit_code_from_your_app_to_confirm')" 
                                                    @input="twoFactorSetup.confirmCode = twoFactorSetup.confirmCode.replace(/\D/g, '')"
                                                />
                                                <button
                                                    type="button"
                                                    class="btn btn-primary"
                                                    :disabled="twoFactorSetup.confirmCode.length !== 6 || twoFactorSetup.isLoading"
                                                    @click="confirmTwoFactor"
                                                >
                                                    <span v-if="!twoFactorSetup.isLoading">{{ $t('profile.confirm_and_enable') }}</span>
                                                    <span v-else class="flex items-center gap-2">
                                                        <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4"></span>
                                                        {{ $t('profile.confirming') }}...
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-danger"
                                                    @click="cancelTwoFactorSetup"
                                                >
                                                    {{ $t('profile.cancel') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2FA Enabled: Show management options -->
                                <div v-if="isTwoFactorEnabled">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                        {{ $t('profile.two_factor_authentication_is_currently_enabled_you_will_be_asked_for_a_code_from_your_authenticator_app_when_you_sign_in') }}
                                    </p>
                                    <div class="flex flex-wrap gap-3">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary"
                                            @click="viewRecoveryCodes"
                                            :disabled="twoFactorSetup.isLoading"
                                        >
                                            {{ $t('profile.view_recovery_codes') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger"
                                            @click="disableTwoFactor"
                                            :disabled="twoFactorSetup.isLoading"
                                            aria-label="Disable two-factor authentication"
                                        >
                                            {{ $t('profile.disable_two_factor_authentication') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Session Information -->
                            <div class="border border-[#ebedf2] dark:border-[#191e3a] rounded-md p-4 bg-white dark:bg-[#0e1726]">
                                <h6 class="text-lg font-bold mb-5">{{ $t('profile.account_security') }}</h6>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between py-3 border-b border-gray-200 dark:border-gray-700">
                                        <div>
                                            <h6 class="font-semibold">{{ $t('profile.email_verification') }}</h6>
                                            <p class="text-sm text-gray-500">{{ $t('profile.your_email_address_verification_status') }}</p>
                                        </div>
                                        <div>
                                            <span v-if="authStore.isEmailVerified" class="badge badge-outline-success">{{ $t('profile.verified') }}</span>
                                            <span v-else class="badge badge-outline-warning">{{ $t('profile.not_verified') }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between py-3">
                                        <div>
                                            <h6 class="font-semibold">{{ $t('profile.last_password_change') }}</h6>
                                            <p class="text-sm text-gray-500">{{ $t('profile.when_your_password_was_last_updated') }}</p>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">{{ lastPasswordChange }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <!-- Preferences Tab -->
                    <TabPanel>
                        <div class="switch">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.choose_theme') }}</h5>
                                    <div class="flex justify-around">
                                        <label class="inline-flex cursor-pointer">
                                            <input
                                                class="form-radio ltr:mr-4 rtl:ml-4 cursor-pointer"
                                                type="radio"
                                                name="themePreference"
                                                value="light"
                                                :checked="appStore.theme === 'light'"
                                                @change="appStore.toggleTheme('light')"
                                            />
                                            <span>
                                                <img class="ms-3" width="100" height="68" alt="settings-light" src="/assets/images/settings-light.svg" />
                                            </span>
                                        </label>

                                        <label class="inline-flex cursor-pointer">
                                            <input
                                                class="form-radio ltr:mr-4 rtl:ml-4 cursor-pointer"
                                                type="radio"
                                                name="themePreference"
                                                value="dark"
                                                :checked="appStore.theme === 'dark'"
                                                @change="appStore.toggleTheme('dark')"
                                            />
                                            <span>
                                                <img class="ms-3" width="100" height="68" alt="settings-dark" src="/assets/images/settings-dark.svg" />
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.activity_data') }}</h5>
                                    <p>{{ $t('profile.download_your_summary_task_and_payment_history_data') }}</p>
                                    <button type="button" class="btn btn-primary">{{ $t('profile.download_data') }}</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.public_profile') }}</h5>
                                    <p>{{ $t('profile.your_profile_will_be_visible_to_anyone_on_the_network') }}</p>
                                    <label class="w-12 h-6 relative">
                                        <input
                                            type="checkbox"
                                            class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                            id="custom_switch_checkbox1"
                                        />
                                        <span
                                            for="custom_switch_checkbox1"
                                            class="bg-[#ebedf2] dark:bg-dark block h-full rounded-full before:absolute before:left-1 before:bg-white dark:before:bg-white-dark dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300"
                                        ></span>
                                    </label>
                                </div>
                                <div class="panel space-y-5">
                                        <h5 class="font-semibold text-lg mb-4">{{ $t('profile.show_my_email') }}</h5>
                                    <p>{{ $t('profile.your_email_will_be_visible_to_anyone_on_the_network') }}</p>
                                    <label class="w-12 h-6 relative">
                                        <input
                                            type="checkbox"
                                            class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                            id="custom_switch_checkbox2"
                                        />
                                        <span
                                            for="custom_switch_checkbox2"
                                            class="bg-[#ebedf2] dark:bg-dark block h-full rounded-full before:absolute before:left-1 before:bg-white dark:before:bg-white-dark dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300"
                                        ></span>
                                    </label>
                                </div>
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.enable_keyboard_shortcuts') }}</h5>
                                    <p>{{ $t('profile.when_enabled_press_ctrl_for_help') }}</p>
                                    <label class="w-12 h-6 relative">
                                        <input
                                            type="checkbox"
                                            class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                            id="custom_switch_checkbox3"
                                        />
                                        <span
                                            for="custom_switch_checkbox3"
                                            class="bg-[#ebedf2] dark:bg-dark block h-full rounded-full before:absolute before:left-1 before:bg-white dark:before:bg-white-dark dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300"
                                        ></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <!-- Danger Zone Tab -->
                    <TabPanel>
                        <div class="switch">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.purge_cache') }}</h5>
                                    <p>{{ $t('profile.remove_the_active_resource_from_the_cache_without_waiting_for_the_predetermined_cache_expiry_time') }}</p>
                                    <button class="btn btn-secondary">{{ $t('profile.clear') }}</button>
                                </div>
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.deactivate_account') }}</h5>
                                    <p>{{ $t('profile.you_will_not_be_able_to_receive_messages_notifications_for_up_to_24_hours') }}</p>
                                    <label class="w-12 h-6 relative">
                                        <input
                                            type="checkbox"
                                            class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                            id="custom_switch_checkbox7"
                                        />
                                        <span
                                            for="custom_switch_checkbox7"
                                            class="bg-[#ebedf2] dark:bg-dark block h-full rounded-full before:absolute before:left-1 before:bg-white dark:before:bg-white-dark dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300"
                                        ></span>
                                    </label>
                                </div>
                                <div class="panel space-y-5">
                                    <h5 class="font-semibold text-lg mb-4">{{ $t('profile.delete_account') }}</h5>
                                    <p>{{ $t('profile.once_you_delete_the_account_there_is_no_going_back_please_be_certain') }}</p>
                                    <button class="btn btn-danger btn-delete-account" @click="confirmDeleteAccount">{{ $t('profile.delete_my_account') }}</button>
                                </div>
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>
            </TabGroup>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue';
import { useVuelidate } from '@vuelidate/core';
import { required, minLength, url, sameAs, helpers } from '@vuelidate/validators';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';
import Swal from 'sweetalert2';
import { useAppStore } from '@/stores/index';
import { useProfileStore } from '@/stores/profile';
import { useAuthStore } from '@/stores/auth';
import { useMeta } from '@/composables/use-meta';
import twoFactorService from '@/services/twoFactorService';
import type { UpdateProfileData, ChangePasswordData, SocialLinks } from '@/types/user';

// Icons
import IconUser from '@/components/icon/icon-user.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconSettings from '@/components/icon/icon-settings.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconLinkedin from '@/components/icon/icon-linkedin.vue';
import IconTwitter from '@/components/icon/icon-twitter.vue';
import IconFacebook from '@/components/icon/icon-facebook.vue';
import IconGithub from '@/components/icon/icon-github.vue';
import IconCamera from '@/components/icon/icon-camera.vue';
import IconEye from '@/components/icon/icon-eye.vue';

// Create a simple eye-off icon component inline (if it doesn't exist)
const IconEyeOff = {
    template: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5">
        <path d="M3 3L21 21M10.584 10.587C10.2087 10.962 9.99778 11.4708 9.99756 12.0013C9.99734 12.5318 10.2079 13.0408 10.583 13.416C10.9581 13.7912 11.4668 14.0021 11.9973 14.0024C12.5278 14.0026 13.0368 13.7921 13.412 13.417M9.363 5.365C10.2204 5.11972 11.1082 4.99684 12 5C16 5 19.333 7.333 22 12C21.222 13.361 20.388 14.524 19.497 15.488M17.357 17.349C15.726 18.449 13.942 19 12 19C8 19 4.667 16.667 2 12C3.369 9.605 4.913 7.825 6.632 6.659" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`
};

useMeta({ title: 'Account Settings' });

const route = useRoute();
const appStore = useAppStore();
const profileStore = useProfileStore();
const authStore = useAuthStore();

// Tab management
const selectedTab = ref(0);

const changeTab = (index: number) => {
    selectedTab.value = index;
};

// Handle hash-based tab navigation
watch(() => route.hash, (hash) => {
    if (hash === '#security') {
        selectedTab.value = 1;
    }
}, { immediate: true });

// Profile Form
const profileForm = reactive<UpdateProfileData & { social_links: SocialLinks }>({
    name: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    bio: '',
    date_of_birth: '',
    timezone: '',
    language: '',
    website: '',
    social_links: {
        twitter: '',
        linkedin: '',
        github: '',
        facebook: '',
    },
});

// Profile validation rules
const profileRules = {
    name: { required: helpers.withMessage('Name is required', required) },
    website: { url: helpers.withMessage('Please enter a valid URL', url) },
};

const v$ = useVuelidate(profileRules, profileForm);

// Password Form
const passwordForm = reactive<ChangePasswordData>({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const isChangingPassword = ref(false);

// Password validation rules
const passwordRules = {
    current_password: { required: helpers.withMessage('Current password is required', required) },
    password: {
        required: helpers.withMessage('New password is required', required),
        minLength: helpers.withMessage('Password must be at least 8 characters', minLength(8))
    },
    password_confirmation: {
        required: helpers.withMessage('Please confirm your password', required),
        sameAs: helpers.withMessage('Passwords do not match', sameAs(computed(() => passwordForm.password)))
    },
};

const vPassword$ = useVuelidate(passwordRules, passwordForm);

// Date picker configuration
const datePickerConfig = {
    dateFormat: 'Y-m-d',
    maxDate: 'today',
};

// Timezones list
const timezones = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Sao_Paulo',
    'America/Mexico_City',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Madrid',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Singapore',
    'Asia/Dubai',
    'Australia/Sydney',
    'Pacific/Auckland',
];

// Computed
const lastPasswordChange = computed(() => {
    // In a real app, this would come from the API
    return 'Not available';
});

// Methods
const loadProfileData = () => {
    if (profileStore.user) {
        profileForm.name = profileStore.user.name || '';
    }
    if (profileStore.profile) {
        profileForm.phone = profileStore.profile.phone || '';
        profileForm.address = profileStore.profile.address || '';
        profileForm.city = profileStore.profile.city || '';
        profileForm.state = profileStore.profile.state || '';
        profileForm.country = profileStore.profile.country || '';
        profileForm.postal_code = profileStore.profile.postal_code || '';
        profileForm.bio = profileStore.profile.bio || '';
        profileForm.date_of_birth = profileStore.profile.date_of_birth || '';
        profileForm.timezone = profileStore.profile.timezone || '';
        profileForm.language = profileStore.profile.language || '';
        profileForm.website = profileStore.profile.website || '';
        profileForm.social_links = {
            twitter: profileStore.profile.social_links?.twitter || '',
            linkedin: profileStore.profile.social_links?.linkedin || '',
            github: profileStore.profile.social_links?.github || '',
            facebook: profileStore.profile.social_links?.facebook || '',
        };
    }
};

const saveProfile = async () => {
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    try {
        // Clean social_links - remove empty strings
        const cleanedSocialLinks: SocialLinks = {};
        if (profileForm.social_links.twitter) cleanedSocialLinks.twitter = profileForm.social_links.twitter;
        if (profileForm.social_links.linkedin) cleanedSocialLinks.linkedin = profileForm.social_links.linkedin;
        if (profileForm.social_links.github) cleanedSocialLinks.github = profileForm.social_links.github;
        if (profileForm.social_links.facebook) cleanedSocialLinks.facebook = profileForm.social_links.facebook;

        const data: UpdateProfileData = {
            name: profileForm.name,
            phone: profileForm.phone || undefined,
            address: profileForm.address || undefined,
            city: profileForm.city || undefined,
            state: profileForm.state || undefined,
            country: profileForm.country || undefined,
            postal_code: profileForm.postal_code || undefined,
            bio: profileForm.bio || undefined,
            date_of_birth: profileForm.date_of_birth || undefined,
            timezone: profileForm.timezone || undefined,
            language: profileForm.language || undefined,
            website: profileForm.website || undefined,
            social_links: Object.keys(cleanedSocialLinks).length > 0 ? cleanedSocialLinks : undefined,
        };

        await profileStore.updateProfile(data);

        // Update auth store user name
        if (authStore.user) {
            authStore.user.name = profileForm.name;
        }

        Swal.fire({
            icon: 'success',
            title: 'Profile Updated',
            text: 'Your profile has been updated successfully.',
            padding: '2em',
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: error.response?.data?.message || 'Failed to update profile. Please try again.',
            padding: '2em',
        });
    }
};

const handleAvatarUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];

    // Validate file size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: 'Avatar image must be less than 2MB.',
            padding: '2em',
        });
        input.value = '';
        return;
    }

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Please upload a JPG, PNG, GIF, or WebP image.',
            padding: '2em',
        });
        input.value = '';
        return;
    }

    try {
        await profileStore.uploadAvatar(file);
        Swal.fire({
            icon: 'success',
            title: 'Avatar Updated',
            text: 'Your avatar has been updated successfully.',
            padding: '2em',
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Upload Failed',
            text: error.response?.data?.message || 'Failed to upload avatar. Please try again.',
            padding: '2em',
        });
    }

    input.value = '';
};

const deleteAvatar = async () => {
    const result = await Swal.fire({
        title: 'Delete Avatar?',
        text: 'Are you sure you want to remove your avatar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        padding: '2em',
    });

    if (result.isConfirmed) {
        try {
            await profileStore.deleteAvatar();
            Swal.fire({
                icon: 'success',
                title: 'Avatar Deleted',
                text: 'Your avatar has been removed.',
                padding: '2em',
            });
        } catch (error: any) {
            Swal.fire({
                icon: 'error',
                title: 'Delete Failed',
                text: error.response?.data?.message || 'Failed to delete avatar. Please try again.',
                padding: '2em',
            });
        }
    }
};

const changePassword = async () => {
    const isValid = await vPassword$.value.$validate();
    if (!isValid) return;

    isChangingPassword.value = true;

    try {
        await profileStore.changePassword(passwordForm);

        // Clear form
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        vPassword$.value.$reset();

        Swal.fire({
            icon: 'success',
            title: 'Password Changed',
            text: 'Your password has been updated successfully.',
            padding: '2em',
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Password Change Failed',
            text: error.response?.data?.message || 'Failed to change password. Please verify your current password.',
            padding: '2em',
        });
    } finally {
        isChangingPassword.value = false;
    }
};

// ==================== Two-Factor Authentication ====================
const isTwoFactorEnabled = computed(() => !!authStore.user?.two_factor_confirmed_at);

const twoFactorSetup = reactive({
    showSetup: false,
    qrCode: '',
    secret: '',
    recoveryCodes: [] as string[],
    confirmCode: '',
    isLoading: false,
});

const enableTwoFactor = async () => {
    twoFactorSetup.isLoading = true;
    try {
        const response = await twoFactorService.enable();
        twoFactorSetup.qrCode = response.qr_code;
        twoFactorSetup.secret = response.secret;
        twoFactorSetup.recoveryCodes = response.recovery_codes;
        twoFactorSetup.showSetup = true;
        twoFactorSetup.confirmCode = '';
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Setup Failed',
            text: error.response?.data?.message || 'Failed to initialize two-factor authentication.',
            padding: '2em',
        });
    } finally {
        twoFactorSetup.isLoading = false;
    }
};

const confirmTwoFactor = async () => {
    if (twoFactorSetup.confirmCode.length !== 6) return;
    twoFactorSetup.isLoading = true;
    try {
        await twoFactorService.confirm(twoFactorSetup.confirmCode);
        // Refresh user data to get updated two_factor_confirmed_at
        await authStore.fetchUser();
        twoFactorSetup.showSetup = false;

        // Show recovery codes
        Swal.fire({
            icon: 'success',
            title: 'Two-Factor Authentication Enabled',
            html: `
                <p class="mb-3">Save these recovery codes in a safe place. Each code can only be used once.</p>
                <div style="background:#f4f4f4;padding:12px;border-radius:8px;font-family:monospace;font-size:14px;text-align:left;line-height:2;">
                    ${twoFactorSetup.recoveryCodes.map(c => `<div>${c}</div>`).join('')}
                </div>
            `,
            width: 500,
            padding: '2em',
            confirmButtonText: 'I have saved my codes',
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Code',
            text: error.response?.data?.message || 'The code you entered is invalid. Please try again.',
            padding: '2em',
        });
    } finally {
        twoFactorSetup.isLoading = false;
    }
};

const cancelTwoFactorSetup = () => {
    twoFactorSetup.showSetup = false;
    twoFactorSetup.qrCode = '';
    twoFactorSetup.secret = '';
    twoFactorSetup.recoveryCodes = [];
    twoFactorSetup.confirmCode = '';
};

const viewRecoveryCodes = async () => {
    twoFactorSetup.isLoading = true;
    try {
        const response = await twoFactorService.getRecoveryCodes();
        Swal.fire({
            title: 'Recovery Codes',
            html: `
                <p class="mb-3 text-sm text-gray-500">Save these codes in a safe place. Each can only be used once.</p>
                <div style="background:#f4f4f4;padding:12px;border-radius:8px;font-family:monospace;font-size:14px;text-align:left;line-height:2;">
                    ${response.recovery_codes.map(c => `<div>${c}</div>`).join('')}
                </div>
            `,
            width: 500,
            padding: '2em',
            showCancelButton: true,
            confirmButtonText: 'Regenerate Codes',
            cancelButtonText: 'Close',
        }).then(async (result) => {
            if (result.isConfirmed) {
                await regenerateRecoveryCodes();
            }
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Failed',
            text: error.response?.data?.message || 'Failed to retrieve recovery codes.',
            padding: '2em',
        });
    } finally {
        twoFactorSetup.isLoading = false;
    }
};

const regenerateRecoveryCodes = async () => {
    const { value: password } = await Swal.fire({
        title: 'Regenerate Recovery Codes',
        text: 'This will invalidate your existing recovery codes. Enter your password to confirm.',
        input: 'password',
        inputPlaceholder: 'Enter your password',
        showCancelButton: true,
        confirmButtonText: 'Regenerate',
        padding: '2em',
    });

    if (!password) return;

    try {
        const response = await twoFactorService.regenerateRecoveryCodes(password);
        Swal.fire({
            icon: 'success',
            title: 'New Recovery Codes',
            html: `
                <p class="mb-3 text-sm">Your old codes are now invalid. Save these new codes.</p>
                <div style="background:#f4f4f4;padding:12px;border-radius:8px;font-family:monospace;font-size:14px;text-align:left;line-height:2;">
                    ${response.recovery_codes.map(c => `<div>${c}</div>`).join('')}
                </div>
            `,
            width: 500,
            padding: '2em',
            confirmButtonText: 'I have saved my codes',
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Failed',
            text: error.response?.data?.message || 'Failed to regenerate recovery codes.',
            padding: '2em',
        });
    }
};

const disableTwoFactor = async () => {
    const { value: password } = await Swal.fire({
        title: 'Disable Two-Factor Authentication',
        text: 'Enter your password to disable two-factor authentication.',
        input: 'password',
        inputPlaceholder: 'Enter your password',
        showCancelButton: true,
        confirmButtonText: 'Disable',
        confirmButtonColor: '#e7515a',
        padding: '2em',
    });

    if (!password) return;

    twoFactorSetup.isLoading = true;
    try {
        await twoFactorService.disable(password);
        await authStore.fetchUser();
        Swal.fire({
            icon: 'success',
            title: 'Two-Factor Disabled',
            text: 'Two-factor authentication has been disabled.',
            padding: '2em',
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Failed',
            text: error.response?.data?.message || 'Failed to disable two-factor authentication.',
            padding: '2em',
        });
    } finally {
        twoFactorSetup.isLoading = false;
    }
};

const confirmDeleteAccount = async () => {
    const result = await Swal.fire({
        title: 'Delete Account?',
        text: 'This action cannot be undone. All your data will be permanently deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete my account',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e7515a',
        padding: '2em',
        input: 'text',
        inputPlaceholder: 'Type "DELETE" to confirm',
        inputValidator: (value) => {
            if (value !== 'DELETE') {
                return 'Please type DELETE to confirm';
            }
        }
    });

    if (result.isConfirmed) {
        // In a real app, this would call the API to delete the account
        Swal.fire({
            icon: 'info',
            title: 'Not Implemented',
            text: 'Account deletion is not available in this demo.',
            padding: '2em',
        });
    }
};

// Initialize
onMounted(async () => {
    if (!profileStore.hasProfile) {
        await profileStore.fetchProfile();
    }
    loadProfileData();
});

// Watch for profile changes
watch(() => profileStore.profile, () => {
    loadProfileData();
}, { deep: true });
</script>
