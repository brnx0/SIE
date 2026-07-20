<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import HistoricoManualPanel from '@/components/aluno/HistoricoManualPanel.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Histórico Escolar', href: '/secretaria/historico-escolar' }];

const aluno = ref<{ aln_id: number; aln_nome: string } | null>(null);

const onAluno = (item: { id: number; label: string } | null) => {
    aluno.value = item ? { aln_id: item.id, aln_nome: item.label } : null;
};
</script>

<template>
    <Head title="Histórico Escolar" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <GraduationCap class="size-5 text-indigo-600" /> Histórico Escolar
            </h1>
            <p class="mb-4 text-sm text-muted-foreground">
                Busque um aluno para ver e lançar anos do histórico escolar (escolaridade anterior / fora da rede).
                Anos calculados pelo sistema aparecem em somente leitura.
            </p>

            <!-- Filtro de aluno -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="grid gap-1.5 sm:max-w-md">
                    <FormLabel :required="true">Aluno</FormLabel>
                    <RemoteCombobox
                        :model-value="aluno?.aln_id ?? null"
                        fetch-url="/api/alunos/search"
                        id-key="aln_id" label-key="aln_nome"
                        :selected-label="aluno?.aln_nome ?? null"
                        :min-chars="2"
                        placeholder="Buscar por nome, matrícula ou CPF..."
                        @selected="onAluno"
                    />
                </div>
            </div>

            <!-- Lançamentos do aluno selecionado -->
            <div v-if="aluno" class="mt-4 overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="border-b bg-muted/40 px-4 py-2 text-sm font-semibold">{{ aluno.aln_nome }}</div>
                <HistoricoManualPanel :aluno="aluno" />
            </div>

            <div v-else class="mt-4 rounded-xl border bg-card px-6 py-10 text-center text-sm text-muted-foreground shadow-sm">
                Selecione um aluno para começar.
            </div>
        </div>
    </AppLayout>
</template>
