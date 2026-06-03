<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { Loader2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{ turId: number }>();

interface AlunoMatricula {
    tma_id: number;
    tma_dt_matricula: string | null;
    tma_fl_renovado: boolean;
    tma_situacao: string;
    tas_descricao_entrada: string | null;
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: number | null;
    idade: number | null;
}

const { push } = useToast();
const alunos   = ref<AlunoMatricula[]>([]);
const loading  = ref(true);
const saving   = ref<number | null>(null);

const load = async () => {
    loading.value = true;
    try {
        const r = await fetch(`/api/turmas/${props.turId}/alunos`);
        alunos.value = await r.json();
    } finally {
        loading.value = false;
    }
};

const toggleRenovado = async (aluno: AlunoMatricula) => {
    saving.value = aluno.tma_id;
    const novoValor = !aluno.tma_fl_renovado;

    const xsrf = decodeURIComponent(
        document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
    );

    try {
        const r = await fetch(`/api/turmas/${props.turId}/alunos/${aluno.tma_id}/renovado`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ tma_fl_renovado: novoValor }),
        });

        if (!r.ok) {
            push('error', 'Erro ao atualizar renovado.');
            return;
        }

        aluno.tma_fl_renovado = novoValor;
    } finally {
        saving.value = null;
    }
};

const formatarData = (d: string | null) =>
    d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';

onMounted(load);
</script>

<template>
    <div class="rounded-xl border bg-card shadow-sm">
        <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-2.5">
            <span class="text-sm font-medium">Alunos matriculados</span>
            <span v-if="!loading" class="text-xs text-muted-foreground">
                {{ alunos.length }} aluno{{ alunos.length !== 1 ? 's' : '' }}
            </span>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12 text-muted-foreground">
            <Loader2 class="mr-2 size-5 animate-spin" /> Carregando...
        </div>

        <div v-else-if="alunos.length === 0" class="py-12 text-center text-sm text-muted-foreground">
            Nenhum aluno matriculado nesta turma.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Nº Matrícula</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Nome</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Dt. Matrícula</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Idade</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Situação</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Renovado</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="(a, idx) in alunos"
                        :key="a.tma_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="px-4 py-2.5 tabular-nums text-muted-foreground">
                            {{ a.aln_nr_matricula ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 font-medium">{{ a.aln_nome }}</td>
                        <td class="px-4 py-2.5 text-center text-muted-foreground">
                            {{ formatarData(a.tma_dt_matricula) }}
                        </td>
                        <td class="px-4 py-2.5 text-center tabular-nums">
                            {{ a.idade != null ? `${a.idade} ano${a.idade !== 1 ? 's' : ''}` : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="a.tma_situacao === 'ATIVA'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                {{ a.tas_descricao_entrada ?? a.tma_situacao }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <input
                                type="checkbox"
                                :checked="a.tma_fl_renovado"
                                :disabled="saving === a.tma_id"
                                class="size-4 cursor-pointer accent-indigo-600 disabled:opacity-50"
                                @change="toggleRenovado(a)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
