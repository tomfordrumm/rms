<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarRange, Pencil, Search, UsersRound, XCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { cancel, edit, index } from '@/routes/admin/reservations';
import type { BreadcrumbItem } from '@/types';

type ReservationPayload = {
    id: number;
    customer_name: string;
    customer_email: string;
    people_count: number;
    date: string;
    time: string;
    status: string;
    table: {
        id: number;
        name: string;
    } | null;
};

type Filters = {
    date: string;
    email: string;
    status: string;
};

const props = defineProps<{
    reservations: ReservationPayload[];
    filters: Filters;
    statusOptions: Array<{ id: string; label: string }>;
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reservations',
        href: index(),
    },
];

const form = useForm({
    date: props.filters.date ?? '',
    email: props.filters.email ?? '',
    status: props.filters.status ?? '',
});

const statusMessage = computed(() => {
    if (props.status === 'reservation-updated') {
        return 'Reservation updated.';
    }

    if (props.status === 'reservation-cancelled') {
        return 'Reservation cancelled.';
    }

    return null;
});

watch(
    () => ({ ...form.data() }),
    (value) => {
        router.get(index().url, value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true },
);

function resetFilters(): void {
    form.date = '';
    form.email = '';
    form.status = '';
}

function cancelReservation(reservationId: number, status: string): void {
    if (status === 'cancelled') {
        return;
    }

    if (!window.confirm('Cancel this reservation?')) {
        return;
    }

    router.patch(cancel(reservationId).url, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Reservations" />

        <div class="space-y-6 px-4 py-6">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <Heading
                    title="Reservations"
                    description="Review bookings, filter the queue and adjust existing reservations."
                />
            </div>

            <Alert v-if="statusMessage">
                <AlertTitle>Update</AlertTitle>
                <AlertDescription>{{ statusMessage }}</AlertDescription>
            </Alert>

            <div class="grid gap-4 rounded-[1.5rem] border border-border/70 bg-muted/15 p-5 md:grid-cols-4">
                <div class="grid gap-2">
                    <Label for="filter-date">Date</Label>
                    <Input id="filter-date" v-model="form.date" type="date" />
                </div>

                <div class="grid gap-2">
                    <Label for="filter-email">Email</Label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="filter-email"
                            v-model="form.email"
                            class="pl-9"
                            placeholder="guest@example.com"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="filter-status">Status</Label>
                    <select
                        id="filter-status"
                        v-model="form.status"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="statusOption in statusOptions"
                            :key="statusOption.id"
                            :value="statusOption.id"
                        >
                            {{ statusOption.label }}
                        </option>
                    </select>
                </div>

                <div class="flex items-end">
                    <Button type="button" variant="outline" @click="resetFilters">
                        Reset filters
                    </Button>
                </div>
            </div>

            <div
                v-if="reservations.length === 0"
                class="rounded-[1.5rem] border border-dashed border-border/70 bg-muted/20 px-6 py-10 text-center"
            >
                <CalendarRange class="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
                <h2 class="text-lg font-semibold">No reservations found</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Try different filters or wait for reservations from the public flow.
                </p>
            </div>

            <Table v-else>
                <TableHeader>
                    <tr>
                        <TableHead>Guest</TableHead>
                        <TableHead>Schedule</TableHead>
                        <TableHead>Table</TableHead>
                        <TableHead>Party</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </tr>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="reservation in reservations" :key="reservation.id">
                        <TableCell>
                            <div class="space-y-2">
                                <div class="font-semibold text-foreground">
                                    {{ reservation.customer_name }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ reservation.customer_email }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    ID {{ reservation.id }}
                                </div>
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="space-y-1">
                                <div class="font-medium text-foreground">{{ reservation.date }}</div>
                                <div class="text-sm text-muted-foreground">{{ reservation.time }}</div>
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="font-medium text-foreground">
                                {{ reservation.table?.name ?? 'Unknown table' }}
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="inline-flex items-center gap-2 font-medium text-foreground">
                                <UsersRound class="h-4 w-4 text-muted-foreground" />
                                {{ reservation.people_count }}
                            </div>
                        </TableCell>

                        <TableCell>
                            <Badge
                                :variant="reservation.status === 'active' ? 'default' : 'secondary'"
                                class="rounded-full px-3 py-1"
                            >
                                {{ reservation.status }}
                            </Badge>
                        </TableCell>

                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="edit(reservation.id).url">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    :disabled="reservation.status === 'cancelled'"
                                    size="sm"
                                    variant="destructive"
                                    @click="cancelReservation(reservation.id, reservation.status)"
                                >
                                    <XCircle class="mr-2 h-4 w-4" />
                                    Cancel
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
