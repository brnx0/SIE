<script setup lang="ts">
import MotoristaForm from '@/components/transporte/MotoristaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Motorista } from '@/types/transporte';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    motorista: Motorista;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Motoristas', href: '/transporte/motoristas' },
    { title: props.motorista.mot_nome, href: `/transporte/motoristas/${props.motorista.mot_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${motorista.mot_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar motorista</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ motorista.mot_nome }}</p>
            </div>
            <MotoristaForm mode="edit" :initial="motorista" />
        </div>
    </AppLayout>
</template>
