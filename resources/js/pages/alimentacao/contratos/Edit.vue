<script setup lang="ts">
import AnexosPanel from '@/components/alimentacao/AnexosPanel.vue';
import ContratoAditivosTab from '@/components/alimentacao/ContratoAditivosTab.vue';
import ContratoForm from '@/components/alimentacao/ContratoForm.vue';
import ContratoItensTab from '@/components/alimentacao/ContratoItensTab.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import type { BreadcrumbItem } from '@/types';
import type { AditivoLinha, Anexo, Contrato, ContratoItemLinha, UnidadeMedida } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    contrato: Contrato;
    itens: ContratoItemLinha[];
    aditivos: AditivoLinha[];
    anexos: Anexo[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
    situacoes: string[];
    tiposAditivo: string[];
    temMovimento: boolean;
    totais: { valor_itens: number; valor_ordens: number };
}>();

const { isReadOnly } = usePermissao();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Contratos', href: '/alimentacao/contratos' },
    { title: `${props.contrato.ctr_nr_contrato}/${props.contrato.ctr_ano}`, href: `/alimentacao/contratos/${props.contrato.ctr_id}/edit` },
]);
</script>

<template>
    <Head :title="`Contrato ${contrato.ctr_nr_contrato}/${contrato.ctr_ano}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Contrato {{ contrato.ctr_nr_contrato }}/{{ contrato.ctr_ano }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ contrato.fornecedor?.frn_razao_social }}</p>
            </div>

            <ContratoForm mode="edit" :initial="contrato" :situacoes="situacoes" :tem-movimento="temMovimento" />
            <ContratoItensTab :contrato="contrato" :itens="itens" :unidades="unidades" :totais="totais" />
            <ContratoAditivosTab :contrato="contrato" :aditivos="aditivos" :itens="itens" :tipos="tiposAditivo" />
            <AnexosPanel
                :base-url="`/alimentacao/contratos/${contrato.ctr_id}/anexos`"
                :anexos="anexos"
                :readonly="isReadOnly"
                descricao="Documentos do contrato (contrato assinado, aditivos, planilhas) em PDF ou imagem, até 10 MB cada."
            />
        </div>
    </AppLayout>
</template>
