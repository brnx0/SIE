<script setup lang="ts">
import PratoForm from '@/components/alimentacao/PratoForm.vue';
import PratoItensTab from '@/components/alimentacao/PratoItensTab.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Prato, PratoItemLinha, UnidadeMedida } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    prato: Prato;
    itens: PratoItemLinha[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
    temNutricional: boolean;
}>();

// `temTabelaNutricional()` é método do model e não serializa via toArray() —
// vem como prop separada e é mesclada aqui.
const pratoCompleto = computed<Prato>(() => ({ ...props.prato, tem_nutricional: props.temNutricional }));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Pratos', href: '/alimentacao/pratos' },
    { title: props.prato.prt_nome, href: `/alimentacao/pratos/${props.prato.prt_id}/edit` },
]);
</script>

<template>
    <Head :title="prato.prt_nome" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ prato.prt_nome }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Prato do cardápio escolar</p>
            </div>
            <PratoForm mode="edit" :initial="pratoCompleto" :unidades="unidades" />
            <PratoItensTab :prato="prato" :itens="itens" :unidades="unidades" />
        </div>
    </AppLayout>
</template>
