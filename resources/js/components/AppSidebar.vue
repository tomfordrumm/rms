<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarRange, LayoutGrid, QrCode, Salad, Store, Tags, UsersRound } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as dishesIndex } from '@/routes/admin/dishes';
import { index as reservationsIndex } from '@/routes/admin/reservations';
import { index as tablesIndex } from '@/routes/admin/tables';
import { edit as editRestaurant } from '@/routes/restaurant';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Restaurant settings',
        href: editRestaurant(),
        icon: Store,
    },
    {
        title: 'Categories',
        href: categoriesIndex(),
        icon: Tags,
    },
    {
        title: 'Dishes',
        href: dishesIndex(),
        icon: Salad,
    },
    {
        title: 'Tables',
        href: tablesIndex(),
        icon: UsersRound,
    },
    {
        title: 'Reservations',
        href: reservationsIndex(),
        icon: CalendarRange,
    },
    {
        title: 'QR',
        href: '/admin/qr',
        icon: QrCode,
    },
];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
