<script setup lang="ts">
import SegmentoForm from '@/components/segmento/SegmentoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Segmento } from '@/types/segmento';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ segmento: Segmento }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Segmentos', href: '/segmentos' },
    { title: props.segmento.seg_nome_reduzido, href: `/segmentos/${props.segmento.seg_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${segmento.seg_nome_reduzido}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar segmento</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ segmento.seg_nome_completo }}</p>
            </div>
            <SegmentoForm mode="edit" :initial="segmento" />
        </div>
    </AppLayout>
</template>
