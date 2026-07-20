<script setup lang="ts">
import InstituicaoForm from '@/components/transporte/InstituicaoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    esferas: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Instituições', href: '/transporte/instituicoes' },
    { title: 'Nova instituição', href: '/transporte/instituicoes/create' },
];
</script>

<template>
    <Head title="Nova instituição" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova instituição</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Cadastre uma instituição de ensino atendida pelo transporte.</p>
            </div>
            <InstituicaoForm mode="create" :esferas="esferas" />
        </div>
    </AppLayout>
</template>
