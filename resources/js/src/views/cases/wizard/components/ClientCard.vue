<template>
    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <div class="flex items-start gap-4">
            <!-- Avatar -->
            <div
                class="w-12 h-12 rounded-full bg-primary/20 text-primary flex items-center justify-center font-semibold text-lg"
            >
                {{ getInitials(client.first_name, client.last_name) }}
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                        {{ client.full_name || `${client.first_name} ${client.last_name}` }}
                    </h4>
                    <span
                        :class="[
                            'px-2 py-0.5 text-xs font-medium rounded-full',
                            getStatusClass(client.status),
                        ]"
                    >
                        {{ client.status_label || client.status }}
                    </span>
                </div>

                <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <p v-if="client.email" class="flex items-center gap-2">
                        <icon-mail class="w-4 h-4" />
                        {{ client.email }}
                    </p>
                    <p v-if="client.phone" class="flex items-center gap-2">
                        <icon-phone class="w-4 h-4" />
                        {{ client.phone }}
                    </p>
                    <p v-if="client.nationality" class="flex items-center gap-2">
                        <icon-globe class="w-4 h-4" />
                        {{ client.nationality }}
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div v-if="showActions" class="flex-shrink-0">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    @click="$emit('change')"
                >
                    {{ $t('wizard.step2.change_client') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import type { Client } from '@/types/client';
import IconMail from '@/components/icon/icon-mail.vue';
import IconPhone from '@/components/icon/icon-phone.vue';
import IconGlobe from '@/components/icon/icon-globe.vue';

interface Props {
    client: Client;
    showActions?: boolean;
}

interface Emits {
    (e: 'change'): void;
}

withDefaults(defineProps<Props>(), {
    showActions: true,
});
defineEmits<Emits>();

function getInitials(firstName: string, lastName: string): string {
    return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
}

function getStatusClass(status: string): string {
    const classes: Record<string, string> = {
        active: 'bg-success/20 text-success',
        prospect: 'bg-info/20 text-info',
        inactive: 'bg-warning/20 text-warning',
        archived: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
    return classes[status] || 'bg-gray-200 text-gray-600';
}
</script>
