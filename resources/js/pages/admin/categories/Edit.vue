<script setup lang="ts">
import CategoryForm from '@/components/admin/CategoryForm.vue';

type CategoryPayload = {
    id: number;
    name: string;
    description: string | null;
    position: number;
    dishes_count: number;
};

defineProps<{
    category: CategoryPayload;
    suggestedPosition?: null;
}>();
</script>

<template>
    <CategoryForm :category="category" :suggested-position="suggestedPosition" />
</template>
