<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import Switch from '@/components/common/Switch.vue';
import LancamentoNotasModal from '@/components/encerramento/LancamentoNotasModal.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Archive, ArrowLeft, CheckCircle2, Loader2, Lock, PencilLine, ScrollText, Search, Users } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Pendencia { disciplina: string; unidade: string }
interface AlunoLinha {
    aln_id: number;
    nome: string;
    matricula: string | null;
    situacao: string;
    status: 'completo' | 'pendente' | 'nao_avaliativa' | 'sem_grade';
    total: number;
    lancadas: number;
    pendencias: Pendencia[];
    encerrado: boolean;
    situacao_final: string | null;
    elegivel_conselho: boolean;
}
interface TurmaLinha { tur_id: number; turma: string; serie: string | null; seg_id: number | null; segmento: string | null; avaliativa: boolean; encerrada: boolean; alunos: AlunoLinha[] }
interface SegmentoOpc { seg_id: number; seg_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Encerramento de Turmas', href: '/encerramento-turmas' }];

// Padrão: o MAIOR ano em exercício (sem depender da ordem da lista).
const anoDefault = [...props.anosLetivos].filter((a) => a.anl_fl_em_exercicio).sort((a, b) => b.anl_ano - a.anl_ano)[0] ?? props.anosLetivos[0];
const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');

const carregando = ref(false);
const carregado = ref(false);
const erro = ref<string | null>(null);
const turmas = ref<TurmaLinha[]>([]);
const segmentos = ref<SegmentoOpc[]>([]);

// Busca + filtro de segmento na listagem.
const busca = ref('');
const segId = ref<number | ''>('');
const turmasFiltradas = computed(() => {
    const q = busca.value.trim().toLowerCase();
    return turmas.value.filter((t) => {
        if (segId.value !== '' && t.seg_id !== segId.value) return false;
        if (q && !`${t.serie ?? ''} ${t.turma}`.toLowerCase().includes(q)) return false;
        return true;
    });
});

// Mestre-detalhe: turma selecionada.
const selectedTurId = ref<number | null>(null);
const turmaSel = computed(() => turmas.value.find((t) => t.tur_id === selectedTurId.value) ?? null);

const pendenciasAbertas = reactive<Record<number, boolean>>({});
// Toggle "aprovado pelo conselho" — apenas visual, sempre desmarcado.
const conselho = reactive<Record<number, boolean>>({});

const buscarDados = async () => {
    erro.value = null;
    const url = new URL('/encerramento-turmas/dados', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    url.searchParams.set('esc_id', String(escId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) {
        const j = await r.json();
        turmas.value = j.turmas ?? [];
        segmentos.value = j.segmentos ?? [];
        carregado.value = true;
    } else {
        erro.value = `Não foi possível listar as turmas (erro ${r.status}).`;
    }
};

const listar = async () => {
    if (!anlId.value || !escId.value) return;
    carregando.value = true;
    selectedTurId.value = null;
    segId.value = '';
    try {
        await buscarDados();
    } catch {
        erro.value = 'Falha de conexão ao listar as turmas.';
    } finally {
        carregando.value = false;
    }
};

// Recarrega mantendo a turma aberta (após lançar notas no modal).
const recarregar = async () => {
    try { await buscarDados(); } catch { /* mantém estado */ }
};

// A cada lançamento/remoção no modal, atualiza a situação (completude) do aluno na lista — com debounce.
let recarregarTimer: number | undefined;
const onSaved = () => {
    houveLancamento.value = true;
    if (recarregarTimer) clearTimeout(recarregarTimer);
    recarregarTimer = window.setTimeout(() => recarregar(), 600);
};

// Modal de lançamento de notas/faltas do aluno.
const modalOpen = ref(false);
const modalAluno = ref<{ aln_id: number; aln_nome: string; matricula: string | null } | null>(null);
const houveLancamento = ref(false);
const abrirLancamento = (a: AlunoLinha) => {
    modalAluno.value = { aln_id: a.aln_id, aln_nome: a.nome, matricula: a.matricula };
    houveLancamento.value = false;
    modalOpen.value = true;
};
const onModalOpen = (v: boolean) => {
    modalOpen.value = v;
    if (!v && houveLancamento.value) recarregar(); // atualiza completude ao fechar
};

const abrirTurma = (t: TurmaLinha) => {
    selectedTurId.value = t.tur_id;
    Object.keys(pendenciasAbertas).forEach((k) => delete pendenciasAbertas[Number(k)]);
};
const voltar = () => { selectedTurId.value = null; };

const statusInfo = (s: AlunoLinha['status']) => ({
    completo:       { label: 'Notas completas', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' },
    pendente:       { label: 'Pendência de notas', cls: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-300' },
    nao_avaliativa: { label: 'Avaliação descritiva', cls: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300' },
    sem_grade:      { label: 'Sem grade definida', cls: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300' },
}[s]);

// ===== Confirmação (diálogo) =====
type ConfirmVariant = 'danger' | 'warning' | 'success';
const confirmState = reactive<{ open: boolean; title: string; message: string; variant: ConfirmVariant; confirmLabel: string }>({
    open: false, title: '', message: '', variant: 'warning', confirmLabel: 'Confirmar',
});
let confirmResolve: ((v: boolean) => void) | null = null;
const confirmar = (opts: { title: string; message: string; variant?: ConfirmVariant; confirmLabel?: string }): Promise<boolean> => {
    confirmState.title = opts.title;
    confirmState.message = opts.message;
    confirmState.variant = opts.variant ?? 'warning';
    confirmState.confirmLabel = opts.confirmLabel ?? 'Confirmar';
    confirmState.open = true;
    return new Promise((resolve) => { confirmResolve = resolve; });
};
const onConfirmOk = () => { confirmState.open = false; confirmResolve?.(true); confirmResolve = null; };
const onConfirmCancel = () => { confirmResolve?.(false); confirmResolve = null; };

// ===== Encerrar / cancelar =====
const processando = ref(false);
const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const postJson = (url: string, body: unknown) =>
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() }, credentials: 'same-origin', body: JSON.stringify(body) });

const encerrarTurma = async () => {
    const t = turmaSel.value;
    if (!t) return;
    const conselhoIds = t.alunos.filter((a) => a.elegivel_conselho && conselho[a.aln_id]).map((a) => a.aln_id);
    const ok = await confirmar({
        title: 'Encerrar turma',
        message: `${t.serie ? t.serie + ' — ' : ''}Turma ${t.turma}\n\nA situação final de cada aluno será calculada e gravada.`,
        variant: 'success',
        confirmLabel: 'Encerrar turma',
    });
    if (!ok) return;
    processando.value = true;
    erro.value = null;
    try {
        const r = await postJson('/encerramento-turmas/encerrar', { tur_id: t.tur_id, conselho: conselhoIds });
        if (r.ok) {
            await recarregar();
        } else {
            const j = await r.json().catch(() => ({}));
            erro.value = (j.message ?? 'Não foi possível encerrar.') + (Array.isArray(j.erros) ? ' ' + j.erros.join(' · ') : '');
        }
    } catch {
        erro.value = 'Falha de conexão ao encerrar.';
    } finally {
        processando.value = false;
    }
};

const cancelarTurma = async () => {
    const t = turmaSel.value;
    if (!t) return;
    const ok = await confirmar({
        title: 'Cancelar encerramento',
        message: 'A situação final de TODOS os alunos será removida e a turma voltará a ficar aberta.\n\n⚠️ As matrículas já feitas para o ano seguinte (renovação) serão removidas.',
        variant: 'danger',
        confirmLabel: 'Cancelar encerramento',
    });
    if (!ok) return;
    processando.value = true;
    try { const r = await postJson('/encerramento-turmas/cancelar', { tur_id: t.tur_id }); if (r.ok) await recarregar(); } catch { erro.value = 'Falha ao cancelar.'; } finally { processando.value = false; }
};

const cancelarAluno = async (a: AlunoLinha) => {
    const t = turmaSel.value;
    if (!t) return;
    const ok = await confirmar({
        title: 'Reabrir aluno',
        message: `${a.nome}\n\nA situação final dele será removida e a turma voltará a ficar aberta.\n\n⚠️ Se houver matrícula dele no ano seguinte (renovação), ela será removida.`,
        variant: 'danger',
        confirmLabel: 'Reabrir',
    });
    if (!ok) return;
    processando.value = true;
    try { const r = await postJson('/encerramento-turmas/cancelar-aluno', { tur_id: t.tur_id, aln_id: a.aln_id }); if (r.ok) await recarregar(); } catch { erro.value = 'Falha ao cancelar.'; } finally { processando.value = false; }
};

// Ata Final — abre o relatório na MESMA aba (não abre nova guia). Só com a turma encerrada.
const abrirAtaFinal = () => {
    const t = turmaSel.value;
    if (!t || !anlId.value || !escId.value) return;
    router.get('/relatorios/ata-final/gerar', { anl_id: anlId.value, esc_id: escId.value, tur_id: t.tur_id });
};

const podeEncerrar = (a: AlunoLinha) => a.status === 'completo' || a.status === 'nao_avaliativa';
const completos = (t: TurmaLinha) => t.alunos.filter((a) => podeEncerrar(a)).length;
// Encerramento é da turma inteira: só libera se todos os alunos estiverem aptos.
const podeEncerrarTurma = (t: TurmaLinha) => t.alunos.length > 0 && t.alunos.every((a) => podeEncerrar(a));
const pendentesTurma = (t: TurmaLinha) => t.alunos.filter((a) => !podeEncerrar(a)).length;
</script>

<template>
    <Head title="Encerramento de Turmas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <Archive class="size-5 text-indigo-600" /> Encerramento de Turmas
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Liste as turmas da escola. Acesse uma turma para ver a situação da matrícula e as notas de cada aluno.
            </p>

            <!-- Filtros -->
            <div class="mb-4 grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }}<span v-if="a.anl_fl_em_exercicio"> (em exercício)</span>
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button :disabled="!anlId || !escId || carregando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="listar">
                        <Loader2 v-if="carregando" class="mr-1 size-4 animate-spin" />
                        Listar Turmas
                    </Button>
                </div>
            </div>

            <div v-if="erro" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                {{ erro }}
            </div>

            <!-- ===== Lista de turmas (verde=encerrada / cinza=não) ===== -->
            <template v-if="carregado && !turmaSel">
                <div v-if="!turmas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                    Nenhuma turma regular encontrada para esta escola/ano.
                </div>
                <template v-else>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                            <span class="font-medium text-muted-foreground">Legenda:</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-emerald-500"></span> Fechada</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-slate-400"></span> Ativa</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                v-if="segmentos.length"
                                v-model="segId"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                title="Filtrar por segmento"
                            >
                                <option value="">Todos os segmentos</option>
                                <option v-for="sg in segmentos" :key="sg.seg_id" :value="sg.seg_id">{{ sg.seg_nome }}</option>
                            </select>
                            <div class="relative w-full max-w-xs sm:w-64">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <input v-model="busca" type="text" placeholder="Buscar turma..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            </div>
                        </div>
                    </div>
                    <div v-if="!turmasFiltradas.length" class="rounded-xl border bg-card py-10 text-center text-sm text-muted-foreground">
                        Nenhuma turma encontrada<span v-if="busca"> para "{{ busca }}"</span><span v-if="segId !== ''"> neste segmento</span>.
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="t in turmasFiltradas"
                            :key="t.tur_id"
                            type="button"
                            :class="[
                                'group flex items-center justify-between gap-3 rounded-xl border p-4 text-left shadow-sm transition hover:shadow-md',
                                t.encerrada
                                    ? 'border-emerald-300 bg-emerald-50 hover:border-emerald-400 dark:border-emerald-900 dark:bg-emerald-950/30'
                                    : 'border-slate-200 bg-slate-50 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900/40',
                            ]"
                            @click="abrirTurma(t)"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ t.serie ? `${t.serie} — ` : '' }}Turma {{ t.turma }}</div>
                                <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                    <span class="inline-flex items-center gap-1"><Users class="size-3.5" /> {{ t.alunos.length }} aluno(s)</span>
                                    <span aria-hidden="true">·</span>
                                    <span :class="t.encerrada ? 'font-medium text-emerald-700 dark:text-emerald-300' : ''">
                                        {{ t.encerrada ? 'Fechada' : 'Ativa' }}
                                    </span>
                                </div>
                            </div>
                            <span
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="t.encerrada ? 'bg-emerald-500 text-white' : 'bg-slate-300 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                            >
                                <CheckCircle2 v-if="t.encerrada" class="size-5" />
                                <Lock v-else class="size-4" />
                            </span>
                        </button>
                    </div>
                </template>
            </template>

