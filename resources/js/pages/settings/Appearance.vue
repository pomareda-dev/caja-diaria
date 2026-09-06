<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apriencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apriencia" />

    <h1 class="sr-only">Apriencia</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Apriencia"
            description="Actualiza la apariencia de la aplicación"
        />
        <AppearanceTabs />
    </div>
</template>
