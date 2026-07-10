<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCurrency } from '@/composables/useCurrency';
import { useSettings } from '@/composables/useSettings';
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
const { densityClass } = useSettings();

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
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead :class="densityClass.header">Fecha</TableHead>
                        <TableHead :class="densityClass.header">Movimiento</TableHead>
                        <TableHead :class="densityClass.header">Categoría</TableHead>
                        <TableHead :class="densityClass.header">Origen</TableHead>
                        <TableHead :class="[densityClass.header, 'text-right']">Cantidad</TableHead>
                        <TableHead :class="[densityClass.header, 'text-right']">Proyección</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in items"
                        :key="item.id"
                    >
                        <TableCell :class="[densityClass.cell, 'font-medium whitespace-nowrap']">
                            {{ formatDate(item.date) }}
                        </TableCell>
                        <TableCell :class="densityClass.cell">
                            <div class="flex items-center gap-2">
                                <span>{{ item.description }}</span>
                                <Badge
                                    v-if="item.source === 'recurring'"
                                    variant="outline"
                                    class="text-amber-600 border-amber-300 bg-amber-50 dark:text-amber-400 dark:border-amber-800 dark:bg-amber-950 text-[10px] px-1.5 py-0"
                                >
                                    Recurrente
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-muted-foreground']">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="item.category_color"
                                    class="inline-block size-3 rounded-full shrink-0"
                                    :style="{ backgroundColor: item.category_color }"
                                />
                                {{ item.category_name ?? 'Sin categoría' }}
                            </div>
                        </TableCell>
                        <TableCell :class="densityClass.cell">
                            <span class="text-xs text-muted-foreground">
                                {{ sourceLabel(item.source) }}
                            </span>
                        </TableCell>
                        <TableCell
                            :class="[densityClass.cell, 'text-right font-medium tabular-nums', item.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400']"
                        >
                            {{ formatSign(item.amount) }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-right font-medium tabular-nums']">
                            {{ format(item.running_balance) }}
                        </TableCell>
                    </TableRow>

                    <!-- Empty state -->
                    <TableRow v-if="items.length === 0">
                        <TableCell
                            colspan="6"
                            class="text-center py-12 text-muted-foreground"
                        >
                            No hay movimientos proyectados.
                            <br>
                            <span class="text-xs">
                                Crea plantillas recurrentes y genera proyecciones para ver el
                                timeline financiero.
                            </span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
