<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Switch from '@/components/common/Switch.vue';
import { ChevronLeft, ChevronRight, Loader2, RefreshCw, Search, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{ anlId: number; escId: number; turId: number; uniId: number }>();

interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null; dt_saida: string | null }
interface Plano { dae_id: number; dt_inicio: string; dt_fim: string; conteudo: string | null; metodologia: string | null }
interface ConteudoInfo { conteudo: string; metodologia: string; executado: boolean; dae_id: number | null }

const DIA_DOW: Record<string, number> = { dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 };
const DIA_LABEL: Record<string, string> = { dom: 'Dom', seg: 'Seg', ter: 'Ter', qua: 'Qua', qui: 'Qui', sex: 'Sex', sab: 'Sáb' };

const carregando = ref(true);
const recarregando = ref(false);
const erro = ref<string | null>(null);
const dias = ref<string[]>([]);
const alunos = ref<Aluno[]>([]);
const periodo = ref<{ dt_inicio: string; dt_fim: string } | null>(null);
const periodoAberto = ref(true);
const turmaAberta = ref(true);
const dataSel = ref<string>('');
const busca = ref('');

const presencaMap = reactive<Record<string, boolean>>({});
const status = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});

// Conteúdo/metodologia + planejamento executado: 1 por dia (AEE não tem disciplina).
const planos = ref<Plano[]>([]);
const conteudoMap = reactive<Record<string, ConteudoInfo>>({});
const conteudoStatus = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});
const conteudoTimer: Record<string, number> = {};

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const k = (dt: string, aln: number) => `${dt}|${aln}`;

