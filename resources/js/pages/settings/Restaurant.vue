<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, store, update } from '@/routes/restaurant';
import type { BreadcrumbItem } from '@/types';
import { Check, Clock3, ImagePlus, Store, WandSparkles } from 'lucide-vue-next';

type RestaurantPayload = {
    id: number;
    name: string;
    description: string | null;
    slug: string;
    contacts: string | null;
    work_hours: string | null;
    open_time: string | null;
    close_time: string | null;
    closed_dates: string[];
    logo_path: string | null;
    cover_path: string | null;
    logo_url: string | null;
    cover_url: string | null;
};

type Props = {
    restaurant: RestaurantPayload | null;
    isOnboarding: boolean;
    status?: string;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Restaurant settings',
        href: edit(),
    },
];

const slugTouched = ref(Boolean(props.restaurant?.slug));
const logoPreviewUrl = ref<string | null>(props.restaurant?.logo_url ?? null);
const coverPreviewUrl = ref<string | null>(props.restaurant?.cover_url ?? null);

const form = useForm({
    name: props.restaurant?.name ?? '',
    description: props.restaurant?.description ?? '',
    slug: props.restaurant?.slug ?? '',
    contacts: props.restaurant?.contacts ?? '',
    work_hours: props.restaurant?.work_hours ?? '',
    open_time: props.restaurant?.open_time ?? '',
    close_time: props.restaurant?.close_time ?? '',
    closed_dates:
        props.restaurant && props.restaurant.closed_dates.length !== 0
            ? [...props.restaurant.closed_dates]
            : [''],
    logo: null as File | null,
    cover: null as File | null,
});

const submitLabel = computed(() =>
    props.isOnboarding ? 'Save and continue' : 'Save changes',
);

const statusMessage = computed(() => {
    if (props.status === 'restaurant-created') {
        return 'Restaurant created. You can now access the rest of the system.';
    }

    if (props.status === 'restaurant-updated') {
        return 'Restaurant settings updated.';
    }

    return null;
});

const generatedSlug = computed(() => {
    return slugify(form.slug || form.name);
});

watch(
    () => form.name,
    (value) => {
        if (!slugTouched.value) {
            form.slug = slugify(value);
        }
    },
);

watch(
    () => form.logo,
    (file) => {
        updatePreview(file, 'logo');
    },
);

watch(
    () => form.cover,
    (file) => {
        updatePreview(file, 'cover');
    },
);

onBeforeUnmount(() => {
    revokePreview(logoPreviewUrl.value, props.restaurant?.logo_url ?? null);
    revokePreview(coverPreviewUrl.value, props.restaurant?.cover_url ?? null);
});

function submit(): void {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'cover');
        },
    };

    if (props.restaurant) {
        form.transform((data) => ({
            ...data,
            _method: 'patch',
        })).post(update.url(), options);

        return;
    }

    form.post(store.url(), options);
}

function addClosedDate(): void {
    form.closed_dates.push('');
}

function removeClosedDate(index: number): void {
    form.closed_dates.splice(index, 1);

    if (form.closed_dates.length === 0) {
        form.closed_dates.push('');
    }
}

function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function updatePreview(file: File | null, kind: 'logo' | 'cover'): void {
    const currentUrl = kind === 'logo' ? logoPreviewUrl.value : coverPreviewUrl.value;
    const originalUrl =
        kind === 'logo'
            ? (props.restaurant?.logo_url ?? null)
            : (props.restaurant?.cover_url ?? null);

    revokePreview(currentUrl, originalUrl);

    const nextUrl = file ? URL.createObjectURL(file) : originalUrl;

    if (kind === 'logo') {
        logoPreviewUrl.value = nextUrl;
    } else {
        coverPreviewUrl.value = nextUrl;
    }
}

function revokePreview(value: string | null, original: string | null): void {
    if (value && value !== original) {
        URL.revokeObjectURL(value);
    }
}
</script>

