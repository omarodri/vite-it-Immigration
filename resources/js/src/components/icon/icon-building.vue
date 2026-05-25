<template>
    <svg v-if="!fill" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M3 21H21"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
        />
        <path
            d="M19 21V5C19 3.89543 18.1046 3 17 3H7C5.89543 3 5 3.89543 5 5V21"
            stroke="currentColor"
            stroke-width="1.5"
        />
        <path
            opacity="0.5"
            d="M9 7H10M9 11H10M9 15H10M14 7H15M14 11H15M14 15H15"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
        />
        <path
            d="M10 21V18C10 17.4477 10.4477 17 11 17H13C13.5523 17 14 17.4477 14 18V21"
            stroke="currentColor"
            stroke-width="1.5"
        />
    </svg>
    <svg v-else width="18" height="18" viewBox="0 0 18 18" fill="none">
        <path
            d="M14.25 15.75V3.75C14.25 3.336 13.914 3 13.5 3H4.5C4.086 3 3.75 3.336 3.75 3.75V15.75H2.25V17.25H15.75V15.75H14.25ZM7.5 15.75V13.5H10.5V15.75H7.5ZM7.5 11.25H6V9.75H7.5V11.25ZM7.5 8.25H6V6.75H7.5V8.25ZM12 11.25H10.5V9.75H12V11.25ZM12 8.25H10.5V6.75H12V8.25Z"
            fill="currentColor"
        />
    </svg>
</template>
<script lang="ts" setup>
    defineProps({
        fill: {
            type: Boolean,
            default: false,
        },
    });
</script>
