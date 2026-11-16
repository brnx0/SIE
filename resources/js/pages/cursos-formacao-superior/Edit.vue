<script setup lang="ts">
import CursoFormacaoSuperiorForm from '@/components/formacao/CursoFormacaoSuperiorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AreaInep, CursoFormacaoSuperiorCadastro, NivelGraduacao } from '@/types/formacao';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    curso: CursoFormacaoSuperiorCadastro;
    niveis: Pick<NivelGraduacao, 'ngr_id' | 'ngr_nome' | 'ngr_fl_ativo'>[];
    areas: AreaInep[];
    totalVinculos: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Curso de Formação Superior', href: '/cursos-formacao-superior' },
    { title: props.curso.cfs_nome, href: `/cursos-formacao-superior/${props.curso.cfs_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${curso.cfs_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar curso de formação superior</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-mono">{{ curso.cfs_codigo }}</span> · {{ curso.cfs_nome }}
                    <template v-if="totalVinculos > 0">
                        · utilizado em {{ totalVinculos }} formaç{{ totalVinculos === 1 ? 'ão' : 'ões' }} de profissionais (vínculos preservados ao salvar)
                    </template>
                </p>
            </div>
            <CursoFormacaoSuperiorForm :key="curso.cfs_id" mode="edit" :initial="curso" :niveis="niveis" :areas="areas" />
        </div>
    </AppLayout>
</template>
