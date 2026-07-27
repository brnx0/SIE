<script setup lang="ts">
import VeiculoForm from '@/components/transporte/VeiculoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Veiculo } from '@/types/transporte';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    veiculo: Veiculo;
    tipos: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Veículos', href: '/transporte/veiculos' },
    { title: props.veiculo.vei_placa, href: `/transporte/veiculos/${props.veiculo.vei_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${veiculo.vei_placa}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar veículo</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ veiculo.vei_placa }}</p>
            </div>
            <VeiculoForm mode="edit" :initial="veiculo" :tipos="tipos" />
        </div>
    </AppLayout>
</template>
