<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <tr :class="cn('transition-colors hover:bg-muted/20', props.class)">
        <slot />
    </tr>
</template>
