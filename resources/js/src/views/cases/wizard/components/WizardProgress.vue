<template>
    <div class="mb-8" role="navigation" aria-label="Wizard progress">
        <!-- Desktop Progress -->
        <div class="hidden md:flex items-center justify-between">
            <template v-for="(step, index) in steps" :key="step.id">
                <!-- Step Circle -->
                <div class="flex flex-col items-center">
                    <button
                        type="button"
                        :class="[
                            'w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-200',
                            getStepClasses(step),
                        ]"
                        :disabled="!canNavigateToStep(step)"
                        :aria-label="`${$t(step.title)}${step.isCompleted ? ' - ' + $t('wizard.completed') : ''}${step.id === currentStep ? ' - ' + $t('wizard.current') : ''}`"
                        :aria-current="step.id === currentStep ? 'step' : undefined"
                        @click="$emit('navigate', step.id)"
                    >
                        <component :is="getStepIcon(step)" class="w-5 h-5" v-if="step.isCompleted" aria-hidden="true" />
                        <span v-else aria-hidden="true">{{ step.id }}</span>
                    </button>
                    <span
                        :class="[
                            'mt-2 text-xs font-medium',
                            step.id === currentStep ? 'text-primary' : 'text-gray-500 dark:text-gray-400',
                        ]"
                        aria-hidden="true"
                    >
                        {{ $t(step.title) }}
                    </span>
                </div>

                <!-- Connector Line -->
                <div
                    v-if="index < steps.length - 1"
                    :class="[
                        'flex-1 h-0.5 mx-4',
                        step.isCompleted ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-700',
                    ]"
                ></div>
            </template>
        </div>

        <!-- Mobile Progress -->
        <div class="md:hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $t('wizard.step') }} {{ currentStep }} {{ $t('wizard.of') }} {{ steps.length }}
                </span>
                <span class="text-sm text-primary font-semibold">
                    {{ $t(currentStepTitle) }}
                </span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                    class="bg-primary h-2 rounded-full transition-all duration-300"
                    :style="{ width: `${progressPercentage}%` }"
                ></div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import type { WizardStep } from '@/types/wizard';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';

interface Props {
    steps: WizardStep[];
    currentStep: number;
}

interface Emits {
    (e: 'navigate', step: number): void;
}

const props = defineProps<Props>();
defineEmits<Emits>();

const currentStepTitle = computed(() => {
    const step = props.steps.find((s) => s.id === props.currentStep);
    return step?.title || '';
});

const progressPercentage = computed(() => {
    return ((props.currentStep - 1) / (props.steps.length - 1)) * 100;
});

function getStepClasses(step: WizardStep): string {
    if (step.id === props.currentStep) {
        return 'bg-primary text-white ring-4 ring-primary/30';
    }
    if (step.isCompleted) {
        return 'bg-primary text-white';
    }
    if (step.isValid && step.id < props.currentStep) {
        return 'bg-success text-white';
    }
    return 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400';
}

function canNavigateToStep(step: WizardStep): boolean {
    // Can navigate to completed steps or current step
    return step.isCompleted || step.id === props.currentStep || step.id === props.currentStep - 1;
}

function getStepIcon(step: WizardStep) {
    if (step.isCompleted) {
        return IconCircleCheck;
    }
    return null;
}
</script>
