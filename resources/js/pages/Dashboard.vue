<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { dashboard } from '@/routes';

const { format, formatSigned } = useCurrency();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tablero',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Tablero" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Formato de moneda</CardTitle>
                    <CardDescription>
                        Ejemplo del formato PEN usando <code>useCurrency</code>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-1 text-sm">
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Ingreso:</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ formatSigned(1500) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Egreso:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">{{ formatSigned(-320.5) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-muted-foreground">Saldo:</span>
                            <span class="font-medium">{{ format(1179.5) }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
