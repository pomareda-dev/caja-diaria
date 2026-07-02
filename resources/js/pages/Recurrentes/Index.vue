<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import recurrentes from '@/routes/recurrentes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Recurrentes',
                href: recurrentes.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Recurrentes" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Recurrentes</h1>
            <p class="text-muted-foreground text-sm">Gestiona tus transacciones periódicas</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Transacciones Recurrentes</CardTitle>
                <CardDescription>
                    Aquí podrás gestionar tus ingresos y gastos recurrentes.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-muted-foreground text-sm">Próximamente</p>
            </CardContent>
        </Card>
    </div>
</template>
