<script setup lang="ts">
import TurmaAeeForm from '@/components/turma-aee/TurmaAeeForm.vue';
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
    { title: 'Turmas AEE', href: '/turmas-aee' },
    { title: 'Nova Turma AEE', href: '/turmas-aee/create' },
];
</script>

<template>
    <Head title="Nova Turma AEE" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova Turma AEE</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Atendimento Educacional Especializado.</p>
            </div>
            <TurmaAeeForm
                mode="create"
                :anos-letivos="anosLetivos"
                :escolas="escolas"
                :is-admin="isAdmin"
                :user-escola="userEscola"
            />
        </div>
    </AppLayout>
</template>
