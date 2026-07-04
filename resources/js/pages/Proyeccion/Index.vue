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
import proyeccion from '@/routes/proyeccion';

export interface ProjectionItem {
    id: number;
    date: string;
    description: string;
    category_id: number | null;
    category_name: string | null;
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
                        <TableHead>Fecha</TableHead>
                        <TableHead>Movimiento</TableHead>
                        <TableHead>Categoría</TableHead>
                        <TableHead>Origen</TableHead>
                        <TableHead class="text-right">Cantidad</TableHead>
                        <TableHead class="text-right">Proyección</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="item in items"
                        :key="item.id"
                    >
                        <TableCell class="font-medium whitespace-nowrap">
                            {{ formatDate(item.date) }}
                        </TableCell>
                        <TableCell>
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
                        <TableCell class="text-muted-foreground">
                            {{ item.category_name ?? 'Sin categoría' }}
                        </TableCell>
                        <TableCell>
                            <span class="text-xs text-muted-foreground">
                                {{ sourceLabel(item.source) }}
                            </span>
                        </TableCell>
                        <TableCell
                            class="text-right font-medium tabular-nums"
                            :class="item.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ formatSign(item.amount) }}
                        </TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
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
