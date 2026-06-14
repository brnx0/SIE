<script setup lang="ts">
import InstrumentoAvaliativoForm from '@/components/diario/InstrumentoAvaliativoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { InstrumentoAvaliativo } from '@/types/instrumento-avaliativo';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    instrumento: InstrumentoAvaliativo;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Instrumentos Avaliativos', href: '/diario/instrumentos-avaliativos' },
    { title: props.instrumento.iav_nome, href: `/diario/instrumentos-avaliativos/${props.instrumento.iav_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${instrumento.iav_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar instrumento avaliativo</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Atualize os dados do instrumento.
                    <br />Campos marcados com <span class="text-rose-600">*</span> são obrigatórios.
                </p>
            </div>
            <InstrumentoAvaliativoForm mode="edit" :initial="instrumento" />
        </div>
    </AppLayout>
</template>
