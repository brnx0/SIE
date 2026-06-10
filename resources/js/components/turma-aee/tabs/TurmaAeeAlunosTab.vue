<script setup lang="ts">
import MatricularAlunoAeeModal from '@/components/turma-aee/MatricularAlunoAeeModal.vue';
import { Button } from '@/components/ui/button';
import { Loader2, RefreshCw, School, Trash2, UserPlus } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    turId: number;
    turEscId: number;
    turEscNome: string;
    anlAno: number;
}>();

interface AlunoAlocado {
    tma_id: number;
    tma_dt_matricula: string | null;
    tma_situacao: string;
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: number | null;
    esc_regular_nome: string | null;
    cross_escola: boolean;
}

const alunos  = ref<AlunoAlocado[]>([]);
const loading = ref(true);
const removing = ref<number | null>(null);
const modalOpen = ref(false);

const load = async () => {
    loading.value = true;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos`);
        alunos.value = await r.json();
    } finally {
        loading.value = false;
    }
};

const desmatricular = async (a: AlunoAlocado) => {
    if (!confirm(`Remover ${a.aln_nome} desta turma AEE?`)) return;
    removing.value = a.tma_id;
    try {
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        await fetch(`/api/turmas-aee/${props.turId}/alunos/${a.tma_id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        await load();
    } finally {
        removing.value = null;
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
            <h3 class="text-sm font-semibold">Alunos Matriculados no AEE</h3>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="loading" title="Atualizar" @click="load">
                    <RefreshCw :class="['mr-1 size-4', loading && 'animate-spin']" /> Atualizar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="modalOpen = true"
                >
                    <UserPlus class="mr-1 size-4" /> Matricular Aluno
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Lista alunos com PCD/TGD/AH marcado no quadro de saúde e matriculados no ano vigente.
            Atendimento cross-escola é permitido (regular em uma escola, AEE em outra).
        </p>

        <!-- Lista alocados -->
        <div v-if="loading" class="flex items-center justify-center py-12 text-muted-foreground">
            <Loader2 class="mr-2 size-5 animate-spin" /> Carregando...
        </div>
        <div v-else-if="alunos.length === 0" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum aluno matriculado nesta turma AEE.
        </div>
        <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Nº Matrícula</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Nome</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Escola Regular</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Dt. Matrícula</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Situação</th>
                        <th class="w-12 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(a, idx) in alunos" :key="a.tma_id" :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                        <td class="px-4 py-2.5 tabular-nums text-muted-foreground">{{ a.aln_nr_matricula ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-medium">{{ a.aln_nome }}</td>
                        <td class="px-4 py-2.5 text-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate">{{ a.esc_regular_nome ?? '—' }}</span>
                                <span
                                    v-if="a.cross_escola"
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                                    title="Aluno cursa regular em outra escola"
                                >
                                    <School class="size-3" /> outra escola
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-center text-muted-foreground">{{ formatarData(a.tma_dt_matricula) }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                {{ a.tma_situacao }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <button
                                type="button"
                                class="rounded p-1.5 text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:hover:bg-rose-900/30"
                                :disabled="removing === a.tma_id"
                                title="Desmatricular"
                                @click="desmatricular(a)"
                            >
                                <Loader2 v-if="removing === a.tma_id" class="size-4 animate-spin" />
                                <Trash2 v-else class="size-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <MatricularAlunoAeeModal
            v-model:open="modalOpen"
            :tur-id="turId"
            :tur-esc-id="turEscId"
            :tur-esc-nome="turEscNome"
            :anl-ano="anlAno"
            @matriculado="load"
        />
    </div>
</template>
