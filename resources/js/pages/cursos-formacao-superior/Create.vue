<script setup lang="ts">
import CursoFormacaoSuperiorForm from '@/components/formacao/CursoFormacaoSuperiorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AreaInep, NivelGraduacao } from '@/types/formacao';
import { Head } from '@inertiajs/vue3';

defineProps<{
    niveis: Pick<NivelGraduacao, 'ngr_id' | 'ngr_nome' | 'ngr_fl_ativo'>[];
    areas: AreaInep[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Curso de Formação Superior', href: '/cursos-formacao-superior' },
    { title: 'Novo', href: '/cursos-formacao-superior/create' },
];
</script>

<template>
    <Head title="Novo curso de formação superior" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cadastrar curso de formação superior</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Informe os códigos conforme a tabela oficial do INEP.
                    <br />Campos marcados com <span class="text-rose-600">*</span> são obrigatórios.
                </p>
            </div>
            <CursoFormacaoSuperiorForm mode="create" :niveis="niveis" :areas="areas" />
        </div>
    </AppLayout>
</template>
