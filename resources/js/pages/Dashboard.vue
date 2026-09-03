<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import BalanceLineChart from '@/components/ui/chart/BalanceLineChart.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { dashboard } from '@/routes';
import deudas from '@/routes/deudas';

interface BudgetCategory {
    id: number;
    name: string;
    color: string | null;
    monthly_limit: number;
    spent: number;
}

interface ActiveDebtSummary {
    id: number;
    name: string;
    remaining: number;
    paid_installments: number;
    installments_count: number;
    rate_factor: number;
    next_date: string | null;
    next_amount: number;
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
    debtsOverview: ActiveDebtSummary[];
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

const isCurrentMonth = computed(
    () => props.selectedMonth === props.currentMonth,
);

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

// --- Keyboard shortcuts ---
useKeyboardShortcuts([
    { key: 'ArrowLeft', handler: () => navigateMonth(-1) },
    { key: 'ArrowRight', handler: () => navigateMonth(1) },
]);

// --- Progress bar helpers ---
function progressPercentage(cat: BudgetCategory): number {
    if (!cat.monthly_limit || cat.monthly_limit <= 0) {
        return 0;
    }

    return (cat.spent / cat.monthly_limit) * 100;
}

function debtProgress(debt: ActiveDebtSummary): number {
    if (debt.installments_count === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((debt.paid_installments / debt.installments_count) * 100),
    );
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
</script>

<template>
    <Head :title="`Tablero — ${monthLabel}`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 sm:gap-6">
        <!-- Header -->
        <div class="mb-2">
            <h1 class="text-2xl font-bold tracking-tight">Tablero</h1>
            <p class="text-sm text-muted-foreground">
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
                title="Mes anterior (←)"
            >
                <ChevronLeft class="size-4" />
            </Button>

            <span
                class="min-w-[160px] text-center text-lg font-semibold capitalize"
            >
                {{ monthLabel }}
            </span>

            <Button
                variant="outline"
                size="icon"
                @click="navigateMonth(1)"
                aria-label="Mes siguiente"
                title="Mes siguiente (→)"
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
        <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
            <Card class="py-3 sm:py-5">
                <CardContent class="px-4 sm:px-6">
                    <div
                        class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Balance actual
                        </CardTitle>
                        <p
                            class="text-xl font-bold tabular-nums sm:text-2xl"
                            :class="
                                cards.realBalance >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                            "
                        >
                            {{ format(cards.realBalance) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="py-3 sm:py-5">
                <CardContent class="px-4 sm:px-6">
                    <div
                        class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Ingresos del mes
                        </CardTitle>
                        <p
                            class="text-xl font-bold text-green-600 tabular-nums sm:text-2xl dark:text-green-400"
                        >
                            {{ formatSigned(cards.monthIncome) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="py-3 sm:py-5">
                <CardContent class="px-4 sm:px-6">
                    <div
                        class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Gastos del mes
                        </CardTitle>
                        <p
                            class="text-xl font-bold text-red-600 tabular-nums sm:text-2xl dark:text-red-400"
                        >
                            -{{ format(cards.monthExpense) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="py-3 sm:py-5">
                <CardContent class="px-4 sm:px-6">
                    <div
                        class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Proyección a fin de mes
                        </CardTitle>
                        <p
                            class="text-xl font-bold tabular-nums sm:text-2xl"
                            :class="
                                cards.projectedEndOfMonth >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                            "
                        >
                            {{ format(cards.projectedEndOfMonth) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Row: Budget Overview + Mini Reconciliation -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Budget Overview -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >Resumen de presupuesto</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div v-if="budgetOverview.length > 0" class="space-y-4">
                        <div
                            v-for="cat in budgetOverview"
                            :key="cat.id"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="cat.color"
                                        class="inline-block size-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: cat.color }"
                                    />
                                    <span class="font-medium">{{
                                        cat.name
                                    }}</span>
                                </div>
                                <span
                                    class="text-muted-foreground tabular-nums"
                                >
                                    {{ format(cat.spent) }}
                                    /
                                    {{ format(cat.monthly_limit) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div
                                    class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                                >
                                    <div
                                        role="progressbar"
                                        :aria-valuenow="
                                            Math.round(progressPercentage(cat))
                                        "
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        :aria-label="`Presupuesto ${cat.name}: ${Math.round(progressPercentage(cat))}% usado`"
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="
                                            progressColor(
                                                progressPercentage(cat),
                                            )
                                        "
                                        :style="{
                                            width:
                                                Math.min(
                                                    progressPercentage(cat),
                                                    100,
                                                ) + '%',
                                        }"
                                    />
                                </div>
                                <span
                                    class="shrink-0 text-xs font-medium tabular-nums"
                                    :class="{
                                        'text-red-600 dark:text-red-400':
                                            progressPercentage(cat) > 100,
                                        'text-amber-600 dark:text-amber-400':
                                            progressPercentage(cat) >= 75 &&
                                            progressPercentage(cat) <= 100,
                                        'text-green-600 dark:text-green-400':
                                            progressPercentage(cat) < 75,
                                    }"
                                >
                                    {{ Math.round(progressPercentage(cat)) }}%
                                </span>
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
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
                            <span class="text-sm text-muted-foreground"
                                >Total cuentas</span
                            >
                            <span class="text-sm font-medium tabular-nums">{{
                                format(reconciliation.totalAccounts)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground"
                                >Balance real</span
                            >
                            <span class="text-sm font-medium tabular-nums">{{
                                format(reconciliation.realBalance)
                            }}</span>
                        </div>
                        <hr class="border-t border-border" />
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Diferencia</span>
                            <span
                                class="text-sm font-bold tabular-nums"
                                :class="
                                    reconciliation.reconciled
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                "
                            >
                                {{
                                    reconciliation.reconciled
                                        ? '✅ Conciliado'
                                        : `⚠️ ${format(Math.abs(reconciliation.difference))}`
                                }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Active Debts -->
        <Card>
            <CardHeader class="flex-row items-center justify-between space-y-0">
                <CardTitle class="text-base">Deudas activas</CardTitle>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="deudas.index()">Ver todas</Link>
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="debtsOverview.length > 0" class="space-y-4">
                    <div
                        v-for="debt in debtsOverview"
                        :key="debt.id"
                        class="space-y-1.5"
                    >
                        <div
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <Link
                                :href="deudas.show.url(debt.id)"
                                class="truncate font-medium transition-colors hover:underline"
                            >
                                {{ debt.name }}
                            </Link>
                            <span
                                class="shrink-0 text-muted-foreground tabular-nums"
                            >
                                {{ debt.paid_installments }}/{{
                                    debt.installments_count
                                }}
                                · {{ debtProgress(debt) }}%
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                            >
                                <div
                                    role="progressbar"
                                    :aria-valuenow="debtProgress(debt)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-label="`Progreso de ${debt.name}: ${debtProgress(debt)}%`"
                                    class="h-full rounded-full bg-primary transition-all duration-300"
                                    :style="{
                                        width: debtProgress(debt) + '%',
                                    }"
                                />
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span>
                                Restante
                                <span class="font-medium tabular-nums">
                                    {{ format(debt.remaining) }}
                                </span>
                            </span>
                            <span v-if="debt.next_date">
                                Próxima cuota
                                <span class="font-medium tabular-nums">
                                    {{ formatDate(debt.next_date) }}
                                    · {{ format(debt.next_amount) }}
                                </span>
                            </span>
                            <span v-else>Sin cuotas pendientes</span>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    No hay deudas activas.
                </p>
            </CardContent>
        </Card>

        <!-- Row: Upcoming Projections + Chart -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Upcoming Projected Movements -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base"
                        >Próximos movimientos (7 días)</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="upcomingProjections.length > 0"
                        class="space-y-3"
                    >
                        <div
                            v-for="mov in upcomingProjections"
                            :key="mov.id"
                            class="flex items-center justify-between gap-4 rounded-md border p-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ mov.description }}</span
                                    >
                                    <Badge
                                        v-if="mov.is_projected"
                                        variant="outline"
                                        class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                                    >
                                        Proyectado
                                    </Badge>
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ formatDate(mov.date) }}
                                    <span v-if="mov.category_name">
                                        · {{ mov.category_name }}</span
                                    >
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-sm font-bold tabular-nums"
                                :class="
                                    mov.amount >= 0
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                "
                            >
                                {{ formatSigned(mov.amount) }}
                            </span>
                        </div>
                    </div>
                    <p
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
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
