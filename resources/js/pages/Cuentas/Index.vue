<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, CheckCircle2, AlertTriangle } from '@lucide/vue';
import { ref, computed } from 'vue';
import AccountDialog from '@/components/accounts/AccountDialog.vue';
import type { AccountData } from '@/components/accounts/AccountDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    TableFoot,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCurrency } from '@/composables/useCurrency';
import { useSettings } from '@/composables/useSettings';
import cuentas from '@/routes/cuentas';

const props = defineProps<{
    accounts: AccountData[];
    reconciliation: {
        totalAccounts: number;
        realBalance: number;
        difference: number;
        reconciled: boolean;
    };
}>();

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

const { format } = useCurrency();
const { densityClass } = useSettings();

// --- Kind helpers ---
const kindLabels: Record<string, string> = {
    bank: 'Banco',
    wallet: 'Billetera',
    cash: 'Efectivo',
    credit: 'Tarjeta de crédito',
    other: 'Otro',
};

const kindBadgeVariant: Record<string, string> = {
    bank: 'default',
    wallet: 'secondary',
    cash: 'outline',
    credit: 'destructive',
    other: 'secondary',
};

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingAccount = ref<AccountData | null>(null);
const deleteTarget = ref<AccountData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
    editingAccount.value = null;
    showCreateDialog.value = true;
}

function openEdit(account: AccountData) {
    editingAccount.value = account;
    showCreateDialog.value = true;
}

function confirmDelete(account: AccountData) {
    deleteTarget.value = account;
    showDeleteDialog.value = true;
}

function executeDelete() {
    if (!deleteTarget.value) {
return;
}

    router.delete(cuentas.destroy.url(deleteTarget.value.id), {
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

// --- Totals ---
const totalBalance = computed(() => {
    return props.accounts.reduce((sum, a) => sum + a.balance, 0);
});

</script>

<template>
    <Head title="Cuentas" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Cuentas</h1>
            <p class="text-muted-foreground text-sm">
                Administra tus cuentas y saldos, y concilia contra el balance real
            </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-4">
            <div />
            <Button @click="openCreate">
                <Plus class="size-4 mr-1" />
                Nueva cuenta
            </Button>
        </div>

        <!-- Accounts Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead :class="densityClass.header">Tipo</TableHead>
                        <TableHead :class="densityClass.header">Cuenta</TableHead>
                        <TableHead :class="[densityClass.header, 'text-right']">Saldo</TableHead>
                        <TableHead :class="[densityClass.header, 'text-center']">Estado</TableHead>
                        <TableHead :class="[densityClass.header, 'w-[100px]']"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="account in accounts"
                        :key="account.id"
                        class="group"
                    >
                        <TableCell :class="densityClass.cell">
                            <Badge :variant="kindBadgeVariant[account.kind] as any">
                                {{ kindLabels[account.kind] }}
                            </Badge>
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'font-medium']">
                            <div class="flex items-center gap-2">
                                {{ account.name }}
                                <Badge
                                    v-if="account.exclude_from_reconciliation"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    Excluida
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-right font-medium tabular-nums']">
                            {{ format(account.balance) }}
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-center']">
                            <span
                                v-if="account.exclude_from_reconciliation"
                                class="text-xs text-muted-foreground"
                            >
                                —
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400"
                            >
                                <CheckCircle2 class="size-3.5" />
                                Incluida
                            </span>
                        </TableCell>
                        <TableCell :class="densityClass.cell">
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    @click="openEdit(account)"
                                    aria-label="Ajustar saldo"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                    @click="confirmDelete(account)"
                                    aria-label="Eliminar cuenta"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>

                    <!-- Empty state -->
                    <TableRow v-if="accounts.length === 0">
                        <TableCell
                            colspan="5"
                            class="text-center py-12 text-muted-foreground"
                        >
                            No hay cuentas registradas.
                            <br>
                            <Button
                                variant="link"
                                class="mt-1"
                                @click="openCreate"
                            >
                                Crear la primera cuenta
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>

                <!-- Footer with totals -->
                <TableFooter v-if="accounts.length > 0">
                    <TableRow>
                        <TableCell :class="[densityClass.cell, 'font-semibold']" colspan="2">
                            Total
                        </TableCell>
                        <TableCell :class="[densityClass.cell, 'text-right font-bold tabular-nums']">
                            {{ format(totalBalance) }}
                        </TableCell>
                        <TableCell :class="densityClass.cell" colspan="2" />
                    </TableRow>
                </TableFooter>
            </Table>
        </div>

        <!-- Reconciliation Panel -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <span>Conciliación</span>
                    <Badge
                        :variant="reconciliation.reconciled ? 'secondary' : 'destructive'"
                    >
                        {{ reconciliation.reconciled ? 'Conciliado' : 'Descuadre' }}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    Verificación cruzada entre el saldo de tus cuentas y el balance real de movimientos
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="flex flex-col gap-1">
                        <span class="text-sm text-muted-foreground">
                            Total cuentas
                        </span>
                        <span
                            class="text-2xl font-bold tabular-nums"
                            :class="reconciliation.reconciled ? 'text-green-600 dark:text-green-400' : ''"
                        >
                            {{ format(reconciliation.totalAccounts) }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            Saldos de cuentas (excluidas no)
                        </span>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-sm text-muted-foreground">
                            Balance real
                        </span>
                        <span class="text-2xl font-bold tabular-nums">
                            {{ format(reconciliation.realBalance) }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            Apertura + movimientos hasta hoy
                        </span>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-sm text-muted-foreground">
                            Diferencia
                        </span>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-2xl font-bold tabular-nums"
                                :class="reconciliation.reconciled
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'"
                            >
                                {{ format(reconciliation.difference) }}
                            </span>
                        </div>
                        <span
                            class="text-sm"
                            :class="reconciliation.reconciled
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'"
                        >
                            <span v-if="reconciliation.reconciled" class="inline-flex items-center gap-1">
                                <CheckCircle2 class="size-4" />
                                Conciliado
                            </span>
                            <span v-else class="inline-flex items-center gap-1">
                                <AlertTriangle class="size-4" />
                                Descuadre de {{ format(Math.abs(reconciliation.difference)) }}
                            </span>
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Create / Edit Dialog -->
    <AccountDialog
        v-model:open="showCreateDialog"
        :account="editingAccount"
        @saved="showCreateDialog = false"
    />

    <!-- Delete Confirmation Dialog -->
    <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = false">
        <DialogContent class="sm:max-w-[380px]">
            <DialogHeader>
                <DialogTitle>Eliminar cuenta</DialogTitle>
                <DialogDescription>
                    ¿Estás seguro de eliminar esta cuenta?
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
