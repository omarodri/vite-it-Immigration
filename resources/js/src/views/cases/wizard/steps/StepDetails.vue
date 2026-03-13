<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('wizard.step4.title') }}
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('wizard.step4.description') }}
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column: Basic Details -->
            <div class="space-y-5">
                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium mb-2">
                        {{ $t('cases.priority') }}
                    </label>
                    <select
                        :value="wizard.state.caseDetails.priority"
                        class="form-select"
                        @change="updateField('priority', ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="low">{{ $t('cases.low') }}</option>
                        <option value="medium">{{ $t('cases.medium') }}</option>
                        <option value="high">{{ $t('cases.high') }}</option>
                        <option value="urgent">{{ $t('cases.urgent') }}</option>
                    </select>
                </div>

                <!-- Language -->
                <div>
                    <label class="block text-sm font-medium mb-2">
                        {{ $t('cases.language') }}
                    </label>
                    <select
                        :value="wizard.state.caseDetails.language"
                        class="form-select"
                        @change="updateField('language', ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="es">{{ $t('common.spanish') }}</option>
                        <option value="en">{{ $t('common.english') }}</option>
                        <option value="fr">{{ $t('common.french') }}</option>
                    </select>
                </div>

                <!-- Assigned To -->
                <div>
                    <label class="block text-sm font-medium mb-2">
                        {{ $t('cases.assigned_to') }}
                    </label>
                    <!-- Loading state -->
                    <div v-if="isLoadingStaff" class="animate-pulse h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <!-- Error state -->
                    <div v-else-if="staffError" class="text-sm text-danger">
                        {{ $t('cases.staff_load_error') }}
                        <button type="button" class="ml-2 text-primary underline" @click="loadStaff">{{ $t('cases.retry') }}</button>
                    </div>
                    <!-- Empty state -->
                    <div v-else-if="staffMembers.length === 0" class="text-sm text-warning">{{ $t('cases.no_active_consultants') }}</div>
                    <!-- Normal state -->
                    <select
                        v-else
                        :value="wizard.state.caseDetails.assigned_to || ''"
                        class="form-select"
                        @change="updateField('assigned_to', ($event.target as HTMLSelectElement).value ? parseInt(($event.target as HTMLSelectElement).value) : null)"
                    >
                        <option value="">{{ $t('cases.unassigned') }}</option>
                        <option v-for="staff in staffMembers" :key="staff.id" :value="staff.id">
                            {{ staff.name }}
                        </option>
                    </select>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium mb-2">
                        {{ $t('cases.description') }}
                    </label>
                    <textarea
                        :value="wizard.state.caseDetails.description"
                        rows="4"
                        class="form-textarea"
                        :placeholder="$t('cases.description_placeholder')"
                        @input="updateField('description', ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>
                </div>
            </div>

            <!-- Right Column: Important Dates -->
            <div class="space-y-5">
                <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-4">
                    {{ $t('cases.important_dates') }}
                </h4>

                <DateManager v-model="wizard.state.caseDetails.important_dates" />
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted, inject } from 'vue';
import userService from '@/services/userService';
import type { StaffMember, CaseDetailsForm } from '@/types/wizard';
import DateManager from '@/components/DateManager.vue';

// Get wizard from parent
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const staffMembers = ref<StaffMember[]>([]);
const isLoadingStaff = ref(false);
const staffError = ref(false);

// Update a field in the wizard state
function updateField(field: keyof CaseDetailsForm, value: any) {
    wizard.updateDetails({ [field]: value });
}

// Load staff members
const loadStaff = async () => {
    isLoadingStaff.value = true;
    staffError.value = false;
    try {
        staffMembers.value = await userService.getStaff();
    } catch (error) {
        staffError.value = true;
        console.error('Failed to load staff members:', error);
    } finally {
        isLoadingStaff.value = false;
    }
};

// Load staff members on mount
onMounted(async () => {
    await loadStaff();
});
</script>
