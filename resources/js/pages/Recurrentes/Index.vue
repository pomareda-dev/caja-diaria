<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import RecurringDialog from '@/components/recurring/RecurringDialog.vue';
import type { RecurringData, CategoryData } from '@/components/recurring/RecurringDialog.vue';
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
import recurrentes from '@/routes/recurrentes';

const props = defineProps<{
    templates: RecurringData[];
    categories: CategoryData[];
}>();

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

const { format, formatSigned } = useCurrency();
const { densityClass } = useSettings();

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingTemplate = ref<RecurringData | null>(null);
const deleteTarget = ref<RecurringData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
    editingTemplate.value = null;
    showCreateDialog.value = true;
}

function openEdit(template: RecurringData) {
    editingTemplate.value = template;
    showCreateDialog.value = true;
}

function confirmDelete(template: RecurringData) {
    deleteTarget.value = template;
    showDeleteDialog.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(recurrentes.destroy.url(deleteTarget.value.id), {
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

function regenerateProjections() {
    router.post(recurrentes.regenerate.url(), {}, {
        preserveScroll: true,
    });
}

function parseDate(dateStr: string | null): string {
    if (!dateStr) {
        return '—';
    }

    const d = new Date(dateStr + 'T00:00:00');

    return d.toLocaleDateString('es-PE', { month: 'short', year: 'numeric' });
}

function formatSign(value: number): string {
    if (value === 0) {
        return format(value);
    }

    return formatSigned(value);
}
</script>

<template>
    <Head title="Recurrentes" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Transacciones Recurrentes</h1>
            <p class="text-muted-foreground text-sm">
                Gestiona tus plantillas de ingresos y gastos periódicos
            </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="regenerateProjections"
                >
                    <RefreshCw class="size-4 mr-1" />
                    Regenerar proyecciones
                </Button>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4 mr-1" />
                Nueva plantilla
            </Button>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead :class="densityClass.header">Nombre</TableHead>
                        <TableHead :class="[densityClass.header, 'text-right']">Importe</TableHead>
                        <TableHead :class="densityClass.header">Categoría</TableHead>
                        <TableHead :class="[densityClass.header, 'text-center']">Día</TableHead>
                        <TableHead :class="densityClass.header">Inicio</TableHead>
                        <TableHead :class="densityClass.header">Fin</TableHead>
                        <TableHead :class="[densityClass.header, 'text-center']">Estado</TableHead>
                        <TableHead :class="[densityClass.header, 'w-[80px]']"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="tpl in templates"
                        :key="tpl.id"
                        class="group"
                    >
                        <TableCell :class="[densityClass.cell, 'font-medium']">
                            {{ tpl.name }}
                        </TableCell>
                        <TableCell
                            :class="[densityClass.cell, 'text-right font-medium tabular-nums', tpl.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400']"
                        >
                            {{ formatSign(tpl.amount) }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-muted-foreground']">
                            {{ tpl.category_name ?? 'Sin categoría' }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-center tabular-nums']">
                            {{ tpl.day_of_month }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'tabular-nums']">
                            {{ parseDate(tpl.start_month) }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'tabular-nums']">
                            {{ parseDate(tpl.end_month) }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-center']">
                            <Badge
                                :variant="tpl.active ? 'secondary' : 'outline'"
                            >
                                {{ tpl.active ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </TableCell>
                        <TableCell :class="densityClass.cell">
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    @click="openEdit(tpl)"
                                    aria-label="Editar plantilla"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                    @click="confirmDelete(tpl)"
                                    aria-label="Eliminar plantilla"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <!-- Empty state -->
                    <TableRow v-if="templates.length === 0">
                        <TableCell
                            colspan="8"
                            class="text-center py-12 text-muted-foreground"
                        >
                            No hay plantillas recurrentes.
                            <br>
                            <Button
                                variant="link"
                                class="mt-1"
                                @click="openCreate"
                            >
                                Crear la primera plantilla
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <!-- Create / Edit Dialog -->
    <RecurringDialog
        v-model:open="showCreateDialog"
        :template="editingTemplate"
        :categories="categories"
        @saved="showCreateDialog = false"
    />

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = false">
        <DialogContent class="sm:max-w-[380px]">
            <DialogHeader>
                <DialogTitle>Eliminar plantilla</DialogTitle>
                <DialogDescription>
                    ¿Estás seguro de eliminar esta plantilla recurrente?
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
