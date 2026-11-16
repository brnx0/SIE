<script setup lang="ts">
import NivelGraduacaoForm from '@/components/formacao/NivelGraduacaoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { NivelGraduacao } from '@/types/formacao';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ nivel: NivelGraduacao }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Nível de Graduação Acadêmica', href: '/niveis-graduacao' },
    { title: props.nivel.ngr_nome, href: `/niveis-graduacao/${props.nivel.ngr_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${nivel.ngr_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar nível de graduação acadêmica</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ nivel.ngr_nome }}</p>
            </div>
            <NivelGraduacaoForm :key="nivel.ngr_id" mode="edit" :initial="nivel" />
        </div>
    </AppLayout>
</template>
