<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Mail,
    MapPin,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RestaurantPayload = {
    slug: string;
    name: string;
    description: string | null;
    contacts: string | null;
    work_hours: string | null;
    open_time: string | null;
    close_time: string | null;
    closed_dates: string[];
    logo_url: string | null;
    cover_url: string | null;
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
    initialAvailability: AvailabilityPayload;
    initialState: {
        people_count: number;
        date: string | null;
        time: string | null;
        table_id: number | null;
    };
}>();

const availability = ref<AvailabilityPayload>(props.initialAvailability);
const isLoadingAvailability = ref(false);
const activeMonthIndex = ref(0);

const form = useForm({
    customer_name: '',
    customer_email: '',
    people_count: props.initialState.people_count,
    date: props.initialState.date,
    time: props.initialState.time,
    table_id: props.initialState.table_id,
});

const bookingUrl = computed(() => `/r/${props.restaurant.slug}/booking`);
const availabilityUrl = computed(() => `${bookingUrl.value}/availability`);

const calendarMonths = computed(() => {
    const months = new Map<
        string,
        { key: string; label: string; days: string[] }
    >();

    for (const date of availability.value.calendar_dates) {
        const monthKey = date.slice(0, 7);

        if (!months.has(monthKey)) {
            months.set(monthKey, {
                key: monthKey,
                label: new Intl.DateTimeFormat('en-US', {
                    month: 'long',
                    year: 'numeric',
                }).format(parseDate(date)),
                days: [],
            });
        }

        months.get(monthKey)?.days.push(date);
    }

    return Array.from(months.values()).map((month) => ({
        ...month,
        weeks: buildCalendarWeeks(month.days),
    }));
});

const visibleMonth = computed(
    () => calendarMonths.value[activeMonthIndex.value] ?? null,
);

const selectedTable = computed(
    () =>
        availability.value.selected_time_tables.find(
            (table) => table.id === form.table_id,
        ) ?? null,
);

const canSubmit = computed(
    () =>
        availability.value.booking_enabled &&
        Boolean(form.date) &&
        Boolean(form.time) &&
        Boolean(form.table_id) &&
        form.customer_name.trim() !== '' &&
        form.customer_email.trim() !== '',
);

const hoursLabel = computed(() => {
    if (props.restaurant.work_hours?.trim()) {
        return props.restaurant.work_hours;
    }

    if (props.restaurant.open_time && props.restaurant.close_time) {
        return `${formatTimeLabel(props.restaurant.open_time)} - ${formatTimeLabel(props.restaurant.close_time)}`;
    }

    return 'Hours unavailable';
});

if (props.initialState.date) {
    const initialMonthIndex = calendarMonths.value.findIndex((month) =>
        month.days.includes(props.initialState.date as string),
    );

    activeMonthIndex.value = initialMonthIndex >= 0 ? initialMonthIndex : 0;
}

function parseDate(value: string): Date {
    return new Date(`${value}T12:00:00`);
}

function buildCalendarWeeks(days: string[]): Array<Array<string | null>> {
    if (days.length === 0) {
        return [];
    }

    const firstDay = parseDate(days[0]).getDay();
    const cells: Array<string | null> = Array.from(
        { length: firstDay },
        () => null,
    );

    cells.push(...days);

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    const weeks: Array<Array<string | null>> = [];

    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7));
    }

    return weeks;
}

function isClosedDate(date: string): boolean {
    return availability.value.closed_dates.includes(date);
}

function isBookableDate(date: string): boolean {
    return availability.value.bookable_dates.includes(date);
}

function dayState(
    date: string,
): 'selected' | 'closed' | 'available' | 'unavailable' {
    if (form.date === date) {
        return 'selected';
    }

    if (isClosedDate(date)) {
        return 'closed';
    }

    if (isBookableDate(date)) {
        return 'available';
    }

    return 'unavailable';
}

