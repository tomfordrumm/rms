<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    Clock3,
    UsersRound,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type RestaurantPayload = {
    name: string;
    slug: string;
    logo_url: string | null;
    cover_url: string | null;
};

type ReservationPayload = {
    customer_name: string;
    customer_email: string;
    people_count: number;
    date: string;
    time: string;
    status: string;
    table: {
        name: string;
        capacity: number;
    } | null;
};

defineProps<{
    restaurant: RestaurantPayload;
    reservation: ReservationPayload;
    manage_url: string;
}>();

function formatDate(date: string): string {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T12:00:00`));
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(`1970-01-01T${value}:00`));
}
</script>

<template>
    <Head :title="`Reservation confirmed - ${restaurant.name}`" />

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top,#eef5ef_0%,#f8f6f1_48%,#fbfaf7_100%)] px-4 py-8 text-stone-900 sm:px-6 lg:px-8"
    >
        <div class="mx-auto max-w-3xl">
            <div
                class="overflow-hidden rounded-[2rem] border border-stone-900/10 bg-white shadow-[0_36px_120px_-58px_rgba(28,25,23,0.45)]"
            >
                <div
                    class="relative overflow-hidden bg-stone-950 px-8 py-10 text-white"
                >
                    <div
                        v-if="restaurant.cover_url"
                        class="absolute inset-0 bg-cover bg-center opacity-25"
                        :style="{
                            backgroundImage: `url(${restaurant.cover_url})`,
                        }"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-stone-950 to-stone-900/60"
                    />
                    <div class="relative space-y-4">
                        <Badge
                            class="rounded-full border border-white/10 bg-white/10 px-4 py-1.5 text-white"
                        >
                            Confirmation sent
                        </Badge>
                        <div class="flex items-center gap-4">
                            <CheckCircle2 class="h-12 w-12 text-emerald-300" />
                            <div>
                                <h1 class="text-4xl font-black tracking-tight">
                                    Reservation Confirmed
                                </h1>
                                <p
                                    class="mt-2 max-w-2xl text-base leading-7 text-white/75"
                                >
                                    Your table at {{ restaurant.name }} is
                                    booked. We sent the details and management
                                    link to {{ reservation.customer_email }}.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 px-8 py-8 md:grid-cols-2">
                    <div
                        class="space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-6"
                    >
                        <div class="flex items-start gap-3">
                            <CalendarDays
                                class="mt-0.5 h-5 w-5 text-emerald-600"
                            />
                            <div>
                                <div
                                    class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Date
                                </div>
                                <div
                                    class="mt-1 text-lg font-black text-stone-950"
                                >
                                    {{ formatDate(reservation.date) }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Clock3 class="mt-0.5 h-5 w-5 text-amber-600" />
                            <div>
                                <div
                                    class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Time
                                </div>
                                <div
                                    class="mt-1 text-lg font-black text-stone-950"
                                >
                                    {{ formatTime(reservation.time) }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <UsersRound class="mt-0.5 h-5 w-5 text-sky-600" />
                            <div>
                                <div
                                    class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Party
                                </div>
                                <div
                                    class="mt-1 text-lg font-black text-stone-950"
                                >
                                    {{ reservation.people_count }} guests
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="space-y-4 rounded-[1.5rem] border border-stone-200 bg-white p-6"
                    >
                        <div>
                            <div
                                class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Guest
                            </div>
                            <div class="mt-1 text-lg font-black text-stone-950">
                                {{ reservation.customer_name }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Email
                            </div>
                            <div
                                class="mt-1 text-base font-semibold text-stone-800"
                            >
                                {{ reservation.customer_email }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Table
                            </div>
                            <div
                                class="mt-1 text-base font-semibold text-stone-800"
                            >
                                {{
                                    reservation.table?.name ?? 'Assigned table'
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-stone-200 px-8 py-6 sm:flex-row"
                >
                    <Button as-child>
                        <Link :href="manage_url">Manage reservation</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="`/r/${restaurant.slug}/menu`"
                            >Browse menu</Link
                        >
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
