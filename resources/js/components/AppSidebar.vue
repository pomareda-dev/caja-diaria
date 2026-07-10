<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    LayoutDashboard,
    LineChart,
    Repeat,
    Tags,
    Wallet,
} from '@lucide/vue';
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
import categorias from '@/routes/categorias';
import cuentas from '@/routes/cuentas';
import movimientos from '@/routes/movimientos';
import proyeccion from '@/routes/proyeccion';
import recurrentes from '@/routes/recurrentes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Tablero',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Movimientos',
        href: movimientos.index(),
        icon: ArrowLeftRight,
    },
    {
        title: 'Categorías',
        href: categorias.index(),
        icon: Tags,
    },
    {
        title: 'Cuentas',
        href: cuentas.index(),
        icon: Wallet,
    },
    {
        title: 'Recurrentes',
        href: recurrentes.index(),
        icon: Repeat,
    },
    {
        title: 'Proyección',
        href: proyeccion.index(),
        icon: LineChart,
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