function selectDate(date: string): void {
    if (
        !isBookableDate(date) ||
        form.date === date ||
        isLoadingAvailability.value
    ) {
        return;
    }

    form.date = date;
    form.table_id = null;
    void refreshAvailability();
}

function updatePartySize(next: number): void {
    if (
        next < availability.value.booking_rules.min_party_size ||
        next > availability.value.booking_rules.max_party_size ||
        next === form.people_count
    ) {
        return;
    }

    form.people_count = next;
    form.table_id = null;
    void refreshAvailability();
}

function selectTime(time: string): void {
    if (form.time === time || isLoadingAvailability.value) {
        return;
    }

    form.time = time;
    form.table_id = null;
    void refreshAvailability();
}

function selectTable(tableId: number): void {
    form.table_id = tableId;
}

async function refreshAvailability(): Promise<void> {
    isLoadingAvailability.value = true;

    try {
        const url = new URL(availabilityUrl.value, window.location.origin);
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

        if (
            form.date &&
            !availability.value.calendar_dates.includes(form.date)
        ) {
            form.date = null;
        }

        if (
            form.date &&
            !availability.value.bookable_dates.includes(form.date)
        ) {
            form.time = null;
            form.table_id = null;
        }

        if (
            form.time &&
            !availability.value.selected_date_slots.some(
                (slot) => slot.value === form.time,
            )
        ) {
            form.time = null;
            form.table_id = null;
        }

        if (
            form.table_id &&
            !availability.value.selected_time_tables.some(
                (table) => table.id === form.table_id,
            )
        ) {
            form.table_id = null;
        }
    } finally {
        isLoadingAvailability.value = false;
    }
}

function submit(): void {
    form.post(bookingUrl.value, {
        preserveScroll: true,
    });
}

function previousMonth(): void {
    activeMonthIndex.value = Math.max(0, activeMonthIndex.value - 1);
}

function nextMonth(): void {
    activeMonthIndex.value = Math.min(
        calendarMonths.value.length - 1,
        activeMonthIndex.value + 1,
    );
}

function formatFullDate(date: string | null): string {
    if (!date) {
        return 'Choose a date';
    }

    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(parseDate(date));
}

function formatTimeLabel(value: string | null): string {
    if (!value) {
        return 'Choose a time';
    }

    const normalized = value.length === 5 ? `${value}:00` : value;

    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(`1970-01-01T${normalized}`));
}
</script>

