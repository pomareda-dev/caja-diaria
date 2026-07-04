<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Pencil, Trash2 } from '@lucide/vue';
import { useCurrency } from '@/composables/useCurrency';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import MovementDialog from '@/components/movements/MovementDialog.vue';
import type { MovementData, CategoryData } from '@/components/movements/MovementDialog.vue';
import movimientos from '@/routes/movimientos';

const props = defineProps<{
    movements: MovementData[];
    categories: CategoryData[];
    selectedMonth: string;
    openingBalance: number;
    currentMonth: string;
}>();

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

const { format, formatSigned } = useCurrency();

// --- Month navigation ---
const selectedDate = computed(() => {
    const [year, month] = props.selectedMonth.split('-').map(Number);
    return new Date(year, month - 1, 1);
});

const monthLabel = computed(() => {
    return selectedDate.value.toLocaleDateString('es-PE', {
        month: 'long',
        year: 'numeric',
    });
});

const isCurrentMonth = computed(() => props.selectedMonth === props.currentMonth);

function navigateMonth(delta: number) {
    const date = new Date(selectedDate.value);
    date.setMonth(date.getMonth() + delta);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    router.visit(`${movimientos.index.url()}?month=${year}-${month}`, {
        preserveScroll: true,
    });
}

function goToToday() {
    router.visit(movimientos.index.url(), {
        preserveScroll: true,
    });
}

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingMovement = ref<MovementData | null>(null);
const deleteTarget = ref<MovementData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
    editingMovement.value = null;
    showCreateDialog.value = true;
}

function openEdit(movement: MovementData) {
    editingMovement.value = movement;
    showCreateDialog.value = true;
}

function confirmDelete(movement: MovementData) {
    deleteTarget.value = movement;
    showDeleteDialog.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) return;

    router.delete(movimientos.destroy.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            deleteTarget.value = null;
        },
        onError: () => {
            showDeleteDialog.value = false;
            deleteTarget.value = null;
        },
    });
}

// --- Summary calculations ---
const summary = computed(() => {
    const income = props.movements
        .filter((m) => m.amount > 0)
        .reduce((sum, m) => sum + m.amount, 0);
    const expense = props.movements
        .filter((m) => m.amount < 0)
        .reduce((sum, m) => sum + m.amount, 0);
    const closingBalance = props.movements.length > 0
        ? props.movements[props.movements.length - 1].running_balance
        : props.openingBalance;

    return { income, expense, closingBalance };
});

function formatSign(value: number): string {
    if (value === 0) return format(value);
    return formatSigned(value);
}
</script>

