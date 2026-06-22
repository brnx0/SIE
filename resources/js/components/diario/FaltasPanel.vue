<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Switch from '@/components/common/Switch.vue';
import { ChevronLeft, ChevronRight, Loader2, RefreshCw, Search, TriangleAlert } from 'lucide-vue-next';
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
    dt_saida: string | null;
}
interface Justificativa {
    aln_id: number;
    dt_inicio: string;
    dt_fim: string;
    motivo: string | null;
    abona: boolean;
}
interface Plano {
    dpa_id: number;
    dis_id: number;
    dt_inicio: string;
    dt_fim: string;
    conteudo: string | null;
    metodologia: string | null;
}
interface ConteudoInfo {
    conteudo: string;
    metodologia: string;
    executado: boolean;
    dpa_id: number | null;
}

const DIA_DOW: Record<string, number> = { dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 };
const DIA_LABEL: Record<string, string> = { dom: 'Dom', seg: 'Seg', ter: 'Ter', qua: 'Qua', qui: 'Qui', sex: 'Sex', sab: 'Sáb' };

const carregando = ref(true);
const recarregando = ref(false);
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

// Conteúdo/metodologia por (dia, disciplina): chave = `${dt}|${dis}`
const conteudoMap = reactive<Record<string, ConteudoInfo>>({});
const conteudoStatus = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});
const conteudoTimer: Record<string, number> = {};
const planos = ref<Plano[]>([]);
const justificativas = ref<Justificativa[]>([]);
// Sábados letivos do período (já filtrados pela escola da turma): cada um espelha um dia útil.
const sabadosLetivos = ref<{ dt: string; dia_ref: string }[]>([]);

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const k = (trh: number, dt: string, aln: number) => `${trh}|${dt}|${aln}`;

// silent = refresh manual: mantém a tabela visível (gira só o botão) em vez de
// trocar tudo por "Carregando...". Usado quando novos tempos são cadastrados.
const carregar = async (silent = false) => {
    if (silent) recarregando.value = true;
    else carregando.value = true;
    erro.value = null;
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
        // Só limpa/repopula após resposta OK — evita piscar a tabela no refresh
        // e preserva os dados atuais caso a recarga falhe.
        Object.keys(presencaMap).forEach((key) => delete presencaMap[key]);
        Object.keys(status).forEach((key) => delete status[key]);
        Object.keys(conteudoMap).forEach((key) => delete conteudoMap[key]);
        Object.keys(conteudoStatus).forEach((key) => delete conteudoStatus[key]);
        tempos.value = data.tempos ?? [];
        alunos.value = data.alunos ?? [];
        periodo.value = data.periodo ?? null;
        periodoAberto.value = data.periodo_aberto ?? false;
        turmaAberta.value = data.turma_aberta ?? false;
        for (const p of data.presencas ?? []) {
            presencaMap[k(p.trh_id, p.dt, p.aln_id)] = p.presente;
        }
        planos.value = data.planos ?? [];
        justificativas.value = data.justificativas ?? [];
        sabadosLetivos.value = data.sabados_letivos ?? [];
        for (const c of data.conteudos ?? []) {
            if (c.dis_id == null) continue;
            conteudoMap[`${c.dt}|${c.dis_id}`] = {
                conteudo: c.conteudo ?? '',
                metodologia: c.metodologia ?? '',
                executado: !!c.plano_executado,
                dpa_id: c.dpa_id ?? null,
            };
        }
        // Mantém o dia selecionado se ainda existir após a recarga; senão escolhe inicial.
        if (!dataSel.value || !diasComAula.value.some((d) => d.dt === dataSel.value)) {
            escolherDataInicial();
        }
    } finally {
        carregando.value = false;
        recarregando.value = false;
    }
};

onMounted(() => carregar());
watch(() => [props.turId, props.uniId], () => carregar());

