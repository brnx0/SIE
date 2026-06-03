<script setup lang="ts">
import AlunoForm from '@/components/aluno/AlunoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Aluno } from '@/types/aluno';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ aluno: Aluno; matriculas?: any[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos', href: '/alunos' },
    { title: props.aluno.aln_nome, href: `/alunos/${props.aluno.aln_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${aluno.aln_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Editar aluno
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ aluno.aln_nome }}</p>
            </div>
            <AlunoForm mode="edit" :initial="aluno" :matriculas="matriculas" />
        </div>
    </AppLayout>
</template>
