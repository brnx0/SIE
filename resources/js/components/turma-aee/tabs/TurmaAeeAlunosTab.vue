<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { Loader2, Plus, RefreshCw, Search, Trash2, X } from 'lucide-vue-next';
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

interface AlunoElegivel {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: number | null;
}

const { push } = useToast();
const alunos  = ref<AlunoAlocado[]>([]);
const loading = ref(true);

const showForm   = ref(false);
const search     = ref('');
const elegiveis  = ref<AlunoElegivel[]>([]);
const buscando   = ref(false);
const alocando   = ref<number | null>(null);
const removendo  = ref<number | null>(null);

const xsrf = () =>
    decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const load = async () => {
    loading.value = true;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos`);
        alunos.value = await r.json();
    } finally {
        loading.value = false;
    }
};

let timer: ReturnType<typeof setTimeout> | null = null;
const buscarElegiveis = () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(async () => {
        buscando.value = true;
        try {
            const q = encodeURIComponent(search.value);
            const r = await fetch(`/api/turmas-aee/${props.turId}/alunos/elegiveis?search=${q}`);
            elegiveis.value = await r.json();
        } finally {
            buscando.value = false;
        }
    }, 250);
};

const openForm = () => {
    showForm.value = true;
    search.value = '';
    elegiveis.value = [];
    buscarElegiveis();
};

const closeForm = () => {
    showForm.value = false;
    elegiveis.value = [];
};

const alocar = async (aluno: AlunoElegivel) => {
    alocando.value = aluno.aln_id;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ aln_id: aluno.aln_id }),
        });

        if (!r.ok) {
            const json = await r.json().catch(() => ({}));
            const msg = json?.errors?.aln_id?.[0] ?? json?.message ?? 'Erro ao alocar aluno.';
            push('error', msg);
            return;
        }

        push('success', 'Aluno alocado com sucesso.');
        elegiveis.value = elegiveis.value.filter((e) => e.aln_id !== aluno.aln_id);
        await load();
    } finally {
        alocando.value = null;
    }
};

const remover = async (aluno: AlunoAlocado) => {
    if (!confirm(`Remover "${aluno.aln_nome}" desta turma AEE?`)) return;
    removendo.value = aluno.tma_id;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos/${aluno.tma_id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': xsrf(), 'Accept': 'application/json' },
        });

        if (!r.ok) {
            push('error', 'Erro ao remover aluno.');
            return;
        }

        push('success', 'Aluno removido.');
        alunos.value = alunos.value.filter((a) => a.tma_id !== aluno.tma_id);
    } finally {
        removendo.value = null;
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
                <Button v-if="!showForm" type="button" size="sm" variant="outline" @click="openForm">
                    <Plus class="mr-1 size-4" /> Alocar Aluno
                </Button>
            </div>
        </div>

        <!-- Busca de elegíveis -->
        <div v-if="showForm" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-medium">Alocar aluno elegível</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="closeForm">
                    <X class="size-4" />
                </button>
            </div>
            <p class="mb-3 text-xs text-muted-foreground">
                Apenas alunos com matrícula regular ativa neste ano e público-alvo do AEE (deficiência, TGD ou altas habilidades).
            </p>

            <div class="relative">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Buscar por nome..." class="pl-9" @update:model-value="buscarElegiveis" />
            </div>

            <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border bg-card">
                <div v-if="buscando" class="flex items-center justify-center py-8 text-muted-foreground">
                    <Loader2 class="mr-2 size-4 animate-spin" /> Buscando...
                </div>
                <div v-else-if="elegiveis.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    Nenhum aluno elegível encontrado.
                </div>
                <table v-else class="w-full text-sm">
                    <tbody class="divide-y">
                        <tr v-for="e in elegiveis" :key="e.aln_id" class="hover:bg-muted/20">
                            <td class="px-4 py-2.5 tabular-nums text-muted-foreground">{{ e.aln_nr_matricula ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ e.aln_nome }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <Button type="button" size="sm" variant="outline" :disabled="alocando === e.aln_id" @click="alocar(e)">
                                    <Loader2 v-if="alocando === e.aln_id" class="mr-1 size-4 animate-spin" />
                                    <Plus v-else class="mr-1 size-4" />
                                    Alocar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

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
                        <th class="w-12 px-4 py-2.5"></th>
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
                        <td class="px-4 py-2.5 text-center">
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover" :disabled="removendo === a.tma_id" @click="remover(a)">
                                <Loader2 v-if="removendo === a.tma_id" class="size-4 animate-spin text-rose-500" />
                                <Trash2 v-else class="size-4 text-rose-500" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
