<script setup lang="ts">
import RichTextEditor from '@/components/common/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, ChevronRight, ClipboardList, LoaderCircle, Pencil, Plus, Save, Search, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    anlId: number;
    escId: number;
    turId: number;
    uniId: number;
}>();

interface AlunoResumo { aln_id: number; aln_nome: string; aln_nr_matricula: string | null; }
interface Avaliacao { dav_id: number; aln_id: number; dt: string; descricao: string; }

const alunos = ref<AlunoResumo[]>([]);
const avaliacoes = ref<Avaliacao[]>([]);
const periodoAberto = ref(false);
const turmaAberta = ref(false);
const carregando = ref(false);

// Navegação mestre-detalhe
const selectedAlnId = ref<number | null>(null);
const busca = ref('');

// Formulário
const showForm = ref(false);
const editingId = ref<number | null>(null);
const formDt = ref('');
const formDescricao = ref('');
const processing = ref(false);
const erro = ref<string | null>(null);

const podeEditar = computed(() => periodoAberto.value && turmaAberta.value);

const alunoSel = computed(() => alunos.value.find((a) => a.aln_id === selectedAlnId.value) ?? null);
const nomeAluno = (id: number) => alunos.value.find((a) => a.aln_id === id)?.aln_nome ?? '—';

const countPorAluno = computed(() => {
    const m = new Map<number, number>();
    for (const a of avaliacoes.value) m.set(a.aln_id, (m.get(a.aln_id) ?? 0) + 1);
    return m;
});

const alunosFiltrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter(
        (a) => a.aln_nome.toLowerCase().includes(q) || (a.aln_nr_matricula ?? '').toLowerCase().includes(q),
    );
});

const avaliacoesDoAluno = computed(() =>
    avaliacoes.value.filter((a) => a.aln_id === selectedAlnId.value),
);

const hojeStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const fmtBr = (d: string) => {
    const [y, m, day] = d.slice(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

const fetchContexto = async () => {
    carregando.value = true;
    try {
        const url = new URL('/api/diario-aee/avaliacoes/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        url.searchParams.set('uni_id', String(props.uniId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (r.ok) {
            const j = await r.json();
            alunos.value = j.alunos ?? [];
            avaliacoes.value = j.avaliacoes ?? [];
            periodoAberto.value = !!j.periodo_aberto;
            turmaAberta.value = !!j.turma_aberta;
        }
    } finally {
        carregando.value = false;
    }
};

const selecionarAluno = (id: number) => {
    selectedAlnId.value = id;
    showForm.value = false;
    editingId.value = null;
    erro.value = null;
};
const voltar = () => {
    selectedAlnId.value = null;
    showForm.value = false;
    editingId.value = null;
};

const openCreate = () => {
    editingId.value = null;
    formDt.value = hojeStr();
    formDescricao.value = '';
    erro.value = null;
    showForm.value = true;
};

const openEdit = (a: Avaliacao) => {
    editingId.value = a.dav_id;
    formDt.value = a.dt;
    formDescricao.value = a.descricao;
    erro.value = null;
    showForm.value = true;
};

const cancel = () => {
    showForm.value = false;
    editingId.value = null;
    erro.value = null;
};

const save = async () => {
    erro.value = null;
    if (!selectedAlnId.value) { erro.value = 'Selecione o aluno.'; return; }
    if (!formDt.value) { erro.value = 'Informe a data.'; return; }

    processing.value = true;
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const r = await fetch('/api/diario-aee/avaliacoes/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({
                dav_id: editingId.value,
                tur_id: props.turId,
                uni_id: props.uniId,
                aln_id: selectedAlnId.value,
                dt: formDt.value,
                descricao: formDescricao.value,
            }),
        });
        if (r.ok) {
            showForm.value = false;
            editingId.value = null;
            await fetchContexto();
        } else {
            const j = await r.json().catch(() => null);
            erro.value = j?.errors?.descricao?.[0] ?? j?.message ?? 'Erro ao salvar.';
        }
    } finally {
        processing.value = false;
    }
};

