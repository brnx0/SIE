<script setup lang="ts">
import { computed } from 'vue';

// Badge de vencimento de documento (N2/N3): vencido = vermelho,
// vence em até 30 dias = âmbar, em dia = verde, sem data = neutro.
const props = defineProps<{
    date: string | null | undefined;
    label: string;
}>();

const info = computed(() => {
    if (!props.date) {
        return {
            cls: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
            text: `${props.label}: —`,
            title: 'Sem data cadastrada',
        };
    }
    const d = new Date(`${props.date.slice(0, 10)}T00:00:00`);
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    const dias = Math.round((d.getTime() - hoje.getTime()) / 86400000);
    const fmt = d.toLocaleDateString('pt-BR');

    if (dias < 0) {
        return {
            cls: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            text: `${props.label}: ${fmt}`,
            title: `Vencido há ${Math.abs(dias)} dia(s)`,
        };
    }
    if (dias <= 30) {
        return {
            cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            text: `${props.label}: ${fmt}`,
            title: `Vence em ${dias} dia(s)`,
        };
    }
    return {
        cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        text: `${props.label}: ${fmt}`,
        title: 'Em dia',
    };
});
</script>

<template>
    <span :class="['inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium', info.cls]" :title="info.title">
        {{ info.text }}
    </span>
</template>
