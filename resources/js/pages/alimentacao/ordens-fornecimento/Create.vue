<script setup lang="ts">
import OrdemForm from '@/components/alimentacao/OrdemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Contrato, Empenho } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';

defineProps<{
    contratos: Contrato[];
    empenhos: Pick<Empenho, 'emp_id' | 'emp_ctr_id' | 'emp_nr_empenho' | 'emp_vl_empenhado'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ordens de Fornecimento', href: '/alimentacao/ordens-fornecimento' },
    { title: 'Nova', href: '/alimentacao/ordens-fornecimento/create' },
];
</script>

<template>
    <Head title="Nova ordem de fornecimento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova ordem de fornecimento</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Os itens pedidos são informados depois de gravar.
                </p>
            </div>
            <OrdemForm mode="create" :contratos="contratos" :empenhos="empenhos" />
        </div>
    </AppLayout>
</template>