// ── Datas com aula (dia-da-semana de algum tempo do professor) + sábados letivos ─
// diaCode = dia útil cujos tempos valem naquela data ('seg'..). No sábado letivo
// usa-se o dia que ele espelha.
interface DiaAula {
    dt: string;
    label: string;
    diaCode: string;
    sabado: boolean;
}
const diasComAula = computed<DiaAula[]>(() => {
    if (!periodo.value || !tempos.value.length) return [];
    const dows = new Set(tempos.value.map((t) => DIA_DOW[t.trh_dia]));
    const codes = new Set(tempos.value.map((t) => t.trh_dia));
    const out: DiaAula[] = [];
    const [yi, mi, di] = periodo.value.dt_inicio.split('-').map(Number);
    const [yf, mf, df] = periodo.value.dt_fim.split('-').map(Number);
    const cur = new Date(yi, mi - 1, di);
    const fim = new Date(yf, mf - 1, df);
    while (cur <= fim) {
        if (dows.has(cur.getDay())) {
            const dt = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`;
            const dia = Object.keys(DIA_DOW).find((key) => DIA_DOW[key] === cur.getDay())!;
            out.push({ dt, diaCode: dia, sabado: false, label: `${String(cur.getDate()).padStart(2, '0')}/${String(cur.getMonth() + 1).padStart(2, '0')} · ${DIA_LABEL[dia]}` });
        }
        cur.setDate(cur.getDate() + 1);
    }
    // Sábados letivos: só entram se a turma tem tempos no dia espelhado.
    for (const sl of sabadosLetivos.value) {
        if (!codes.has(sl.dia_ref)) continue;
        if (sl.dt < periodo.value.dt_inicio || sl.dt > periodo.value.dt_fim) continue;
        const [, mm, dd] = sl.dt.split('-');
        out.push({ dt: sl.dt, diaCode: sl.dia_ref, sabado: true, label: `${dd}/${mm} · Sáb (${DIA_LABEL[sl.dia_ref]})` });
    }
    return out.sort((a, b) => a.dt.localeCompare(b.dt));
});

const diaCodeDe = (dt: string): string | null => diasComAula.value.find((d) => d.dt === dt)?.diaCode ?? null;

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

const temposDoDia = computed(() => {
    const code = dataSel.value ? diaCodeDe(dataSel.value) : null;
    return code ? tempos.value.filter((t) => t.trh_dia === code) : [];
});

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

// ── Conteúdo / metodologia por disciplina ────────────────────────────────────
const ck = (dis: number) => `${dataSel.value}|${dis}`;
const SEM_INFO: ConteudoInfo = { conteudo: '', metodologia: '', executado: false, dpa_id: null };

// Disciplinas que o professor leciona no dia (1 bloco de registro cada).
const disciplinasDoDia = computed<{ dis_id: number; dis_nome: string }[]>(() => {
    const seen = new Map<number, string>();
    for (const t of temposDoDia.value) {
        if (t.pode_editar && t.trh_dis_id != null && !seen.has(t.trh_dis_id)) {
            seen.set(t.trh_dis_id, t.dis_nome ?? `Disciplina ${t.trh_dis_id}`);
        }
    }
    return [...seen].map(([dis_id, dis_nome]) => ({ dis_id, dis_nome }));
});

// Plano elegível (pendente/aprovado) que cobre a data selecionada p/ a disciplina.
const planoDoDia = (dis: number): Plano | null => {
    const dt = dataSel.value;
    return planos.value.find((p) => p.dis_id === dis && dt >= p.dt_inicio && dt <= p.dt_fim) ?? null;
};

// Leitura sem mutar (template); ensure() cria a entrada ao gravar.
const infoOf = (dis: number): ConteudoInfo => conteudoMap[ck(dis)] ?? SEM_INFO;
const ensure = (dis: number): ConteudoInfo => {
    const key = ck(dis);
    if (!conteudoMap[key]) conteudoMap[key] = { conteudo: '', metodologia: '', executado: false, dpa_id: null };
    return conteudoMap[key];
};

const getConteudo = (dis: number, campo: 'conteudo' | 'metodologia') => {
    const i = infoOf(dis);
    if (i.executado) return planoDoDia(dis)?.[campo] ?? i[campo] ?? '';
    return i[campo] ?? '';
};

const salvarConteudo = async (dis: number) => {
    if (!editavel.value || !dataSel.value) return;
    const key = ck(dis);
    const i = ensure(dis);
    conteudoStatus[key] = 'saving';
    try {
        const r = await fetch('/api/diario/faltas/conteudo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({
                tur_id: props.turId,
                uni_id: props.uniId,
                dis_id: dis,
                dt: dataSel.value,
                conteudo: i.conteudo,
                metodologia: i.metodologia,
                plano_executado: i.executado,
                dpa_id: i.dpa_id,
            }),
        });
        if (r.ok) {
            const j = await r.json();
            // espelha o snapshot devolvido (relevante quando executado)
            i.conteudo = j.conteudo ?? i.conteudo;
            i.metodologia = j.metodologia ?? i.metodologia;
            i.dpa_id = j.dpa_id ?? null;
            conteudoStatus[key] = 'saved';
        } else {
            conteudoStatus[key] = 'error';
        }
    } catch {
        conteudoStatus[key] = 'error';
    }
};

const setConteudo = (dis: number, campo: 'conteudo' | 'metodologia', val: string) => {
    const i = ensure(dis);
    if (i.executado) return; // travado quando planejamento executado
    i[campo] = val;
    conteudoStatus[ck(dis)] = 'idle';
    if (conteudoTimer[ck(dis)]) clearTimeout(conteudoTimer[ck(dis)]);
    conteudoTimer[ck(dis)] = window.setTimeout(() => salvarConteudo(dis), 1200);
};

const toggleExecutado = (dis: number, val: boolean) => {
    const i = ensure(dis);
    if (val) {
        const p = planoDoDia(dis);
        if (!p) return; // sem plano: toggle desabilitado
        i.executado = true;
        i.dpa_id = p.dpa_id;
        i.conteudo = p.conteudo ?? '';
        i.metodologia = p.metodologia ?? '';
    } else {
        i.executado = false;
        i.dpa_id = null;
    }
    salvarConteudo(dis);
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

// ── Justificativa de falta (intervalo por aluno cobre o dia) ─────────────────
const justificativaDe = (aln: number, dt: string): Justificativa | null => {
    if (!dt) return null;
    return justificativas.value.find((j) => j.aln_id === aln && dt >= j.dt_inicio && dt <= j.dt_fim) ?? null;
};
const diaJustificado = (aln: number) => !!justificativaDe(aln, dataSel.value);
// Célula é falta E está coberta por justificativa → exibe "FJ".
const cellJustificada = (t: Tempo, aln: number) => mostrarFalta(t, aln) && diaJustificado(aln);
const motivoCell = (aln: number) => justificativaDe(aln, dataSel.value)?.motivo ?? null;

const faltasDia = (aln: number) => temposDoDia.value.filter((t) => mostrarFalta(t, aln)).length;
const statusDia = (aln: number): 'presente' | 'parcial' | 'ausente' | 'justificada' => {
    const total = temposDoDia.value.length;
    const f = faltasDia(aln);
    if (f === 0) return 'presente';
    if (diaJustificado(aln)) return 'justificada'; // toda falta do dia está coberta pela justificativa
    if (f >= total) return 'ausente';
    return 'parcial';
};
const fmtData = (dt: string | null) => {
    if (!dt) return '';
    const [y, m, d] = dt.split('-');
    return `${d}/${m}/${y}`;
};
// Aluno saiu da turma antes do dia selecionado → lançamento bloqueado.
const saiuNoDia = (a: Aluno) => !!a.dt_saida && dataSel.value > a.dt_saida;
// Rótulo + classe do badge de situação.
const statusInfo = (aln: number): { label: string; cls: string } => {
    switch (statusDia(aln)) {
        case 'presente':
            return { label: 'Presente', cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' };
        case 'justificada':
            return { label: 'Justificada', cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300' };
        case 'parcial':
            return { label: 'Parcial', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' };
        default:
            return { label: 'Ausente', cls: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' };
    }
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
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    :disabled="carregando || recarregando"
                    title="Atualizar — recarrega tempos, alunos e lançamentos"
                    @click="carregar(true)"
                >
                    <RefreshCw :class="['size-4', recarregando && 'animate-spin']" />
                    Atualizar
                </Button>
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
                    <span v-if="!turmaAberta">Turma não está aberta. Frequência em modo leitura.</span>
                    <span v-else>Esta unidade está fechada para lançamento (fora da janela + extensão) — apenas consulta. Se houver outra unidade em andamento, selecione-a acima para lançar nela.</span>
                </div>

                <div v-if="!temposDoDia.length" class="py-10 text-center text-sm text-muted-foreground">Sem aula sua neste dia.</div>

                <template v-else>
                    <!-- Registro do dia por disciplina (conteúdo / metodologia) -->
                    <div v-if="disciplinasDoDia.length" class="mb-4 space-y-3">
                        <div v-for="d in disciplinasDoDia" :key="d.dis_id" class="rounded-lg border bg-background p-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold">{{ d.dis_nome }}</span>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex items-center gap-2 text-xs"
                                        :class="planoDoDia(d.dis_id) ? 'text-slate-700 dark:text-slate-200' : 'text-muted-foreground'"
                                        :title="planoDoDia(d.dis_id) ? 'Traz o conteúdo/metodologia do plano de aula' : 'Sem planejamento (pendente/aprovado) para esta data'"
                                    >
                                        <Switch
                                            :model-value="infoOf(d.dis_id).executado"
                                            :disabled="!editavel || !planoDoDia(d.dis_id)"
                                            @update:model-value="toggleExecutado(d.dis_id, $event)"
                                        />
                                        <span>Planejamento executado</span>
                                    </div>
                                    <span class="text-xs">
                                        <span v-if="conteudoStatus[`${dataSel}|${d.dis_id}`] === 'saving'" class="inline-flex items-center gap-1 text-amber-600"><Loader2 class="size-3.5 animate-spin" /> Salvando</span>
                                        <span v-else-if="conteudoStatus[`${dataSel}|${d.dis_id}`] === 'saved'" class="text-emerald-600">Salvo</span>
                                        <span v-else-if="conteudoStatus[`${dataSel}|${d.dis_id}`] === 'error'" class="text-rose-600">Erro ao salvar</span>
                                    </span>
                                </div>
                            </div>
                            <div v-if="infoOf(d.dis_id).executado" class="mb-2 inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                Conteúdo do plano de aula (somente leitura)
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="text-xs font-medium text-muted-foreground">Conteúdo</label>
                                    <textarea
                                        :value="getConteudo(d.dis_id, 'conteudo')"
                                        :readonly="infoOf(d.dis_id).executado"
                                        :disabled="!editavel"
                                        rows="2"
                                        placeholder="O que foi dado em aula..."
                                        class="mt-1 w-full resize-y rounded-md border bg-background px-2.5 py-1.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 read-only:cursor-default read-only:bg-muted/40 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                                        @input="setConteudo(d.dis_id, 'conteudo', ($event.target as HTMLTextAreaElement).value)"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-muted-foreground">Metodologia</label>
                                    <textarea
                                        :value="getConteudo(d.dis_id, 'metodologia')"
                                        :readonly="infoOf(d.dis_id).executado"
                                        :disabled="!editavel"
                                        rows="2"
                                        placeholder="Como foi trabalhado..."
                                        class="mt-1 w-full resize-y rounded-md border bg-background px-2.5 py-1.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 read-only:cursor-default read-only:bg-muted/40 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                                        @input="setConteudo(d.dis_id, 'metodologia', ($event.target as HTMLTextAreaElement).value)"
                                    ></textarea>
                                </div>
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
                                                <div v-if="a.dt_saida" class="mt-0.5 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saída {{ fmtData(a.dt_saida) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td v-for="t in temposDoDia" :key="t.trh_id" :class="['border-b border-l px-2 py-2 text-center transition-colors', i % 2 ? 'bg-muted/20' : '', 'group-hover:bg-indigo-50/40 dark:group-hover:bg-indigo-950/20']">
                                        <span v-if="saiuNoDia(a)" :title="`Aluno saiu em ${fmtData(a.dt_saida)} — bloqueado`" class="inline-flex size-8 items-center justify-center rounded-md text-xs text-muted-foreground/40">·</span>
                                        <template v-else>
                                        <button
                                            v-if="t.pode_editar"
                                            type="button"
                                            :disabled="!editavel"
                                            :title="
                                                presente(t.trh_id, a.aln_id)
                                                    ? 'Presente — clique p/ falta'
                                                    : cellJustificada(t, a.aln_id)
                                                      ? 'Falta justificada' + (motivoCell(a.aln_id) ? ' — ' + motivoCell(a.aln_id) : '') + ' · clique p/ presença'
                                                      : 'Falta — clique p/ presença'
                                            "
                                            :class="[
                                                'inline-flex size-8 items-center justify-center rounded-md text-xs font-bold transition disabled:opacity-50',
                                                status[k(t.trh_id, dataSel, a.aln_id)] === 'error' ? 'ring-2 ring-rose-400' : '',
                                                presente(t.trh_id, a.aln_id)
                                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-300 dark:ring-emerald-900'
                                                    : cellJustificada(t, a.aln_id)
                                                      ? 'bg-indigo-100 text-indigo-700 ring-1 ring-inset ring-indigo-300 hover:bg-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:ring-indigo-900'
                                                      : 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
                                            ]"
                                            @click="toggle(t.trh_id, a.aln_id)"
                                        >
                                            <Loader2 v-if="status[k(t.trh_id, dataSel, a.aln_id)] === 'saving'" class="size-3.5 animate-spin" />
                                            <template v-else>{{ presente(t.trh_id, a.aln_id) ? 'P' : cellJustificada(t, a.aln_id) ? 'FJ' : 'F' }}</template>
                                        </button>
                                        <span
                                            v-else
                                            :title="
                                                cellJustificada(t, a.aln_id)
                                                    ? 'Falta justificada' + (motivoCell(a.aln_id) ? ' — ' + motivoCell(a.aln_id) : '')
                                                    : `Tempo de ${t.fun_nome ?? 'outro professor'} (somente leitura)`
                                            "
                                            :class="[
                                                'inline-flex size-8 items-center justify-center rounded-md text-xs font-semibold',
                                                reg(t.trh_id, a.aln_id) === false
                                                    ? cellJustificada(t, a.aln_id)
                                                        ? 'bg-indigo-100 text-indigo-700 ring-1 ring-inset ring-indigo-300 dark:bg-indigo-950/40 dark:text-indigo-300'
                                                        : 'bg-rose-100 text-rose-700 ring-1 ring-inset ring-rose-200 dark:bg-rose-950/30 dark:text-rose-300'
                                                    : reg(t.trh_id, a.aln_id) === true
                                                      ? 'bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/20'
                                                      : 'text-muted-foreground/60',
                                            ]"
                                        >
                                            {{ reg(t.trh_id, a.aln_id) === false ? (cellJustificada(t, a.aln_id) ? 'FJ' : 'F') : reg(t.trh_id, a.aln_id) === true ? 'P' : '—' }}
                                        </span>
                                        </template>
                                    </td>
                                    <td :class="['border-b border-l px-2 py-2 text-center transition-colors', i % 2 ? 'bg-muted/20' : '', 'group-hover:bg-indigo-50/40 dark:group-hover:bg-indigo-950/20']">
                                        <span v-if="saiuNoDia(a)" :title="`Saiu em ${fmtData(a.dt_saida)}`" class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saiu</span>
                                        <span v-else :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold', statusInfo(a.aln_id).cls]">
                                            {{ statusInfo(a.aln_id).label }}
                                            <span v-if="faltasDia(a.aln_id)" class="tabular-nums">· {{ faltasDia(a.aln_id) }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded bg-emerald-50 ring-1 ring-inset ring-emerald-200"></span> Presente</span>
                        <span>·</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded bg-rose-600"></span> Falta</span>
                        <span>·</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-flex size-3.5 items-center justify-center rounded bg-indigo-100 text-[7px] font-bold leading-none text-indigo-700 ring-1 ring-inset ring-indigo-300 dark:bg-indigo-950/40 dark:text-indigo-300">FJ</span> Falta justificada</span>
                        <span>· clique alterna · ✓/✕ no topo marca a coluna inteira.</span>
                    </p>
                </template>
            </template>
        </div>
    </section>
</template>
