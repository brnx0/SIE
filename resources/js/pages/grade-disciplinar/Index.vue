<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import GradeDisciplinarTab from '@/components/parametro/GradeDisciplinarTab.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo, SegmentoResumo } from '@/types/parametro';
import { Head } from '@inertiajs/vue3';

defineProps<{
    anosLetivos: AnoLetivo[];
    segmentos: SegmentoResumo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Grade Disciplinar', href: '/grade-disciplinar' },
];
</script>

<template>
    <Head title="Grade Disciplinar" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Grade Disciplinar</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie as disciplinas por segmento, série e ano letivo.</p>
            </div>
            <GradeDisciplinarTab :anos-letivos="anosLetivos" :segmentos="segmentos" />
        </div>
    </AppLayout>
</template>
