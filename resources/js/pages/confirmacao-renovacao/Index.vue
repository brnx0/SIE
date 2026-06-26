<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, Loader2, Search, TriangleAlert, Users } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface SegmentoOpc { seg_id: number; seg_nome: string }
interface TurmaLinha { tur_id: number; turma: string; serie: string | null; seg_id: number | null; segmento: string | null }
interface SerieDestino { ser_id: number; ser_nome: string }
interface TurmaDestino { tur_id: number; turma: string; serie: string | null }
interface AlunoLinha {
    tma_id: number; aln_id: number; nome: string; matricula: string | null;
    situacao_final: string; quer_renovar: boolean | null; aprovado: boolean; destinos: SerieDestino[];
    ja_renovado: boolean; destino_ser_id: number | null; destino_tur_id: number | null;
}

const props = defineProps<{ anosLetivos: AnoLetivo[]; escolas: Escola[]; userEscola: Escola | null; isAdmin: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Confirmação de Renovação', href: '/confirmacao-renovacao' }];

// Padrão: o MAIOR ano em exercício (sem depender da ordem da lista).
const anoDefault = [...props.anosLetivos].filter((a) => a.anl_fl_em_exercicio).sort((a, b) => b.anl_ano - a.anl_ano)[0] ?? props.anosLetivos[0];
const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');

const carregando = ref(false);
const carregado = ref(false);
const erro = ref<string | null>(null);
const turmas = ref<TurmaLinha[]>([]);
const segmentos = ref<SegmentoOpc[]>([]);
const segId = ref<number | ''>('');
const temProxAno = ref(true);
const proxAno = ref<number | null>(null);
const busca = ref('');

const turmasFiltradas = computed(() => {
    const q = busca.value.trim().toLowerCase();
    return turmas.value.filter((t) => {
        if (segId.value !== '' && t.seg_id !== segId.value) return false;
        if (q && !`${t.serie ?? ''} ${t.turma}`.toLowerCase().includes(q)) return false;
        return true;
    });
});

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const postJson = (url: string, body: unknown) =>
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() }, credentials: 'same-origin', body: JSON.stringify(body) });

const listar = async () => {
    if (!anlId.value || !escId.value) return;
    carregando.value = true;
    erro.value = null;
    selectedTurId.value = null;
    segId.value = '';
    try {
        const url = new URL('/confirmacao-renovacao/turmas', window.location.origin);
        url.searchParams.set('anl_id', String(anlId.value));
        url.searchParams.set('esc_id', String(escId.value));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (r.ok) {
            const j = await r.json();
            turmas.value = j.turmas ?? [];
            segmentos.value = j.segmentos ?? [];
            temProxAno.value = !!j.tem_prox_ano;
            proxAno.value = j.prox_ano ?? null;
            carregado.value = true;
        } else { erro.value = `Não foi possível listar as turmas (erro ${r.status}).`; }
    } catch { erro.value = 'Falha de conexão.'; } finally { carregando.value = false; }
};

// ===== Detalhe: alunos =====
const selectedTurId = ref<number | null>(null);
const turmaSel = computed(() => turmas.value.find((t) => t.tur_id === selectedTurId.value) ?? null);
const alunos = ref<AlunoLinha[]>([]);
const carregandoAlunos = ref(false);

const turmasPorSerie = reactive<Record<number, TurmaDestino[]>>({});
const carregandoSerie = reactive<Record<number, boolean>>({});
const serDestino = reactive<Record<number, number | ''>>({});
const turDestino = reactive<Record<number, number | ''>>({});
const statusAln = reactive<Record<number, 'saving' | 'saved' | 'error'>>({});

const pendentes = computed(() => alunos.value.filter((a) => !a.ja_renovado).length);
const renovados = computed(() => alunos.value.filter((a) => a.ja_renovado).length);

