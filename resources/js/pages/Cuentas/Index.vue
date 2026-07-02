<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import cuentas from '@/routes/cuentas';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Cuentas',
                href: cuentas.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Cuentas" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Cuentas</h1>
            <p class="text-muted-foreground text-sm">Administra tus cuentas bancarias y de efectivo</p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Cuentas</CardTitle>
                <CardDescription>
                    Aquí podrás gestionar tus cuentas y saldos.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-muted-foreground text-sm">Próximamente</p>
            </CardContent>
        </Card>
    </div>
</template>
