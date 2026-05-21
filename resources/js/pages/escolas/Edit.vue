<script setup lang="ts">
import EscolaForm from '@/components/escola/EscolaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Escola } from '@/types/escola';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ escola: Escola }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Escolas', href: '/escolas' },
    { title: props.escola.esc_apelido || props.escola.esc_nome, href: `/escolas/${props.escola.esc_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${escola.esc_apelido || escola.esc_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar escola</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ escola.esc_nome }}</p>
            </div>
            <EscolaForm mode="edit" :initial="escola" />
        </div>
    </AppLayout>
</template>
