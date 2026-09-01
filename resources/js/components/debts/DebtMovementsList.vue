<script setup lang="ts">
import type { DebtMovementData } from '@/components/debts/types';
import ResponsiveTable from '@/components/ResponsiveTable.vue';
import type { ResponsiveColumn } from '@/components/ResponsiveTable.vue';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';

defineProps<{
    title: string;
    movements: DebtMovementData[];
    emptyMessage: string;
}>();

const { formatSigned } = useCurrency();

function asMovement(row: Record<string, unknown>): DebtMovementData {
    return row as unknown as DebtMovementData;
}

const columns: ResponsiveColumn[] = [
    {
        key: 'date',
        header: 'Fecha',
        className: 'tabular-nums',
        hideOnMobile: true,
    },
    {
        key: 'description',
        header: 'Descripción',
        primary: true,
    },
    {
        key: 'category_name',
        header: 'Categoría',
        className: 'text-muted-foreground',
    },
    {
        key: 'amount',
        header: 'Importe',
        align: 'right',
        className: 'font-medium tabular-nums',
    },
];

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Card class="gap-4 py-4">
        <CardHeader>
            <CardTitle class="text-base">{{ title }}</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <ResponsiveTable
                :columns="columns"
                :rows="movements as unknown as Record<string, unknown>[]"
                row-key="id"
            >
                <template #cell-date="{ row }">
                    {{ formatDate(asMovement(row).date) }}
                </template>

                <template #cell-category_name="{ row }">
                    <span class="flex items-center gap-1.5">
                        <span
                            v-if="asMovement(row).category_color"
                            class="inline-block size-2.5 shrink-0 rounded-full"
                            :style="{
                                backgroundColor:
                                    asMovement(row).category_color ?? '',
                            }"
                        />
                        {{ asMovement(row).category_name ?? 'Sin categoría' }}
                    </span>
                </template>

                <template #cell-amount="{ row }">
                    <span
                        class="font-medium tabular-nums"
                        :class="
                            asMovement(row).amount >= 0
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        {{ formatSigned(asMovement(row).amount) }}
                    </span>
                </template>

                <template #empty>
                    <p class="py-4 text-center text-sm text-muted-foreground">
                        {{ emptyMessage }}
                    </p>
                </template>
            </ResponsiveTable>
        </CardContent>
    </Card>
</template>