<template>
    <Head :title="`Book at ${restaurant.name}`" />

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top,#f4ecdf_0%,#f5f0e8_42%,#f8f5ef_100%)] text-stone-900"
    >
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
            <section
                class="relative overflow-hidden rounded-[2rem] border border-stone-900/10 bg-stone-950 text-stone-50 shadow-[0_45px_120px_-52px_rgba(28,25,23,0.9)]"
            >
                <div
                    v-if="restaurant.cover_url"
                    class="absolute inset-0 bg-cover bg-center opacity-45"
                    :style="{ backgroundImage: `url(${restaurant.cover_url})` }"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-r from-stone-950 via-stone-950/88 to-stone-900/45"
                />
                <div
                    class="absolute top-6 -right-20 h-56 w-56 rounded-full bg-amber-300/10 blur-3xl"
                />

                <div
                    class="relative grid gap-8 px-6 py-8 md:px-10 md:py-12 lg:grid-cols-[1.2fr_0.8fr] lg:items-end"
                >
                    <div class="space-y-5">
                        <Badge
                            class="rounded-full border border-white/10 bg-white/10 px-4 py-1.5 text-stone-50"
                        >
                            Public booking
                        </Badge>

                        <div class="space-y-3">
                            <div v-if="restaurant.logo_url" class="flex">
                                <img
                                    :src="restaurant.logo_url"
                                    :alt="restaurant.name"
                                    class="h-16 w-16 rounded-2xl border border-white/15 bg-white/10 object-cover p-2 shadow-lg"
                                />
                            </div>

                            <h1
                                class="max-w-2xl text-4xl font-black tracking-tight text-white md:text-6xl"
                            >
                                Reserve Your Table
                            </h1>
                            <p
                                class="max-w-2xl text-base leading-7 text-stone-200/85 md:text-lg"
                            >
                                {{ restaurant.name }} invites you to book a
                                one-hour dining slot with instant confirmation.
                            </p>
                            <p
                                v-if="restaurant.description"
                                class="max-w-2xl text-sm leading-7 text-stone-200/80"
                            >
                                {{ restaurant.description }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur-sm"
                    >
                        <div class="flex items-start gap-3">
                            <Clock3 class="mt-0.5 h-4 w-4 text-amber-200" />
                            <div>
                                <div
                                    class="text-xs tracking-[0.22em] text-stone-300/70 uppercase"
                                >
                                    Hours
                                </div>
                                <div class="mt-1 font-semibold text-white">
                                    {{ hoursLabel }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="restaurant.contacts"
                            class="flex items-start gap-3"
                        >
                            <MapPin class="mt-0.5 h-4 w-4 text-amber-200" />
                            <div>
                                <div
                                    class="text-xs tracking-[0.22em] text-stone-300/70 uppercase"
                                >
                                    Contact
                                </div>
                                <div
                                    class="mt-1 text-sm leading-6 whitespace-pre-line text-stone-100/90"
                                >
                                    {{ restaurant.contacts }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div
                class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(21rem,0.9fr)]"
            >
                <div class="space-y-6">
                    <Alert
                        v-if="availability.booking_notice"
                        class="border-amber-300/50 bg-amber-50/80 text-amber-950"
                    >
                        <AlertTitle>Booking unavailable</AlertTitle>
                        <AlertDescription>{{
                            availability.booking_notice
                        }}</AlertDescription>
                    </Alert>

                    <Card
                        class="overflow-hidden rounded-[1.75rem] border-stone-900/10 shadow-[0_30px_80px_-60px_rgba(28,25,23,0.45)]"
                    >
                        <CardContent class="space-y-8 p-6 md:p-8">
                            <section
                                class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]"
                            >
                                <div class="space-y-5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 text-lg font-black text-emerald-700"
                                        >
                                            1
                                        </div>
                                        <div>
                                            <h2
                                                class="text-2xl font-black tracking-tight text-stone-950"
                                            >
                                                Select Date
                                            </h2>
                                            <p class="text-sm text-stone-500">
                                                Choose from the next 30 days.
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[1.5rem] border border-stone-200 bg-stone-50/90 p-5"
                                    >
                                        <div
                                            class="mb-5 flex items-center justify-between"
                                        >
                                            <Button
                                                :disabled="
                                                    activeMonthIndex === 0
                                                "
                                                size="icon"
                                                type="button"
                                                variant="ghost"
                                                @click="previousMonth"
                                            >
                                                <ChevronLeft class="h-4 w-4" />
                                            </Button>
                                            <div
                                                class="text-lg font-black text-stone-900"
                                            >
                                                {{ visibleMonth?.label }}
                                            </div>
                                            <Button
                                                :disabled="
                                                    activeMonthIndex >=
                                                    calendarMonths.length - 1
                                                "
                                                size="icon"
                                                type="button"
                                                variant="ghost"
                                                @click="nextMonth"
                                            >
                                                <ChevronRight class="h-4 w-4" />
                                            </Button>
                                        </div>

                                        <div
                                            class="mb-3 grid grid-cols-7 gap-2 text-center text-xs font-bold tracking-[0.24em] text-stone-400 uppercase"
                                        >
                                            <div
                                                v-for="label in [
                                                    'Sun',
                                                    'Mon',
                                                    'Tue',
                                                    'Wed',
                                                    'Thu',
                                                    'Fri',
                                                    'Sat',
                                                ]"
                                                :key="label"
                                            >
                                                {{ label }}
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <div
                                                v-for="(
                                                    week, weekIndex
                                                ) in visibleMonth?.weeks ?? []"
                                                :key="weekIndex"
                                                class="grid grid-cols-7 gap-2"
                                            >
                                                <div
                                                    v-for="day in week"
                                                    :key="
                                                        day ??
                                                        `empty-${weekIndex}`
                                                    "
                                                    class="aspect-square"
                                                >
                                                    <button
                                                        v-if="day"
                                                        :disabled="
                                                            dayState(day) !==
                                                                'available' &&
                                                            dayState(day) !==
                                                                'selected'
                                                        "
                                                        class="flex h-full w-full flex-col items-center justify-center rounded-2xl border text-sm font-bold transition"
                                                        :class="{
                                                            'border-emerald-500 bg-emerald-500 text-white shadow-lg shadow-emerald-500/25':
                                                                dayState(
                                                                    day,
                                                                ) ===
                                                                'selected',
                                                            'border-emerald-200 bg-white text-stone-900 hover:border-emerald-400 hover:bg-emerald-50':
                                                                dayState(
                                                                    day,
                                                                ) ===
                                                                'available',
                                                            'border-red-100 bg-red-50/80 text-red-300':
                                                                dayState(
                                                                    day,
                                                                ) === 'closed',
                                                            'border-stone-200 bg-stone-100 text-stone-300':
                                                                dayState(
                                                                    day,
                                                                ) ===
                                                                'unavailable',
                                                        }"
                                                        type="button"
                                                        @click="selectDate(day)"
                                                    >
                                                        {{
                                                            parseDate(
                                                                day,
                                                            ).getDate()
                                                        }}
                                                    </button>
                                                    <div
                                                        v-else
                                                        class="h-full w-full"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-full bg-amber-100 text-lg font-black text-amber-700"
                                        >
                                            2
                                        </div>
                                        <div>
                                            <h2
                                                class="text-2xl font-black tracking-tight text-stone-950"
                                            >
                                                Party Size
                                            </h2>
                                            <p class="text-sm text-stone-500">
                                                Pick a table size that fits your
                                                group.
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[1.5rem] border border-stone-200 bg-stone-50/90 p-5"
                                    >
                                        <div
                                            class="flex items-center justify-between rounded-[1.25rem] border border-stone-200 bg-white p-3 shadow-sm"
                                        >
                                            <Button
                                                :disabled="
                                                    form.people_count <=
                                                    availability.booking_rules
                                                        .min_party_size
                                                "
                                                size="icon"
                                                type="button"
                                                variant="outline"
                                                @click="
                                                    updatePartySize(
                                                        form.people_count - 1,
                                                    )
                                                "
                                            >
                                                -
                                            </Button>

                                            <div class="text-center">
                                                <div
                                                    class="text-4xl font-black text-stone-950"
                                                >
                                                    {{ form.people_count }}
                                                </div>
                                                <div
                                                    class="text-sm text-stone-500"
                                                >
                                                    {{
                                                        form.people_count === 1
                                                            ? 'Guest'
                                                            : 'Guests'
                                                    }}
                                                </div>
                                            </div>

                                            <Button
                                                :disabled="
                                                    form.people_count >=
                                                    availability.booking_rules
                                                        .max_party_size
                                                "
                                                size="icon"
                                                type="button"
                                                @click="
                                                    updatePartySize(
                                                        form.people_count + 1,
                                                    )
                                                "
                                            >
                                                +
                                            </Button>
                                        </div>

                                        <p
                                            class="mt-4 text-sm leading-6 text-stone-500"
                                        >
                                            Up to
                                            {{
                                                availability.booking_rules
                                                    .max_party_size || 0
                                            }}
                                            guests can be booked online in this
                                            version.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-100 text-lg font-black text-sky-700"
                                    >
                                        3
                                    </div>
                                    <div>
                                        <h2
                                            class="text-2xl font-black tracking-tight text-stone-950"
                                        >
                                            Select Time
                                        </h2>
                                        <p class="text-sm text-stone-500">
                                            One-hour slots inside restaurant
                                            working hours.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <button
                                        v-for="slot in availability.selected_date_slots"
                                        :key="slot.value"
                                        :class="{
                                            'border-emerald-500 bg-emerald-500 text-white shadow-lg shadow-emerald-500/20':
                                                form.time === slot.value,
                                            'border-stone-200 bg-white text-stone-900 hover:border-stone-400':
                                                form.time !== slot.value,
                                        }"
                                        class="rounded-[1.25rem] border px-4 py-4 text-left transition"
                                        type="button"
                                        @click="selectTime(slot.value)"
                                    >
                                        <div class="text-lg font-black">
                                            {{ slot.label }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs tracking-[0.2em] uppercase"
                                            :class="
                                                form.time === slot.value
                                                    ? 'text-white/75'
                                                    : 'text-stone-400'
                                            "
                                        >
                                            {{ slot.available_tables_count }}
                                            tables left
                                        </div>
                                    </button>
                                </div>

                                <p
                                    v-if="
                                        form.date &&
                                        availability.selected_date_slots
                                            .length === 0
                                    "
                                    class="rounded-[1.25rem] border border-dashed border-stone-300 bg-stone-50 px-4 py-5 text-sm text-stone-500"
                                >
                                    No slots remain for the selected date and
                                    party size.
                                </p>
                            </section>

                            <section class="space-y-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-full bg-rose-100 text-lg font-black text-rose-700"
                                    >
                                        4
                                    </div>
                                    <div>
                                        <h2
                                            class="text-2xl font-black tracking-tight text-stone-950"
                                        >
                                            Choose Table
                                        </h2>
                                        <p class="text-sm text-stone-500">
                                            We only show tables that fit your
                                            group and are free.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <button
                                        v-for="table in availability.selected_time_tables"
                                        :key="table.id"
                                        :class="{
                                            'border-stone-950 bg-stone-950 text-white shadow-lg shadow-stone-950/20':
                                                form.table_id === table.id,
                                            'border-stone-200 bg-white text-stone-900 hover:border-stone-400':
                                                form.table_id !== table.id,
                                        }"
                                        class="rounded-[1.25rem] border p-4 text-left transition"
                                        type="button"
                                        @click="selectTable(table.id)"
                                    >
                                        <div class="text-lg font-black">
                                            {{ table.name }}
                                        </div>
                                        <div
                                            class="mt-1 text-sm"
                                            :class="
                                                form.table_id === table.id
                                                    ? 'text-white/75'
                                                    : 'text-stone-500'
                                            "
                                        >
                                            Up to {{ table.capacity }} guests
                                        </div>
                                    </button>
                                </div>

                                <p
                                    v-if="
                                        form.time &&
                                        availability.selected_time_tables
                                            .length === 0
                                    "
                                    class="rounded-[1.25rem] border border-dashed border-stone-300 bg-stone-50 px-4 py-5 text-sm text-stone-500"
                                >
                                    No tables remain for the selected time.
                                </p>
                                <InputError :message="form.errors.table_id" />
                            </section>

                            <section class="space-y-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-full bg-violet-100 text-lg font-black text-violet-700"
                                    >
                                        5
                                    </div>
                                    <div>
                                        <h2
                                            class="text-2xl font-black tracking-tight text-stone-950"
                                        >
                                            Guest Details
                                        </h2>
                                        <p class="text-sm text-stone-500">
                                            We only need your name and email to
                                            confirm the booking.
                                        </p>
                                    </div>
                                </div>

                                <form
                                    class="grid gap-5 md:grid-cols-2"
                                    @submit.prevent="submit"
                                >
                                    <div class="grid gap-2">
                                        <Label for="customer_name">Name</Label>
                                        <Input
                                            id="customer_name"
                                            v-model="form.customer_name"
                                            placeholder="Alex Morgan"
                                        />
                                        <InputError
                                            :message="form.errors.customer_name"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="customer_email"
                                            >Email</Label
                                        >
                                        <Input
                                            id="customer_email"
                                            v-model="form.customer_email"
                                            placeholder="alex@example.com"
                                            type="email"
                                        />
                                        <InputError
                                            :message="
                                                form.errors.customer_email
                                            "
                                        />
                                    </div>

                                    <input v-model="form.date" type="hidden" />
                                    <input v-model="form.time" type="hidden" />
                                    <input
                                        v-model="form.table_id"
                                        type="hidden"
                                    />
                                    <input
                                        v-model="form.people_count"
                                        type="hidden"
                                    />

                                    <div class="md:col-span-2">
                                        <Button
                                            :disabled="
                                                form.processing || !canSubmit
                                            "
                                            class="w-full sm:w-auto"
                                            type="submit"
                                        >
                                            Confirm reservation
                                        </Button>
                                        <InputError
                                            :message="form.errors.date"
                                            class="mt-3"
                                        />
                                        <InputError
                                            :message="form.errors.time"
                                            class="mt-2"
                                        />
                                        <p class="mt-3 text-sm text-stone-500">
                                            You will receive a confirmation
                                            email with a unique link to manage
                                            your booking.
                                        </p>
                                    </div>
                                </form>
                            </section>
                        </CardContent>
                    </Card>
                </div>

                <aside class="xl:sticky xl:top-8 xl:self-start">
                    <div
                        class="overflow-hidden rounded-[1.75rem] border border-stone-900/10 bg-[#15202f] text-white shadow-[0_36px_100px_-54px_rgba(21,32,47,0.85)]"
                    >
                        <div class="border-b border-white/10 px-6 py-5">
                            <div class="text-2xl font-black tracking-tight">
                                Booking Summary
                            </div>
                            <p class="mt-2 text-sm leading-6 text-white/70">
                                Review the current selection before you confirm.
                            </p>
                        </div>

                        <div class="space-y-5 px-6 py-6">
                            <div
                                class="flex items-start gap-3 rounded-[1.25rem] border border-white/10 bg-white/5 p-4"
                            >
                                <CalendarDays
                                    class="mt-0.5 h-4 w-4 text-emerald-300"
                                />
                                <div>
                                    <div
                                        class="text-xs tracking-[0.22em] text-white/55 uppercase"
                                    >
                                        Date
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{ formatFullDate(form.date) }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-start gap-3 rounded-[1.25rem] border border-white/10 bg-white/5 p-4"
                            >
                                <Clock3 class="mt-0.5 h-4 w-4 text-amber-300" />
                                <div>
                                    <div
                                        class="text-xs tracking-[0.22em] text-white/55 uppercase"
                                    >
                                        Time
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{ formatTimeLabel(form.time) }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-start gap-3 rounded-[1.25rem] border border-white/10 bg-white/5 p-4"
                            >
                                <UsersRound
                                    class="mt-0.5 h-4 w-4 text-sky-300"
                                />
                                <div>
                                    <div
                                        class="text-xs tracking-[0.22em] text-white/55 uppercase"
                                    >
                                        Party Size
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{ form.people_count }}
                                        {{
                                            form.people_count === 1
                                                ? 'Guest'
                                                : 'Guests'
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-start gap-3 rounded-[1.25rem] border border-white/10 bg-white/5 p-4"
                            >
                                <Mail class="mt-0.5 h-4 w-4 text-rose-300" />
                                <div>
                                    <div
                                        class="text-xs tracking-[0.22em] text-white/55 uppercase"
                                    >
                                        Table
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{
                                            selectedTable?.name ??
                                            'Choose a table'
                                        }}
                                    </div>
                                    <div
                                        v-if="selectedTable"
                                        class="text-sm text-white/65"
                                    >
                                        Capacity {{ selectedTable.capacity }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[1.25rem] border border-emerald-400/20 bg-emerald-400/10 px-4 py-4 text-sm leading-6 text-emerald-100"
                            >
                                Instant confirmation. One-hour reservation
                                slots. No extra guest account required.
                            </div>

                            <div
                                v-if="isLoadingAvailability"
                                class="text-sm text-white/70"
                            >
                                Refreshing availability...
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>
