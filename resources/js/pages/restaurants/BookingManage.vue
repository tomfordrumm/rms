<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Mail,
    RefreshCw,
    UsersRound,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RestaurantPayload = {
    name: string;
    slug: string;
    cover_url: string | null;
    work_hours: string | null;
    open_time?: string | null;
    close_time?: string | null;
};

type ReservationPayload = {
    customer_name: string;
    customer_email: string;
    people_count: number;
    date: string;
    time: string;
    status: string;
    token: string;
    table: {
        id?: number;
        name: string;
        capacity: number;
    } | null;
};

type SlotOption = {
    value: string;
    label: string;
    available_tables_count: number;
};

type TableOption = {
    id: number;
    name: string;
    capacity: number;
};

type AvailabilityPayload = {
    calendar_dates: string[];
    bookable_dates: string[];
    closed_dates: string[];
    selected_date_slots: SlotOption[];
    selected_time_tables: TableOption[];
    booking_rules: {
        slot_duration_minutes: number;
        min_party_size: number;
        max_party_size: number;
        timezone_note: string;
    };
    booking_enabled: boolean;
    booking_notice: string | null;
};

const props = defineProps<{
    restaurant: RestaurantPayload;
    reservation: ReservationPayload;
    initialAvailability: AvailabilityPayload;
    status?: string;
}>();

const availability = ref<AvailabilityPayload>(props.initialAvailability);
const isLoadingAvailability = ref(false);

const form = useForm({
    people_count: props.reservation.people_count,
    date: props.reservation.date,
    time: props.reservation.time,
    table_id: props.reservation.table?.id ?? null,
});

const manageBaseUrl = computed(
    () => `/r/${props.restaurant.slug}/booking/manage/${props.reservation.token}`,
);
const availabilityUrl = computed(() => `/r/${props.restaurant.slug}/booking/availability`);

const selectedTable = computed(() =>
    availability.value.selected_time_tables.find((table) => table.id === form.table_id) ?? null,
);

const canSubmit = computed(
    () =>
        props.reservation.status === 'active'
        && availability.value.booking_enabled
        && Boolean(form.date)
        && Boolean(form.time)
        && Boolean(form.table_id),
);

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

async function refreshAvailability(options: { preserveTable?: boolean } = {}): Promise<void> {
    isLoadingAvailability.value = true;

    try {
        const url = new URL(availabilityUrl.value, window.location.origin);
        url.searchParams.set('token', props.reservation.token);
        url.searchParams.set('people_count', String(form.people_count));

        if (form.date) {
            url.searchParams.set('date', form.date);
        }

        if (form.time) {
            url.searchParams.set('time', form.time);
        }

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        availability.value = (await response.json()) as AvailabilityPayload;

        if (!availability.value.calendar_dates.includes(form.date)) {
            form.date = availability.value.bookable_dates[0] ?? form.date;
        }

        if (!availability.value.bookable_dates.includes(form.date)) {
            form.time = '';
            form.table_id = null;
        }

        if (!availability.value.selected_date_slots.some((slot) => slot.value === form.time)) {
            form.time = availability.value.selected_date_slots[0]?.value ?? '';
            form.table_id = null;
        }

        if (
            !options.preserveTable
            || !availability.value.selected_time_tables.some((table) => table.id === form.table_id)
        ) {
            form.table_id = availability.value.selected_time_tables[0]?.id ?? null;
        }
    } finally {
        isLoadingAvailability.value = false;
    }
}

async function onPeopleCountChange(nextValue: number): Promise<void> {
    const bounded = Math.min(
        Math.max(nextValue, availability.value.booking_rules.min_party_size),
        availability.value.booking_rules.max_party_size,
    );

    if (bounded === form.people_count) {
        return;
    }

    form.people_count = bounded;
    form.table_id = null;
    await refreshAvailability();
}

async function onDateChange(): Promise<void> {
    form.table_id = null;
    await refreshAvailability();
}

async function onTimeChange(): Promise<void> {
    form.table_id = null;
    await refreshAvailability();
}

function selectTable(tableId: number): void {
    form.table_id = tableId;
}

function submit(): void {
    form.patch(manageBaseUrl.value, {
        preserveScroll: true,
    });
}

