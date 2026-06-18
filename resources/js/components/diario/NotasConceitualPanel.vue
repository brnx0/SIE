<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CheckCircle2, Loader2, Pencil, Plus, RefreshCw, Search, TriangleAlert, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    anlId: number;
    escId: number;
    turId: number;
    disId: number;
    uniId: number;
}>();

const TIPO = 'conceitual';

interface Instrumento { iav_id: number; iav_nome: string; iav_fl_recuperacao: boolean }
interface Conceito {
    cnc_id: number;
    cnc_sigla: string;
    cnc_descricao: string;
    cnc_limite_inferior: string | number;
    cnc_limite_superior: string | number;
    cnc_peso: number;
}
interface Avaliacao {
    ava_id: number;
    ava_iav_id: number | null;
    iav_nome: string | null;
    ava_descricao: string | null;
    ava_dt: string;
    ava_valor: number | null;
    ava_fl_recuperacao: boolean;
}
interface NotaCell { valor: number | null; cnc_id: number | null }
interface AlunoRow {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    notas: Record<number, NotaCell>;
    bloqueado: boolean;
}

const carregando = ref(true);
const erro = ref<string | null>(null);
const periodoAberto = ref(true);
const turmaAberta = ref(true);
const tipoDisponivel = ref(true);
const modo = ref<'faixa' | 'conceito'>('faixa');
const avaliacoes = ref<Avaliacao[]>([]);
const instrumentos = ref<Instrumento[]>([]);
const conceitos = ref<Conceito[]>([]);
const rows = ref<AlunoRow[]>([]);
const busca = ref('');

type Status = 'idle' | 'dirty' | 'saving' | 'saved' | 'error' | 'invalid';
const cell = reactive<Record<string, { status: Status; timer: number | null }>>({});
const ck = (aln: number, ava: number) => `${aln}:${ava}`;
const statusDe = (aln: number, ava: number): Status => cell[ck(aln, ava)]?.status ?? 'idle';
// Garante a entrada e devolve o proxy reativo (não o objeto cru).
const ensureCell = (key: string) => {
    if (!cell[key]) cell[key] = { status: 'idle', timer: null };
    return cell[key];
};

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};

const carregar = async () => {
    carregando.value = true;
    erro.value = null;
    try {
        const url = new URL('/api/diario/notas/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        url.searchParams.set('dis_id', String(props.disId));
        url.searchParams.set('uni_id', String(props.uniId));
        url.searchParams.set('tipo', TIPO);
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) { erro.value = 'Não foi possível carregar as avaliações.'; return; }
        const data = await r.json();
        tipoDisponivel.value = data.tipo_disponivel ?? false;
        periodoAberto.value = data.periodo_aberto ?? false;
        turmaAberta.value = data.turma_aberta ?? true;
        modo.value = data.modo === 'conceito' ? 'conceito' : 'faixa';
        avaliacoes.value = data.avaliacoes ?? [];
        instrumentos.value = data.instrumentos ?? [];
        conceitos.value = data.conceitos ?? [];
        rows.value = ((data.alunos ?? []) as any[]).map((a) => ({
            aln_id: a.aln_id,
            aln_nome: a.aln_nome,
            aln_nr_matricula: a.aln_nr_matricula,
            notas: Object.fromEntries(
                Object.entries(a.notas ?? {}).map(([k, v]) => [k, { valor: (v as any)?.valor ?? null, cnc_id: (v as any)?.cnc_id ?? null }]),
            ),
            bloqueado: a.bloqueado ?? false,
        }));
        for (const k of Object.keys(cell)) delete cell[k];
    } catch {
        erro.value = 'Falha de comunicação ao carregar.';
    } finally {
        carregando.value = false;
    }
};

onMounted(carregar);
watch(() => [props.turId, props.disId, props.uniId], carregar);