const remove = async (a: Avaliacao) => {
    if (!confirm(`Remover a avaliação de ${nomeAluno(a.aln_id)} (${fmtBr(a.dt)})?`)) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const r = await fetch(`/api/diario-aee/avaliacoes/${a.dav_id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
    });
    if (r.ok) await fetchContexto();
};

onMounted(fetchContexto);
</script>

<template>
    <div class="rounded-xl border bg-card p-4 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
            <ClipboardList class="size-4 text-indigo-600" />
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Avaliações descritivas</h3>
        </div>

        <div v-if="carregando" class="flex items-center gap-2 text-sm text-muted-foreground">
            <LoaderCircle class="size-4 animate-spin" /> Carregando...
        </div>

        <!-- ===== Lista de alunos ===== -->
        <template v-else-if="!alunoSel">
            <div class="relative mb-3 max-w-md">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="busca" placeholder="Buscar aluno por nome ou matrícula..." class="pl-9" />
            </div>

            <div v-if="alunos.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Nenhum aluno ativo nesta turma.
            </div>
            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="a in alunosFiltrados"
                    :key="a.aln_id"
                    class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2.5 transition hover:bg-muted/50"
                    @click="selecionarAluno(a.aln_id)"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ a.aln_nome }}</p>
                        <p v-if="a.aln_nr_matricula" class="text-xs text-muted-foreground">Matrícula: {{ a.aln_nr_matricula }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="(countPorAluno.get(a.aln_id) ?? 0) > 0
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                                : 'bg-muted text-muted-foreground'"
                        >
                            {{ countPorAluno.get(a.aln_id) ?? 0 }} avaliação(ões)
                        </span>
                        <ChevronRight class="size-4 text-muted-foreground" />
                    </div>
                </li>
            </ul>
        </template>

        <!-- ===== Detalhe do aluno ===== -->
        <template v-else>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Button type="button" variant="ghost" size="sm" @click="voltar">
                        <ArrowLeft class="mr-1 size-4" /> Alunos
                    </Button>
                    <span class="text-sm font-semibold">{{ alunoSel.aln_nome }}</span>
                    <span v-if="alunoSel.aln_nr_matricula" class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                        {{ alunoSel.aln_nr_matricula }}
                    </span>
                </div>
                <Button
                    v-if="!showForm && podeEditar"
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <Plus class="mr-2 size-4" /> Nova Avaliação
                </Button>
            </div>

            <p v-if="!podeEditar" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                Período ou turma fechados — visualização apenas.
            </p>

            <!-- Formulário -->
            <div v-if="showForm" class="mb-4 rounded-lg border bg-background p-4">
                <h4 class="mb-3 text-sm font-semibold">{{ editingId ? 'Editar avaliação' : 'Nova avaliação' }}</h4>
                <div class="grid gap-4 sm:grid-cols-12">
                    <div class="grid gap-1.5 sm:col-span-4">
                        <Label for="dav_dt">Data</Label>
                        <Input id="dav_dt" v-model="formDt" type="date" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-12">
                        <Label>Descrição <span class="text-muted-foreground">(condições realizadas pelo aluno)</span></Label>
                        <RichTextEditor v-model="formDescricao" :limit="2500" :invalid="!!erro" />
                    </div>
                </div>
                <p v-if="erro" class="mt-2 text-sm text-rose-600">{{ erro }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="cancel">
                        <X class="mr-2 size-4" /> Cancelar
                    </Button>
                    <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing" @click="save">
                        <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                        <Save v-else class="mr-2 size-4" />
                        Salvar
                    </Button>
                </div>
            </div>

            <!-- Lista de avaliações do aluno -->
            <div v-if="avaliacoesDoAluno.length === 0 && !showForm" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Nenhuma avaliação registrada para este aluno neste período.
            </div>
            <div v-else class="grid gap-3">
                <div v-for="a in avaliacoesDoAluno" :key="a.dav_id" class="rounded-lg border p-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">{{ fmtBr(a.dt) }}</span>
                        <div v-if="podeEditar" class="flex gap-1">
                            <Button type="button" variant="ghost" size="sm" @click="openEdit(a)" aria-label="Editar">
                                <Pencil class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(a)" aria-label="Remover">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="prose prose-sm max-w-none dark:prose-invert" v-html="a.descricao"></div>
                </div>
            </div>
        </template>
    </div>
</template>
