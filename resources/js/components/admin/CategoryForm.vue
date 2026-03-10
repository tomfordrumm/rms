<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit, index, store, update } from '@/routes/admin/categories';
import type { BreadcrumbItem } from '@/types';

type CategoryPayload = {
    id: number;
    name: string;
    description: string | null;
    position: number;
    dishes_count?: number;
};

const props = defineProps<{
    category: CategoryPayload | null;
    suggestedPosition?: number | null;
}>();

const isEditing = props.category !== null;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Categories',
        href: index(),
    },
    {
        title: isEditing ? 'Edit category' : 'Create category',
        href: isEditing ? edit(props.category.id) : create(),
    },
];

const form = useForm({
    name: props.category?.name ?? '',
    description: props.category?.description ?? '',
    position: String(props.category?.position ?? props.suggestedPosition ?? 1),
});

function submit(): void {
    if (props.category) {
        form.put(update(props.category.id).url, {
            preserveScroll: true,
        });

        return;
    }

    form.post(store().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="isEditing ? 'Edit category' : 'Create category'" />

        <div class="space-y-6 px-4 py-6">
            <div
                class="relative overflow-hidden rounded-[1.75rem] border border-border/70 bg-gradient-to-br from-stone-950 via-zinc-900 to-orange-950 px-6 py-8 text-stone-50 shadow-[0_24px_90px_-52px_rgba(17,12,8,0.9)]"
            >
                <div class="absolute inset-y-0 right-0 w-48 bg-orange-400/10 blur-3xl" />
                <Heading
                    :title="isEditing ? 'Refine category details' : 'Create a menu category'"
                    :description="
                        isEditing
                            ? 'Update the label, description and display order used across the menu.'
                            : 'Categories control how dishes are grouped and the order they appear in the public menu.'
                    "
                    class="relative text-stone-50"
                />
            </div>

            <Card class="border-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>{{ isEditing ? 'Category details' : 'New category' }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Starters" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                placeholder="Short note for the team or public menu."
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2 md:max-w-40">
                            <Label for="position">Position</Label>
                            <Input
                                id="position"
                                v-model="form.position"
                                inputmode="numeric"
                                placeholder="1"
                            />
                            <InputError :message="form.errors.position" />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button :disabled="form.processing" type="submit">
                                {{ isEditing ? 'Save category' : 'Create category' }}
                            </Button>
                            <Button as-child type="button" variant="ghost">
                                <Link :href="index().url">Back to list</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
