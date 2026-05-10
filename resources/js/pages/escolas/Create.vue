<script setup lang="ts">
import EscolaForm from '@/components/escola/EscolaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Escolas', href: '/escolas' },
    { title: 'Nova', href: '/escolas/create' },
];
</script>

<template>
    <Head title="Nova escola" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cadastrar escola</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Preencha os dados da escola.
                    <br>Campos marcados com <span class="text-rose-600">*</span> são obrigatórios.
                </p>
            </div>
            <EscolaForm mode="create" />
        </div>
    </AppLayout>
</template>
