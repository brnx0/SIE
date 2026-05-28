<script setup lang="ts">
import DisciplinaForm from '@/components/disciplina/DisciplinaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AreaConhecimento } from '@/types/disciplina';
import { Head } from '@inertiajs/vue3';

defineProps<{
    areas: Pick<AreaConhecimento, 'arc_id' | 'arc_nome'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disciplinas', href: '/disciplinas' },
    { title: 'Nova', href: '/disciplinas/create' },
];
</script>

<template>
    <Head title="Nova disciplina" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cadastrar disciplina</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Preencha os dados da disciplina.
                    <br />Campos marcados com <span class="text-rose-600">*</span> são obrigatórios.
                </p>
            </div>
            <DisciplinaForm mode="create" :areas="areas" />
        </div>
    </AppLayout>
</template>
