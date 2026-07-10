<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { ColorPicker } from '@/components/ui/color-picker';
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
import categorias from '@/routes/categorias';

export interface CategoryData {
    id: number;
    name: string;
    kind: 'expense' | 'income' | 'transfer';
    color: string | null;
    monthly_limit: number | null;
    spent: number;
    balance: number;
    sort_order: number;
}

const props = defineProps<{
    open: boolean;
    category: CategoryData | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const form = useForm({
    name: '',
    kind: 'expense',
    monthly_limit: '',
    color: '',
    sort_order: '',
});

function resetForm() {
    form.reset();
    form.clearErrors();
}

function populateFormForEdit(category: CategoryData) {
    form.name = category.name;
    form.kind = category.kind;
    form.monthly_limit = category.monthly_limit !== null ? String(category.monthly_limit) : '';
    form.color = category.color || '';
    form.sort_order = String(category.sort_order);
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.category) {
            populateFormForEdit(props.category);
        } else if (isOpen && !props.category) {
            const maxOrder = 0;
            form.sort_order = String(maxOrder);
        }
    },
);

const isEditing = () => props.category !== null;

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
        form.put(categorias.update.url(props.category!.id), options);
    } else {
        form.post(categorias.store.url(), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing() ? 'Editar categoría' : 'Nueva categoría' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing()
                            ? 'Actualiza los datos de la categoría.'
                            : 'Crea una nueva categoría para clasificar tus movimientos.'
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
                        placeholder="Ej: Mercado, Sueldo"
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
                                <SelectItem value="expense">
                                    Gasto
                                </SelectItem>
                                <SelectItem value="income">
                                    Ingreso
                                </SelectItem>
                                <SelectItem value="transfer">
                                    Transferencia
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.kind" />
                </div>

                <!-- Límite mensual -->
                <div class="grid gap-2">
                    <Label for="monthly_limit">Límite mensual (opcional)</Label>
                    <Input
                        id="monthly_limit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        v-model="form.monthly_limit"
                    />
                    <InputError :message="form.errors.monthly_limit" />
                </div>

                <!-- Color -->
                <div class="grid gap-2">
                    <Label>Color (opcional)</Label>
                    <ColorPicker v-model="form.color" />
                    <InputError :message="form.errors.color" />
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
