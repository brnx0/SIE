<script setup lang="ts">
import ItemForm from '@/components/alimentacao/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CategoriaAlimento, TipoItem, UnidadeMedida } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';

defineProps<{
    tipos: Pick<TipoItem, 'tpi_id' | 'tpi_nome'>[];
    categorias: Pick<CategoriaAlimento, 'cat_id' | 'cat_nome'>[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Itens', href: '/alimentacao/itens' },
    { title: 'Novo', href: '/alimentacao/itens/create' },
];
</script>

<template>
    <Head title="Novo item" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo item</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    As conversões de embalagem deste item podem ser cadastradas depois de gravar.
                </p>
            </div>
            <ItemForm mode="create" :tipos="tipos" :categorias="categorias" :unidades="unidades" />
        </div>
    </AppLayout>
</template>
