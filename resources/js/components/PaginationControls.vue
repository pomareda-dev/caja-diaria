<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

withDefaults(
  defineProps<{
    pagination: PaginationMeta;
    perPageOptions?: number[];
  }>(),
  {
    perPageOptions: () => [10, 25, 50, 100],
  }
);

const emit = defineEmits<{
  'update:page': [page: number];
  'update:per-page': [perPage: number];
}>();

function onPerPageChange(value: unknown) {
  emit('update:per-page', Number(value));
}
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <!-- left: rows-per-page selector + range info -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center gap-2">
        <span class="text-sm text-muted-foreground">Filas por página</span>
        <Select
          :model-value="pagination.per_page"
          @update:model-value="onPerPageChange"
        >
          <SelectTrigger class="h-8 w-[70px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="opt in perPageOptions"
              :key="opt"
              :value="opt"
            >
              {{ opt }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <span class="text-sm text-muted-foreground">
        Mostrando {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} de
        {{ pagination.total }}
      </span>
    </div>
    <!-- right: prev/next + page indicator -->
    <div
      v-if="pagination.last_page > 1"
      class="flex items-center gap-2"
    >
      <Button
        variant="outline"
        size="sm"
        :disabled="pagination.current_page <= 1"
        @click="emit('update:page', pagination.current_page - 1)"
      >
        Anterior
      </Button>
      <span class="text-sm whitespace-nowrap text-muted-foreground">
        Página {{ pagination.current_page }} de
        {{ pagination.last_page }}
      </span>
      <Button
        variant="outline"
        size="sm"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="emit('update:page', pagination.current_page + 1)"
      >
        Siguiente
      </Button>
    </div>
  </div>
</template>
