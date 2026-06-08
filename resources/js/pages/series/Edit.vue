<script setup lang="ts">
import SerieForm from '@/components/serie/SerieForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Segmento } from '@/types/segmento';
import type { Serie, SerieIndicador, SerieParaReplicar } from '@/types/serie';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    serie: Serie;
    segmentos: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>[];
    indicadores: SerieIndicador[];
    seriesParaReplicar: SerieParaReplicar[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Séries', href: '/series' },
    { title: props.serie.ser_nome, href: `/series/${props.serie.ser_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${serie.ser_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar série</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ serie.ser_nome }}</p>
            </div>
            <SerieForm
                mode="edit"
                :initial="serie"
                :segmentos="segmentos"
                :indicadores="indicadores"
                :series-para-replicar="seriesParaReplicar"
            />
        </div>
    </AppLayout>
</template>
