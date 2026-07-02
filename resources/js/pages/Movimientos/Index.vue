<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import movimientos from '@/routes/movimientos';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Movimientos',
                href: movimientos.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Movimientos" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Movimientos</h1>
            <p class="text-muted-foreground text-sm">Registra y consulta tus ingresos y egresos</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Movimientos</CardTitle>
                <CardDescription>
                    Aquí podrás gestionar todos los movimientos de tus cuentas.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-muted-foreground text-sm">Próximamente</p>
            </CardContent>
        </Card>
    </div>
</template>
