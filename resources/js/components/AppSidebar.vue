<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BookOpen,
    FolderGit2,
    LayoutDashboard,
    Repeat,
    Tags,
    Wallet,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
