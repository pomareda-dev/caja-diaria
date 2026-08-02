<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PaginationControls from '@/components/PaginationControls.vue';
import type { PaginationMeta } from '@/components/PaginationControls.vue';
import ResponsiveTable from '@/components/ResponsiveTable.vue';
import type { ResponsiveColumn } from '@/components/ResponsiveTable.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import proyeccion from '@/routes/proyeccion';

export interface ProjectionItem {
    id: number;
    date: string;
    description: string;
    category_id: number | null;
    category_name: string | null;
    category_color: string | null;
    amount: number;
    source: string;
    is_projected: boolean;
    running_balance: number;
}

const props = defineProps<{
    items: ProjectionItem[];
    openingBalance: number;
    horizonMonths: number;
    pagination: PaginationMeta;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Proyección',
                href: proyeccion.index(),
            },
        ],
    },
});

const { format, formatSigned } = useCurrency();

function changePage(page: number) {
    router.get(
        proyeccion.index.url(),
        {
            page,
            per_page: props.pagination.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                // nothing
            },
        },
    );
}

function changePerPage(perPage: number) {
    router.get(
        proyeccion.index.url(),
        {
            per_page: perPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function asProjectionItem(row: Record<string, unknown>): ProjectionItem {
    return row as unknown as ProjectionItem;
}

// --- Table columns ---
const tableColumns: ResponsiveColumn[] = [
    {
        key: 'date',
        header: 'Fecha',
        className: 'font-medium whitespace-nowrap',
    },
    { key: 'description', header: 'Movimiento', primary: true },
    {
        key: 'category',
        header: 'Categoría',
        className: 'text-muted-foreground',
    },
    { key: 'source', header: 'Origen', hideOnMobile: true },
    {
        key: 'amount',
        header: 'Cantidad',
        align: 'right',
        className: 'font-medium tabular-nums',
    },
    {
        key: 'running_balance',
        header: 'Proyección',
        align: 'right',
        hideOnMobile: true,
        className: 'font-medium tabular-nums',
    },
];

function sourceLabel(source: string): string {
    const labels: Record<string, string> = {
        manual: 'Manual',
        recurring: 'Recurrente',
        import: 'Importado',
    };

    return labels[source] ?? source;
}

function formatDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');

    return d.toLocaleDateString('es-PE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatSign(value: number): string {
    if (value === 0) {
        return format(value);
    }

    return formatSigned(value);
}
</script>

<template>
    <Head title="Proyección" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Proyección Financiera</h1>
            <p class="text-muted-foreground text-sm">
                Balance proyectado para los próximos {{ horizonMonths }} meses
            </p>
        </div>

        <!-- Opening Balance Card -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Balance inicial</CardTitle>
                <CardDescription>
                    Saldo real actual antes de movimientos futuros
                </CardDescription>
            </CardHeader>
            <CardContent>
                <span class="text-3xl font-bold tabular-nums">
                    {{ format(openingBalance) }}
                </span>
            </CardContent>
        </Card>

        <!-- Future Movements Table -->
        <ResponsiveTable
            :columns="tableColumns"
            :rows="items as unknown as Record<string, unknown>[]"
            row-key="id"
        >
            <template #cell-date="{ row }">
                {{ formatDate(asProjectionItem(row).date) }}
            </template>

            <template #cell-description="{ row }">
                <div class="flex items-center gap-2">
                    <span>{{ asProjectionItem(row).description }}</span>
                    <Badge
                        v-if="asProjectionItem(row).source === 'recurring'"
                        variant="outline"
                        class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                    >
                        Recurrente
                    </Badge>
                </div>
            </template>

            <template #cell-category="{ row }">
                <div class="flex items-center gap-2">
                    <span
                        v-if="asProjectionItem(row).category_color"
                        class="inline-block size-3 shrink-0 rounded-full"
                        :style="{
                            backgroundColor:
                                asProjectionItem(row).category_color ??
                                undefined,
                        }"
                    />
                    {{ asProjectionItem(row).category_name ?? 'Sin categoría' }}
                </div>
            </template>

            <template #cell-source="{ row }">
                <span class="text-xs text-muted-foreground">
                    {{ sourceLabel(asProjectionItem(row).source) }}
                </span>
            </template>

            <template #cell-amount="{ row }">
                <span
                    class="font-medium tabular-nums"
                    :class="
                        asProjectionItem(row).amount >= 0
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-red-600 dark:text-red-400'
                    "
                >
                    {{ formatSign(asProjectionItem(row).amount) }}
                </span>
            </template>

            <template #cell-running_balance="{ row }">
                {{ format(asProjectionItem(row).running_balance) }}
            </template>

            <template #empty>
                No hay movimientos proyectados.
                <br />
                <span class="text-xs">
                    Crea plantillas recurrentes y genera proyecciones para ver
                    el timeline financiero.
                </span>
            </template>
        </ResponsiveTable>

        <PaginationControls
            :pagination="pagination"
            @update:page="changePage"
            @update:per-page="changePerPage"
        />
    </div>
</template>
