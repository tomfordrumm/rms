<script setup lang="ts">
import ReservationForm from '@/components/admin/ReservationForm.vue';

type TableOption = {
    id: number;
    name: string;
    capacity: number;
    is_active: boolean;
};

type ReservationPayload = {
    id: number;
    customer_name: string;
    customer_email: string;
    people_count: number;
    date: string;
    time: string;
    status: string;
    table_id: number;
    table: TableOption | null;
};

defineProps<{
    reservation: ReservationPayload;
    tables: TableOption[];
    statusOptions: Array<{ id: string; label: string }>;
}>();
</script>

<template>
    <ReservationForm :reservation="reservation" :tables="tables" />
</template>