const carregarTurmasSerie = async (serId: number) => {
    if (turmasPorSerie[serId] || carregandoSerie[serId] || !selectedTurId.value) return;
    carregandoSerie[serId] = true;
    try {
        const url = new URL('/confirmacao-renovacao/turmas-destino', window.location.origin);
        url.searchParams.set('tur_origem_id', String(selectedTurId.value));
        url.searchParams.set('ser_id', String(serId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (r.ok) turmasPorSerie[serId] = (await r.json()).turmas ?? [];
    } finally { carregandoSerie[serId] = false; }
};

const carregarAlunos = async (tur: number) => {
    carregandoAlunos.value = true;
    erro.value = null;
    loteOferecido.value = false;
    [serDestino, turDestino, statusAln].forEach((o) => Object.keys(o).forEach((k) => delete (o as Record<number, unknown>)[Number(k)]));
    try {
        const url = new URL('/confirmacao-renovacao/alunos', window.location.origin);
        url.searchParams.set('tur_id', String(tur));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (r.ok) {
            alunos.value = (await r.json()).alunos ?? [];
            for (const a of alunos.value) {
                if (a.ja_renovado) continue;
                if (a.destinos.length === 1) { serDestino[a.aln_id] = a.destinos[0].ser_id; carregarTurmasSerie(a.destinos[0].ser_id); }
                else serDestino[a.aln_id] = '';
            }
        } else { erro.value = `Não foi possível carregar os alunos (erro ${r.status}).`; }
    } catch { erro.value = 'Falha de conexão.'; } finally { carregandoAlunos.value = false; }
};

const abrirTurma = (t: TurmaLinha) => { selectedTurId.value = t.tur_id; carregarAlunos(t.tur_id); };
const voltar = () => { selectedTurId.value = null; alunos.value = []; };

const escolherSerie = (a: AlunoLinha, serId: number | '') => {
    serDestino[a.aln_id] = serId;
    turDestino[a.aln_id] = '';
    if (serId !== '') carregarTurmasSerie(serId);
};

const turmasDoAluno = (a: AlunoLinha): TurmaDestino[] => {
    const s = serDestino[a.aln_id];
    return s === '' || s === undefined ? [] : (turmasPorSerie[s] ?? []);
};

// Diálogo de confirmação reutilizável.
type ConfirmVariant = 'danger' | 'warning' | 'success';
const confirmState = reactive<{ open: boolean; title: string; message: string; confirmLabel: string; variant: ConfirmVariant }>({ open: false, title: '', message: '', confirmLabel: 'Confirmar', variant: 'warning' });
let confirmResolve: ((v: boolean) => void) | null = null;
const pedirConfirmacao = (opts: { title: string; message: string; confirmLabel?: string; variant?: ConfirmVariant }): Promise<boolean> => {
    confirmState.title = opts.title;
    confirmState.message = opts.message;
    confirmState.confirmLabel = opts.confirmLabel ?? 'Confirmar';
    confirmState.variant = opts.variant ?? 'warning';
    confirmState.open = true;
    return new Promise((resolve) => { confirmResolve = resolve; });
};
const onConfirmOk = () => { confirmState.open = false; confirmResolve?.(true); confirmResolve = null; };
const onConfirmCancel = () => { confirmResolve?.(false); confirmResolve = null; };

// Lote: depois do 1º aluno, oferece renovar os demais (mesma série) para a mesma turma.
const loteOferecido = ref(false);
const pendentesParaSerie = (serId: number, exceptAln: number): AlunoLinha[] =>
    alunos.value.filter((x) => !x.ja_renovado && x.aln_id !== exceptAln && x.destinos.some((d) => d.ser_id === serId));

const confirmarLote = async (serId: number, turId: number) => {
    erro.value = null;
    try {
        const r = await postJson('/confirmacao-renovacao/confirmar-lote', { tur_origem_id: selectedTurId.value, ser_id: serId, tur_destino_id: turId });
        if (r.ok) { await carregarAlunos(selectedTurId.value as number); }
        else { const j = await r.json().catch(() => ({})); erro.value = j.message ?? 'Não foi possível renovar em lote.'; }
    } catch { erro.value = 'Falha de conexão no lote.'; }
};

const confirmar = async (a: AlunoLinha) => {
    const ser = serDestino[a.aln_id];
    const tur = turDestino[a.aln_id];
    if (ser === '' || ser === undefined || tur === '' || tur === undefined) return;
    if (a.quer_renovar !== true) {
        const ok = await pedirConfirmacao({ title: 'Renovar manualmente', message: `${a.nome}\n\nEste aluno NÃO marcou interesse em renovar. Deseja renovar mesmo assim?`, confirmLabel: 'Renovar mesmo assim', variant: 'warning' });
        if (!ok) return;
    }
    statusAln[a.aln_id] = 'saving';
    erro.value = null;
    try {
        const r = await postJson('/confirmacao-renovacao/confirmar', { origem_tma_id: a.tma_id, ser_id: ser, tur_destino_id: tur });
        if (r.ok) {
            a.ja_renovado = true; a.destino_ser_id = ser; a.destino_tur_id = tur; statusAln[a.aln_id] = 'saved';
            // Primeira renovação da turma → oferece renovar os demais (mesma série) para a mesma turma.
            if (!loteOferecido.value) {
                loteOferecido.value = true;
                const compat = pendentesParaSerie(ser, a.aln_id);
                if (compat.length) {
                    const ok = await pedirConfirmacao({ title: 'Renovar os demais?', message: `Renovar também os outros ${compat.length} aluno(s) desta turma para a mesma turma destino?`, confirmLabel: 'Renovar todos', variant: 'success' });
                    if (ok) await confirmarLote(ser, tur);
                }
            }
        } else { const j = await r.json().catch(() => ({})); erro.value = j.message ?? 'Não foi possível confirmar.'; statusAln[a.aln_id] = 'error'; }
    } catch { erro.value = 'Falha de conexão.'; statusAln[a.aln_id] = 'error'; }
};

const nomeSerie = (id: number | null): string => {
    for (const a of alunos.value) { const d = a.destinos.find((x) => x.ser_id === id); if (d) return d.ser_nome; }
    return '—';
};
const nomeTurmaDestino = (serId: number | null, turId: number | null): string => {
    if (serId == null || turId == null) return '—';
    const t = (turmasPorSerie[serId] ?? []).find((x) => x.tur_id === turId);
    return t ? `${t.serie ? t.serie + ' — ' : ''}Turma ${t.turma}` : `Turma ${turId}`;
};
</script>

<template>
    <Head title="Confirmação de Renovação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ArrowRight class="size-5 text-indigo-600" /> Confirmação de Renovação
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                A partir das turmas <strong>encerradas</strong>, matricula no ano seguinte os alunos que marcaram interesse em renovar. Aprovado → série de promoção; reprovado → série de conservação.
            </p>

            <div class="mb-4 grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}<span v-if="a.anl_fl_em_exercicio"> (em exercício)</span></option>
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
                        <Loader2 v-if="carregando" class="mr-1 size-4 animate-spin" /> Listar Turmas
                    </Button>
                </div>
            </div>

            <div v-if="erro" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ erro }}</div>

            <template v-if="carregado && !turmaSel">
                <div v-if="!temProxAno" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" /> O ano letivo seguinte ainda não existe — cadastre-o e crie as turmas para poder renovar.
                </div>
                <div v-if="!turmas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhuma turma encerrada nesta escola/ano.</div>
                <template v-else>
                    <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
                        <select v-if="segmentos.length" v-model="segId" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Todos os segmentos</option>
                            <option v-for="sg in segmentos" :key="sg.seg_id" :value="sg.seg_id">{{ sg.seg_nome }}</option>
                        </select>
                        <div class="relative w-full max-w-xs sm:w-56">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="busca" type="text" placeholder="Buscar turma..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                    </div>
                    <div v-if="!turmasFiltradas.length" class="rounded-xl border bg-card py-10 text-center text-sm text-muted-foreground">Nenhuma turma encontrada.</div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button v-for="t in turmasFiltradas" :key="t.tur_id" type="button" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-card p-4 text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800" @click="abrirTurma(t)">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ t.serie ? `${t.serie} — ` : '' }}Turma {{ t.turma }}</div>
                                <div v-if="t.segmento" class="mt-0.5 text-xs text-muted-foreground">{{ t.segmento }}</div>
                            </div>
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300"><Users class="size-4" /></span>
                        </button>
                    </div>
                </template>
            </template>

            <template v-else-if="turmaSel">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="voltar"><ArrowLeft class="mr-1 size-4" /> Turmas</Button>
                        <h2 class="text-lg font-semibold">{{ turmaSel.serie ? `${turmaSel.serie} — ` : '' }}Turma {{ turmaSel.turma }}</h2>
                    </div>
                    <span class="text-sm text-muted-foreground">{{ renovados }} renovado(s) · {{ pendentes }} pendente(s)</span>
                </div>

                <div v-if="carregandoAlunos" class="rounded-xl border bg-card py-12 text-center text-sm text-muted-foreground">Carregando alunos...</div>
                <div v-else-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno marcou interesse em renovar nesta turma.</div>

                <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Aluno</th>
                                    <th class="px-3 py-2 font-semibold">Resultado</th>
                                    <th class="px-3 py-2 font-semibold">Série destino</th>
                                    <th class="px-3 py-2 font-semibold">Turma destino</th>
                                    <th class="px-3 py-2 text-right font-semibold">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="a in alunos" :key="a.aln_id" class="align-top hover:bg-muted/20">
                                    <td class="px-3 py-2">
                                        <div class="font-medium">{{ a.nome }}</div>
                                        <div v-if="a.matricula" class="text-[11px] text-muted-foreground">Matr. {{ a.matricula }}</div>
                                        <span v-if="a.quer_renovar === true" class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">marcou renovar</span>
                                        <span v-else class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300" title="Não marcou interesse em renovar — pode renovar manualmente">não marcou renovar</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium', a.aprovado ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300']">
                                            {{ a.situacao_final }}
                                        </span>
                                    </td>

                                    <!-- Já renovado -->
                                    <template v-if="a.ja_renovado">
                                        <td class="px-3 py-2 text-emerald-700 dark:text-emerald-300">{{ nomeSerie(a.destino_ser_id) }}</td>
                                        <td class="px-3 py-2 text-emerald-700 dark:text-emerald-300">{{ nomeTurmaDestino(a.destino_ser_id, a.destino_tur_id) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><CheckCircle2 class="size-3.5" /> Renovado</span>
                                        </td>
                                    </template>

                                    <!-- Pendente -->
                                    <template v-else>
                                        <td class="px-3 py-2">
                                            <span v-if="!a.destinos.length" class="text-[11px] text-amber-700 dark:text-amber-300">Sem série de destino configurada</span>
                                            <span v-else-if="a.destinos.length === 1" class="text-sm">{{ a.destinos[0].ser_nome }}</span>
                                            <select v-else :value="serDestino[a.aln_id] ?? ''" class="h-8 rounded-md border border-input bg-background px-2 text-sm" @change="escolherSerie(a, ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : '')">
                                                <option value="">Selecione...</option>
                                                <option v-for="d in a.destinos" :key="d.ser_id" :value="d.ser_id">{{ d.ser_nome }}</option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Loader2 v-if="serDestino[a.aln_id] && carregandoSerie[serDestino[a.aln_id] as number]" class="size-4 animate-spin text-muted-foreground" />
                                            <select v-else-if="serDestino[a.aln_id]" v-model="turDestino[a.aln_id]" class="h-8 w-full rounded-md border border-input bg-background px-2 text-sm">
                                                <option value="">Selecione a turma...</option>
                                                <option v-for="t in turmasDoAluno(a)" :key="t.tur_id" :value="t.tur_id">{{ t.serie ? t.serie + ' — ' : '' }}Turma {{ t.turma }}</option>
                                            </select>
                                            <span v-if="serDestino[a.aln_id] && !carregandoSerie[serDestino[a.aln_id] as number] && !turmasDoAluno(a).length" class="text-[11px] text-amber-700 dark:text-amber-300">Nenhuma turma no ano +1 — crie/duplique antes.</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <Button size="sm" :disabled="!serDestino[a.aln_id] || !turDestino[a.aln_id] || statusAln[a.aln_id] === 'saving'" class="gap-1 bg-emerald-600 text-white hover:bg-emerald-700" @click="confirmar(a)">
                                                <Loader2 v-if="statusAln[a.aln_id] === 'saving'" class="size-3.5 animate-spin" /> Confirmar
                                            </Button>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <div v-else class="rounded-xl border bg-card px-6 py-10 text-center text-sm text-muted-foreground shadow-sm">
                Selecione o ano letivo e a escola para listar as turmas encerradas.
            </div>
        </div>

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
