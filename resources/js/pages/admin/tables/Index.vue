<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Armchair, Pencil, Plus, Trash2, UsersRound } from 'lucide-vue-next';
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
import { create, destroy, edit, index } from '@/routes/admin/tables';
import type { BreadcrumbItem } from '@/types';

type TablePayload = {
    id: number;
    name: string;
    capacity: number;
    is_active: boolean;
    reservations_count: number;
};

const props = defineProps<{
    tables: TablePayload[];
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tables',
        href: index(),
    },
];

const statusMessage = computed(() => {
    if (props.status === 'table-created') {
        return 'Table created.';
    }

    if (props.status === 'table-updated') {
        return 'Table updated.';
    }

    if (props.status === 'table-deleted') {
        return 'Table deleted.';
    }

    if (props.status === 'table-delete-blocked') {
        return 'Table cannot be deleted while it has reservations.';
    }

    return null;
});

function removeTable(tableId: number, reservationsCount: number): void {
    if (reservationsCount > 0) {
        return;
    }

    if (!window.confirm('Delete this table?')) {
        return;
    }

    router.delete(destroy(tableId).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tables" />

        <div class="space-y-6 px-4 py-6">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <Heading
                    title="Tables"
                    description="Manage your dining floor layout and seating capacity."
                />

                <Button as-child>
                    <Link :href="create().url">
                        <Plus class="mr-2 h-4 w-4" />
                        Create table
                    </Link>
                </Button>
            </div>

            <Alert v-if="statusMessage">
                <AlertTitle>Update</AlertTitle>
                <AlertDescription>{{ statusMessage }}</AlertDescription>
            </Alert>

            <div
                v-if="tables.length === 0"
                class="rounded-[1.5rem] border border-dashed border-border/70 bg-muted/20 px-6 py-10 text-center"
            >
                <Armchair class="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
                <h2 class="text-lg font-semibold">No tables yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Add your first table to start preparing reservations management.
                </p>
            </div>

            <Table v-else>
                <TableHeader>
                    <tr>
                        <TableHead>Table</TableHead>
                        <TableHead>Capacity</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Reservations</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </tr>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="tableItem in tables" :key="tableItem.id">
                        <TableCell>
                            <div class="space-y-2">
                                <div class="font-semibold text-foreground">
                                    {{ tableItem.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    ID {{ tableItem.id }}
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="inline-flex items-center gap-2 font-medium text-foreground">
                                <UsersRound class="h-4 w-4 text-muted-foreground" />
                                {{ tableItem.capacity }} guests
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="tableItem.is_active ? 'default' : 'secondary'"
                                class="rounded-full px-3 py-1"
                            >
                                {{ tableItem.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="tableItem.reservations_count > 0 ? 'secondary' : 'outline'"
                                class="rounded-full px-3 py-1"
                            >
                                {{ tableItem.reservations_count }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="edit(tableItem.id).url">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    :disabled="tableItem.reservations_count > 0"
                                    size="sm"
                                    variant="destructive"
                                    @click="removeTable(tableItem.id, tableItem.reservations_count)"
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
