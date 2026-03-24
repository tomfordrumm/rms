<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
    >
        <rect x="4" y="6" width="40" height="36" rx="10" fill="currentColor" opacity="0.16" />
        <path d="M14 16H25.5C31.299 16 36 20.701 36 26.5C36 32.299 31.299 37 25.5 37H14V16Z" fill="currentColor" />
        <path d="M19 21H25.5C28.538 21 31 23.462 31 26.5C31 29.538 28.538 32 25.5 32H19V21Z" fill="white" opacity="0.92" />
        <path d="M14 16H24.5C28.09 16 31 18.91 31 22.5C31 26.09 28.09 29 24.5 29H14V16Z" fill="currentColor" opacity="0.88" />
        <path d="M14 29H25C28.866 29 32 32.134 32 36V37H14V29Z" fill="currentColor" opacity="0.72" />
        <path d="M33 15L35.598 20.263L41 20.999L37 24.896L37.944 30.357L33 27.757L28.056 30.357L29 24.896L25 20.999L30.402 20.263L33 15Z" fill="currentColor" />
    </svg>
</template>
