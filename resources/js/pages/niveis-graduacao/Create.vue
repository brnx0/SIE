<script setup lang="ts">
import NivelGraduacaoForm from '@/components/formacao/NivelGraduacaoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Nível de Graduação Acadêmica', href: '/niveis-graduacao' },
    { title: 'Novo', href: '/niveis-graduacao/create' },
];
</script>

<template>
    <Head title="Novo nível de graduação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cadastrar nível de graduação acadêmica</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Campos marcados com <span class="text-rose-600">*</span> são obrigatórios.</p>
            </div>
            <NivelGraduacaoForm mode="create" />
        </div>
    </AppLayout>
</template>
