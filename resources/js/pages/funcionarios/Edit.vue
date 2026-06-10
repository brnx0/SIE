<script setup lang="ts">
import FuncionarioForm from '@/components/funcionario/FuncionarioForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Funcionario } from '@/types/funcionario';
import { Head } from '@inertiajs/vue3';

interface HorarioItem {
    trh_id: number;
    matricula: string | null;
    turma: string | null;
    escola: string | null;
    dia: string;
    dia_cod: string;
    horario: string | null;
    disciplina: string | null;
    tempo: number;
    turno: string | null;
}

const props = defineProps<{ funcionario: Funcionario; horarios?: HorarioItem[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Funcionários', href: '/funcionarios' },
    { title: props.funcionario.fun_nome, href: `/funcionarios/${props.funcionario.fun_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${funcionario.fun_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Editar funcionário
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ funcionario.fun_nome }}</p>
            </div>
            <FuncionarioForm mode="edit" :initial="funcionario" :horarios="horarios ?? []" />
        </div>
    </AppLayout>
</template>
