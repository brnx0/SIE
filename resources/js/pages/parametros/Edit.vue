<script setup lang="ts">
import ParametroForm from '@/components/parametro/ParametroForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo, DiaNaoLetivo, GradeHorario, MediaEscola, ParametroEntidade, SegmentoResumo, Unidade } from '@/types/parametro';
import { Head } from '@inertiajs/vue3';

defineProps<{
    parametro: ParametroEntidade;
    anosLetivos: AnoLetivo[];
    unidades: Unidade[];
    diasNaoLetivos: DiaNaoLetivo[];
    mediasEscola: MediaEscola[];
    segmentos: SegmentoResumo[];
    gradeHorarios: GradeHorario[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Parâmetros do Sistema', href: '/parametros' },
];
</script>

<template>
    <Head title="Parâmetros do Sistema" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Parâmetros do Sistema</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Configurações gerais da entidade.</p>
            </div>
            <ParametroForm
                :initial="parametro"
                :anos-letivos="anosLetivos"
                :unidades="unidades"
                :dias-nao-letivos="diasNaoLetivos"
                :medias-escola="mediasEscola"
                :segmentos="segmentos"
                :grade-horarios="gradeHorarios"
            />
        </div>
    </AppLayout>
</template>
