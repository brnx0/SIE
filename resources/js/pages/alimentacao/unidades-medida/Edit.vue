<script setup lang="ts">
import ConversoesTab from '@/components/alimentacao/ConversoesTab.vue';
import UnidadeMedidaForm from '@/components/alimentacao/UnidadeMedidaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { UnidadeMedida } from '@/types/alimentacao';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ConversaoLinha {
    cvu_id: number;
    cvu_umd_destino_id: number;
    cvu_fator: number;
    cvu_fl_ativo: boolean;
    destino_sigla: string | null;
    destino_nome: string | null;
}

const props = defineProps<{
    unidade: UnidadeMedida;
    conversoes: ConversaoLinha[];
    unidadesDestino: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Unidades de Medida', href: '/alimentacao/unidades-medida' },
    { title: props.unidade.umd_sigla, href: `/alimentacao/unidades-medida/${props.unidade.umd_id}/edit` },
]);
</script>

<template>
    <Head :title="`${unidade.umd_sigla} — ${unidade.umd_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ unidade.umd_sigla }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ unidade.umd_nome }}</p>
            </div>
            <UnidadeMedidaForm mode="edit" :initial="unidade" />
            <ConversoesTab :unidade="unidade" :conversoes="conversoes" :unidades-destino="unidadesDestino" />
        </div>
    </AppLayout>
</template>
