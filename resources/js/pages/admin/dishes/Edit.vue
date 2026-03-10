<script setup lang="ts">
import DishForm from '@/components/admin/DishForm.vue';

type CategoryOption = {
    id: number;
    name: string;
    position: number;
};

type DishPayload = {
    id: number;
    name: string;
    description: string | null;
    weight: string;
    price: string;
    is_active: boolean;
    image_path: string | null;
    image_url: string | null;
    category_ids: string[];
};

defineProps<{
    dish: DishPayload;
    categories: CategoryOption[];
}>();
</script>

<template>
    <DishForm :dish="dish" :categories="categories" />
</template>
