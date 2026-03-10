<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <th :class="cn('px-6 py-4 text-left font-semibold', props.class)">
        <slot />
    </th>
</template>
