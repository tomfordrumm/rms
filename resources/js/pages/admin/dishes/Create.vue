<script setup lang="ts">
import DishForm from '@/components/admin/DishForm.vue';

type CategoryOption = {
    id: number;
    name: string;
    position: number;
};

defineProps<{
    dish: null;
    categories: CategoryOption[];
}>();
</script>

<template>
    <DishForm :dish="dish" :categories="categories" />
</template>
