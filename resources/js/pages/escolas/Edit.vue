<script setup lang="ts">
import EscolaForm from '@/components/escola/EscolaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivoOption, EscolaSegmento } from '@/types/escola_segmento';
import type { Escola } from '@/types/escola';
import type { Segmento } from '@/types/segmento';
import type { CensoEscolarResumo } from '@/types/censo';
import type { AnoLetivo } from '@/types/parametro';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    escola: Escola;
    escolaSegmentos: EscolaSegmento[];
    segmentos: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>[];
    anosLetivos: AnoLetivoOption[];
    anoLetivoAtual: Pick<AnoLetivo, 'anl_id' | 'anl_ano' | 'anl_dt_censo' | 'anl_fl_em_exercicio'> | null;
    censoHistorico: CensoEscolarResumo[];
    censoAtual: Pick<CensoEscolarResumo, 'cen_id' | 'cen_anl_id'> | null;
    censoPreviousExists: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Escolas', href: '/escolas' },
    { title: props.escola.esc_apelido || props.escola.esc_nome, href: `/escolas/${props.escola.esc_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${escola.esc_apelido || escola.esc_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar escola</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ escola.esc_nome }}</p>
            </div>
            <EscolaForm
                mode="edit"
                :initial="escola"
                :escola-segmentos="escolaSegmentos"
                :segmentos="segmentos"
                :anos-letivos="anosLetivos"
                :ano-letivo-atual="anoLetivoAtual"
                :censo-historico="censoHistorico"
                :censo-atual="censoAtual"
                :censo-previous-exists="censoPreviousExists"
            />
        </div>
    </AppLayout>
</template>
