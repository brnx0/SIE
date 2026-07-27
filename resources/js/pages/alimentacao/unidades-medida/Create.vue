<script setup lang="ts">
import UnidadeMedidaForm from '@/components/alimentacao/UnidadeMedidaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Unidades de Medida', href: '/alimentacao/unidades-medida' },
    { title: 'Nova', href: '/alimentacao/unidades-medida/create' },
];
</script>

<template>
    <Head title="Nova unidade de medida" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova unidade de medida</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    As conversões desta unidade podem ser cadastradas depois de gravar.
                </p>
            </div>
            <UnidadeMedidaForm mode="create" />
        </div>
    </AppLayout>
</template>
