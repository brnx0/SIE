<script setup lang="ts">
import AlunoForm from '@/components/aluno/AlunoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos', href: '/alunos' },
    { title: 'Novo', href: '/alunos/create' },
];
</script>

<template>
    <Head title="Novo aluno" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Cadastrar aluno
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Preencha as informações do aluno a seguir. 
                    <br>Campos marcados com 
                    <span class="text-rose-600">*</span> são obrigatórios.
                </p>
            </div>
            <AlunoForm mode="create" />
        </div>
    </AppLayout>
</template>
