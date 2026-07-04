<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import cuentas from '@/routes/cuentas';

export interface AccountData {
    id: number;
    name: string;
    kind: 'bank' | 'wallet' | 'cash' | 'credit' | 'other';
    balance: number;
    exclude_from_reconciliation: boolean;
    sort_order: number;
}

const props = defineProps<{
    open: boolean;
    account: AccountData | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const form = useForm({
    name: '',
    kind: 'bank',
    balance: '',
    exclude_from_reconciliation: false,
    sort_order: '',
});

function resetForm() {
    form.reset();
    form.clearErrors();
}

function populateFormForEdit(account: AccountData) {
    form.name = account.name;
    form.kind = account.kind;
    form.balance = String(account.balance);
    form.exclude_from_reconciliation = account.exclude_from_reconciliation;
    form.sort_order = String(account.sort_order);
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.account) {
            populateFormForEdit(props.account);
        }
    },
);

const isEditing = () => props.account !== null;

function closeDialog() {
    emit('update:open', false);
    resetForm();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            closeDialog();
        },
    };

    if (isEditing()) {
        form.put(cuentas.update.url(props.account!.id), options);
    } else {
        form.post(cuentas.store.url(), options);
    }
}

const kindLabels: Record<string, string> = {
    bank: 'Banco',
    wallet: 'Billetera',
    cash: 'Efectivo',
    credit: 'Tarjeta de crédito',
    other: 'Otro',
};
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing() ? 'Editar cuenta' : 'Nueva cuenta' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing()
                            ? 'Actualiza los datos de la cuenta.'
                            : 'Registra una nueva cuenta para gestionar tu saldo.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <!-- Nombre -->
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        type="text"
                        placeholder="Ej: BCP, Yape, Efectivo"
                        v-model="form.name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Tipo -->
                <div class="grid gap-2">
                    <Label for="kind">Tipo</Label>
                    <Select v-model="form.kind">
                        <SelectTrigger>
                            <SelectValue placeholder="Seleccionar tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem
                                    v-for="(label, value) in kindLabels"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.kind" />
                </div>

                <!-- Saldo -->
                <div class="grid gap-2">
                    <Label for="balance">Saldo</Label>
                    <Input
                        id="balance"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        v-model="form.balance"
                    />
                    <InputError :message="form.errors.balance" />
                </div>

                <!-- Orden -->
                <div class="grid gap-2">
                    <Label for="sort_order">Orden</Label>
                    <Input
                        id="sort_order"
                        type="number"
                        step="1"
                        placeholder="0"
                        v-model="form.sort_order"
                    />
                    <InputError :message="form.errors.sort_order" />
                </div>

                <!-- Excluir de conciliación -->
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="exclude_from_reconciliation"
                        :checked="form.exclude_from_reconciliation"
                        @update:checked="form.exclude_from_reconciliation = $event"
                    />
                    <Label for="exclude_from_reconciliation" class="text-sm font-normal leading-none cursor-pointer">
                        Excluir de conciliación
                    </Label>
                </div>
                <InputError :message="form.errors.exclude_from_reconciliation" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeDialog"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Guardando...' : isEditing() ? 'Actualizar' : 'Crear' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
