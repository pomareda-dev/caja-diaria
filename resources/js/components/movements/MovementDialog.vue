<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Switch } from '@/components/ui/switch';
import movimientos from '@/routes/movimientos';

export interface MovementData {
    id: number;
    date: string;
    description: string;
    category_id: number | null;
    category_name: string | null;
    category_color: string | null;
    amount: number;
    running_balance: number;
    is_projected: boolean;
    notes: string | null;
}

export interface CategoryData {
    id: number;
    name: string;
    kind: string;
    color: string | null;
}

const props = defineProps<{
    open: boolean;
    movement: MovementData | null;
    categories: CategoryData[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const transactionType = ref<'income' | 'expense'>('expense');
const displayAmount = ref('');

const form = useForm({
    date: '',
    description: '',
    category_id: 'none',
    amount: '',
    notes: '',
    is_projected: false,
});

function resetForm() {
    form.reset();
    form.clearErrors();
    transactionType.value = 'expense';
    displayAmount.value = '';
    form.is_projected = false;
}

function populateFormForEdit(movement: MovementData) {
    form.date = movement.date;
    form.description = movement.description;
    form.category_id = movement.category_id ? String(movement.category_id) : 'none';
    form.notes = movement.notes || '';
    form.is_projected = movement.is_projected ?? false;

    if (movement.amount >= 0) {
        transactionType.value = 'income';
        displayAmount.value = String(movement.amount);
    } else {
        transactionType.value = 'expense';
        displayAmount.value = String(Math.abs(movement.amount));
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.movement) {
            populateFormForEdit(props.movement);
        } else if (isOpen && !props.movement) {
            const today = new Date();
            form.date = today.toISOString().split('T')[0];
        }
    },
);

const isEditing = () => props.movement !== null;

function closeDialog() {
    emit('update:open', false);
    resetForm();
}

function submit() {
    const absAmount = Math.abs(parseFloat(displayAmount.value) || 0);
    const signedAmount =
        transactionType.value === 'income' ? absAmount : -absAmount;

    form.transform((data) => ({
        ...data,
        amount: String(signedAmount),
        category_id:
            data.category_id === 'none' ? null : Number(data.category_id),
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            closeDialog();
        },
    };

    if (isEditing()) {
        form.put(movimientos.update.url(props.movement!.id), options);
    } else {
        form.post(movimientos.store.url(), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing() ? 'Editar movimiento' : 'Nuevo movimiento' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing()
                            ? 'Actualiza los datos del movimiento.'
                            : 'Registra un nuevo ingreso o gasto.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <!-- Tipo: Ingreso / Gasto -->
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1"
                        :class="transactionType === 'income' ? 'bg-green-50 text-green-700 border-green-300 dark:bg-green-950 dark:text-green-400 dark:border-green-800' : ''"
                        @click="transactionType = 'income'"
                    >
                        Ingreso
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1"
                        :class="transactionType === 'expense' ? 'bg-red-50 text-red-700 border-red-300 dark:bg-red-950 dark:text-red-400 dark:border-red-800' : ''"
                        @click="transactionType = 'expense'"
                    >
                        Gasto
                    </Button>
                </div>

                <!-- Monto -->
                <div class="grid gap-2">
                    <Label for="amount">Monto</Label>
                    <Input
                        id="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        v-model="displayAmount"
                    />
                    <InputError :message="form.errors.amount" />
                </div>

                <!-- Fecha -->
                <div class="grid gap-2">
                    <Label for="date">Fecha</Label>
                    <Input
                        id="date"
                        type="date"
                        v-model="form.date"
                    />
                    <InputError :message="form.errors.date" />
                </div>

                <!-- Proyectado -->
                <div class="flex items-center gap-3">
                    <Switch
                        id="is_projected"
                        v-model="form.is_projected"
                    />
                    <Label for="is_projected" class="cursor-pointer">
                        Marcar como proyectado
                    </Label>
                    <InputError :message="form.errors.is_projected" />
                </div>

                <!-- Descripción -->
                <div class="grid gap-2">
                    <Label for="description">Descripción</Label>
                    <Input
                        id="description"
                        type="text"
                        placeholder="Ej: Compra en supermercado"
                        v-model="form.description"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Categoría -->
                <div class="grid gap-2">
                    <Label for="category">Categoría</Label>
                    <Select v-model="form.category_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Seleccionar categoría" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="none">
                                    Sin categoría
                                </SelectItem>
                                <SelectItem
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                >
                                    {{ cat.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <!-- Notas -->
                <div class="grid gap-2">
                    <Label for="notes">Notas (opcional)</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[80px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-3 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Notas adicionales..."
                    />
                    <InputError :message="form.errors.notes" />
                </div>

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
                        {{ form.processing ? 'Guardando...' : isEditing() ? 'Actualizar' : 'Registrar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
