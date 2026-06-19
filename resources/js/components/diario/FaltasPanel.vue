<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, Loader2, Search, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    anlId: number;
    escId: number;
    turId: number;
    uniId: number;
}>();

interface Tempo {
    trh_id: number;
    trh_dia: string;
    trh_tempo: number;
    trh_hora: string | null;
    trh_dis_id: number | null;
    dis_nome: string | null;
    fun_nome: string | null;
    pode_editar: boolean;
}
interface Aluno {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
}

const DIA_DOW: Record<string, number> = { dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 };
const DIA_LABEL: Record<string, string> = { dom: 'Dom', seg: 'Seg', ter: 'Ter', qua: 'Qua', qui: 'Qui', sex: 'Sex', sab: 'Sáb' };

const carregando = ref(true);
const erro = ref<string | null>(null);
const tempos = ref<Tempo[]>([]);
const alunos = ref<Aluno[]>([]);
const periodo = ref<{ dt_inicio: string; dt_fim: string } | null>(null);
const periodoAberto = ref(true);
const turmaAberta = ref(true);
const dataSel = ref<string>('');
const busca = ref('');

// presença: `${trh}|${dt}|${aln}` → bool · status idem
const presencaMap = reactive<Record<string, boolean>>({});
const status = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});

// Conteúdo/metodologia por DIA (1 por dia, não por tempo): chave = dt
const conteudoDia = reactive<Record<string, { conteudo: string; metodologia: string }>>({});
const conteudoStatus = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});
const conteudoTimer: Record<string, number> = {};

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const k = (trh: number, dt: string, aln: number) => `${trh}|${dt}|${aln}`;

const carregar = async () => {
    carregando.value = true;
    erro.value = null;
    Object.keys(presencaMap).forEach((key) => delete presencaMap[key]);
    Object.keys(status).forEach((key) => delete status[key]);
    Object.keys(conteudoDia).forEach((key) => delete conteudoDia[key]);
    Object.keys(conteudoStatus).forEach((key) => delete conteudoStatus[key]);
    try {
        const url = new URL('/api/diario/faltas/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        url.searchParams.set('uni_id', String(props.uniId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) {
            erro.value = 'Não foi possível carregar a frequência.';
            return;
        }
        const data = await r.json();
        tempos.value = data.tempos ?? [];
        alunos.value = data.alunos ?? [];
        periodo.value = data.periodo ?? null;
        periodoAberto.value = data.periodo_aberto ?? false;
        turmaAberta.value = data.turma_aberta ?? false;
        for (const p of data.presencas ?? []) {
            presencaMap[k(p.trh_id, p.dt, p.aln_id)] = p.presente;
        }
        for (const c of data.conteudos ?? []) {
            conteudoDia[c.dt] = { conteudo: c.conteudo ?? '', metodologia: c.metodologia ?? '' };
        }
        escolherDataInicial();
    } finally {
        carregando.value = false;
    }
};

onMounted(carregar);
watch(() => [props.turId, props.uniId], carregar);

