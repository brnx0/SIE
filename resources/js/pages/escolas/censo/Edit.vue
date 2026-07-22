<script setup lang="ts">
import CensoEscolarForm from '@/components/escola/CensoEscolarForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem } from '@/types';
import type { CensoEscolar } from '@/types/censo';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    escola: { esc_id: number; esc_nome: string; esc_apelido: string };
    censo: CensoEscolar;
}>();

// Nível 2 (somente leitura) na tela /escolas → formulário desabilitado, sem salvar.
const { isReadOnly } = usePermissao();

const escolaNome = computed(() => props.escola.esc_apelido || props.escola.esc_nome);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Escolas', href: '/escolas' },
    { title: escolaNome.value, href: `/escolas/${props.escola.esc_id}/edit` },
    { title: 'Censo Escolar', href: '' },
];
</script>

<template>
    <Head :title="`Censo Escolar — ${escolaNome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Censo Escolar
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ escolaNome }}</p>
            </div>
            <CensoEscolarForm :escola="escola" :censo="censo" :readonly="isReadOnly" />
        </div>
    </AppLayout>
</template>