function cancelReservation(): void {
    if (props.reservation.status === 'cancelled') {
        return;
    }

    if (!window.confirm('Cancel this reservation?')) {
        return;
    }

    form.transform((data) => data).patch(`${manageBaseUrl.value}/cancel`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Manage reservation - ${restaurant.name}`" />

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top,#f5efe7_0%,#faf7f1_48%,#fcfbf8_100%)] px-4 py-8 text-stone-900 sm:px-6 lg:px-8"
    >
        <div class="mx-auto max-w-5xl space-y-6">
            <section
                class="relative overflow-hidden rounded-[2rem] border border-stone-900/10 bg-stone-950 px-8 py-10 text-white shadow-[0_36px_120px_-58px_rgba(28,25,23,0.55)]"
            >
                <div
                    v-if="restaurant.cover_url"
                    class="absolute inset-0 bg-cover bg-center opacity-20"
                    :style="{ backgroundImage: `url(${restaurant.cover_url})` }"
                />
                <div class="absolute inset-0 bg-gradient-to-r from-stone-950 to-stone-900/60" />
                <div class="relative space-y-4">
                    <Badge class="rounded-full border border-white/10 bg-white/10 px-4 py-1.5 text-white">
                        Reservation management
                    </Badge>
                    <h1 class="text-4xl font-black tracking-tight">
                        Update your booking at {{ restaurant.name }}
                    </h1>
                    <p class="max-w-2xl text-base leading-7 text-white/75">
                        Adjust the party size, date, time and assigned table when availability allows. Cancellation remains available below.
                    </p>
                </div>
            </section>

            <Alert
                v-if="status === 'reservation-updated'"
                class="border-emerald-300/60 bg-emerald-50 text-emerald-900"
            >
                <CheckCircle2 class="h-4 w-4" />
                <AlertTitle>Reservation updated</AlertTitle>
                <AlertDescription>
                    Your reservation has been updated and email notifications were sent.
                </AlertDescription>
            </Alert>

            <Alert
                v-if="status === 'reservation-cancelled'"
                class="border-emerald-300/60 bg-emerald-50 text-emerald-900"
            >
                <AlertTitle>Reservation cancelled</AlertTitle>
                <AlertDescription>
                    Your booking is now marked as cancelled.
                </AlertDescription>
            </Alert>

            <Alert
                v-if="availability.booking_notice"
                class="border-amber-300/60 bg-amber-50 text-amber-900"
            >
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Availability notice</AlertTitle>
                <AlertDescription>{{ availability.booking_notice }}</AlertDescription>
            </Alert>

            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <div class="space-y-6 rounded-[1.75rem] border border-stone-200 bg-white p-6 shadow-[0_30px_80px_-62px_rgba(28,25,23,0.45)]">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-black tracking-tight text-stone-950">
                                Edit reservation
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-stone-500">
                                Only free slots and tables are shown. Your current reservation is excluded from the conflict check while you edit.
                            </p>
                        </div>

                        <Button
                            :disabled="isLoadingAvailability || reservation.status === 'cancelled'"
                            size="sm"
                            type="button"
                            variant="outline"
                            @click="refreshAvailability({ preserveTable: true })"
                        >
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Refresh
                        </Button>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="people_count">Party size</Label>
                            <Input
                                id="people_count"
                                :disabled="reservation.status === 'cancelled'"
                                :model-value="String(form.people_count)"
                                inputmode="numeric"
                                @update:model-value="(value) => onPeopleCountChange(Number(value || 1))"
                            />
                            <InputError :message="form.errors.people_count" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="date">Date</Label>
                            <Input
                                id="date"
                                v-model="form.date"
                                :disabled="reservation.status === 'cancelled'"
                                type="date"
                                @change="onDateChange"
                            />
                            <InputError :message="form.errors.date" />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="time">Time slot</Label>
                            <select
                                id="time"
                                v-model="form.time"
                                :disabled="reservation.status === 'cancelled' || availability.selected_date_slots.length === 0"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                @change="onTimeChange"
                            >
                                <option :value="null">Select a time</option>
                                <option
                                    v-for="slot in availability.selected_date_slots"
                                    :key="slot.value"
                                    :value="slot.value"
                                >
                                    {{ slot.label }} · {{ slot.available_tables_count }} tables
                                </option>
                            </select>
                            <InputError :message="form.errors.time" />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-black tracking-tight text-stone-950">
                                Choose table
                            </h3>
                            <p class="mt-1 text-sm text-stone-500">
                                Pick any currently available table that fits the updated party size.
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <button
                                v-for="table in availability.selected_time_tables"
                                :key="table.id"
                                :disabled="reservation.status === 'cancelled'"
                                :class="{
                                    'border-stone-950 bg-stone-950 text-white shadow-lg shadow-stone-950/20': form.table_id === table.id,
                                    'border-stone-200 bg-white text-stone-900 hover:border-stone-400': form.table_id !== table.id,
                                }"
                                class="rounded-[1.25rem] border p-4 text-left transition"
                                type="button"
                                @click="selectTable(table.id)"
                            >
                                <div class="text-lg font-black">{{ table.name }}</div>
                                <div
                                    class="mt-1 text-sm"
                                    :class="form.table_id === table.id ? 'text-white/75' : 'text-stone-500'"
                                >
                                    Capacity {{ table.capacity }}
                                </div>
                            </button>
                        </div>

                        <p
                            v-if="form.time && availability.selected_time_tables.length === 0"
                            class="rounded-[1.25rem] border border-dashed border-stone-300 bg-stone-50 px-4 py-5 text-sm text-stone-500"
                        >
                            No tables remain for the selected date, time and party size.
                        </p>
                        <InputError :message="form.errors.table_id" />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button
                            :disabled="form.processing || !canSubmit"
                            type="button"
                            @click="submit"
                        >
                            Save changes
                        </Button>
                        <Button
                            :disabled="form.processing || reservation.status === 'cancelled'"
                            type="button"
                            variant="destructive"
                            @click="cancelReservation"
                        >
                            <XCircle class="mr-2 h-4 w-4" />
                            Cancel reservation
                        </Button>
                    </div>
                </div>

                <aside class="space-y-6">
                    <div class="rounded-[1.75rem] border border-stone-200 bg-stone-50 p-6">
                        <h2 class="text-2xl font-black tracking-tight text-stone-950">
                            Reservation summary
                        </h2>
                        <div class="mt-5 space-y-4">
                            <div class="flex items-start gap-3">
                                <CalendarDays class="mt-0.5 h-5 w-5 text-emerald-600" />
                                <div>
                                    <div class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Date
                                    </div>
                                    <div class="mt-1 font-semibold text-stone-950">
                                        {{ form.date ? formatDate(form.date) : 'Choose a date' }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Clock3 class="mt-0.5 h-5 w-5 text-amber-600" />
                                <div>
                                    <div class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Time
                                    </div>
                                    <div class="mt-1 font-semibold text-stone-950">
                                        {{ form.time ? formatTime(form.time) : 'Choose a time' }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <UsersRound class="mt-0.5 h-5 w-5 text-sky-600" />
                                <div>
                                    <div class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Party size
                                    </div>
                                    <div class="mt-1 font-semibold text-stone-950">
                                        {{ form.people_count }} guests
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Mail class="mt-0.5 h-5 w-5 text-rose-600" />
                                <div>
                                    <div class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                        Guest
                                    </div>
                                    <div class="mt-1 font-semibold text-stone-950">
                                        {{ reservation.customer_name }}
                                    </div>
                                    <div class="text-sm text-stone-500">
                                        {{ reservation.customer_email }}
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-[1.25rem] border border-stone-200 bg-white p-4">
                                <div class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Table
                                </div>
                                <div class="mt-1 text-lg font-black text-stone-950">
                                    {{ selectedTable?.name ?? reservation.table?.name ?? 'Assigned table' }}
                                </div>
                            </div>

                            <div class="rounded-[1.25rem] border border-stone-200 bg-white p-4">
                                <div class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    Status
                                </div>
                                <div class="mt-2">
                                    <Badge :variant="reservation.status === 'active' ? 'default' : 'secondary'">
                                        {{ reservation.status }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>
