<script setup lang="ts">
import ItemConversoesTab from '@/components/alimentacao/ItemConversoesTab.vue';
import ItemForm from '@/components/alimentacao/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CategoriaAlimento, Item, ItemConversaoLinha, TipoItem, UnidadeMedida } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    item: Item;
    conversoes: ItemConversaoLinha[];
    tipos: Pick<TipoItem, 'tpi_id' | 'tpi_nome'>[];
    categorias: Pick<CategoriaAlimento, 'cat_id' | 'cat_nome'>[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

// Trocar a unidade de controle com conversões cadastradas apontaria os fatores
// para a unidade errada — o backend barra, e o form já desabilita o campo.
const temConversoes = computed(() => (props.conversoes?.length ?? 0) > 0);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Itens', href: '/alimentacao/itens' },
    { title: props.item.itm_nome, href: `/alimentacao/itens/${props.item.itm_id}/edit` },
]);
</script>

<template>
    <Head :title="item.itm_nome" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ item.itm_nome }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <template v-if="item.itm_cd_item">Cód: {{ item.itm_cd_item }} · </template>Item da alimentação escolar
                </p>
            </div>
            <ItemForm
                mode="edit"
                :initial="item"
                :tipos="tipos"
                :categorias="categorias"
                :unidades="unidades"
                :tem-conversoes="temConversoes"
            />
            <ItemConversoesTab :item="item" :conversoes="conversoes" :unidades="unidades" />
        </div>
    </AppLayout>
</template>
