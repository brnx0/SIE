<script setup lang="ts">
import MonitorForm from '@/components/transporte/MonitorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { MonitorTransporte } from '@/types/transporte';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    monitor: MonitorTransporte;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monitores', href: '/transporte/monitores' },
    { title: props.monitor.tmo_nome, href: `/transporte/monitores/${props.monitor.tmo_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${monitor.tmo_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar monitor</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ monitor.tmo_nome }}</p>
            </div>
            <MonitorForm mode="edit" :initial="monitor" />
        </div>
    </AppLayout>
</template>
