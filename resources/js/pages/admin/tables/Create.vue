<script setup lang="ts">
import RestaurantTableForm from '@/components/admin/RestaurantTableForm.vue';

defineProps<{
    table: null;
}>();
</script>

<template>
    <RestaurantTableForm :table="table" />
</template>
