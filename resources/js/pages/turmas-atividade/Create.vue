<script setup lang="ts">
import TurmaAtividadeForm from '@/components/turma-atividade/TurmaAtividadeForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { EscolaResumo } from '@/types/turma';
import { Head } from '@inertiajs/vue3';

defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Turmas Atividades', href: '/turmas-atividade' },
    { title: 'Nova Turma Atividade', href: '/turmas-atividade/create' },
];
</script>

<template>
    <Head title="Nova Turma Atividade" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova Turma Atividade</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Atividade Complementar.</p>
            </div>
            <TurmaAtividadeForm
                mode="create"
                :anos-letivos="anosLetivos"
                :escolas="escolas"
                :is-admin="isAdmin"
                :user-escola="userEscola"
            />
        </div>
    </AppLayout>
</template>