// ── Datas / colunas ──────────────────────────────────────────────────────────
const hojeStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const isFutura = (a: Avaliacao) => (a.ava_dt?.substring(0, 10) ?? '') > hojeStr();
const fmtDate = (d: string) => {
    if (!d) return '';
    const [, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}`;
};

// Modo faixa exige valor na avaliação; sem valor não dá pra lançar nota.
const semValor = (a: Avaliacao) => modo.value === 'faixa' && (a.ava_valor === null || Number(a.ava_valor) <= 0);

const regulares = computed(() => avaliacoes.value.filter((a) => !a.ava_fl_recuperacao));
const recuperacoes = computed(() => avaliacoes.value.filter((a) => a.ava_fl_recuperacao));
const colunas = computed(() => [...regulares.value, ...recuperacoes.value]);
const somaValores = computed(() => regulares.value.reduce((s, a) => s + (Number(a.ava_valor) || 0), 0));
const valorDisponivel = computed(() => Math.max(0, Math.round((10 - somaValores.value) * 100) / 100));

// ── Cálculo do conceito ──────────────────────────────────────────────────────
// Arredonda meio-pra-cima para 1 casa decimal (granularidade das faixas).
const round1 = (n: number) => Math.round(n * 10) / 10;

// Faixa do total: maior conceito cujo limite inferior ≤ total (faixas contíguas, sem vãos).
const faixaDe = (total: number): Conceito | null => {
    const lista = [...conceitos.value].sort((a, b) => Number(a.cnc_limite_inferior) - Number(b.cnc_limite_inferior));
    let res: Conceito | null = null;
    for (const c of lista) {
        if (total >= Number(c.cnc_limite_inferior)) res = c;
    }
    return res ?? lista[0] ?? null;
};
const conceitoPorId = (id: number | null): Conceito | null =>
    id ? conceitos.value.find((c) => c.cnc_id === id) ?? null : null;
const pesoPorId = (id: number | null): number => conceitoPorId(id)?.cnc_peso ?? 0;
// Conceito cujo peso é o mais próximo do alvo (mapeia média de pesos → conceito).
const conceitoPorPeso = (peso: number): Conceito | null => {
    if (!conceitos.value.length) return null;
    return conceitos.value.reduce((best, c) =>
        Math.abs(c.cnc_peso - peso) < Math.abs(best.cnc_peso - peso) ? c : best, conceitos.value[0]);
};

const conceitoBase = (row: AlunoRow): Conceito | null => {
    const regs = regulares.value.filter((a) => !isFutura(a));
    if (!regs.length) return null;
    if (modo.value === 'faixa') {
        const total = round1(regs.reduce((s, a) => s + (Number(row.notas[a.ava_id]?.valor) || 0), 0));
        return faixaDe(total);
    }
    // modo conceito: conta por conceito; moda única vence; empate → média dos pesos (arredondada).
    const counts = new Map<number, number>();
    const pesos: number[] = [];
    for (const a of regs) {
        const cid = row.notas[a.ava_id]?.cnc_id;
        if (cid) {
            counts.set(cid, (counts.get(cid) ?? 0) + 1);
            pesos.push(pesoPorId(cid));
        }
    }
    if (!counts.size) return null;
    const max = Math.max(...counts.values());
    const tied = [...counts.entries()].filter(([, c]) => c === max).map(([cid]) => cid);
    if (tied.length === 1) {
        return conceitoPorId(tied[0]);
    }
    // empate na frequência → média dos pesos de TODAS as avaliações, arredondada.
    const media = pesos.reduce((s, p) => s + p, 0) / pesos.length;
    return conceitoPorPeso(Math.round(media));
};

const conceitoRecuperacao = (row: AlunoRow): Conceito | null => {
    let best: Conceito | null = null;
    for (const a of recuperacoes.value) {
        if (isFutura(a)) continue;
        let c: Conceito | null = null;
        if (modo.value === 'faixa') {
            const v = row.notas[a.ava_id]?.valor;
            if (v !== null && v !== undefined) c = faixaDe(round1(Number(v)));
        } else {
            c = conceitoPorId(row.notas[a.ava_id]?.cnc_id ?? null);
        }
        if (c && (!best || c.cnc_peso > best.cnc_peso)) best = c;
    }
    return best;
};

const resultado = (row: AlunoRow): Conceito | null => {
    const base = conceitoBase(row);
    const rec = conceitoRecuperacao(row);
    // Recuperação sobrescreve só se for melhor.
    if (rec && (!base || rec.cnc_peso > base.cnc_peso)) return rec;
    return base;
};

// Valor numérico exibido junto do conceito (só no modo faixa), coerente com a recuperação.
const valorFaixa = (row: AlunoRow): number | null => {
    if (modo.value !== 'faixa') return null;
    const regs = regulares.value.filter((a) => !isFutura(a));
    const base = round1(regs.reduce((s, a) => s + (Number(row.notas[a.ava_id]?.valor) || 0), 0));
    const baseConc = regs.length ? faixaDe(base) : null;

    let recConc: Conceito | null = null;
    let recVal: number | null = null;
    for (const a of recuperacoes.value) {
        if (isFutura(a)) continue;
        const v = row.notas[a.ava_id]?.valor;
        if (v !== null && v !== undefined) {
            const valor = round1(Number(v));
            const c = faixaDe(valor);
            if (c && (!recConc || c.cnc_peso > recConc.cnc_peso)) { recConc = c; recVal = valor; }
        }
    }
    if (recConc && (!baseConc || recConc.cnc_peso > baseConc.cnc_peso)) return recVal;
    return regs.length ? base : null;
};

const filtrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return rows.value;
    return rows.value.filter((a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q));
});

// ── Autosave ─────────────────────────────────────────────────────────────────
const salvarNota = async (row: AlunoRow, ava: Avaliacao) => {
    const key = ck(row.aln_id, ava.ava_id);
    const st = ensureCell(key);
    if (st.timer) { clearTimeout(st.timer); st.timer = null; }
    if (!periodoAberto.value || !turmaAberta.value || isFutura(ava) || row.bloqueado || semValor(ava)) return;

    const body: Record<string, unknown> = { nta_ava_id: ava.ava_id, nta_aln_id: row.aln_id };
    if (modo.value === 'conceito') {
        body.nta_cnc_id = row.notas[ava.ava_id]?.cnc_id || null;
    } else {
        const raw = row.notas[ava.ava_id]?.valor;
        const valor = raw === null || (raw as any) === '' ? null : Number(raw);
        if (valor !== null && (Number.isNaN(valor) || valor < 0 || valor > Number(ava.ava_valor))) {
            st.status = 'invalid';
            return;
        }
        body.nta_valor = valor;
    }

    st.status = 'saving';
    try {
        const r = await fetch('/api/diario/notas/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        if (!r.ok) {
            const j = await r.json().catch(() => ({}));
            st.status = 'error';
            if (j?.message || j?.errors) erroLinha.value = j?.errors?.nta_valor?.[0] ?? j?.message ?? null;
            return;
        }
        erroLinha.value = null;
        st.status = 'saved';
    } catch {
        st.status = 'error';
    }
};

const erroLinha = ref<string | null>(null);

const aoDigitarNota = (row: AlunoRow, ava: Avaliacao) => {
    if (!periodoAberto.value || !turmaAberta.value || isFutura(ava) || row.bloqueado || semValor(ava)) return;
    const key = ck(row.aln_id, ava.ava_id);
    const st = ensureCell(key);
    st.status = 'dirty';
    if (st.timer) clearTimeout(st.timer);
    st.timer = window.setTimeout(() => salvarNota(row, ava), 1000);
};

// ── Modal de avaliação ───────────────────────────────────────────────────────
const showModal = ref(false);
const editId = ref<number | null>(null);
const enviandoAval = ref(false);
const erroAval = ref<string | null>(null);
const form = reactive({ iavId: null as number | null, descricao: '', dt: '', valor: '' as number | string });

const instrumentoSelecionado = computed(() => instrumentos.value.find((i) => i.iav_id === Number(form.iavId)));
const recuperacaoSelecionada = computed(() => !!instrumentoSelecionado.value?.iav_fl_recuperacao);

const abrirNovo = () => {
    editId.value = null;
    erroAval.value = null;
    form.iavId = null; form.descricao = ''; form.dt = ''; form.valor = '';
    showModal.value = true;
};
const abrirEdit = (a: Avaliacao) => {
    editId.value = a.ava_id;
    erroAval.value = null;
    form.iavId = a.ava_iav_id;
    form.descricao = a.ava_descricao ?? '';
    form.dt = a.ava_dt;
    form.valor = a.ava_valor === null ? '' : Number(a.ava_valor);
    showModal.value = true;
};

const salvarAvaliacao = async () => {
    enviandoAval.value = true;
    erroAval.value = null;
    const payload: Record<string, unknown> = {
        ava_esc_id: props.escId,
        ava_anl_id: props.anlId,
        ava_tur_id: props.turId,
        ava_dis_id: props.disId,
        ava_uni_id: props.uniId,
        ava_iav_id: form.iavId,
        ava_tipo: TIPO,
        ava_descricao: form.descricao || null,
        ava_dt: form.dt,
        ava_valor: modo.value === 'faixa' ? form.valor : null,
    };
    const url = editId.value ? `/api/diario/notas/avaliacoes/${editId.value}` : '/api/diario/notas/avaliacoes';
    try {
        const r = await fetch(url, {
            method: editId.value ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        if (!r.ok) {
            const j = await r.json().catch(() => ({}));
            erroAval.value = j?.errors?.ava_valor?.[0] ?? j?.message ?? 'Não foi possível salvar a avaliação.';
            return;
        }
        showModal.value = false;
        await carregar();
    } catch {
        erroAval.value = 'Falha de comunicação.';
    } finally {
        enviandoAval.value = false;
    }
};

const excluirAvaliacao = async (a: Avaliacao) => {
    if (!confirm(`Excluir a avaliação "${a.iav_nome ?? a.ava_descricao}" e todas as notas lançadas nela?`)) return;
    try {
        const r = await fetch(`/api/diario/notas/avaliacoes/${a.ava_id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
        });
        if (r.ok) await carregar();
    } catch { /* ignore */ }
};

