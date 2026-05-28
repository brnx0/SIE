<script setup lang="ts">
import DisciplinaForm from '@/components/disciplina/DisciplinaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AreaConhecimento, Disciplina } from '@/types/disciplina';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    disciplina: Disciplina;
    areas: Pick<AreaConhecimento, 'arc_id' | 'arc_nome'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disciplinas', href: '/disciplinas' },
    { title: props.disciplina.dis_nome, href: `/disciplinas/${props.disciplina.dis_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${disciplina.dis_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar disciplina</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ disciplina.dis_nome }}</p>
            </div>
            <DisciplinaForm mode="edit" :initial="disciplina" :areas="areas" />
        </div>
    </AppLayout>
</template>
