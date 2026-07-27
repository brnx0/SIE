<script setup lang="ts">
import FornecedorForm from '@/components/alimentacao/FornecedorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Fornecedores', href: '/alimentacao/fornecedores' },
    { title: 'Novo', href: '/alimentacao/fornecedores/create' },
];
</script>

<template>
    <Head title="Novo fornecedor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo fornecedor</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Dados cadastrais, contato e endereço do fornecedor.</p>
            </div>
            <FornecedorForm mode="create" />
        </div>
    </AppLayout>
</template>
