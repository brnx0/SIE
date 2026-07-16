<script setup lang="ts">
import FornecedorForm from '@/components/alimentacao/FornecedorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Fornecedor } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ fornecedor: Fornecedor }>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Fornecedores', href: '/alimentacao/fornecedores' },
    { title: props.fornecedor.frn_razao_social, href: `/alimentacao/fornecedores/${props.fornecedor.frn_id}/edit` },
]);
</script>

<template>
    <Head :title="fornecedor.frn_razao_social" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ fornecedor.frn_razao_social }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <template v-if="fornecedor.frn_nome_fantasia">{{ fornecedor.frn_nome_fantasia }} · </template>Fornecedor
                </p>
            </div>
            <FornecedorForm mode="edit" :initial="fornecedor" />
        </div>
    </AppLayout>
</template>
