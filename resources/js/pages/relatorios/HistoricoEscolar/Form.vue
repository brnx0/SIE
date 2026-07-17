<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import { ref } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Histórico Escolar', href: '/relatorios/historico-escolar' }];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('historico-escolar', {
    aln_id: null as number | null,
});
const salvos = restaurar();

const alnId = ref<number | null>(salvos.aln_id ?? null);

const gerar = () => {
    if (!alnId.value) return;
    salvar({ aln_id: alnId.value });
    window.open(`/alunos/${alnId.value}/historico`, '_blank');
};
</script>

<template>
    <Head title="Histórico Escolar" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ScrollText class="size-5 text-indigo-600" /> Histórico Escolar / Certificado
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Busque o aluno e gere o PDF. O histórico inclui os anos do sistema e os lançados manualmente, e é emitido pela escola do último ano escolar do aluno.
            </p>

            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                    <FormLabel :required="true">Aluno</FormLabel>
                    <RemoteCombobox
                        v-model="alnId"
                        fetch-url="/api/alunos/search"
                        id-key="aln_id" label-key="aln_nome"
                        :min-chars="2"
                        placeholder="Digite o nome, matrícula ou CPF..."
                    />
                </div>
                <div class="flex items-end justify-end">
                    <Button :disabled="!alnId" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <ScrollText class="mr-1 size-4" /> Gerar PDF
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
