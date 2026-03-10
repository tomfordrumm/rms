<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <tbody :class="cn('divide-y divide-border/70', props.class)">
        <slot />
    </tbody>
</template>