const carregar = async (silent = false) => {
    if (silent) recarregando.value = true;
    else carregando.value = true;
    erro.value = null;
    try {
        const url = new URL('/api/diario-aee/frequencia/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        url.searchParams.set('uni_id', String(props.uniId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) { erro.value = 'Não foi possível carregar a frequência.'; return; }
        const data = await r.json();
        Object.keys(presencaMap).forEach((key) => delete presencaMap[key]);
        Object.keys(status).forEach((key) => delete status[key]);
        Object.keys(conteudoMap).forEach((key) => delete conteudoMap[key]);
        Object.keys(conteudoStatus).forEach((key) => delete conteudoStatus[key]);
        dias.value = data.dias ?? [];
        alunos.value = data.alunos ?? [];
        periodo.value = data.periodo ?? null;
        periodoAberto.value = data.periodo_aberto ?? false;
        turmaAberta.value = data.turma_aberta ?? false;
        planos.value = data.planos ?? [];
        for (const p of data.presencas ?? []) presencaMap[k(p.dt, p.aln_id)] = p.presente;
        for (const c of data.conteudos ?? []) {
            conteudoMap[c.dt] = { conteudo: c.conteudo ?? '', metodologia: c.metodologia ?? '', executado: !!c.plano_executado, dae_id: c.dae_id ?? null };
        }
        if (!dataSel.value || !diasComAtendimento.value.some((d) => d.dt === dataSel.value)) escolherDataInicial();
    } finally {
        carregando.value = false;
        recarregando.value = false;
    }
};

onMounted(() => carregar());
watch(() => [props.turId, props.uniId], () => carregar());

const diasComAtendimento = computed<{ dt: string; label: string }[]>(() => {
    if (!periodo.value || !dias.value.length) return [];
    const dows = new Set(dias.value.map((d) => DIA_DOW[d]));
    const out: { dt: string; label: string }[] = [];
    const [yi, mi, di] = periodo.value.dt_inicio.split('-').map(Number);
    const [yf, mf, df] = periodo.value.dt_fim.split('-').map(Number);
    const cur = new Date(yi, mi - 1, di);
    const fim = new Date(yf, mf - 1, df);
    while (cur <= fim) {
        if (dows.has(cur.getDay())) {
            const dt = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`;
            const code = Object.keys(DIA_DOW).find((key) => DIA_DOW[key] === cur.getDay())!;
            out.push({ dt, label: `${String(cur.getDate()).padStart(2, '0')}/${String(cur.getMonth() + 1).padStart(2, '0')} · ${DIA_LABEL[code]}` });
        }
        cur.setDate(cur.getDate() + 1);
    }
    return out;
});

const hojeStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const escolherDataInicial = () => {
    const ds = diasComAtendimento.value;
    if (!ds.length) { dataSel.value = ''; return; }
    const hoje = hojeStr();
    const passados = ds.filter((d) => d.dt <= hoje);
    dataSel.value = (passados.length ? passados[passados.length - 1] : ds[0]).dt;
};

const idxData = computed(() => diasComAtendimento.value.findIndex((d) => d.dt === dataSel.value));
const irData = (delta: number) => {
    const i = idxData.value + delta;
    if (i >= 0 && i < diasComAtendimento.value.length) dataSel.value = diasComAtendimento.value[i].dt;
};

const editavel = computed(() => periodoAberto.value && turmaAberta.value);
const presente = (aln: number) => {
    const key = k(dataSel.value, aln);
    return key in presencaMap ? presencaMap[key] : true; // default presente
};
const fmtData = (dt: string | null) => {
    if (!dt) return '';
    const [y, m, d] = dt.split('-');
    return `${d}/${m}/${y}`;
};
const saiuNoDia = (a: Aluno) => !!a.dt_saida && dataSel.value > a.dt_saida;

const alunosFiltrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter((a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q));
});

const marcar = async (aln: number, val: boolean) => {
    if (!editavel.value) return;
    const key = k(dataSel.value, aln);
    presencaMap[key] = val;
    status[key] = 'saving';
    try {
        const r = await fetch('/api/diario-aee/frequencia/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, uni_id: props.uniId, aln_id: aln, dt: dataSel.value, presente: val }),
        });
        status[key] = r.ok ? 'saved' : 'error';
    } catch {
        status[key] = 'error';
    }
};
const toggle = (aln: number) => marcar(aln, !presente(aln));

const marcarLote = async (val: boolean) => {
    if (!editavel.value) return;
    for (const a of alunos.value) {
        if (!saiuNoDia(a)) presencaMap[k(dataSel.value, a.aln_id)] = val;
    }
    try {
        const r = await fetch('/api/diario-aee/frequencia/lote', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, uni_id: props.uniId, dt: dataSel.value, presente: val }),
        });
        if (!r.ok) erro.value = 'Não foi possível salvar em lote.';
    } catch {
        erro.value = 'Não foi possível salvar em lote.';
    }
};

const totalPresentes = computed(() => alunosFiltrados.value.filter((a) => !saiuNoDia(a) && presente(a.aln_id)).length);
const totalAusentes = computed(() => alunosFiltrados.value.filter((a) => !saiuNoDia(a) && !presente(a.aln_id)).length);

// ── Conteúdo / metodologia + planejamento executado (1 por dia) ──────────────
const SEM_INFO: ConteudoInfo = { conteudo: '', metodologia: '', executado: false, dae_id: null };
const planoDoDia = (): Plano | null => {
    const dt = dataSel.value;
    return planos.value.find((p) => dt >= p.dt_inicio && dt <= p.dt_fim) ?? null;
};
const infoDia = (): ConteudoInfo => conteudoMap[dataSel.value] ?? SEM_INFO;
const ensureDia = (): ConteudoInfo => {
    const key = dataSel.value;
    if (!conteudoMap[key]) conteudoMap[key] = { conteudo: '', metodologia: '', executado: false, dae_id: null };
    return conteudoMap[key];
};
const getConteudo = (campo: 'conteudo' | 'metodologia') => {
    const i = infoDia();
    if (i.executado) return planoDoDia()?.[campo] ?? i[campo] ?? '';
    return i[campo] ?? '';
};

const salvarConteudo = async () => {
    if (!editavel.value || !dataSel.value) return;
    const key = dataSel.value;
    const i = ensureDia();
    conteudoStatus[key] = 'saving';
    try {
        const r = await fetch('/api/diario-aee/frequencia/conteudo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({
                tur_id: props.turId,
                uni_id: props.uniId,
                dt: dataSel.value,
                conteudo: i.conteudo,
                metodologia: i.metodologia,
                plano_executado: i.executado,
                dae_id: i.dae_id,
            }),
        });
        if (r.ok) {
            const j = await r.json();
            i.conteudo = j.conteudo ?? i.conteudo;
            i.metodologia = j.metodologia ?? i.metodologia;
            i.dae_id = j.dae_id ?? null;
            conteudoStatus[key] = 'saved';
        } else {
            conteudoStatus[key] = 'error';
        }
    } catch {
        conteudoStatus[key] = 'error';
    }
};

const setConteudo = (campo: 'conteudo' | 'metodologia', val: string) => {
    const i = ensureDia();
    if (i.executado) return; // travado quando planejamento executado
    i[campo] = val;
    const key = dataSel.value;
    conteudoStatus[key] = 'idle';
    if (conteudoTimer[key]) clearTimeout(conteudoTimer[key]);
    conteudoTimer[key] = window.setTimeout(salvarConteudo, 1200);
};

const toggleExecutado = (val: boolean) => {
    const i = ensureDia();
    if (val) {
        const p = planoDoDia();
        if (!p) return;
        i.executado = true;
        i.dae_id = p.dae_id;
        i.conteudo = p.conteudo ?? '';
        i.metodologia = p.metodologia ?? '';
    } else {
        i.executado = false;
        i.dae_id = null;
    }
    salvarConteudo();
};
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-card/95 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Frequência AEE</h2>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="busca" type="text" placeholder="Filtrar aluno..." class="h-9 w-44 rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                </div>
                <template v-if="diasComAtendimento.length">
                    <Button variant="outline" size="sm" :disabled="idxData <= 0" @click="irData(-1)"><ChevronLeft class="size-4" /></Button>
                    <select v-model="dataSel" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="d in diasComAtendimento" :key="d.dt" :value="d.dt">{{ d.label }}</option>
                    </select>
                    <Button variant="outline" size="sm" :disabled="idxData >= diasComAtendimento.length - 1" @click="irData(1)"><ChevronRight class="size-4" /></Button>
                </template>
                <Button variant="outline" size="sm" class="gap-1.5" :disabled="carregando || recarregando" @click="carregar(true)">
                    <RefreshCw :class="['size-4', recarregando && 'animate-spin']" /> Atualizar
                </Button>
            </div>
        </div>

        <div class="p-4">
            <div v-if="carregando" class="py-12 text-center text-sm text-muted-foreground">Carregando...</div>
            <div v-else-if="erro" class="py-12 text-center text-sm text-rose-600">{{ erro }}</div>
            <div v-else-if="!dias.length" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>Esta turma AEE não tem dias de atendimento definidos.</span>
            </div>
            <div v-else-if="!alunos.length" class="py-12 text-center text-sm text-muted-foreground">Nenhum aluno na turma AEE.</div>
            <div v-else-if="!diasComAtendimento.length" class="py-12 text-center text-sm text-muted-foreground">Sem dias de atendimento no período.</div>

            <template v-else>
                <div v-if="!editavel" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ !turmaAberta ? 'Turma não está aberta.' : 'Fora do período de lançamento.' }} Frequência em modo leitura.</span>
                </div>

                <!-- Registro do dia: conteúdo / metodologia + planejamento executado (do plano AEE) -->
                <div class="mb-4 rounded-lg border bg-background p-3">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold">Registro do dia</span>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center gap-2 text-xs"
                                :class="planoDoDia() ? 'text-slate-700 dark:text-slate-200' : 'text-muted-foreground'"
                                :title="planoDoDia() ? 'Traz o conteúdo/metodologia do plano AEE' : 'Sem planejamento AEE (pendente/aprovado) para esta data'"
                            >
                                <Switch
                                    :model-value="infoDia().executado"
                                    :disabled="!editavel || !planoDoDia()"
                                    @update:model-value="toggleExecutado($event)"
                                />
                                <span>Planejamento executado</span>
                            </div>
                            <span class="text-xs">
                                <span v-if="conteudoStatus[dataSel] === 'saving'" class="inline-flex items-center gap-1 text-amber-600"><Loader2 class="size-3.5 animate-spin" /> Salvando</span>
                                <span v-else-if="conteudoStatus[dataSel] === 'saved'" class="text-emerald-600">Salvo</span>
                                <span v-else-if="conteudoStatus[dataSel] === 'error'" class="text-rose-600">Erro ao salvar</span>
                            </span>
                        </div>
                    </div>
                    <div v-if="infoDia().executado" class="mb-2 inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                        Conteúdo do plano AEE (somente leitura)
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Conteúdo</label>
                            <textarea
                                :value="getConteudo('conteudo')"
                                :readonly="infoDia().executado"
                                :disabled="!editavel"
                                rows="2"
                                placeholder="O que foi trabalhado no atendimento..."
                                class="mt-1 w-full resize-y rounded-md border bg-background px-2.5 py-1.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 read-only:cursor-default read-only:bg-muted/40 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                                @input="setConteudo('conteudo', ($event.target as HTMLTextAreaElement).value)"
                            ></textarea>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground">Metodologia</label>
                            <textarea
                                :value="getConteudo('metodologia')"
                                :readonly="infoDia().executado"
                                :disabled="!editavel"
                                rows="2"
                                placeholder="Como foi trabalhado..."
                                class="mt-1 w-full resize-y rounded-md border bg-background px-2.5 py-1.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 read-only:cursor-default read-only:bg-muted/40 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                                @input="setConteudo('metodologia', ($event.target as HTMLTextAreaElement).value)"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Matriz alunos × presença do dia (clique alterna P/F) -->
                <div class="max-h-[70vh] overflow-auto rounded-lg border">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-30 min-w-[240px] border-b bg-muted/90 px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur">
                                    Aluno
                                </th>
                                <th class="sticky top-0 z-20 border-b border-l bg-muted/90 px-2 py-1 text-center align-top backdrop-blur">
                                    <div class="mx-auto flex items-center justify-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        Presença
                                        <button type="button" :disabled="!editavel" title="Todos presentes" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 disabled:opacity-40" @click="marcarLote(true)">✓</button>
                                        <button type="button" :disabled="!editavel" title="Todos ausentes" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 disabled:opacity-40" @click="marcarLote(false)">✕</button>
                                    </div>
                                </th>
                                <th class="sticky top-0 z-20 border-b border-l bg-muted/90 px-2 py-1.5 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!alunosFiltrados.length">
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-muted-foreground">Nenhum aluno encontrado.</td>
                            </tr>
                            <tr v-for="(a, i) in alunosFiltrados" :key="a.aln_id" class="group">
                                <td :class="['sticky left-0 z-10 border-b px-3 py-2 transition-colors', i % 2 ? 'bg-muted/30' : 'bg-card', 'group-hover:bg-indigo-50/70 dark:group-hover:bg-indigo-950/40']">
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 shrink-0 text-right text-[11px] tabular-nums text-muted-foreground">{{ i + 1 }}</span>
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-medium text-slate-900 dark:text-slate-50">{{ a.aln_nome }}</div>
                                            <div v-if="a.aln_nr_matricula" class="text-[10px] text-muted-foreground">Mat. {{ a.aln_nr_matricula }}</div>
                                            <div v-if="a.dt_saida" class="mt-0.5 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saída {{ fmtData(a.dt_saida) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td :class="['border-b border-l px-2 py-2 text-center transition-colors', i % 2 ? 'bg-muted/20' : '', 'group-hover:bg-indigo-50/40 dark:group-hover:bg-indigo-950/20']">
                                    <span v-if="saiuNoDia(a)" :title="`Saiu em ${fmtData(a.dt_saida)}`" class="inline-flex size-8 items-center justify-center rounded-md text-xs text-muted-foreground/40">—</span>
                                    <button
                                        v-else
                                        type="button"
                                        :disabled="!editavel"
                                        :title="presente(a.aln_id) ? 'Presente — clique p/ falta' : 'Falta — clique p/ presença'"
                                        :class="[
                                            'inline-flex size-8 items-center justify-center rounded-md text-xs font-bold transition disabled:opacity-50',
                                            status[`${dataSel}|${a.aln_id}`] === 'error' ? 'ring-2 ring-rose-400' : '',
                                            presente(a.aln_id)
                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-300 dark:ring-emerald-900'
                                                : 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
                                        ]"
                                        @click="toggle(a.aln_id)"
                                    >
                                        <Loader2 v-if="status[`${dataSel}|${a.aln_id}`] === 'saving'" class="size-3.5 animate-spin" />
                                        <template v-else>{{ presente(a.aln_id) ? 'P' : 'F' }}</template>
                                    </button>
                                </td>
                                <td :class="['border-b border-l px-2 py-2 text-center transition-colors', i % 2 ? 'bg-muted/20' : '', 'group-hover:bg-indigo-50/40 dark:group-hover:bg-indigo-950/20']">
                                    <span v-if="saiuNoDia(a)" :title="`Saiu em ${fmtData(a.dt_saida)}`" class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saiu</span>
                                    <span
                                        v-else
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            presente(a.aln_id)
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
                                        ]"
                                    >{{ presente(a.aln_id) ? 'Presente' : 'Ausente' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded bg-emerald-50 ring-1 ring-inset ring-emerald-200"></span> Presente</span>
                    <span aria-hidden="true">·</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded bg-rose-600"></span> Falta</span>
                    <span aria-hidden="true">·</span>
                    <span>clique alterna</span>
                    <span aria-hidden="true">·</span>
                    <span>✓/✕ no topo marca todos</span>
                    <span class="ml-auto">Presentes: {{ totalPresentes }} · Ausentes: {{ totalAusentes }}</span>
                </div>
            </template>
        </div>
    </section>
</template>
