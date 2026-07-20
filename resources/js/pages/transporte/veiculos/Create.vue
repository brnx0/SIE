<script setup lang="ts">
import VeiculoForm from '@/components/transporte/VeiculoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    tipos: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Veículos', href: '/transporte/veiculos' },
    { title: 'Novo veículo', href: '/transporte/veiculos/create' },
];
</script>

<template>
    <Head title="Novo veículo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo veículo</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Cadastre um veículo da frota do transporte escolar.</p>
            </div>
            <VeiculoForm mode="create" :tipos="tipos" />
        </div>
    </AppLayout>
</template>
