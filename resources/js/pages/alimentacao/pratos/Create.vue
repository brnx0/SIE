<script setup lang="ts">
import PratoForm from '@/components/alimentacao/PratoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { UnidadeMedida } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';

defineProps<{
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pratos', href: '/alimentacao/pratos' },
    { title: 'Novo', href: '/alimentacao/pratos/create' },
];
</script>

<template>
    <Head title="Novo prato" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo prato</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    A ficha técnica (itens e quantidade por aluno) é montada depois de gravar.
                </p>
            </div>
            <PratoForm mode="create" :unidades="unidades" />
        </div>
    </AppLayout>
</template>
