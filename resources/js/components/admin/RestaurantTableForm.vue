<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Armchair, UsersRound } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit, index, store, update } from '@/routes/admin/tables';
import type { BreadcrumbItem } from '@/types';

type TablePayload = {
    id: number;
    name: string;
    capacity: number;
    is_active: boolean;
};

const props = defineProps<{
    table: TablePayload | null;
}>();

const isEditing = props.table !== null;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tables',
        href: index(),
    },
    {
        title: isEditing ? 'Edit table' : 'Create table',
        href: isEditing ? edit(props.table.id) : create(),
    },
];

const form = useForm({
    name: props.table?.name ?? '',
    capacity: String(props.table?.capacity ?? ''),
    is_active: props.table?.is_active ?? true,
});

function submit(): void {
    if (props.table) {
        form.put(update(props.table.id).url, {
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
        <Head :title="isEditing ? 'Edit table' : 'Create table'" />

        <div class="space-y-6 px-4 py-6">
            <div
                class="relative overflow-hidden rounded-[1.75rem] border border-border/70 bg-gradient-to-br from-slate-950 via-zinc-900 to-cyan-950 px-6 py-8 text-stone-50 shadow-[0_24px_90px_-52px_rgba(17,12,8,0.9)]"
            >
                <div class="absolute inset-y-0 right-0 w-48 bg-cyan-300/10 blur-3xl" />
                <div class="relative space-y-4">
                    <Badge
                        variant="secondary"
                        class="border border-white/10 bg-white/10 text-stone-50"
                    >
                        <Armchair class="mr-2 h-3.5 w-3.5" />
                        {{ isEditing ? 'Edit table' : 'Dining floor' }}
                    </Badge>

                    <Heading
                        :title="isEditing ? 'Adjust the table setup' : 'Add a restaurant table'"
                        :description="
                            isEditing
                                ? 'Update the internal label, seating capacity and current availability flag.'
                                : 'Create a table record to use later in reservations and floor management.'
                        "
                        class="text-stone-50"
                    />
                </div>
            </div>

            <Card class="border-border/70 shadow-sm">
                <CardHeader>
                    <CardTitle>{{ isEditing ? 'Table details' : 'New table' }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" placeholder="Table 7" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="capacity">Capacity</Label>
                                <Input
                                    id="capacity"
                                    v-model="form.capacity"
                                    inputmode="numeric"
                                    placeholder="4"
                                />
                                <InputError :message="form.errors.capacity" />
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
                                <div class="flex items-center gap-2 font-medium">
                                    <UsersRound class="h-4 w-4" />
                                    Active table
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    Inactive tables stay in the system but can be hidden from operational use.
                                </div>
                            </div>
                        </label>

                        <div class="flex items-center gap-3">
                            <Button :disabled="form.processing" type="submit">
                                {{ isEditing ? 'Save table' : 'Create table' }}
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
