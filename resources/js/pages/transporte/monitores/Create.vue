<script setup lang="ts">
import MonitorForm from '@/components/transporte/MonitorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monitores', href: '/transporte/monitores' },
    { title: 'Novo monitor', href: '/transporte/monitores/create' },
];
</script>

<template>
    <Head title="Novo monitor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo monitor</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Cadastre um monitor do transporte escolar.</p>
            </div>
            <MonitorForm mode="create" />
        </div>
    </AppLayout>
</template>
