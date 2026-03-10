<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { EyeOff, Pencil, Plus, Salad, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, destroy, edit, index } from '@/routes/admin/dishes';
import type { BreadcrumbItem } from '@/types';

type DishPayload = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    weight: string;
    is_active: boolean;
    image_url: string | null;
    categories: Array<{
        id: number;
        name: string;
    }>;
};

const props = defineProps<{
    dishes: DishPayload[];
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dishes',
        href: index(),
    },
];

const statusMessage = computed(() => {
    if (props.status === 'dish-created') {
        return 'Dish created.';
    }

    if (props.status === 'dish-updated') {
        return 'Dish updated.';
    }

    if (props.status === 'dish-deleted') {
        return 'Dish deleted.';
    }

    return null;
});

function removeDish(dishId: number): void {
    if (!window.confirm('Delete this dish?')) {
        return;
    }

    router.delete(destroy(dishId).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dishes" />

        <div class="space-y-6 px-4 py-6">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <Heading
                    title="Dishes"
                    description="Manage pricing, categories and menu visibility."
                />

                <Button as-child>
                    <Link :href="create().url">
                        <Plus class="mr-2 h-4 w-4" />
                        Create dish
                    </Link>
                </Button>
            </div>

            <Alert v-if="statusMessage">
                <AlertTitle>Update</AlertTitle>
                <AlertDescription>{{ statusMessage }}</AlertDescription>
            </Alert>

            <div
                v-if="dishes.length === 0"
                class="rounded-[1.5rem] border border-dashed border-border/70 bg-muted/20 px-6 py-10 text-center"
            >
                <Salad class="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
                <h2 class="text-lg font-semibold">No dishes yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Add dishes with categories, pricing and images to build the menu.
                </p>
            </div>

            <Table v-else>
                <TableHeader>
                    <tr>
                        <TableHead>Thumbnail</TableHead>
                        <TableHead>Dish</TableHead>
                        <TableHead>Categories</TableHead>
                        <TableHead>Price</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </tr>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="dish in dishes" :key="dish.id">
                        <TableCell>
                            <div
                                class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-border/70 bg-muted/40"
                            >
                                <img
                                    v-if="dish.image_url"
                                    :src="dish.image_url"
                                    :alt="dish.name"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="text-lg font-semibold uppercase text-muted-foreground"
                                >
                                    {{ dish.name.slice(0, 1) }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="space-y-2">
                                <div class="font-semibold text-foreground">
                                    {{ dish.name }}
                                </div>
                                <div class="max-w-md text-sm text-muted-foreground">
                                    {{ dish.description || 'No description provided.' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ dish.weight }} · ID {{ dish.id }}
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex max-w-sm flex-wrap gap-2">
                                <Badge
                                    v-for="category in dish.categories"
                                    :key="category.id"
                                    variant="secondary"
                                    class="rounded-full px-3 py-1"
                                >
                                    {{ category.name }}
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="font-semibold text-foreground">
                                €{{ dish.price }}
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="dish.is_active ? 'default' : 'secondary'"
                                class="rounded-full px-3 py-1"
                            >
                                <EyeOff v-if="!dish.is_active" class="mr-1.5 h-3.5 w-3.5" />
                                {{ dish.is_active ? 'Visible' : 'Hidden' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="edit(dish.id).url">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>

                                <Button size="sm" variant="destructive" @click="removeDish(dish.id)">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
