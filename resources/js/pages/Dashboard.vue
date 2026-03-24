<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChefHat,
    Clock3,
    LayoutGrid,
    Sparkles,
    Table2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as dishesIndex } from '@/routes/admin/dishes';
import { index as reservationsIndex } from '@/routes/admin/reservations';
import { index as tablesIndex } from '@/routes/admin/tables';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type DashboardStats = {
    dishes_count: number;
    tables_count: number;
    active_reservations_count: number;
};

type ReservationTrendPoint = {
    date: string;
    label: string;
    count: number;
};

type DashboardPageProps = {
    auth?: {
        restaurant?: {
            name?: string | null;
        } | null;
    };
};

const props = defineProps<{
    stats: DashboardStats;
    reservation_trend: ReservationTrendPoint[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const page = usePage<DashboardPageProps>();

const restaurantName = computed(
    () => page.props.auth?.restaurant?.name ?? 'Your restaurant',
);

const maxTrendCount = computed(() =>
    props.reservation_trend.reduce((max, point) => Math.max(max, point.count), 0),
);

const hasTrendData = computed(() => maxTrendCount.value > 0);

const chartPoints = computed(() => {
    if (props.reservation_trend.length === 0) {
        return '';
    }

    const width = 100;
    const height = 100;
    const denominator = Math.max(props.reservation_trend.length - 1, 1);
    const maxCount = Math.max(maxTrendCount.value, 1);

    return props.reservation_trend
        .map((point, index) => {
            const x = (index / denominator) * width;
            const y = height - (point.count / maxCount) * height;

            return `${x},${y}`;
        })
        .join(' ');
});

const areaPoints = computed(() => {
    if (chartPoints.value === '') {
        return '';
    }

    return `0,100 ${chartPoints.value} 100,100`;
});

const trendSummary = computed(() =>
    props.reservation_trend.reduce((sum, point) => sum + point.count, 0),
);

const peakDay = computed(() =>
    props.reservation_trend.reduce<ReservationTrendPoint | null>((peak, point) => {
        if (peak === null || point.count > peak.count) {
            return point;
        }

        return peak;
    }, null),
);

const trendLabels = computed(() =>
    props.reservation_trend.filter(
        (_, index) => index % 6 === 0 || index === props.reservation_trend.length - 1,
    ),
);

const recentTrend = computed(() => [...props.reservation_trend.slice(-5)].reverse());

const statCards = computed(() => [
    {
        title: 'Dishes',
        value: props.stats.dishes_count,
        description: 'Items currently available in the restaurant menu.',
        href: dishesIndex().url,
        action: 'Manage menu',
        icon: ChefHat,
        accent: 'from-amber-500/20 via-amber-500/5 to-transparent',
    },
    {
        title: 'Tables',
        value: props.stats.tables_count,
        description: 'Registered tables ready for reservations and floor planning.',
        href: tablesIndex().url,
        action: 'Open tables',
        icon: Table2,
        accent: 'from-emerald-500/20 via-emerald-500/5 to-transparent',
    },
    {
        title: 'Active reservations',
        value: props.stats.active_reservations_count,
        description: 'Upcoming active bookings from today onward.',
        href: reservationsIndex().url,
        action: 'Review bookings',
        icon: Clock3,
        accent: 'from-sky-500/20 via-sky-500/5 to-transparent',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-6">
            <section class="relative overflow-hidden rounded-[2rem] border border-border/70 bg-card shadow-sm">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(251,191,36,0.18),transparent_32%),radial-gradient(circle_at_85%_20%,rgba(34,197,94,0.16),transparent_24%),linear-gradient(135deg,rgba(15,23,42,0.03),transparent_55%)]" />

                <div class="relative flex flex-col gap-6 p-6 md:p-8 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl space-y-4">
                        <Badge variant="secondary" class="rounded-full px-3 py-1 text-xs tracking-[0.2em] uppercase">
                            <Sparkles class="mr-2 h-3.5 w-3.5" />
                            Restaurant snapshot
                        </Badge>

                        <div class="space-y-3">
                            <h1 class="max-w-xl text-3xl leading-tight font-semibold tracking-tight text-foreground md:text-4xl">
                                {{ restaurantName }}
                            </h1>
                            <p class="max-w-xl text-sm leading-6 text-muted-foreground md:text-base">
                                A live operational overview of menu volume, floor capacity and booking momentum over the last 30 days.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <Button as-child class="rounded-full px-5">
                            <Link :href="reservationsIndex().url">
                                <CalendarDays class="mr-2 h-4 w-4" />
                                Open reservations
                            </Link>
                        </Button>

                        <Button as-child variant="outline" class="rounded-full px-5">
                            <Link :href="dishesIndex().url">
                                <LayoutGrid class="mr-2 h-4 w-4" />
                                Update menu
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <Card
                    v-for="card in statCards"
                    :key="card.title"
                    class="relative overflow-hidden rounded-[1.75rem] border border-border/70 bg-card shadow-sm"
                >
                    <div class="absolute inset-0 bg-gradient-to-br opacity-90" :class="card.accent" />

                    <CardHeader class="relative flex flex-row items-start justify-between space-y-0 pb-3">
                        <div class="space-y-1">
                            <CardDescription class="text-xs tracking-[0.2em] uppercase">
                                {{ card.title }}
                            </CardDescription>
                            <CardTitle class="text-4xl font-semibold tracking-tight">
                                {{ card.value }}
                            </CardTitle>
                        </div>

                        <div class="rounded-2xl border border-border/60 bg-background/80 p-3 backdrop-blur">
                            <component :is="card.icon" class="h-5 w-5 text-foreground" />
                        </div>
                    </CardHeader>

                    <CardContent class="relative space-y-4">
                        <p class="min-h-12 text-sm leading-6 text-muted-foreground">
                            {{ card.description }}
                        </p>

                        <Button as-child variant="ghost" class="h-auto rounded-full px-0 text-sm font-medium">
                            <Link :href="card.href">
                                {{ card.action }}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </section>

            <Card class="overflow-hidden rounded-[2rem] border border-border/70 bg-card shadow-sm">
                <CardHeader class="border-b border-border/60 pb-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="space-y-2">
                            <CardTitle class="text-2xl font-semibold tracking-tight">
                                Reservation activity
                            </CardTitle>
                            <CardDescription class="max-w-2xl text-sm leading-6">
                                Daily active reservations for the last 30 days, grouped by reservation date.
                            </CardDescription>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-border/60 bg-muted/20 px-4 py-3">
                                <div class="text-xs tracking-[0.2em] uppercase text-muted-foreground">
                                    30-day total
                                </div>
                                <div class="mt-2 text-2xl font-semibold">
                                    {{ trendSummary }}
                                </div>
                            </div>

                            <div class="rounded-2xl border border-border/60 bg-muted/20 px-4 py-3">
                                <div class="text-xs tracking-[0.2em] uppercase text-muted-foreground">
                                    Peak day
                                </div>
                                <div class="mt-2 text-2xl font-semibold">
                                    {{ peakDay?.count ?? 0 }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ peakDay?.label ?? 'No reservations yet' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-6">
                    <div
                        v-if="hasTrendData"
                        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_220px]"
                    >
                        <div class="space-y-4">
                            <div class="relative h-72 overflow-hidden rounded-[1.5rem] border border-border/60 bg-[linear-gradient(180deg,rgba(251,191,36,0.08),transparent_35%),linear-gradient(0deg,rgba(15,23,42,0.02),rgba(15,23,42,0.02))] p-4">
                                <div class="absolute inset-x-4 top-1/4 border-t border-dashed border-border/60" />
                                <div class="absolute inset-x-4 top-2/4 border-t border-dashed border-border/60" />
                                <div class="absolute inset-x-4 top-3/4 border-t border-dashed border-border/60" />

                                <svg
                                    viewBox="0 0 100 100"
                                    preserveAspectRatio="none"
                                    class="h-full w-full"
                                    aria-label="Reservation trend chart"
                                    role="img"
                                >
                                    <defs>
                                        <linearGradient id="trend-fill" x1="0" x2="0" y1="0" y2="1">
                                            <stop offset="0%" stop-color="currentColor" stop-opacity="0.32" />
                                            <stop offset="100%" stop-color="currentColor" stop-opacity="0.02" />
                                        </linearGradient>
                                    </defs>

                                    <polygon
                                        :points="areaPoints"
                                        fill="url(#trend-fill)"
                                        class="text-amber-500"
                                    />

                                    <polyline
                                        :points="chartPoints"
                                        fill="none"
                                        class="text-amber-500"
                                        stroke="currentColor"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2.5"
                                        vector-effect="non-scaling-stroke"
                                    />

                                    <circle
                                        v-for="(point, index) in reservation_trend"
                                        :key="point.date"
                                        :cx="reservation_trend.length === 1 ? 50 : (index / (reservation_trend.length - 1)) * 100"
                                        :cy="100 - (point.count / Math.max(maxTrendCount, 1)) * 100"
                                        r="1.8"
                                        class="fill-amber-500 stroke-background"
                                        stroke-width="1.2"
                                        vector-effect="non-scaling-stroke"
                                    />
                                </svg>
                            </div>

                            <div class="grid grid-cols-5 gap-2 text-xs text-muted-foreground md:grid-cols-6">
                                <div
                                    v-for="point in trendLabels"
                                    :key="point.date"
                                    class="truncate"
                                >
                                    {{ point.label }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="rounded-[1.5rem] border border-border/60 bg-muted/15 p-4">
                                <div class="text-xs tracking-[0.2em] uppercase text-muted-foreground">
                                    Highest day
                                </div>
                                <div class="mt-2 text-3xl font-semibold">
                                    {{ peakDay?.count ?? 0 }}
                                </div>
                                <div class="mt-1 text-sm text-muted-foreground">
                                    {{ peakDay?.label ?? 'No peak detected' }}
                                </div>
                            </div>

                            <div class="space-y-2 rounded-[1.5rem] border border-border/60 bg-muted/15 p-4">
                                <div class="text-xs tracking-[0.2em] uppercase text-muted-foreground">
                                    Recent days
                                </div>

                                <div
                                    v-for="point in recentTrend"
                                    :key="point.date"
                                    class="flex items-center justify-between rounded-2xl bg-background/80 px-3 py-2"
                                >
                                    <span class="text-sm text-muted-foreground">{{ point.label }}</span>
                                    <span class="text-sm font-semibold text-foreground">{{ point.count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex min-h-72 flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-border/70 bg-muted/15 px-6 text-center"
                    >
                        <CalendarDays class="mb-4 h-10 w-10 text-muted-foreground" />
                        <h3 class="text-lg font-semibold">No active reservations yet</h3>
                        <p class="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            Once bookings start coming in, the dashboard will show day-by-day activity for the last 30 days.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
