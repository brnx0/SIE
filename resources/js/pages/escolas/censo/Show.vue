<script setup lang="ts">
import CensoEscolarForm from '@/components/escola/CensoEscolarForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { CensoEscolar } from '@/types/censo';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    escola: { esc_id: number; esc_nome: string; esc_apelido: string };
    censo: CensoEscolar;
}>();

const escolaNome = computed(() => props.escola.esc_apelido || props.escola.esc_nome);
const ano = computed(() => props.censo.ano_letivo?.anl_ano ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Escolas', href: '/escolas' },
    { title: escolaNome.value, href: `/escolas/${props.escola.esc_id}/edit` },
    { title: `Censo ${ano.value} (visualização)`, href: '' },
];
</script>

<template>
    <Head :title="`Censo ${ano} — ${escolaNome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Censo Escolar {{ ano }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ escolaNome }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    Finalizado
                </span>
            </div>
            <CensoEscolarForm :escola="escola" :censo="censo" :readonly="true" />
        </div>
    </AppLayout>
</template>
