<script setup lang="ts">
import SerieForm from '@/components/serie/SerieForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Segmento } from '@/types/segmento';
import { Head } from '@inertiajs/vue3';

defineProps<{
    segmentos: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Séries', href: '/series' },
    { title: 'Nova', href: '/series/create' },
];
</script>

<template>
    <Head title="Nova série" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cadastrar série</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Preencha os dados da série.
                    <br />Campos marcados com <span class="text-rose-600">*</span> são obrigatórios.
                </p>
            </div>
            <SerieForm mode="create" :segmentos="segmentos" />
        </div>
    </AppLayout>
</template>
