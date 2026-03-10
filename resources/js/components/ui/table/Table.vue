<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'overflow-hidden rounded-[1.75rem] border border-border/70 bg-background shadow-[0_20px_60px_-45px_rgba(15,23,42,0.35)]',
                props.class,
            )
        "
    >
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <slot />
            </table>
        </div>
    </div>
</template>