<template>
    <Head :title="monthLabel" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Movimientos</h1>
            <p class="text-muted-foreground text-sm">
                Registra y consulta tus ingresos y egresos
            </p>
        </div>

        <!-- Month Navigation + Create Button -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    @click="navigateMonth(-1)"
                    aria-label="Mes anterior"
                >
                    <ChevronLeft class="size-4" />
                </Button>

                <span class="min-w-[160px] text-center text-lg font-semibold capitalize">
                    {{ monthLabel }}
                </span>

                <Button
                    variant="outline"
                    size="icon"
                    @click="navigateMonth(1)"
                    aria-label="Mes siguiente"
                >
                    <ChevronRight class="size-4" />
                </Button>

                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="isCurrentMonth"
                    @click="goToToday"
                >
                    Hoy
                </Button>
            </div>

            <Button @click="openCreate">
                <Plus class="size-4 mr-1" />
                Nuevo movimiento
            </Button>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Fecha</TableHead>
                        <TableHead>Movimiento</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead class="text-right">Cantidad</TableHead>
                        <TableHead class="text-right">Balance</TableHead>
                        <TableHead class="w-[80px]"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <!-- Opening balance row -->
                    <TableRow class="bg-muted/30">
                        <TableCell colspan="3" class="font-medium text-muted-foreground">
                            Saldo inicial
                        </TableCell>
                        <TableCell class="text-right font-medium text-muted-foreground">
                            {{ format(openingBalance) }}
                        </TableCell>
                        <TableCell class="text-right font-medium">
                            {{ format(openingBalance) }}
                        </TableCell>
                        <TableCell></TableCell>
                    </TableRow>

                    <!-- Movement rows -->
                    <TableRow
                        v-for="movement in movements"
                        :key="movement.id"
                        class="group"
                        :class="{ 'opacity-60': movement.is_projected }"
                    >
                        <TableCell class="font-medium">
                            {{ new Date(movement.date + 'T00:00:00').toLocaleDateString('es-PE', { day: 'numeric', month: 'short' }) }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <span>{{ movement.description }}</span>
                                <Badge
                                    v-if="movement.is_projected"
                                    variant="outline"
                                    class="text-amber-600 border-amber-300 bg-amber-50 dark:text-amber-400 dark:border-amber-800 dark:bg-amber-950 text-[10px] px-1.5 py-0"
                                >
                                    Proyectado
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ movement.category_name ?? 'Sin categoría' }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium tabular-nums"
                            :class="movement.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ formatSigned(movement.amount) }}
                        </TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
                            {{ format(movement.running_balance) }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    @click="openEdit(movement)"
                                    aria-label="Editar movimiento"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                    @click="confirmDelete(movement)"
                                    aria-label="Eliminar movimiento"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <!-- Empty state -->
                    <TableRow v-if="movements.length === 0">
                        <TableCell
                            colspan="6"
                            class="text-center py-12 text-muted-foreground"
                        >
                            No hay movimientos en este mes.
                            <br>
                            <Button
                                variant="link"
                                class="mt-1"
                                @click="openCreate"
                            >
                                Crear el primer movimiento
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Monthly Summary -->
        <div
            v-if="movements.length > 0"
            class="flex flex-wrap gap-6 rounded-md border p-4"
        >
            <div class="flex flex-col gap-1">
                <span class="text-xs text-muted-foreground uppercase tracking-wide">
                    Ingresos
                </span>
                <span class="text-lg font-semibold text-green-600 dark:text-green-400 tabular-nums">
                    {{ format(summary.income) }}
                </span>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-xs text-muted-foreground uppercase tracking-wide">
                    Gastos
                </span>
                <span class="text-lg font-semibold text-red-600 dark:text-red-400 tabular-nums">
                    {{ format(Math.abs(summary.expense)) }}
                </span>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-xs text-muted-foreground uppercase tracking-wide">
                    Neto del mes
                </span>
                <span
                    class="text-lg font-semibold tabular-nums"
                    :class="summary.income + summary.expense >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                >
                    {{ formatSign(summary.income + summary.expense) }}
                </span>
            </div>
            <div class="flex flex-col gap-1 ml-auto">
                <span class="text-xs text-muted-foreground uppercase tracking-wide">
                    Balance final
                </span>
                <span class="text-lg font-semibold tabular-nums">
                    {{ format(summary.closingBalance) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Create / Edit Dialog -->
    <MovementDialog
        v-model:open="showCreateDialog"
        :movement="editingMovement"
        :categories="categories"
        @saved="showCreateDialog = false"
    />

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = false">
        <DialogContent class="sm:max-w-[380px]">
            <DialogHeader>
                <DialogTitle>Eliminar movimiento</DialogTitle>
                <DialogDescription>
                    ¿Estás seguro de eliminar este movimiento?
                    <br>
                    <strong>{{ deleteTarget?.description }}</strong>
                    &mdash;
                    <span v-if="deleteTarget">
                        {{ formatSigned(deleteTarget.amount) }}
                    </span>
                    <br>
                    Esta acción no se puede deshacer.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    variant="outline"
                    @click="showDeleteDialog = false"
                >
                    Cancelar
                </Button>
                <Button
                    variant="destructive"
                    @click="executeDelete"
                >
                    Eliminar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
