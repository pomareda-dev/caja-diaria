<script setup lang="ts">
import type { DebtDetailData } from '@/components/debts/types';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { computed } from 'vue';

const props = defineProps<{
  debt: DebtDetailData;
}>();

const { format } = useCurrency();

const progressPercentage = computed(() => {
  if (!props.debt.is_active) {
    return 100;
  }

  if (props.debt.installments_count === 0) {
    return 0;
  }

  return Math.min(100, Math.round((props.debt.paid_installments / props.debt.installments_count) * 100));
});
</script>

<template>
  <Card class="gap-4 py-4">
    <CardContent class="space-y-4">
      <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
          <dt class="text-sm text-muted-foreground">Prestado</dt>
          <dd class="text-lg font-semibold tabular-nums">
            {{ format(debt.principal_amount) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">A pagar</dt>
          <dd class="text-lg font-semibold tabular-nums">
            {{ format(debt.total_to_pay) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Pagado</dt>
          <dd class="text-lg font-semibold text-green-600 tabular-nums dark:text-green-400">
            {{ format(debt.paid) }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Restante</dt>
          <dd class="text-lg font-semibold tabular-nums">
            {{ format(debt.remaining) }}
          </dd>
        </div>
      </dl>

      <div class="space-y-1.5">
        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">Cuotas pagadas</span>
          <span class="font-medium tabular-nums">
            {{ debt.paid_installments }}/{{ debt.installments_count }} · {{ progressPercentage }}%
          </span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
          <div
            role="progressbar"
            :aria-valuenow="progressPercentage"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Progreso de ${debt.name}: ${progressPercentage}%`"
            class="h-full rounded-full bg-primary transition-all duration-300"
            :style="{ width: progressPercentage + '%' }"
          />
        </div>
      </div>
    </CardContent>
  </Card>
</template>
