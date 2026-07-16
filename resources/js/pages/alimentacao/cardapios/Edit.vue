<script setup lang="ts">
import CardapioForm from '@/components/alimentacao/CardapioForm.vue';
import CardapioGradeTab from '@/components/alimentacao/CardapioGradeTab.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Cardapio, CardapioDiaLinha } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    cardapio: Cardapio;
    dias: CardapioDiaLinha[];
    anosLetivos: { anl_id: number; anl_ano: number }[];
    turnos: string[];
    refeicoes: string[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Cardápio Semanal', href: '/alimentacao/cardapios' },
    { title: props.cardapio.crd_nome, href: `/alimentacao/cardapios/${props.cardapio.crd_id}/edit` },
]);

const escopo = computed(() =>
    props.cardapio.crd_esc_id
        ? props.cardapio.escola?.esc_nome
        : 'Padrão da rede — vale para toda escola sem cardápio próprio',
);
</script>

<template>
    <Head :title="cardapio.crd_nome" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ cardapio.crd_nome }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ escopo }}</p>
            </div>
            <CardapioForm mode="edit" :initial="cardapio" :anos-letivos="anosLetivos" />
            <CardapioGradeTab :cardapio="cardapio" :dias="dias" :turnos="turnos" :refeicoes="refeicoes" />
        </div>
    </AppLayout>
</template>
