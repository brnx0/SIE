<script setup lang="ts">
import RotaForm from '@/components/transporte/RotaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import { Head } from '@inertiajs/vue3';

defineProps<{
    anosLetivos: AnoLetivo[];
    turnos: Record<string, string>;
    zonas: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rotas', href: '/transporte/rotas' },
    { title: 'Nova rota', href: '/transporte/rotas/create' },
];
</script>

<template>
    <Head title="Nova rota" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova rota</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Defina os dados da rota. Pontos e passageiros são adicionados após a criação.</p>
            </div>
            <RotaForm mode="create" :anos-letivos="anosLetivos" :turnos="turnos" :zonas="zonas" />
        </div>
    </AppLayout>
</template>
