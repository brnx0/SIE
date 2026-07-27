<script setup lang="ts">
import CatalogoForm from '@/components/alimentacao/CatalogoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CatalogoConfig } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ config: CatalogoConfig; proximaOrdem?: number | null }>();

const base = computed(() => '/' + props.config.rota.replace(/\./g, '/'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.config.plural, href: base.value },
    { title: 'Novo', href: `${base.value}/create` },
]);
</script>

<template>
    <Head :title="`Nov${config.artigo} ${config.singular.toLowerCase()}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Nov{{ config.artigo }} {{ config.singular.toLowerCase() }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ config.descricao }}</p>
            </div>
            <CatalogoForm mode="create" :config="config" :proxima-ordem="proximaOrdem" />
        </div>
    </AppLayout>
</template>
