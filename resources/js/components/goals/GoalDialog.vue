<script setup lang="ts">
import type { GoalData } from '@/components/goals/types';
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
import metas from '@/routes/metas';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
  open: boolean;
  goal: GoalData | null;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved'): void;
}>();

const form = useForm({
  name: '',
  target_amount: '',
  target_date: '',
});

watch(
  () => props.open,
  isOpen => {
    if (!isOpen) {
      return;
    }

    form.reset();
    form.clearErrors();

    if (props.goal) {
      form.name = props.goal.name;
      form.target_amount = String(props.goal.target_amount);
      form.target_date = props.goal.target_date ?? '';
    }
  }
);

const isEditing = () => props.goal !== null;

function closeDialog(): void {
  emit('update:open', false);
}

function submit(): void {
  form.transform(data => ({
    ...data,
    target_amount: Number(data.target_amount),
    target_date: data.target_date || null,
  }));

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      emit('saved');
      closeDialog();
    },
  };

  if (isEditing()) {
    form.put(metas.update.url(props.goal!.id), options);
  } else {
    form.post(metas.store.url(), options);
  }
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="closeDialog"
  >
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>
          {{ isEditing() ? 'Editar meta' : 'Nueva meta' }}
        </DialogTitle>
        <DialogDescription>
          {{
            isEditing()
              ? 'Actualiza el nombre, el monto objetivo o la fecha. Si subes el monto, la meta podría reabrirse.'
              : 'Define cuánto querés ahorrar y, opcionalmente, para cuándo.'
          }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <div class="grid gap-2">
          <Label for="name">Nombre</Label>
          <Input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Ej: Laptop nueva"
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="target_amount">Monto objetivo</Label>
          <Input
            id="target_amount"
            v-model="form.target_amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
          />
          <InputError :message="form.errors.target_amount" />
        </div>

        <div class="grid gap-2">
          <Label for="target_date">Fecha objetivo (opcional)</Label>
          <Input
            id="target_date"
            v-model="form.target_date"
            type="date"
          />
          <InputError :message="form.errors.target_date" />
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
