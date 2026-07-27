<script setup lang="ts">
import EmpenhoForm from '@/components/alimentacao/EmpenhoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ situacoes: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Empenhos', href: '/alimentacao/empenhos' },
    { title: 'Novo', href: '/alimentacao/empenhos/create' },
];
</script>

<template>
    <Head title="Novo empenho" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo empenho</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    O saldo é calculado a partir das ordens de fornecimento que o consomem.
                </p>
            </div>
            <EmpenhoForm mode="create" :situacoes="situacoes" />
        </div>
    </AppLayout>
</template>
