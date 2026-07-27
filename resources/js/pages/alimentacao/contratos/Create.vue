<script setup lang="ts">
import ContratoForm from '@/components/alimentacao/ContratoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ situacoes: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contratos', href: '/alimentacao/contratos' },
    { title: 'Novo', href: '/alimentacao/contratos/create' },
];
</script>

<template>
    <Head title="Novo contrato" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo contrato</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Os itens contratados e os aditivos são informados depois de gravar.
                </p>
            </div>
            <ContratoForm mode="create" :situacoes="situacoes" />
        </div>
    </AppLayout>
</template>
