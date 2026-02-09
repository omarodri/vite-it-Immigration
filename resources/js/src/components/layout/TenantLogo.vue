<template>
    <router-link to="/" class="main-logo flex items-center shrink-0">
        <!-- Custom logo if available -->
        <img
            v-if="logoUrl"
            class="w-8 ml-[5px] flex-none"
            :src="logoUrl"
            :alt="companyName"
        />
        <!-- Default logo -->
        <img
            v-else
            class="w-8 ml-[5px] flex-none"
            src="/assets/images/logo.svg"
            alt="VITE-IT"
        />
        <div class="flex flex-col">
            <span
                class="text-2xl ltr:ml-1.5 rtl:mr-1.5 font-semibold align-middle lg:inline dark:text-white-light truncate max-w-[140px]"
                :style="{ color: showBrandColor ? primaryColor : undefined }"
            >
                {{ displayName }}
            </span>
            <span
                v-if="showSubtitle"
                class="text-xs ltr:ml-1.5 rtl:mr-1.5 align-middle lg:inline text-gray-500 dark:text-gray-400"
            >
                Immigration
            </span>
        </div>
    </router-link>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { useTenantStore } from '@/stores/tenant';

interface Props {
    showSubtitle?: boolean;
    showBrandColor?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showSubtitle: true,
    showBrandColor: false,
});

const tenantStore = useTenantStore();

const logoUrl = computed(() => tenantStore.logoUrl);
const companyName = computed(() => tenantStore.companyName);
const primaryColor = computed(() => tenantStore.primaryColor);

const displayName = computed(() => {
    const name = tenantStore.companyName;
    // Shorten long names for display
    if (name.length > 15) {
        return name.substring(0, 12) + '...';
    }
    return name;
});
</script>
