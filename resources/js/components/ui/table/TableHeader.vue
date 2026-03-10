<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <thead
        :class="
            cn(
                'bg-muted/35 text-xs uppercase tracking-[0.2em] text-muted-foreground',
                props.class,
            )
        "
    >
        <slot />
    </thead>
</template>