// ── Datas com aula (dia-da-semana de algum tempo do professor) ───────────────
const diasComAula = computed<{ dt: string; label: string }[]>(() => {
    if (!periodo.value || !tempos.value.length) return [];
    const dows = new Set(tempos.value.map((t) => DIA_DOW[t.trh_dia]));
    const out: { dt: string; label: string }[] = [];
    const [yi, mi, di] = periodo.value.dt_inicio.split('-').map(Number);
    const [yf, mf, df] = periodo.value.dt_fim.split('-').map(Number);
    const cur = new Date(yi, mi - 1, di);
    const fim = new Date(yf, mf - 1, df);
    while (cur <= fim) {
        if (dows.has(cur.getDay())) {
            const dt = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`;
            const dia = Object.keys(DIA_DOW).find((key) => DIA_DOW[key] === cur.getDay())!;
            out.push({ dt, label: `${String(cur.getDate()).padStart(2, '0')}/${String(cur.getMonth() + 1).padStart(2, '0')} · ${DIA_LABEL[dia]}` });
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
    const dias = diasComAula.value;
    if (!dias.length) {
        dataSel.value = '';
        return;
    }
    const hoje = hojeStr();
    const passados = dias.filter((d) => d.dt <= hoje);
    dataSel.value = (passados.length ? passados[passados.length - 1] : dias[0]).dt;
};

const dowDe = (dt: string) => {
    const [y, m, d] = dt.split('-').map(Number);
    return new Date(y, m - 1, d).getDay();
};
const temposDoDia = computed(() => (dataSel.value ? tempos.value.filter((t) => DIA_DOW[t.trh_dia] === dowDe(dataSel.value)) : []));

const idxData = computed(() => diasComAula.value.findIndex((d) => d.dt === dataSel.value));
const irData = (delta: number) => {
    const i = idxData.value + delta;
    if (i >= 0 && i < diasComAula.value.length) dataSel.value = diasComAula.value[i].dt;
};

const editavel = computed(() => periodoAberto.value && turmaAberta.value);
const presente = (trh: number, aln: number) => {
    const key = k(trh, dataSel.value, aln);
    return key in presencaMap ? presencaMap[key] : true; // default presente
};

const marcar = async (trh: number, aln: number, val: boolean) => {
    if (!editavel.value) return;
    const key = k(trh, dataSel.value, aln);
    presencaMap[key] = val;
    status[key] = 'saving';
    try {
        const r = await fetch('/api/diario/faltas/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, uni_id: props.uniId, trh_id: trh, aln_id: aln, dt: dataSel.value, presente: val }),
        });
        status[key] = r.ok ? 'saved' : 'error';
    } catch {
        status[key] = 'error';
    }
};

const marcarLote = async (trh: number, val: boolean) => {
    if (!editavel.value) return;
    for (const a of alunos.value) presencaMap[k(trh, dataSel.value, a.aln_id)] = val;
    try {
        const r = await fetch('/api/diario/faltas/lote', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, uni_id: props.uniId, trh_id: trh, dt: dataSel.value, presente: val }),
        });
        if (!r.ok) erro.value = 'Não foi possível salvar em lote.';
    } catch {
        erro.value = 'Não foi possível salvar em lote.';
    }
};

const alunosFiltrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter((a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q));
});

const toggle = (trh: number, aln: number) => marcar(trh, aln, !presente(trh, aln));

// ── Conteúdo / metodologia do dia (1 por dia) ────────────────────────────────
const getDia = (campo: 'conteudo' | 'metodologia') => conteudoDia[dataSel.value]?.[campo] ?? '';
const salvarConteudo = async () => {
    if (!editavel.value || !dataSel.value) return;
    const dt = dataSel.value;
    const info = conteudoDia[dt] ?? { conteudo: '', metodologia: '' };
    conteudoStatus[dt] = 'saving';
    try {
        const r = await fetch('/api/diario/faltas/conteudo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, uni_id: props.uniId, dt, conteudo: info.conteudo, metodologia: info.metodologia }),
        });
        conteudoStatus[dt] = r.ok ? 'saved' : 'error';
    } catch {
        conteudoStatus[dt] = 'error';
    }
};
const setDia = (campo: 'conteudo' | 'metodologia', val: string) => {
    const dt = dataSel.value;
    if (!conteudoDia[dt]) conteudoDia[dt] = { conteudo: '', metodologia: '' };
    conteudoDia[dt][campo] = val;
    conteudoStatus[dt] = 'idle';
    if (conteudoTimer[dt]) clearTimeout(conteudoTimer[dt]);
    conteudoTimer[dt] = window.setTimeout(salvarConteudo, 1200);
};
const tempoLabel = (t: Tempo) => `${t.trh_tempo}º tempo${t.dis_nome ? ' · ' + t.dis_nome : ''}${t.trh_hora ? ' · ' + t.trh_hora.substring(0, 5) : ''}`;
// Primeiro + último nome do professor (ex.: "BRENO JESUS").
const nomeCurto = (nome: string | null) => {
    if (!nome) return '—';
    const p = nome.trim().split(/\s+/).filter(Boolean);
    return p.length <= 1 ? (p[0] ?? '—') : `${p[0]} ${p[p.length - 1]}`;
};
// Registro bruto: true/false se lançado; null se não há registro.
const reg = (trh: number, aln: number): boolean | null => {
    const key = k(trh, dataSel.value, aln);
    return key in presencaMap ? presencaMap[key] : null;
};
// Tempo editável: sem registro assume presente. Tempo de outro professor: só o que foi lançado.
const mostrarFalta = (t: Tempo, aln: number) => (t.pode_editar ? !presente(t.trh_id, aln) : reg(t.trh_id, aln) === false);
const faltasDia = (aln: number) => temposDoDia.value.filter((t) => mostrarFalta(t, aln)).length;
const statusDia = (aln: number): 'presente' | 'parcial' | 'ausente' => {
    const total = temposDoDia.value.length;
    const f = faltasDia(aln);
    if (f === 0) return 'presente';
    if (f >= total) return 'ausente';
    return 'parcial';
};
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <!-- Cabeçalho -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-card/95 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Frequência</h2>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="busca"
                        type="text"
                        placeholder="Filtrar aluno..."
                        class="h-9 w-44 rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>
                <template v-if="diasComAula.length">
                    <Button variant="outline" size="sm" :disabled="idxData <= 0" @click="irData(-1)"><ChevronLeft class="size-4" /></Button>
                    <select v-model="dataSel" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="d in diasComAula" :key="d.dt" :value="d.dt">{{ d.label }}</option>
                    </select>
                    <Button variant="outline" size="sm" :disabled="idxData >= diasComAula.length - 1" @click="irData(1)"><ChevronRight class="size-4" /></Button>
                </template>
            </div>
        </div>

        <div class="p-4">
            <div v-if="carregando" class="py-12 text-center text-sm text-muted-foreground">Carregando...</div>
            <div v-else-if="erro" class="py-12 text-center text-sm text-rose-600">{{ erro }}</div>
            <div v-else-if="!tempos.length" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>Você não possui tempos no quadro de horário desta turma. Configure o quadro de horário para lançar frequência.</span>
            </div>
            <div v-else-if="!alunos.length" class="py-12 text-center text-sm text-muted-foreground">Nenhum aluno ativo nesta turma.</div>

            <template v-else>
                <div v-if="!editavel" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ !turmaAberta ? 'Turma não está aberta.' : 'Fora do período de lançamento.' }} Frequência em modo leitura.</span>
                </div>

                <div v-if="!temposDoDia.length" class="py-10 text-center text-sm text-muted-foreground">Sem aula sua neste dia.</div>

                <template v-else>
                    <!-- Registro do dia (conteúdo / metodologia) — 1 por dia -->
                    <div class="mb-4 rounded-lg border bg-background p-3">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold">Registro do dia</span>
                            <span class="text-xs">
                                <span v-if="conteudoStatus[dataSel] === 'saving'" class="inline-flex items-center gap-1 text-amber-600"><Loader2 class="size-3.5 animate-spin" /> Salvando</span>
                                <span v-else-if="conteudoStatus[dataSel] === 'saved'" class="text-emerald-600">Salvo</span>
                                <span v-else-if="conteudoStatus[dataSel] === 'error'" class="text-rose-600">Erro ao salvar</span>
                            </span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-muted-foreground">Conteúdo</label>
                                <textarea
                                    :value="getDia('conteudo')"
                                    :disabled="!editavel"
                                    maxlength="255"
                                    rows="2"
                                    placeholder="O que foi dado em aula..."
                                    class="mt-1 w-full resize-y rounded-md border bg-background px-2.5 py-1.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                                    @input="setDia('conteudo', ($event.target as HTMLTextAreaElement).value)"
                                ></textarea>
                                <div class="mt-0.5 text-right text-[10px] text-muted-foreground">{{ getDia('conteudo').length }}/255</div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-muted-foreground">Metodologia</label>
                                <textarea
                                    :value="getDia('metodologia')"
                                    :disabled="!editavel"
                                    maxlength="255"
                                    rows="2"
                                    placeholder="Como foi trabalhado..."
                                    class="mt-1 w-full resize-y rounded-md border bg-background px-2.5 py-1.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                                    @input="setDia('metodologia', ($event.target as HTMLTextAreaElement).value)"
                                ></textarea>
                                <div class="mt-0.5 text-right text-[10px] text-muted-foreground">{{ getDia('metodologia').length }}/255</div>
                            </div>
                        </div>
                    </div>

                    <!-- Matriz alunos × tempos do dia (clique na célula alterna P/F) -->
                    <div class="max-h-[70vh] overflow-auto rounded-lg border">
                        <table class="w-full border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 top-0 z-30 min-w-[220px] border-b bg-muted/90 px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur">
                                        Aluno
                                    </th>
                                    <th v-for="t in temposDoDia" :key="t.trh_id" :class="['sticky top-0 z-20 border-b border-l px-2 py-1 text-center align-top backdrop-blur', t.pode_editar ? 'bg-muted/90' : 'bg-muted/50']">
                                        <div class="mx-auto flex w-24 items-center justify-center gap-1 truncate text-xs font-semibold text-slate-700 dark:text-slate-200" :title="tempoLabel(t) + (t.fun_nome ? ' · ' + t.fun_nome : '')">
                                            <span>{{ t.trh_tempo }}º<span v-if="t.dis_nome"> · {{ t.dis_nome }}</span></span>
                                            <template v-if="t.pode_editar">
                                                <button type="button" :disabled="!editavel" title="Todos presentes" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 disabled:opacity-40" @click="marcarLote(t.trh_id, true)">✓</button>
                                                <button type="button" :disabled="!editavel" title="Todos ausentes" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 disabled:opacity-40" @click="marcarLote(t.trh_id, false)">✕</button>
                                            </template>
                                        </div>
                                        <div class="mx-auto w-24 truncate text-[10px] font-normal text-muted-foreground" :title="t.fun_nome ?? ''">
                                            {{ nomeCurto(t.fun_nome) }}<span v-if="!t.pode_editar"> · leitura</span>
                                        </div>
                                    </th>
                                    <th class="sticky top-0 z-20 border-b border-l bg-muted/90 px-2 py-1.5 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!alunosFiltrados.length">
                                    <td :colspan="temposDoDia.length + 2" class="px-4 py-8 text-center text-sm text-muted-foreground">Nenhum aluno encontrado.</td>
                                </tr>
                                <tr v-for="(a, i) in alunosFiltrados" :key="a.aln_id" class="group">
                                    <td :class="['sticky left-0 z-10 border-b px-3 py-2 transition-colors', i % 2 ? 'bg-muted/30' : 'bg-card', 'group-hover:bg-indigo-50/70 dark:group-hover:bg-indigo-950/40']">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 shrink-0 text-right text-[11px] tabular-nums text-muted-foreground">{{ i + 1 }}</span>
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-medium text-slate-900 dark:text-slate-50">{{ a.aln_nome }}</div>
                                                <div v-if="a.aln_nr_matricula" class="text-[10px] text-muted-foreground">Mat. {{ a.aln_nr_matricula }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td v-for="t in temposDoDia" :key="t.trh_id" :class="['border-b border-l px-2 py-2 text-center transition-colors', i % 2 ? 'bg-muted/20' : '', 'group-hover:bg-indigo-50/40 dark:group-hover:bg-indigo-950/20']">
                                        <button
                                            v-if="t.pode_editar"
                                            type="button"
                                            :disabled="!editavel"
                                            :title="presente(t.trh_id, a.aln_id) ? 'Presente — clique p/ falta' : 'Falta — clique p/ presença'"
                                            :class="[
                                                'inline-flex size-8 items-center justify-center rounded-md text-xs font-bold transition disabled:opacity-50',
                                                status[k(t.trh_id, dataSel, a.aln_id)] === 'error' ? 'ring-2 ring-rose-400' : '',
                                                presente(t.trh_id, a.aln_id)
                                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-300 dark:ring-emerald-900'
                                                    : 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
                                            ]"
                                            @click="toggle(t.trh_id, a.aln_id)"
                                        >
                                            <Loader2 v-if="status[k(t.trh_id, dataSel, a.aln_id)] === 'saving'" class="size-3.5 animate-spin" />
                                            <template v-else>{{ presente(t.trh_id, a.aln_id) ? 'P' : 'F' }}</template>
                                        </button>
                                        <span
                                            v-else
                                            :title="`Tempo de ${t.fun_nome ?? 'outro professor'} (somente leitura)`"
                                            :class="[
                                                'inline-flex size-8 items-center justify-center rounded-md text-xs font-semibold',
                                                reg(t.trh_id, a.aln_id) === false
                                                    ? 'bg-rose-100 text-rose-700 ring-1 ring-inset ring-rose-200 dark:bg-rose-950/30 dark:text-rose-300'
                                                    : reg(t.trh_id, a.aln_id) === true
                                                      ? 'bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/20'
                                                      : 'text-muted-foreground/60',
                                            ]"
                                        >
                                            {{ reg(t.trh_id, a.aln_id) === false ? 'F' : reg(t.trh_id, a.aln_id) === true ? 'P' : '—' }}
                                        </span>
                                    </td>
                                    <td :class="['border-b border-l px-2 py-2 text-center transition-colors', i % 2 ? 'bg-muted/20' : '', 'group-hover:bg-indigo-50/40 dark:group-hover:bg-indigo-950/20']">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                statusDia(a.aln_id) === 'presente'
                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                    : statusDia(a.aln_id) === 'parcial'
                                                      ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                      : 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
                                            ]"
                                        >
                                            {{ statusDia(a.aln_id) === 'presente' ? 'Presente' : statusDia(a.aln_id) === 'parcial' ? 'Parcial' : 'Ausente' }}
                                            <span v-if="faltasDia(a.aln_id)" class="tabular-nums">· {{ faltasDia(a.aln_id) }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded bg-emerald-50 ring-1 ring-inset ring-emerald-200"></span> Presente</span>
                        ·
                        <span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded bg-rose-600"></span> Falta</span>
                        · clique alterna · ✓/✕ no topo marca a coluna inteira.
                    </p>
                </template>
            </template>
        </div>
    </section>
</template>
