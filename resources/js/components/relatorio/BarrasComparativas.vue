<script setup lang="ts">
/**
 * Barras horizontais comparativas para relatórios.
 *
 * Horizontais de propósito: os rótulos são nomes de escola, que não cabem no
 * eixo X sem virar de lado e ficar ilegível.
 *
 * Passe `fixo` na instância que vai para o bloco de impressão. Chart.js
 * `responsive` mede o container, e o `#print-area` fica com `display: none` na
 * tela — o canvas nasceria com altura zero e a folha sairia em branco. Com
 * `fixo`, o tamanho é do atributo do canvas e não depende de layout.
 */
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';
import { Bar } from 'vue-chartjs';
import { computed } from 'vue';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip);

const props = withDefaults(defineProps<{
    itens: { nome: string; valor: number }[];
    /** Rótulo da grandeza — vai no tooltip. */
    rotulo?: string;
    fixo?: boolean;
    largura?: number;
    altura?: number;
}>(), {
    rotulo: 'Valor',
    fixo: false,
    largura: 900,
    altura: 320,
});

const moeda = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const chartData = computed(() => ({
    labels: props.itens.map((i) => i.nome),
    datasets: [{
        label: props.rotulo,
        data: props.itens.map((i) => i.valor),
        backgroundColor: '#6366f1',
        hoverBackgroundColor: '#4f46e5',
        borderRadius: 4,
        barThickness: 'flex' as const,
        maxBarThickness: 28,
    }],
}));

const chartOptions = computed(() => ({
    indexAxis: 'y' as const,
    responsive: !props.fixo,
    maintainAspectRatio: false,
    // Sem animação na cópia impressa: window.print() dispara antes de a animação
    // terminar e a barra sairia pela metade na folha.
    animation: props.fixo ? (false as const) : undefined,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: any) => `${props.rotulo}: ${moeda(ctx.parsed.x ?? 0)}`,
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            ticks: { callback: (v: any) => moeda(Number(v)) },
            grid: { color: 'rgba(148, 163, 184, 0.2)' },
        },
        y: {
            grid: { display: false },
        },
    },
}));
</script>

<template>
    <Bar
        v-if="itens.length"
        :data="chartData"
        :options="chartOptions"
        :width="fixo ? largura : undefined"
        :height="fixo ? altura : undefined"
    />
</template>
