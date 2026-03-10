<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, Plus, Salad, Tags } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as categoriesIndex, store as storeCategory } from '@/routes/admin/categories';
import { create, edit, index, store, update } from '@/routes/admin/dishes';
import type { BreadcrumbItem } from '@/types';

type CategoryOption = {
    id: number;
    name: string;
    position: number;
};

type DishPayload = {
    id: number;
    name: string;
    description: string | null;
    weight: string;
    price: string;
    is_active: boolean;
    image_path: string | null;
    image_url: string | null;
    category_ids: string[];
};

const props = defineProps<{
    dish: DishPayload | null;
    categories: CategoryOption[];
}>();

const isEditing = props.dish !== null;
const categoryOptions = ref<CategoryOption[]>([...props.categories]);
const isCategoryDialogOpen = ref(false);
const isCreatingCategory = ref(false);
const inlineCategoryErrors = ref<Record<string, string[]>>({});
const imagePreviewUrl = ref<string | null>(props.dish?.image_url ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dishes',
        href: index(),
    },
    {
        title: isEditing ? 'Edit dish' : 'Create dish',
        href: isEditing ? edit(props.dish.id) : create(),
    },
];

const form = useForm({
    name: props.dish?.name ?? '',
    description: props.dish?.description ?? '',
    weight: props.dish?.weight ?? '',
    price: props.dish?.price ?? '',
    image: null as File | null,
    is_active: props.dish?.is_active ?? true,
    category_ids: props.dish?.category_ids ?? [],
});

const inlineCategoryForm = useForm({
    name: '',
    description: '',
});

const sortedCategories = computed(() =>
    [...categoryOptions.value].sort((left, right) => {
        if (left.position !== right.position) {
            return left.position - right.position;
        }

        return left.name.localeCompare(right.name);
    }),
);

watch(
    () => form.image,
    (file) => {
        const original = props.dish?.image_url ?? null;

        if (imagePreviewUrl.value && imagePreviewUrl.value !== original) {
            URL.revokeObjectURL(imagePreviewUrl.value);
        }

        imagePreviewUrl.value = file ? URL.createObjectURL(file) : original;
    },
);

onBeforeUnmount(() => {
    const original = props.dish?.image_url ?? null;

    if (imagePreviewUrl.value && imagePreviewUrl.value !== original) {
        URL.revokeObjectURL(imagePreviewUrl.value);
    }
});

function submit(): void {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('image');
        },
    };

    if (props.dish) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
            is_active: data.is_active ? 1 : 0,
        })).post(update(props.dish.id).url, options);

        return;
    }

    form.transform((data) => ({
        ...data,
        is_active: data.is_active ? 1 : 0,
    })).post(store().url, options);
}

function toggleCategory(categoryId: number): void {
    const value = String(categoryId);

    form.category_ids = form.category_ids.includes(value)
        ? form.category_ids.filter((item) => item !== value)
        : [...form.category_ids, value];
}

