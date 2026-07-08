<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Pencil, Trash2 } from '@lucide/vue';
import { ref, computed } from 'vue';
import CategoryDialog from '@/components/categories/CategoryDialog.vue';
import type { CategoryData } from '@/components/categories/CategoryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import categorias from '@/routes/categorias';

const props = defineProps<{
    categories: CategoryData[];
    selectedMonth: string;
    currentMonth: string;
}>();

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

const { format } = useCurrency();
const { densityClass } = useSettings();

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
    router.visit(`${categorias.index.url()}?month=${year}-${month}`, {
        preserveScroll: true,
    });
}

function goToToday() {
    router.visit(categorias.index.url(), {
        preserveScroll: true,
    });
}

// --- Kind helpers ---
const kindLabels: Record<string, string> = {
    expense: 'Gasto',
    income: 'Ingreso',
    transfer: 'Transferencia',
};

const kindBadgeVariant: Record<string, string> = {
    expense: 'destructive',
    income: 'secondary',
    transfer: 'outline',
};

function progressPercentage(cat: CategoryData): number {
    if (!cat.monthly_limit || cat.monthly_limit <= 0) {
return 0;
}

    return (cat.spent / cat.monthly_limit) * 100;
}

function progressColor(pct: number): string {
    if (pct > 100) {
return 'bg-red-500';
}

    if (pct >= 75) {
return 'bg-amber-500';
}

    return 'bg-green-500';
}

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingCategory = ref<CategoryData | null>(null);
const deleteTarget = ref<CategoryData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
    editingCategory.value = null;
    showCreateDialog.value = true;
}

function openEdit(category: CategoryData) {
    editingCategory.value = category;
    showCreateDialog.value = true;
}

function confirmDelete(category: CategoryData) {
    deleteTarget.value = category;
    showDeleteDialog.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) {
return;
}

    router.delete(categorias.destroy.url(deleteTarget.value.id), {
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
</script>

<template>
    <Head :title="`Presupuestos — ${monthLabel}`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Categorías y presupuestos</h1>
            <p class="text-muted-foreground text-sm">
                Gestiona tus categorías y controla tu presupuesto mensual
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
                Nueva categoría
            </Button>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead :class="densityClass.header">Tipo</TableHead>
                        <TableHead :class="densityClass.header">Nombre</TableHead>
                        <TableHead :class="[densityClass.header, 'text-right']">Gasto del mes</TableHead>
                        <TableHead :class="[densityClass.header, 'text-right']">Límite</TableHead>
                        <TableHead :class="[densityClass.header, 'min-w-[180px]']">Progreso</TableHead>
                        <TableHead :class="[densityClass.header, 'w-[80px]']"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="cat in categories"
                        :key="cat.id"
                        class="group"
                    >
                        <TableCell :class="densityClass.cell">
                            <Badge :variant="kindBadgeVariant[cat.kind] as any">
                                {{ kindLabels[cat.kind] }}
                            </Badge>
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'font-medium']">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="cat.color"
                                    class="inline-block size-3 rounded-full shrink-0"
                                    :style="{ backgroundColor: cat.color }"
                                />
                                {{ cat.name }}
                            </div>
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-right font-medium tabular-nums text-red-600 dark:text-red-400']">
                            {{ format(cat.spent) }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-right tabular-nums text-muted-foreground']">
                            {{ cat.monthly_limit !== null ? format(cat.monthly_limit) : '—' }}
                        </TableCell>
                        <TableCell :class="densityClass.cell">
                            <div v-if="cat.monthly_limit !== null && cat.monthly_limit > 0" class="flex items-center gap-3">
                                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="progressColor(progressPercentage(cat))"
                                        :style="{ width: Math.min(progressPercentage(cat), 100) + '%' }"
                                    />
                                </div>
                                <span
                                    class="text-xs font-medium tabular-nums shrink-0"
                                    :class="{
                                        'text-red-600 dark:text-red-400': progressPercentage(cat) > 100,
                                        'text-amber-600 dark:text-amber-400': progressPercentage(cat) >= 75 && progressPercentage(cat) <= 100,
                                        'text-green-600 dark:text-green-400': progressPercentage(cat) < 75,
                                    }"
                                >
                                    {{ Math.round(progressPercentage(cat)) }}%
                                </span>
                            </div>
                            <span
                                v-else
                                class="text-xs text-muted-foreground"
                            >
                                Sin límite
                            </span>
                        </TableCell>
                        <TableCell :class="densityClass.cell">
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    @click="openEdit(cat)"
                                    aria-label="Editar categoría"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                    @click="confirmDelete(cat)"
                                    aria-label="Eliminar categoría"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <!-- Empty state -->
                    <TableRow v-if="categories.length === 0">
                        <TableCell
                            colspan="6"
                            class="text-center py-12 text-muted-foreground"
                        >
                            No hay categorías.
                            <br>
                            <Button
                                variant="link"
                                class="mt-1"
                                @click="openCreate"
                            >
                                Crear la primera categoría
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Create / Edit Dialog -->
    <CategoryDialog
        v-model:open="showCreateDialog"
        :category="editingCategory"
        @saved="showCreateDialog = false"
    />

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = false">
        <DialogContent class="sm:max-w-[380px]">
            <DialogHeader>
                <DialogTitle>Eliminar categoría</DialogTitle>
                <DialogDescription>
                    ¿Estás seguro de eliminar esta categoría?
                    <br>
                    <strong>{{ deleteTarget?.name }}</strong>
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
