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

const chartData = computed(() => ({
    labels: props.data.map((d) => {
        // Show only day number for compact labels
        const day = d.date.split('-')[2];
        // Show label every ~5 days or first day
        return day;
    }),
    datasets: [
        {
            label: 'Balance',
            data: props.data.map((d) => d.balance),
            borderColor: 'hsl(var(--primary))',
            backgroundColor: (ctx: any) => {
                if (!ctx.chart.chartArea) {
                    return;
                }
                const gradient = ctx.chart.ctx.createLinearGradient(
                    0,
                    ctx.chart.chartArea.top,
                    0,
                    ctx.chart.chartArea.bottom,
                );
                gradient.addColorStop(0, 'hsl(var(--primary) / 0.3)');
                gradient.addColorStop(1, 'hsl(var(--primary) / 0.02)');
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
                color: 'hsl(var(--border) / 0.5)',
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
    <div class="relative h-64 w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
