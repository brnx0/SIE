<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Loader2, RefreshCw } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{ turId: number }>();

interface AlunoAlocado {
    tma_id: number;
    tma_dt_matricula: string | null;
    tma_situacao: string;
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: number | null;
}

const alunos  = ref<AlunoAlocado[]>([]);
const loading = ref(true);

const load = async () => {
    loading.value = true;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos`);
        alunos.value = await r.json();
    } finally {
        loading.value = false;
    }
};

const formatarData = (d: string | null) =>
    d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';

onMounted(load);
</script>

<template>
    <div class="grid gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Alunos Alocados</h3>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="loading" title="Atualizar" @click="load">
                    <RefreshCw :class="['mr-1 size-4', loading && 'animate-spin']" /> Atualizar
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            A alocação de alunos em turmas AEE é feita por funcionalidade externa. Esta listagem é apenas consulta.
        </p>

        <!-- Lista alocados -->
        <div v-if="loading" class="flex items-center justify-center py-12 text-muted-foreground">
            <Loader2 class="mr-2 size-5 animate-spin" /> Carregando...
        </div>
        <div v-else-if="alunos.length === 0" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum aluno alocado nesta turma AEE.
        </div>
        <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Nº Matrícula</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Nome</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Dt. Alocação</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(a, idx) in alunos" :key="a.tma_id" :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                        <td class="px-4 py-2.5 tabular-nums text-muted-foreground">{{ a.aln_nr_matricula ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-medium">{{ a.aln_nome }}</td>
                        <td class="px-4 py-2.5 text-center text-muted-foreground">{{ formatarData(a.tma_dt_matricula) }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                {{ a.tma_situacao }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