            <!-- ===== Detalhe da turma: legenda + alunos ===== -->
            <template v-else-if="turmaSel">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="voltar"><ArrowLeft class="mr-1 size-4" /> Turmas</Button>
                        <h2 class="text-lg font-semibold">{{ turmaSel.serie ? `${turmaSel.serie} — ` : '' }}Turma {{ turmaSel.turma }}</h2>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="turmaSel.encerrada ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                        >{{ turmaSel.encerrada ? 'Fechada' : 'Ativa' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">{{ completos(turmaSel) }}/{{ turmaSel.alunos.length }} aptos</span>
                        <Button
                            v-if="turmaSel.encerrada"
                            variant="outline"
                            title="Emitir a Ata Final do encerramento"
                            class="gap-1"
                            @click="abrirAtaFinal"
                        >
                            <ScrollText class="size-4" /> Ata Final
                        </Button>
                        <Button
                            v-if="turmaSel.encerrada"
                            :disabled="processando"
                            title="Cancelar o encerramento da turma e reabrir"
                            class="gap-1 border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"
                            variant="outline"
                            @click="cancelarTurma"
                        >
                            <Loader2 v-if="processando" class="size-4 animate-spin" />
                            <ArrowLeft v-else class="size-4" /> Cancelar encerramento
                        </Button>
                        <Button
                            v-else
                            :disabled="!podeEncerrarTurma(turmaSel) || processando"
                            :title="podeEncerrarTurma(turmaSel) ? 'Encerrar a turma' : `${pendentesTurma(turmaSel)} aluno(s) com notas pendentes`"
                            class="gap-1 bg-emerald-600 text-white hover:bg-emerald-700"
                            @click="encerrarTurma"
                        >
                            <Loader2 v-if="processando" class="size-4 animate-spin" />
                            <Lock v-else class="size-4" /> Encerrar turma
                        </Button>
                    </div>
                </div>
                <p v-if="!podeEncerrarTurma(turmaSel)" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    A turma só pode ser encerrada quando todos os alunos estiverem com as notas completas. Pendentes: {{ pendentesTurma(turmaSel) }}.
                </p>

