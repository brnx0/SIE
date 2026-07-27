<script setup lang="ts">
import CardapioForm from '@/components/alimentacao/CardapioForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    anosLetivos: { anl_id: number; anl_ano: number }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cardápio Semanal', href: '/alimentacao/cardapios' },
    { title: 'Novo', href: '/alimentacao/cardapios/create' },
];
</script>

<template>
    <Head title="Novo cardápio" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo cardápio</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    A grade de pratos por dia é montada depois de gravar.
                </p>
            </div>
            <CardapioForm mode="create" :anos-letivos="anosLetivos" />
        </div>
    </AppLayout>
</template>
