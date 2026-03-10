<script setup lang="ts">
import RestaurantTableForm from '@/components/admin/RestaurantTableForm.vue';

type TablePayload = {
    id: number;
    name: string;
    capacity: number;
    is_active: boolean;
};

defineProps<{
    table: TablePayload;
}>();
</script>

<template>
    <RestaurantTableForm :table="table" />
</template>
