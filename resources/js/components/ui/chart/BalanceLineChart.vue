<script setup lang="ts">
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler);

const props = defineProps<{
    data: { date: string; balance: number }[];
}>();

// Canvas does not resolve CSS var() — read the resolved value from the DOM and
// pass a concrete HSL/RGB string to Chart.js so addColorStop/borderColor work.
function resolveToken(name: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
    return raw ? `hsl(${raw})` : fallback;
}

function resolveTokenWithAlpha(name: string, alpha: number, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
    return raw ? `hsl(${raw} / ${alpha})` : fallback;
}

const chartData = computed(() => ({
    labels: props.data.map((d) => d.date.split('-')[2]),
    datasets: [
        {
            label: 'Balance',
            data: props.data.map((d) => d.balance),
            borderColor: resolveToken('--primary', 'hsl(0 0% 9%)'),
            backgroundColor: (ctx: any) => {
                const { chart } = ctx;
                if (!chart.chartArea) {
                    return resolveTokenWithAlpha('--primary', 0.3, 'hsl(0 0% 9% / 0.3)');
                }
                const gradient = chart.ctx.createLinearGradient(
                    0,
                    chart.chartArea.top,
                    0,
                    chart.chartArea.bottom,
                );
                gradient.addColorStop(
                    0,
                    resolveTokenWithAlpha('--primary', 0.3, 'hsl(0 0% 9% / 0.3)'),
                );
                gradient.addColorStop(
                    1,
                    resolveTokenWithAlpha('--primary', 0.02, 'hsl(0 0% 9% / 0.02)'),
                );
                return gradient;
            },
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                title: (items: any[]) => {
                    if (items.length > 0) {
                        const idx = items[0].dataIndex;
                        return props.data[idx]?.date ?? '';
                    }
                    return '';
                },
                label: (item: any) => {
                    const value = item.raw as number;
                    return new Intl.NumberFormat('es-PE', {
                        style: 'currency',
                        currency: 'PEN',
                        minimumFractionDigits: 2,
                    }).format(value);
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxTicksLimit: 10,
                font: {
                    size: 11,
                },
            },
        },
        y: {
            grid: {
                color: resolveTokenWithAlpha('--border', 0.5, 'hsl(0 0% 9% / 0.1)'),
            },
            ticks: {
                maxTicksLimit: 6,
                font: {
                    size: 11,
                },
                callback: (value: any) => {
                    return new Intl.NumberFormat('es-PE', {
                        style: 'currency',
                        currency: 'PEN',
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    }).format(value);
                },
            },
        },
    },
    interaction: {
        intersect: false,
        mode: 'index' as const,
    },
};
</script>

<template>
    <div
        v-if="data.length === 0"
        class="flex h-64 items-center justify-center text-sm text-muted-foreground"
    >
        No hay datos para este mes.
    </div>
    <div v-else class="relative h-64 w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
