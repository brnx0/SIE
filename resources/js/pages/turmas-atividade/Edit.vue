<script setup lang="ts">
import TurmaAtividadeForm from '@/components/turma-atividade/TurmaAtividadeForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { EscolaResumo, ProfessorResumo, Turma, TurmaProfessor } from '@/types/turma';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    turma: Turma;
    total_matriculados: number;
    professores: TurmaProfessor[];
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
    professoresDisponiveis: ProfessorResumo[];
}>();

const turmaComTotal = { ...props.turma, total_matriculados: props.total_matriculados };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Turmas Atividades', href: '/turmas-atividade' },
    { title: `Turma ${props.turma.tur_nome}`, href: `/turmas-atividade/${props.turma.tur_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar Turma Atividade — ${turma.tur_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Editar Turma Atividade — {{ turma.tur_nome }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ turma.escola?.esc_nome }}</p>
            </div>
            <TurmaAtividadeForm
                mode="edit"
                :initial="turmaComTotal"
                :anos-letivos="anosLetivos"
                :escolas="escolas"
                :is-admin="isAdmin"
                :user-escola="userEscola"
                :professores="professores ?? []"
                :professores-disponiveis="professoresDisponiveis"
            />
        </div>
    </AppLayout>
</template>