const podeSalvarAval = computed(() =>
    !!form.iavId && !!form.dt && (modo.value !== 'faixa' || Number(form.valor) > 0) && !enviandoAval.value,
);
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <!-- Cabeçalho -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Avaliação Conceitual</h2>
                <p class="text-xs text-muted-foreground">
                    Modo: <span class="font-semibold">{{ modo === 'faixa' ? 'numérico (faixa)' : 'conceito direto' }}</span>
                    <template v-if="modo === 'faixa'">
                        · soma <span class="font-semibold tabular-nums">{{ somaValores.toFixed(2) }}</span>/10
                        <span v-if="valorDisponivel > 0"> · disponível {{ valorDisponivel.toFixed(2) }}</span>
                    </template>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="busca" placeholder="Buscar aluno..." class="h-9 w-44 pl-8" />
                </div>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-input px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted" :disabled="carregando" @click="carregar">
                    <RefreshCw :class="['size-3.5', carregando && 'animate-spin']" /> Atualizar
                </button>
                <Button type="button" size="sm" class="bg-fuchsia-600 hover:bg-fuchsia-700" :disabled="!periodoAberto || !turmaAberta" @click="abrirNovo">
                    <Plus class="mr-1.5 size-4" /> Nova avaliação
                </Button>
            </div>
        </div>

        <div class="p-4">
            <div v-if="carregando" class="flex items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                <Loader2 class="size-4 animate-spin" /> Carregando...
            </div>
            <div v-else-if="erro" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-8 text-center text-sm text-rose-700">{{ erro }}</div>
            <div v-else-if="!tipoDisponivel" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                A série desta turma não possui avaliação conceitual configurada.
            </div>
            <template v-else>
                <div v-if="erroLinha" class="mb-3 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    <TriangleAlert class="size-4 shrink-0" /> {{ erroLinha }}
                </div>
                <div v-if="!turmaAberta" class="mb-3 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                    <TriangleAlert class="size-4 shrink-0" /> A turma não está aberta. O lançamento só é permitido com a turma aberta — apenas consulta.
                </div>
                <div v-else-if="!periodoAberto" class="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="size-4 shrink-0" /> Período de lançamento fechado para esta unidade. Apenas consulta.
                </div>

                <div v-if="avaliacoes.length === 0" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                    Nenhuma avaliação criada. Clique em <strong>Nova avaliação</strong> para começar.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 min-w-[220px] border-b border-r bg-muted/60 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aluno</th>
                                <th v-for="a in colunas" :key="a.ava_id" :class="['w-28 border-b border-r px-2 py-2 text-center align-top', a.ava_fl_recuperacao ? 'bg-amber-100/70 dark:bg-amber-950/40' : 'bg-muted/60']">
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <span class="mx-auto block w-24 break-words leading-tight" :title="a.iav_nome ?? a.ava_descricao ?? ''">{{ a.iav_nome ?? a.ava_descricao }}</span>
                                    </div>
                                    <div v-if="a.ava_descricao" class="mx-auto w-24 break-words text-[10px] font-normal italic leading-tight text-muted-foreground" :title="a.ava_descricao">{{ a.ava_descricao }}</div>
                                    <div class="text-[10px] font-normal text-muted-foreground">
                                        {{ fmtDate(a.ava_dt) }}<span v-if="modo === 'faixa' && a.ava_valor"> · /{{ Number(a.ava_valor).toFixed(2) }}</span>
                                        <span v-if="a.ava_fl_recuperacao" class="font-semibold text-amber-700 dark:text-amber-300"> · Rec</span>
                                        <span v-if="isFutura(a)" class="font-semibold text-sky-600 dark:text-sky-400"> · agendada</span>
                                    </div>
                                    <button v-if="semValor(a)" type="button" class="mt-0.5 inline-block rounded bg-amber-100 px-1 py-0.5 text-[10px] font-semibold text-amber-800 hover:bg-amber-200 dark:bg-amber-950/50 dark:text-amber-300" title="Defina o valor da avaliação para liberar o lançamento" @click="abrirEdit(a)">
                                        ⚠ defina o valor
                                    </button>
                                    <div class="mt-1 flex items-center justify-center gap-1">
                                        <button type="button" class="rounded p-0.5 hover:bg-muted" title="Editar" @click="abrirEdit(a)"><Pencil class="size-3 text-muted-foreground" /></button>
                                        <button type="button" class="rounded p-0.5 hover:bg-muted" title="Excluir" :disabled="!periodoAberto" @click="excluirAvaliacao(a)"><X class="size-3 text-rose-500" /></button>
                                    </div>
                                </th>
                                <th class="border-b border-l-2 border-fuchsia-200 bg-fuchsia-50/70 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-fuchsia-700 dark:border-fuchsia-900 dark:bg-fuchsia-950/40 dark:text-fuchsia-300">
                                    {{ modo === 'faixa' ? 'Média / Conceito' : 'Conceito' }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filtrados" :key="row.aln_id" :class="[row.bloqueado ? 'bg-muted/40 opacity-60' : 'hover:bg-muted/20']">
                                <td :class="['sticky left-0 z-10 border-b border-r px-3 py-1.5', row.bloqueado ? 'bg-muted/40' : 'bg-card']">
                                    <div class="font-medium leading-tight">{{ row.aln_nome }}</div>
                                    <div v-if="row.aln_nr_matricula" class="text-[10px] text-muted-foreground">Mat. {{ row.aln_nr_matricula }}</div>
                                    <div v-if="row.bloqueado" class="mt-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">Já lançado como numérica</div>
                                </td>
                                <td v-for="a in colunas" :key="a.ava_id" :class="['border-b border-r px-1.5 py-1 text-center', a.ava_fl_recuperacao ? 'bg-amber-50/40 dark:bg-amber-950/10' : '']">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Modo numérico (faixa) -->
                                        <input
                                            v-if="modo === 'faixa'"
                                            v-model="row.notas[a.ava_id].valor"
                                            type="number" step="0.01" min="0" :max="a.ava_valor ?? undefined"
                                            :disabled="!periodoAberto || !turmaAberta || isFutura(a) || row.bloqueado || semValor(a)"
                                            :title="!turmaAberta ? 'Turma não está aberta.' : (semValor(a) ? 'Defina o valor da avaliação antes de lançar notas.' : '')"
                                            :class="['h-8 w-16 rounded-md border bg-background px-1.5 text-center text-sm tabular-nums shadow-sm focus:outline-none focus:ring-1 focus:ring-fuchsia-500 disabled:opacity-60', statusDe(row.aln_id, a.ava_id) === 'invalid' || statusDe(row.aln_id, a.ava_id) === 'error' ? 'border-rose-400' : 'border-input']"
                                            @input="aoDigitarNota(row, a)"
                                            @blur="salvarNota(row, a)"
                                        />
                                        <!-- Modo conceito direto -->
                                        <select
                                            v-else
                                            v-model.number="row.notas[a.ava_id].cnc_id"
                                            :disabled="!periodoAberto || !turmaAberta || isFutura(a) || row.bloqueado"
                                            class="h-8 w-16 rounded-md border border-input bg-background px-1 text-center text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-fuchsia-500 disabled:opacity-60"
                                            @change="salvarNota(row, a)"
                                        >
                                            <option :value="null">—</option>
                                            <option v-for="c in conceitos" :key="c.cnc_id" :value="c.cnc_id">{{ c.cnc_sigla }}</option>
                                        </select>
                                        <span class="w-3">
                                            <Loader2 v-if="statusDe(row.aln_id, a.ava_id) === 'saving'" class="size-3 animate-spin text-muted-foreground" />
                                            <CheckCircle2 v-else-if="statusDe(row.aln_id, a.ava_id) === 'saved'" class="size-3 text-emerald-500" />
                                            <TriangleAlert v-else-if="statusDe(row.aln_id, a.ava_id) === 'invalid' || statusDe(row.aln_id, a.ava_id) === 'error'" class="size-3 text-rose-500" />
                                        </span>
                                    </div>
                                </td>
                                <td class="border-b border-l-2 border-fuchsia-200 bg-fuchsia-50/40 px-3 py-1.5 text-center dark:border-fuchsia-900 dark:bg-fuchsia-950/20">
                                    <template v-if="resultado(row)">
                                        <div v-if="modo === 'faixa' && valorFaixa(row) !== null" class="text-sm font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ valorFaixa(row)!.toFixed(1) }}</div>
                                        <div class="font-bold text-fuchsia-700 dark:text-fuchsia-300">{{ resultado(row)!.cnc_sigla }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ resultado(row)!.cnc_descricao }}</div>
                                    </template>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                            <tr v-if="filtrados.length === 0">
                                <td :colspan="colunas.length + 2" class="px-4 py-8 text-center text-sm text-muted-foreground">Nenhum aluno encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted-foreground">
                        <template v-if="modo === 'conceito'">Conceito final = mais frequente; empate → média dos pesos.</template>
                        <template v-else>Conceito final = soma das notas regulares → faixa.</template>
                        Recuperação (âmbar) fica fora do cálculo e só sobrescreve se for melhor.
                    </p>
                </div>
            </template>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
            <div class="w-full max-w-md rounded-2xl bg-card shadow-2xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold">{{ editId ? 'Editar avaliação' : 'Nova avaliação' }}</h3>
                    <button type="button" class="rounded p-1 hover:bg-muted" @click="showModal = false"><X class="size-5" /></button>
                </div>
                <div class="flex flex-col gap-3 p-5">
                    <div>
                        <Label>Instrumento <span class="text-rose-600">*</span></Label>
                        <select v-model.number="form.iavId" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-fuchsia-500">
                            <option :value="null" disabled>Selecione o instrumento...</option>
                            <option v-for="i in instrumentos" :key="i.iav_id" :value="i.iav_id">{{ i.iav_nome }}</option>
                        </select>
                    </div>
                    <div>
                        <Label>Descrição (opcional)</Label>
                        <Input v-model="form.descricao" placeholder="Complemento..." maxlength="150" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Data <span class="text-rose-600">*</span></Label>
                            <Input v-model="form.dt" type="date" />
                        </div>
                        <div v-if="modo === 'faixa'">
                            <Label>Valor <span class="text-rose-600">*</span></Label>
                            <Input v-model="form.valor" type="number" step="0.01" min="0.01" max="10" />
                            <p v-if="!recuperacaoSelecionada" class="mt-1 text-[11px] text-muted-foreground">Disponível p/ regulares: {{ valorDisponivel.toFixed(2) }}</p>
                        </div>
                    </div>
                    <p v-if="modo === 'conceito'" class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                        Modo conceito direto: a avaliação não tem valor; o professor lança o conceito por aluno.
                    </p>
                    <p v-if="recuperacaoSelecionada" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Instrumento de recuperação: fica fora do cálculo e sobrescreve o resultado só se for melhor.
                    </p>
                    <p v-if="erroAval" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ erroAval }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t px-5 py-3">
                    <Button type="button" variant="outline" @click="showModal = false">Cancelar</Button>
                    <Button type="button" class="bg-fuchsia-600 hover:bg-fuchsia-700" :disabled="!podeSalvarAval" @click="salvarAvaliacao">
                        <Loader2 v-if="enviandoAval" class="mr-2 size-4 animate-spin" /> Salvar
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
