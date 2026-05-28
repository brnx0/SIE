<script setup lang="ts">
import TurmaForm from '@/components/turma/TurmaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { EscolaResumo, Turma } from '@/types/turma';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    turma: Turma;
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Turmas', href: '/turmas' },
    { title: `Turma ${props.turma.tur_nome}`, href: `/turmas/${props.turma.tur_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar Turma — ${turma.tur_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Editar Turma — {{ turma.serie?.ser_nome }} / {{ turma.tur_nome }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ turma.escola?.esc_nome }}</p>
            </div>
            <TurmaForm
                mode="edit"
                :initial="turma"
                :anos-letivos="anosLetivos"
                :escolas="escolas"
                :is-admin="isAdmin"
                :user-escola="userEscola"
            />
        </div>
    </AppLayout>
</template>
