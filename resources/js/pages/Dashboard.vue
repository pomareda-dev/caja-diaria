<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import BalanceLineChart from '@/components/ui/chart/BalanceLineChart.vue';
import { dashboard } from '@/routes';

interface BudgetCategory {
    id: number;
    name: string;
    color: string | null;
    monthly_limit: number;
    spent: number;
}

interface UpcomingMovement {
    id: number;
    date: string;
    description: string;
    category_name: string | null;
    amount: number;
    is_projected: boolean;
}

interface ChartPoint {
    date: string;
    balance: number;
}

const props = defineProps<{
    cards: {
        realBalance: number;
        monthIncome: number;
        monthExpense: number;
        projectedEndOfMonth: number;
    };
    budgetOverview: BudgetCategory[];
    reconciliation: {
        totalAccounts: number;
        realBalance: number;
        difference: number;
        reconciled: boolean;
    };
    upcomingProjections: UpcomingMovement[];
    chartData: ChartPoint[];
    selectedMonth: string;
    currentMonth: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tablero',
                href: dashboard(),
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
    router.visit(`${dashboard.url()}?month=${year}-${month}`, {
        preserveScroll: true,
    });
}

function goToToday() {
    router.visit(dashboard.url(), {
        preserveScroll: true,
    });
}

// --- Progress bar helpers ---
function progressPercentage(cat: BudgetCategory): number {
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

// --- Date formatting ---
function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

function formatShortDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Head :title="`Tablero — ${monthLabel}`" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Tablero</h1>
            <p class="text-muted-foreground text-sm">
                Resumen financiero del mes
            </p>
        </div>

        <!-- Month Navigation -->
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

        <!-- Metric Cards (4) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Balance actual
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="cards.realBalance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ format(cards.realBalance) }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Ingresos del mes
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 tabular-nums">
                        {{ formatSigned(cards.monthIncome) }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Gastos del mes
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 tabular-nums">
                        -{{ format(cards.monthExpense) }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        Proyección a fin de mes
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="cards.projectedEndOfMonth >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ format(cards.projectedEndOfMonth) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Row: Budget Overview + Mini Reconciliation -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Budget Overview -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Resumen de presupuesto</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="budgetOverview.length > 0" class="space-y-4">
                        <div
                            v-for="cat in budgetOverview"
                            :key="cat.id"
                            class="space-y-1.5"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="cat.color"
                                        class="inline-block size-2.5 rounded-full shrink-0"
                                        :style="{ backgroundColor: cat.color }"
                                    />
                                    <span class="font-medium">{{ cat.name }}</span>
                                </div>
                                <span class="tabular-nums text-muted-foreground">
                                    {{ format(cat.spent) }}
                                    /
                                    {{ format(cat.monthly_limit) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
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
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground py-4 text-center">
                        No hay categorías con presupuesto definido.
                    </p>
                </CardContent>
            </Card>

            <!-- Mini Reconciliation -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Mini conciliación</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Total cuentas</span>
                            <span class="text-sm font-medium tabular-nums">{{ format(reconciliation.totalAccounts) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Balance real</span>
                            <span class="text-sm font-medium tabular-nums">{{ format(reconciliation.realBalance) }}</span>
                        </div>
                        <hr class="border-t border-border">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Diferencia</span>
                            <span
                                class="text-sm font-bold tabular-nums"
                                :class="reconciliation.reconciled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ reconciliation.reconciled ? '✅ Conciliado' : `⚠️ ${format(Math.abs(reconciliation.difference))}` }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Row: Upcoming Projections + Chart -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Upcoming Projected Movements -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Próximos movimientos (7 días)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="upcomingProjections.length > 0" class="space-y-3">
                        <div
                            v-for="mov in upcomingProjections"
                            :key="mov.id"
                            class="flex items-center justify-between gap-4 rounded-md border p-3"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium truncate">{{ mov.description }}</span>
                                    <Badge
                                        v-if="mov.is_projected"
                                        variant="outline"
                                        class="text-amber-600 border-amber-300 bg-amber-50 dark:text-amber-400 dark:border-amber-800 dark:bg-amber-950 text-[10px] px-1.5 py-0"
                                    >
                                        Proyectado
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ formatDate(mov.date) }}
                                    <span v-if="mov.category_name"> · {{ mov.category_name }}</span>
                                </p>
                            </div>
                            <span
                                class="text-sm font-bold tabular-nums shrink-0"
                                :class="mov.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ formatSigned(mov.amount) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground py-4 text-center">
                        No hay movimientos proyectados para los próximos 7 días.
                    </p>
                </CardContent>
            </Card>

            <!-- Chart -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Balance del mes</CardTitle>
                </CardHeader>
                <CardContent>
                    <BalanceLineChart :data="chartData" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