                <!-- Legenda dos alunos -->
                <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border bg-muted/30 px-4 py-2.5 text-xs">
                    <span class="font-medium text-muted-foreground">Legenda:</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-emerald-500"></span> Notas completas — pode encerrar</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-rose-500"></span> Pendência de notas — não pode encerrar</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-slate-400"></span> Avaliação descritiva (sem nota)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-amber-500"></span> Sem grade definida</span>
                </div>

                <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Aluno</th>
                                    <th class="px-3 py-2 font-semibold">Situação</th>
                                    <th class="px-3 py-2 font-semibold">Notas</th>
                                    <th class="px-3 py-2 text-center font-semibold">Apr. Conselho</th>
                                    <th class="px-3 py-2 text-right font-semibold">Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-if="!turmaSel.alunos.length">
                                    <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</td>
                                </tr>
                                <template v-for="a in turmaSel.alunos" :key="a.aln_id">
                                    <tr :class="a.status === 'completo' ? 'bg-emerald-50/40 dark:bg-emerald-950/10' : a.status === 'pendente' ? 'bg-rose-50/40 dark:bg-rose-950/10' : ''">
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ a.nome }}</div>
                                            <div v-if="a.matricula" class="text-[11px] text-muted-foreground">Matr. {{ a.matricula }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span :class="['rounded-full px-2 py-0.5 text-[11px] font-medium', a.encerrado ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300']">{{ a.situacao }}</span>
                                            <span v-if="a.encerrado" class="ml-1 text-[10px] text-muted-foreground">(final)</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset', statusInfo(a.status).cls]">
                                                {{ statusInfo(a.status).label }}
                                            </span>
                                            <button
                                                v-if="a.status === 'pendente'"
                                                type="button"
                                                class="ml-2 text-[11px] font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                @click="pendenciasAbertas[a.aln_id] = !pendenciasAbertas[a.aln_id]"
                                            >
                                                {{ pendenciasAbertas[a.aln_id] ? 'ocultar' : `ver ${a.pendencias.length} pendência(s)` }}
                                            </button>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Switch
                                                v-if="!a.encerrado && a.elegivel_conselho"
                                                :model-value="conselho[a.aln_id] ?? false"
                                                @update:model-value="conselho[a.aln_id] = $event"
                                            />
                                            <span
                                                v-else
                                                class="text-[11px] text-muted-foreground"
                                                :title="a.encerrado ? '' : 'Disponível apenas para alunos que não atingiram a média ou a frequência'"
                                            >—</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <Button
                                                v-if="a.encerrado"
                                                size="sm"
                                                variant="outline"
                                                :disabled="processando"
                                                class="gap-1 text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                                title="Cancelar o encerramento deste aluno (reabre a turma)"
                                                @click="cancelarAluno(a)"
                                            >
                                                <ArrowLeft class="size-3.5" /> Reabrir
                                            </Button>
                                            <Button v-else size="sm" variant="outline" class="gap-1" title="Lançar notas e faltas do aluno" @click="abrirLancamento(a)">
                                                <PencilLine class="size-3.5" /> Lançar notas
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="pendenciasAbertas[a.aln_id] && a.pendencias.length" class="bg-rose-50/30 dark:bg-rose-950/10">
                                        <td colspan="5" class="px-3 pb-3 pt-0">
                                            <div class="rounded-lg border border-rose-200 bg-background p-2 dark:border-rose-900">
                                                <p class="mb-1 text-[11px] font-semibold text-rose-700 dark:text-rose-300">Notas pendentes:</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span v-for="(p, i) in a.pendencias" :key="i" class="rounded bg-rose-100 px-2 py-0.5 text-[11px] text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                                                        {{ p.disciplina }} · {{ p.unidade }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>

        <LancamentoNotasModal
            v-if="modalAluno && turmaSel"
            :open="modalOpen"
            :tur-id="turmaSel.tur_id"
            :aluno="modalAluno"
            @update:open="onModalOpen"
            @saved="onSaved"
        />

        <ConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :message="confirmState.message"
            :variant="confirmState.variant"
            :confirm-label="confirmState.confirmLabel"
            @update:open="confirmState.open = $event"
            @confirm="onConfirmOk"
            @cancel="onConfirmCancel"
        />
    </AppLayout>
</template>
