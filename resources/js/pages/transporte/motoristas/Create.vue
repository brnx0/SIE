<script setup lang="ts">
import MotoristaForm from '@/components/transporte/MotoristaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Motoristas', href: '/transporte/motoristas' },
    { title: 'Novo motorista', href: '/transporte/motoristas/create' },
];
</script>

<template>
    <Head title="Novo motorista" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo motorista</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Cadastre um condutor do transporte escolar.</p>
            </div>
            <MotoristaForm mode="create" />
        </div>
    </AppLayout>
</template>
