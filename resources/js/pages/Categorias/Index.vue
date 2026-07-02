<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import categorias from '@/routes/categorias';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categorías',
                href: categorias.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Categorías" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Categorías</h1>
            <p class="text-muted-foreground text-sm">Organiza tus movimientos por categorías</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Categorías</CardTitle>
                <CardDescription>
                    Aquí podrás gestionar las categorías de tus movimientos.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-muted-foreground text-sm">Próximamente</p>
            </CardContent>
        </Card>
    </div>
</template>
