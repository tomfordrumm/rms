<script setup lang="ts">
import CategoryForm from '@/components/admin/CategoryForm.vue';

defineProps<{
    category: null;
    suggestedPosition: number;
}>();
</script>

<template>
    <CategoryForm :category="category" :suggested-position="suggestedPosition" />
</template>
