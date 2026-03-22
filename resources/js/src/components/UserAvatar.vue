<script lang="ts" setup>
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    name: string;
    avatarUrl?: string | null;
    size?: 'xs' | 'sm' | 'md' | 'lg';
}>(), {
    avatarUrl: null,
    size: 'sm',
});

const sizeClasses: Record<string, string> = {
    xs: 'w-6 h-6 text-[10px]',
    sm: 'w-8 h-8 text-xs',
    md: 'w-10 h-10 text-sm',
    lg: 'w-24 h-24 text-3xl',
};

const imgFailed = ref(false);

const initials = computed(() => {
    if (!props.name) return '';
    return props.name
        .split(' ')
        .map(w => w[0])
        .filter(Boolean)
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const showImage = computed(() => props.avatarUrl && !imgFailed.value);
</script>

<template>
    <div class="relative inline-flex items-center justify-center rounded-full overflow-hidden flex-shrink-0"
         :class="sizeClasses[size]">
        <img v-if="showImage"
             :src="avatarUrl!"
             :alt="name"
             class="w-full h-full object-cover"
             loading="lazy"
             @error="imgFailed = true" />
        <div v-else
             class="w-full h-full bg-gradient-to-r from-primary to-secondary flex items-center justify-center text-white font-bold">
            {{ initials }}
        </div>
    </div>
</template>
