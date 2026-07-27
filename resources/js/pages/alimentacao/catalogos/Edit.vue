<script setup lang="ts">
import CatalogoForm from '@/components/alimentacao/CatalogoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CatalogoConfig, CatalogoRegistro } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ config: CatalogoConfig; registro: CatalogoRegistro }>();

const base = computed(() => '/' + props.config.rota.replace(/\./g, '/'));
const nome = computed(() => String(props.registro[props.config.col_nome] ?? ''));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.config.plural, href: base.value },
    { title: nome.value, href: `${base.value}/${props.registro[props.config.col_id]}/edit` },
]);
</script>

<template>
    <Head :title="nome" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ nome }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ config.singular }}</p>
            </div>
            <CatalogoForm mode="edit" :config="config" :initial="registro" />
        </div>
    </AppLayout>
</template>
