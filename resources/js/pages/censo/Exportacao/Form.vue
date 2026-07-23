<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { FileDown, Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anoExercicio: AnoLetivo | null;
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Exportação do Censo', href: '/relatorios/censo-exportacao' },
];

const escId = ref<number | null>(props.userEscola?.esc_id ?? null);
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));

function baixar() {
    if (!escId.value) return;
    // Download direto (arquivo), no gesto do clique — abre em nova aba do navegador.
    window.open(`/relatorios/censo-exportacao/download?esc_id=${Number(escId.value)}`, '_blank');
}
</script>

<template>
    <Head title="Exportação do Censo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <FileDown class="size-5 text-indigo-600" /> Exportação do Censo Escolar
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Gera o arquivo de Matrícula Inicial (Educacenso 2026) da escola selecionada, no formato
                oficial (registros separados por “|”, codificação ISO-8859-1). Ano letivo em exercício.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel>Ano Letivo (em exercício)</FormLabel>
                    <div class="rounded-md border bg-muted/40 px-3 py-2 text-sm">
                        {{ anoExercicio?.anl_ano ?? 'Nenhum ano em exercício definido' }}
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="!isAdmin && !!userEscola" placeholder="Selecione a escola..." />
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!escId || !anoExercicio" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="baixar">
                        <Download class="mr-1 size-4" />
                        Gerar Arquivo
                    </Button>
                </div>
            </div>

            <p class="mt-4 text-xs text-muted-foreground">
                Em construção: por enquanto o arquivo traz o registro <b>00 (identificação da escola)</b> e o
                terminador <b>99</b>. Os demais registros (infraestrutura, turmas, profissionais, alunos e
                matrículas) serão adicionados na sequência.
            </p>
        </div>
    </AppLayout>
</template>
