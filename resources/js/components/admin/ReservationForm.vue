<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarRange, Clock3, Mail, UsersRound } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, index, update } from '@/routes/admin/reservations';
import type { BreadcrumbItem } from '@/types';

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

const props = defineProps<{
    reservation: ReservationPayload;
    tables: TableOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reservations',
        href: index(),
    },
    {
        title: 'Edit reservation',
        href: edit(props.reservation.id),
    },
];

const form = useForm({
    date: props.reservation.date,
    time: props.reservation.time,
    table_id: String(props.reservation.table_id),
    people_count: String(props.reservation.people_count),
});

function submit(): void {
    form.put(update(props.reservation.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit reservation" />

        <div class="space-y-6 px-4 py-6">
            <div
                class="relative overflow-hidden rounded-[1.75rem] border border-border/70 bg-gradient-to-br from-stone-950 via-zinc-900 to-blue-950 px-6 py-8 text-stone-50 shadow-[0_24px_90px_-52px_rgba(17,12,8,0.9)]"
            >
                <div class="absolute inset-y-0 right-0 w-48 bg-blue-300/10 blur-3xl" />
                <div class="relative space-y-4">
                    <Badge
                        variant="secondary"
                        class="border border-white/10 bg-white/10 text-stone-50"
                    >
                        <CalendarRange class="mr-2 h-3.5 w-3.5" />
                        Reservation #{{ reservation.id }}
                    </Badge>

                    <Heading
                        title="Edit reservation"
                        description="Adjust the schedule, assigned table and party size while keeping availability constraints intact."
                        class="text-stone-50"
                    />
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <Card class="border-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Reservation details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="date">Date</Label>
                                    <Input id="date" v-model="form.date" type="date" />
                                    <InputError :message="form.errors.date" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="time">Time</Label>
                                    <Input id="time" v-model="form.time" type="time" />
                                    <InputError :message="form.errors.time" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="table_id">Table</Label>
                                    <select
                                        id="table_id"
                                        v-model="form.table_id"
                                        class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option
                                            v-for="table in tables"
                                            :key="table.id"
                                            :value="String(table.id)"
                                        >
                                            {{ table.name }} · {{ table.capacity }} guests{{ table.is_active ? '' : ' · inactive' }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.table_id" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="people_count">People count</Label>
                                    <Input
                                        id="people_count"
                                        v-model="form.people_count"
                                        inputmode="numeric"
                                        placeholder="4"
                                    />
                                    <InputError :message="form.errors.people_count" />
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Button :disabled="form.processing" type="submit">
                                    Save reservation
                                </Button>
                                <Button as-child type="button" variant="ghost">
                                    <Link :href="index().url">Back to list</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Guest summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div class="flex items-start gap-3">
                                <UsersRound class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div class="font-medium text-foreground">{{ reservation.customer_name }}</div>
                                    <div class="text-muted-foreground">Customer name</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Mail class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div class="font-medium text-foreground">{{ reservation.customer_email }}</div>
                                    <div class="text-muted-foreground">Contact email</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Clock3 class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div class="font-medium text-foreground">
                                        {{ reservation.date }} at {{ reservation.time }}
                                    </div>
                                    <div class="text-muted-foreground">Current schedule</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Alert :class="reservation.status === 'cancelled' ? '' : 'border-amber-200/70 bg-amber-50/60'">
                        <AlertTitle>Status: {{ reservation.status }}</AlertTitle>
                        <AlertDescription>
                            Cancelled reservations stay visible in the system. Updating date, time or table does not reactivate them.
                        </AlertDescription>
                    </Alert>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
