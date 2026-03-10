<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
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
import { create, destroy, edit, index } from '@/routes/admin/categories';
import type { BreadcrumbItem } from '@/types';

type CategoryPayload = {
    id: number;
    name: string;
    description: string | null;
    position: number;
    dishes_count: number;
};

const props = defineProps<{
    categories: CategoryPayload[];
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Categories',
        href: index(),
    },
];

const statusMessage = computed(() => {
    if (props.status === 'category-created') {
        return 'Category created.';
    }

    if (props.status === 'category-updated') {
        return 'Category updated.';
    }

    if (props.status === 'category-deleted') {
        return 'Category deleted.';
    }

    if (props.status === 'category-delete-blocked') {
        return 'Category cannot be deleted while it contains dishes.';
    }

    return null;
});

function removeCategory(categoryId: number, dishesCount: number): void {
    if (dishesCount > 0) {
        return;
    }

    if (!window.confirm('Delete this category?')) {
        return;
    }

    router.delete(destroy(categoryId).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Categories" />

        <div class="space-y-6 px-4 py-6">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <Heading
                    title="Categories"
                    description="Manage menu groups and the order they appear in."
                />

                <Button as-child>
                    <Link :href="create().url">
                        <Plus class="mr-2 h-4 w-4" />
                        Create category
                    </Link>
                </Button>
            </div>

            <Alert v-if="statusMessage">
                <AlertTitle>Update</AlertTitle>
                <AlertDescription>{{ statusMessage }}</AlertDescription>
            </Alert>

            <div
                v-if="categories.length === 0"
                class="rounded-[1.5rem] border border-dashed border-border/70 bg-muted/20 px-6 py-10 text-center"
            >
                <Tags class="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
                <h2 class="text-lg font-semibold">No categories yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Create the first category to structure dishes in the admin area and public menu.
                </p>
            </div>

            <Table v-else>
                <TableHeader>
                    <tr>
                        <TableHead>Position</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Dishes</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </tr>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="category in categories" :key="category.id">
                        <TableCell>
                            <Badge
                                variant="outline"
                                class="rounded-full border-stone-300/70 px-3 py-1"
                            >
                                #{{ category.position }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="space-y-2">
                                <div class="font-semibold text-foreground">
                                    {{ category.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    ID {{ category.id }}
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="max-w-xl text-muted-foreground">
                            {{ category.description || 'No description provided.' }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="category.dishes_count > 0 ? 'secondary' : 'outline'"
                                class="rounded-full px-3 py-1"
                            >
                                {{ category.dishes_count }} {{ category.dishes_count === 1 ? 'dish' : 'dishes' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="edit(category.id).url">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    :disabled="category.dishes_count > 0"
                                    size="sm"
                                    variant="destructive"
                                    @click="removeCategory(category.id, category.dishes_count)"
                                >
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