<template>
    <Head title="Restaurant settings" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <SettingsLayout full-width>
            <div class="space-y-8">
                <div
                    class="relative overflow-hidden rounded-[2rem] border border-border/70 bg-gradient-to-br from-stone-950 via-zinc-900 to-amber-950 px-6 py-8 text-stone-50 shadow-[0_32px_120px_-48px_rgba(17,12,8,0.9)]"
                >
                    <div
                        class="absolute -right-16 top-4 h-40 w-40 rounded-full bg-amber-400/15 blur-3xl"
                    />
                    <div
                        class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-amber-200/50 to-transparent"
                    />

                    <div class="relative space-y-4">
                        <Badge
                            variant="secondary"
                            class="border border-white/10 bg-white/10 text-stone-50"
                        >
                            <Store class="mr-2 h-3.5 w-3.5" />
                            {{ isOnboarding ? 'First-time setup' : 'Restaurant profile' }}
                        </Badge>

                        <div class="space-y-2">
                            <h1
                                class="font-serif text-3xl leading-tight tracking-[0.02em] md:text-4xl"
                            >
                                {{
                                    isOnboarding
                                        ? 'Create the restaurant your team will manage'
                                        : 'Keep your restaurant details accurate and public-ready'
                                }}
                            </h1>
                            <p class="max-w-2xl text-sm leading-6 text-stone-200/80">
                                {{
                                    isOnboarding
                                        ? 'This is the only required step before the rest of the admin area opens up. Fill in the core profile now and refine it later.'
                                        : 'This page powers your public menu identity and reservation-facing business details. Save here to keep the customer-facing profile in sync.'
                                }}
                            </p>
                        </div>

                        <div class="grid gap-3 text-xs text-stone-200/75 md:grid-cols-3">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div class="mb-2 flex items-center gap-2 text-stone-50">
                                    <WandSparkles class="h-4 w-4" />
                                    Identity
                                </div>
                                Name and slug become the foundation of your public presence.
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div class="mb-2 flex items-center gap-2 text-stone-50">
                                    <ImagePlus class="h-4 w-4" />
                                    Visuals
                                </div>
                                Logo and cover make the admin record useful for later menu pages.
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div class="mb-2 flex items-center gap-2 text-stone-50">
                                    <Clock3 class="h-4 w-4" />
                                    Schedule
                                </div>
                                Add the opening hours you want guests to see first.
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="statusMessage" class="rounded-2xl">
                    <Alert class="border-emerald-200 bg-emerald-50 text-emerald-900">
                        <Check class="h-4 w-4" />
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>{{ statusMessage }}</AlertDescription>
                    </Alert>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,2.2fr)_minmax(22rem,1fr)]">
                    <Card class="rounded-[1.75rem] border-border/70 shadow-sm">
                        <CardHeader class="space-y-2">
                            <CardTitle class="text-xl">Core restaurant settings</CardTitle>
                            <CardDescription>
                                The same screen handles first creation and future edits.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <form class="space-y-8" @submit.prevent="submit">
                                <section class="grid gap-6 md:grid-cols-2">
                                    <div class="grid gap-2 md:col-span-2">
                                        <Label for="name">Restaurant name</Label>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            placeholder="Casa Atlantica"
                                        />
                                        <InputError :message="form.errors.name" />
                                    </div>

                                    <div class="grid gap-2 md:col-span-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label for="slug">Slug</Label>
                                            <span class="text-xs text-muted-foreground">
                                                Preview: /{{ generatedSlug || 'your-restaurant' }}
                                            </span>
                                        </div>
                                        <Input
                                            id="slug"
                                            v-model="form.slug"
                                            placeholder="auto-generated-from-name"
                                            @focus="slugTouched = true"
                                        />
                                        <InputError :message="form.errors.slug" />
                                    </div>

                                    <div class="grid gap-2 md:col-span-2">
                                        <Label for="description">Description</Label>
                                        <textarea
                                            id="description"
                                            v-model="form.description"
                                            rows="4"
                                            class="min-h-28 rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20"
                                            placeholder="Short restaurant story, positioning or concept."
                                        />
                                        <InputError :message="form.errors.description" />
                                    </div>
                                </section>

                                <section class="grid gap-6 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="open_time">Opening time</Label>
                                        <Input
                                            id="open_time"
                                            v-model="form.open_time"
                                            type="time"
                                        />
                                        <InputError :message="form.errors.open_time" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="close_time">Closing time</Label>
                                        <Input
                                            id="close_time"
                                            v-model="form.close_time"
                                            type="time"
                                        />
                                        <InputError :message="form.errors.close_time" />
                                    </div>

                                    <div class="grid gap-2 md:col-span-2">
                                        <Label for="work_hours">Working hours notes</Label>
                                        <textarea
                                            id="work_hours"
                                            v-model="form.work_hours"
                                            rows="4"
                                            class="min-h-28 rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20"
                                            placeholder="Mon-Fri 12:00-23:00, Sat-Sun 10:00-00:00"
                                        />
                                        <InputError :message="form.errors.work_hours" />
                                    </div>

                                    <div class="grid gap-3 md:col-span-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <Label>Closed dates</Label>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="addClosedDate"
                                            >
                                                Add date
                                            </Button>
                                        </div>

                                        <div
                                            v-for="(date, index) in form.closed_dates"
                                            :key="`${index}-${date}`"
                                            class="flex items-start gap-3"
                                        >
                                            <Input
                                                v-model="form.closed_dates[index]"
                                                type="date"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="removeClosedDate(index)"
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                        <InputError :message="form.errors.closed_dates" />
                                    </div>
                                </section>

                                <section class="grid gap-6 md:grid-cols-2">
                                    <div class="grid gap-2 md:col-span-2">
                                        <Label for="contacts">Contacts</Label>
                                        <textarea
                                            id="contacts"
                                            v-model="form.contacts"
                                            rows="4"
                                            class="min-h-28 rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20"
                                            placeholder="Phone, email, address or any admin notes you want to keep together."
                                        />
                                        <InputError :message="form.errors.contacts" />
                                    </div>

                                    <div class="grid gap-3">
                                        <Label for="logo">Logo</Label>
                                        <Input
                                            id="logo"
                                            type="file"
                                            accept=".png,.jpg,.jpeg,.webp"
                                            @input="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                        />
                                        <InputError :message="form.errors.logo" />
                                    </div>

                                    <div class="grid gap-3">
                                        <Label for="cover">Cover</Label>
                                        <Input
                                            id="cover"
                                            type="file"
                                            accept=".png,.jpg,.jpeg,.webp"
                                            @input="form.cover = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                        />
                                        <InputError :message="form.errors.cover" />
                                    </div>
                                </section>

                                <div class="flex items-center gap-4">
                                    <Button :disabled="form.processing">
                                        {{ form.processing ? 'Saving...' : submitLabel }}
                                    </Button>
                                    <span class="text-sm text-muted-foreground">
                                        {{
                                            isOnboarding
                                                ? 'Once saved, dashboard access is unlocked.'
                                                : 'Changes apply immediately to the stored restaurant profile.'
                                        }}
                                    </span>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <div class="space-y-6">
                        <Card class="rounded-[1.75rem] border-border/70 bg-stone-50/70">
                            <CardHeader>
                                <CardTitle class="text-lg">Visual preview</CardTitle>
                                <CardDescription>
                                    Current assets for the restaurant profile.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="space-y-2">
                                    <p class="text-xs font-medium uppercase tracking-[0.22em] text-muted-foreground">
                                        Cover
                                    </p>
                                    <div
                                        class="aspect-[16/10] overflow-hidden rounded-2xl border border-dashed border-border bg-stone-950/95"
                                    >
                                        <img
                                            v-if="coverPreviewUrl"
                                            :src="coverPreviewUrl"
                                            alt="Cover preview"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full items-center justify-center px-6 text-center text-sm text-stone-300"
                                        >
                                            Upload a cover image to define the public mood of the restaurant.
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <p class="text-xs font-medium uppercase tracking-[0.22em] text-muted-foreground">
                                        Logo
                                    </p>
                                    <div
                                        class="flex min-h-28 items-center justify-center rounded-2xl border border-dashed border-border bg-background p-6"
                                    >
                                        <img
                                            v-if="logoPreviewUrl"
                                            :src="logoPreviewUrl"
                                            alt="Logo preview"
                                            class="max-h-20 rounded-xl object-contain"
                                        />
                                        <div
                                            v-else
                                            class="text-center text-sm text-muted-foreground"
                                        >
                                            No logo uploaded yet.
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="rounded-[1.75rem] border-border/70">
                            <CardHeader>
                                <CardTitle class="text-lg">Onboarding checklist</CardTitle>
                                <CardDescription>
                                    Keep the first pass lean. You can refine details later.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3 text-sm text-muted-foreground">
                                <div class="flex gap-3 rounded-2xl border border-border/70 p-4">
                                    <Check class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    Add the restaurant name and review the slug preview.
                                </div>
                                <div class="flex gap-3 rounded-2xl border border-border/70 p-4">
                                    <Check class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    Set the core opening and closing times guests will rely on.
                                </div>
                                <div class="flex gap-3 rounded-2xl border border-border/70 p-4">
                                    <Check class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    Upload visuals now or return later without losing access.
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