async function createCategoryInline(): Promise<void> {
    inlineCategoryErrors.value = {};
    isCreatingCategory.value = true;

    try {
        const response = await fetch(storeCategory().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name: inlineCategoryForm.name,
                description: inlineCategoryForm.description,
            }),
        });

        if (response.status === 422) {
            const payload = (await response.json()) as { errors?: Record<string, string[]> };
            inlineCategoryErrors.value = payload.errors ?? {};
            return;
        }

        if (!response.ok) {
            inlineCategoryErrors.value = {
                name: ['Unable to create category right now.'],
            };
            return;
        }

        const payload = (await response.json()) as {
            category: CategoryOption & { description?: string | null };
        };

        categoryOptions.value = [...categoryOptions.value, payload.category];
        form.category_ids = [...form.category_ids, String(payload.category.id)];
        inlineCategoryForm.reset();
        isCategoryDialogOpen.value = false;
    } finally {
        isCreatingCategory.value = false;
    }
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="isEditing ? 'Edit dish' : 'Create dish'" />

        <div class="space-y-6 px-4 py-6">
            <div
                class="relative overflow-hidden rounded-[1.75rem] border border-border/70 bg-gradient-to-br from-emerald-950 via-zinc-900 to-amber-950 px-6 py-8 text-stone-50 shadow-[0_24px_90px_-52px_rgba(17,12,8,0.9)]"
            >
                <div class="absolute -top-8 right-0 h-32 w-32 rounded-full bg-amber-300/15 blur-3xl" />
                <div class="relative space-y-4">
                    <Badge
                        variant="secondary"
                        class="border border-white/10 bg-white/10 text-stone-50"
                    >
                        <Salad class="mr-2 h-3.5 w-3.5" />
                        {{ isEditing ? 'Edit dish' : 'Menu entry' }}
                    </Badge>
                    <Heading
                        :title="isEditing ? 'Adjust the dish details' : 'Add a new dish to the menu'"
                        :description="
                            isEditing
                                ? 'Update pricing, categories, visibility and visual assets without removing the dish.'
                                : 'Create a dish record with categories, pricing and an image so it is ready for the public menu.'
                        "
                        class="text-stone-50"
                    />
                </div>
            </div>

            <form class="grid gap-6 xl:grid-cols-[1.35fr_0.85fr]" @submit.prevent="submit">
                <Card class="border-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Dish details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" placeholder="Charred octopus" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="description">Description</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
                                    class="min-h-32 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="Describe ingredients, preparation or serving style."
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="weight">Weight</Label>
                                <Input id="weight" v-model="form.weight" placeholder="240 g" />
                                <InputError :message="form.errors.weight" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="price">Price</Label>
                                <Input
                                    id="price"
                                    v-model="form.price"
                                    inputmode="decimal"
                                    placeholder="14.50"
                                />
                                <InputError :message="form.errors.price" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card class="border-border/70 shadow-sm">
                        <CardHeader class="flex flex-row items-start justify-between gap-4">
                            <div class="space-y-1">
                                <CardTitle class="flex items-center gap-2">
                                    <Tags class="h-4 w-4" />
                                    Categories
                                </CardTitle>
                                <p class="text-sm text-muted-foreground">
                                    Choose one or more categories for this dish.
                                </p>
                            </div>

                            <Dialog v-model:open="isCategoryDialogOpen">
                                <DialogTrigger as-child>
                                    <Button size="sm" type="button" variant="outline">
                                        <Plus class="mr-2 h-4 w-4" />
                                        New category
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Create category inline</DialogTitle>
                                        <DialogDescription>
                                            The category is created for the current restaurant and selected immediately for this dish.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div class="space-y-4">
                                        <div class="grid gap-2">
                                            <Label for="inline-category-name">Name</Label>
                                            <Input
                                                id="inline-category-name"
                                                v-model="inlineCategoryForm.name"
                                                placeholder="Chef specials"
                                            />
                                            <InputError :message="inlineCategoryErrors.name?.[0]" />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="inline-category-description">Description</Label>
                                            <textarea
                                                id="inline-category-description"
                                                v-model="inlineCategoryForm.description"
                                                rows="4"
                                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                placeholder="Optional note."
                                            />
                                            <InputError :message="inlineCategoryErrors.description?.[0]" />
                                        </div>
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            :disabled="isCreatingCategory"
                                            type="button"
                                            @click="createCategoryInline"
                                        >
                                            Create category
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Alert v-if="sortedCategories.length === 0">
                                <AlertTitle>No categories yet</AlertTitle>
                                <AlertDescription>
                                    Create the first category inline or open the full categories section.
                                    <Link class="underline underline-offset-4" :href="categoriesIndex().url">
                                        Manage categories
                                    </Link>
                                </AlertDescription>
                            </Alert>

                            <div v-else class="grid gap-3">
                                <button
                                    v-for="category in sortedCategories"
                                    :key="category.id"
                                    class="flex items-start justify-between rounded-xl border px-4 py-3 text-left transition-colors"
                                    :class="
                                        form.category_ids.includes(String(category.id))
                                            ? 'border-emerald-500 bg-emerald-500/8'
                                            : 'border-border/70 bg-background hover:border-foreground/20'
                                    "
                                    type="button"
                                    @click="toggleCategory(category.id)"
                                >
                                    <div>
                                        <div class="font-medium">{{ category.name }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            Position {{ category.position }}
                                        </div>
                                    </div>
                                    <Badge variant="outline">
                                        {{ form.category_ids.includes(String(category.id)) ? 'Selected' : 'Choose' }}
                                    </Badge>
                                </button>
                            </div>

                            <InputError :message="form.errors.category_ids" />
                        </CardContent>
                    </Card>

                    <Card class="border-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <ImagePlus class="h-4 w-4" />
                                Media and visibility
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div class="grid gap-2">
                                <Label for="image">Dish image</Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/png,image/jpeg,image/jpg,image/webp"
                                    @input="form.image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                />
                                <p class="text-xs text-muted-foreground">
                                    JPEG, PNG or WebP. Up to 4 MB.
                                </p>
                                <InputError :message="form.errors.image" />
                            </div>

                            <div
                                class="overflow-hidden rounded-2xl border border-dashed border-border/70 bg-muted/40"
                            >
                                <img
                                    v-if="imagePreviewUrl"
                                    :src="imagePreviewUrl"
                                    alt="Dish preview"
                                    class="h-48 w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-48 items-center justify-center text-sm text-muted-foreground"
                                >
                                    No image selected
                                </div>
                            </div>

                            <label
                                class="flex items-start gap-3 rounded-xl border border-border/70 bg-muted/30 px-4 py-3"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-border"
                                />
                                <div>
                                    <div class="font-medium">Visible in public menu</div>
                                    <div class="text-sm text-muted-foreground">
                                        Disable this to hide the dish without deleting it.
                                    </div>
                                </div>
                            </label>

                            <div class="flex items-center gap-3">
                                <Button :disabled="form.processing" type="submit">
                                    {{ isEditing ? 'Save dish' : 'Create dish' }}
                                </Button>
                                <Button as-child type="button" variant="ghost">
                                    <Link :href="index().url">Back to list</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
