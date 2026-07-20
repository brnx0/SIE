<script setup lang="ts">
import InstituicaoForm from '@/components/transporte/InstituicaoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { InstituicaoTransporte } from '@/types/transporte';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    instituicao: InstituicaoTransporte;
    esferas: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Instituições', href: '/transporte/instituicoes' },
    { title: props.instituicao.tin_nome, href: `/transporte/instituicoes/${props.instituicao.tin_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${instituicao.tin_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar instituição</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ instituicao.tin_nome }}</p>
            </div>
            <InstituicaoForm mode="edit" :initial="instituicao" :esferas="esferas" />
        </div>
    </AppLayout>
</template>
