<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CheckCircle2, Loader2, Pencil, Plus, RefreshCw, Search, Trash2, TriangleAlert, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    anlId: number;
    escId: number;
    turId: number;
    disId: number;
    uniId: number;
}>();

const TIPO = 'numerica';

interface Instrumento {
    iav_id: number;
    iav_nome: string;
    iav_fl_recuperacao: boolean;
}
interface Avaliacao {
    ava_id: number;
    ava_iav_id: number | null;
    iav_nome: string | null;
    ava_descricao: string | null;
    ava_dt: string;
    ava_valor: number;
    ava_fl_recuperacao: boolean;
    ava_fl_migrada?: boolean;
}
interface AlunoRow {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    notas: Record<number, number | null>;
    bloqueado: boolean;
    dt_saida: string | null;
}

const carregando = ref(true);
const erro = ref<string | null>(null);
const periodoAberto = ref(true);
const turmaAberta = ref(true);
const tipoDisponivel = ref(true);
const avaliacoes = ref<Avaliacao[]>([]);
const instrumentos = ref<Instrumento[]>([]);
const rows = ref<AlunoRow[]>([]);
const busca = ref('');

// Estado por célula: `${aln}:${ava}` → status/timer
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
        if (!r.ok) {
            erro.value = 'Não foi possível carregar as avaliações.';
            return;
        }
        const data = await r.json();
        tipoDisponivel.value = data.tipo_disponivel ?? false;
        periodoAberto.value = data.periodo_aberto ?? false;
        turmaAberta.value = data.turma_aberta ?? true;
        avaliacoes.value = data.avaliacoes ?? [];
        instrumentos.value = data.instrumentos ?? [];
        rows.value = ((data.alunos ?? []) as any[]).map((a) => ({
            aln_id: a.aln_id,
            aln_nome: a.aln_nome,
            aln_nr_matricula: a.aln_nr_matricula,
            // contexto devolve { valor, cnc_id } por avaliação — numérica usa só o valor
            notas: Object.fromEntries(
                Object.entries(a.notas ?? {}).map(([k, v]) => [k, (v as any)?.valor ?? null]),
            ),
            bloqueado: a.bloqueado ?? false,
            dt_saida: a.dt_saida ?? null,
        }));
        for (const k of Object.keys(cell)) delete cell[k];
    } catch {
        erro.value = 'Falha de comunicação ao carregar as avaliações.';
    } finally {
        carregando.value = false;
    }
};

onMounted(carregar);
watch(() => [props.turId, props.disId, props.uniId], carregar);

// ── Colunas / agregados ──────────────────────────────────────────────────────
// Migradas (histórico de outra turma) ficam fora do cálculo de soma e média.
const regulares = computed(() => avaliacoes.value.filter((a) => !a.ava_fl_recuperacao && !a.ava_fl_migrada));
const recuperacoes = computed(() => avaliacoes.value.filter((a) => a.ava_fl_recuperacao && !a.ava_fl_migrada));
const migradas = computed(() => avaliacoes.value.filter((a) => a.ava_fl_migrada));
const colunas = computed(() => [...regulares.value, ...recuperacoes.value, ...migradas.value]);
const somaValores = computed(() => regulares.value.reduce((s, a) => s + Number(a.ava_valor), 0));
const valorDisponivel = computed(() => Math.max(0, Math.round((10 - somaValores.value) * 100) / 100));

// Avaliação com data futura: não permite lançamento e fica fora da média.
const hojeStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const isFutura = (a: Avaliacao) => (a.ava_dt?.substring(0, 10) ?? '') > hojeStr();
const fmtSaida = (dt: string | null) => {
    if (!dt) return '';
    const [y, m, d] = dt.split('-');
    return `${d}/${m}/${y}`;
};
// Aluno saiu antes da data da avaliação → lançamento bloqueado nessa coluna.
const saiuAntes = (row: AlunoRow, a: Avaliacao) => !!row.dt_saida && (a.ava_dt?.substring(0, 10) ?? '') > row.dt_saida;

const media = (row: AlunoRow): number | null => {
    const regs = regulares.value.filter((a) => !isFutura(a));
    if (regs.length === 0) return null;
    // Média = SOMA das notas regulares.
    const base = regs.reduce((s, a) => s + (Number(row.notas[a.ava_id]) || 0), 0);
    // Recuperação: usa a nota da recuperação se for MAIOR que a média anterior.
    let recNota: number | null = null;
    for (const a of recuperacoes.value) {
        if (isFutura(a)) continue;
        const v = row.notas[a.ava_id];
        if (v !== null && v !== undefined && !Number.isNaN(Number(v))) {
            const n = Number(v);
            if (recNota === null || n > recNota) recNota = n;
        }
    }
    const total = recNota !== null && recNota > base ? recNota : base;
    // Arredondamento ao 0,5 só na média final.
    return Math.round(total * 2) / 2;
};

const filtrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return rows.value;
    return rows.value.filter(
        (a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q),
    );
});

// ── Autosave de nota ─────────────────────────────────────────────────────────
const fmtDate = (d: string) => {
    if (!d) return '';
    const [, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}`;
};

const salvarNota = async (row: AlunoRow, ava: Avaliacao) => {
    const key = ck(row.aln_id, ava.ava_id);
    const st = ensureCell(key);
    if (st.timer) { clearTimeout(st.timer); st.timer = null; }
    if (!periodoAberto.value || !turmaAberta.value || isFutura(ava) || row.bloqueado || saiuAntes(row, ava)) return;

    const raw = row.notas[ava.ava_id];
    const valor = raw === null || (raw as any) === '' ? null : Number(raw);

    if (valor !== null && (Number.isNaN(valor) || valor < 0 || valor > Number(ava.ava_valor))) {
        st.status = 'invalid';
        return;
    }

    st.status = 'saving';
    try {
        const r = await fetch('/api/diario/notas/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ nta_ava_id: ava.ava_id, nta_aln_id: row.aln_id, nta_valor: valor }),
        });
        st.status = r.ok ? 'saved' : 'error';
    } catch {
        st.status = 'error';
    }
};

const aoDigitarNota = (row: AlunoRow, ava: Avaliacao) => {
    if (!periodoAberto.value || !turmaAberta.value || isFutura(ava) || row.bloqueado || saiuAntes(row, ava)) return;
    // Não deixa passar do valor máximo da avaliação (nem negativo).
    const v = row.notas[ava.ava_id];
    if (v !== null && (v as any) !== '' && !Number.isNaN(Number(v))) {
        const max = Number(ava.ava_valor);
        if (Number(v) > max) row.notas[ava.ava_id] = max;
        else if (Number(v) < 0) row.notas[ava.ava_id] = 0;
    }
    const key = ck(row.aln_id, ava.ava_id);
    const st = ensureCell(key);
    st.status = 'dirty';
    if (st.timer) clearTimeout(st.timer);
    st.timer = window.setTimeout(() => salvarNota(row, ava), 1200);
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
    form.iavId = null;
    form.descricao = '';
    form.dt = '';
    form.valor = '';
    showModal.value = true;
};
const abrirEdit = (a: Avaliacao) => {
    editId.value = a.ava_id;
    erroAval.value = null;
    form.iavId = a.ava_iav_id;
    form.descricao = a.ava_descricao ?? '';
    form.dt = a.ava_dt;
    form.valor = Number(a.ava_valor);
    showModal.value = true;
};

const salvarAvaliacao = async () => {
    enviandoAval.value = true;
    erroAval.value = null;
    const payload = {
        ava_esc_id: props.escId,
        ava_anl_id: props.anlId,
        ava_tur_id: props.turId,
        ava_dis_id: props.disId,
        ava_uni_id: props.uniId,
        ava_iav_id: form.iavId,
        ava_tipo: TIPO,
        ava_descricao: form.descricao || null,
        ava_dt: form.dt,
        ava_valor: form.valor,
    };
    const url = editId.value
        ? `/api/diario/notas/avaliacoes/${editId.value}`
        : '/api/diario/notas/avaliacoes';
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
    if (!confirm(`Excluir a avaliação "${a.ava_descricao}" e todas as notas lançadas nela?`)) return;
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
    !!form.iavId && !!form.dt && Number(form.valor) > 0 && !enviandoAval.value,
);

// Valor da avaliação: só 1 casa decimal (corta a 2ª na digitação).
const limitarValor1Casa = (e: Event) => {
    const el = e.target as HTMLInputElement;
    if (/\.\d{2,}/.test(el.value)) {
        el.value = el.value.replace(/^(-?\d*\.\d)\d+$/, '$1');
        form.valor = el.value === '' ? '' : Number(el.value);
    }
};
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <!-- Cabeçalho -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Avaliação Numérica</h2>
                <p class="text-xs text-muted-foreground">
                    Soma das avaliações: <span class="font-semibold tabular-nums">{{ somaValores.toFixed(2) }}</span> / 10
                    <span v-if="valorDisponivel > 0"> · disponível {{ valorDisponivel.toFixed(2) }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="busca" placeholder="Buscar aluno..." class="h-9 w-44 pl-8" />
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-input px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted"
                    :disabled="carregando"
                    @click="carregar"
                >
                    <RefreshCw :class="['size-3.5', carregando && 'animate-spin']" /> Atualizar
                </button>
                <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="!periodoAberto || !turmaAberta" @click="abrirNovo">
                    <Plus class="mr-1.5 size-4" /> Nova avaliação
                </Button>
            </div>
        </div>

        <div class="p-4">
            <div v-if="carregando" class="flex items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                <Loader2 class="size-4 animate-spin" /> Carregando...
            </div>
            <div v-else-if="erro" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-8 text-center text-sm text-rose-700">
                {{ erro }}
            </div>
            <div v-else-if="!tipoDisponivel" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                A série desta turma não possui avaliação numérica configurada.
            </div>
            <template v-else>
                <!-- Aviso turma fechada -->
                <div v-if="!turmaAberta" class="mb-3 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                    <TriangleAlert class="size-4 shrink-0" />
                    A turma não está aberta. O lançamento só é permitido com a turma aberta — apenas consulta.
                </div>
                <!-- Aviso período -->
                <div v-else-if="!periodoAberto" class="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="size-4 shrink-0" />
                    Período de lançamento fechado para esta unidade. As notas ficam apenas para consulta.
                </div>

                <!-- Sem avaliações -->
                <div v-if="avaliacoes.length === 0" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                    Nenhuma avaliação criada. Clique em <strong>Nova avaliação</strong> para começar.
                </div>

                <!-- Matriz -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 min-w-[220px] border-b border-r bg-muted/60 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Aluno
                                </th>
                                <th
                                    v-for="a in colunas"
                                    :key="a.ava_id"
                                    :class="['w-28 border-b border-r px-2 py-2 text-center align-top', a.ava_fl_migrada ? 'bg-violet-100/70 dark:bg-violet-950/40' : (a.ava_fl_recuperacao ? 'bg-amber-100/70 dark:bg-amber-950/40' : 'bg-muted/60')]"
                                >
                                    <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                        <span class="mx-auto block w-24 break-words leading-tight" :title="a.iav_nome ?? a.ava_descricao ?? ''">{{ a.iav_nome ?? a.ava_descricao }}</span>
                                    </div>
                                    <div v-if="a.ava_descricao" class="mx-auto line-clamp-2 w-24 cursor-help break-words text-[10px] font-normal italic leading-tight text-muted-foreground" :title="a.ava_descricao">
                                        {{ a.ava_descricao }}
                                    </div>
                                    <div class="text-[10px] font-normal text-muted-foreground">
                                        {{ fmtDate(a.ava_dt) }} · /{{ Number(a.ava_valor).toFixed(2) }}
                                        <span v-if="a.ava_fl_recuperacao" class="font-semibold text-amber-700 dark:text-amber-300">· Rec</span>
                                        <span v-if="a.ava_fl_migrada" class="font-semibold text-violet-700 dark:text-violet-300">· Migrada</span>
                                        <span v-if="isFutura(a)" class="font-semibold text-sky-600 dark:text-sky-400">· agendada</span>
                                    </div>
                                    <div class="mt-1 flex items-center justify-center gap-1">
                                        <button type="button" class="rounded p-0.5 hover:bg-muted" title="Editar" @click="abrirEdit(a)">
                                            <Pencil class="size-3 text-muted-foreground" />
                                        </button>
                                        <button type="button" class="rounded p-0.5 hover:bg-muted" title="Excluir" :disabled="!periodoAberto" @click="excluirAvaliacao(a)">
                                            <Trash2 class="size-3 text-rose-500" />
                                        </button>
                                    </div>
                                </th>
                                <th class="border-b border-l-2 border-indigo-200 bg-indigo-50/70 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-300">
                                    Média
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filtrados" :key="row.aln_id" :class="[row.bloqueado ? 'bg-muted/40 opacity-60' : 'hover:bg-muted/20']">
                                <td :class="['sticky left-0 z-10 border-b border-r px-3 py-1.5', row.bloqueado ? 'bg-muted/40' : 'bg-card']">
                                    <div class="font-medium leading-tight">{{ row.aln_nome }}</div>
                                    <div v-if="row.aln_nr_matricula" class="text-[10px] text-muted-foreground">Mat. {{ row.aln_nr_matricula }}</div>
                                    <div v-if="row.bloqueado" class="mt-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">Já lançado como conceitual</div>
                                    <div v-if="row.dt_saida" class="mt-0.5 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saída {{ fmtSaida(row.dt_saida) }}</div>
                                </td>
                                <td
                                    v-for="a in colunas"
                                    :key="a.ava_id"
                                    :class="['border-b border-r px-1.5 py-1 text-center', a.ava_fl_migrada ? 'bg-violet-50/40 dark:bg-violet-950/10' : (a.ava_fl_recuperacao ? 'bg-amber-50/40 dark:bg-amber-950/10' : '')]"
                                >
                                    <div class="relative flex items-center justify-center">
                                        <input
                                            v-model="row.notas[a.ava_id]"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="a.ava_valor"
                                            :disabled="!periodoAberto || !turmaAberta || isFutura(a) || row.bloqueado || saiuAntes(row, a)"
                                            :title="!turmaAberta ? 'Turma não está aberta.' : saiuAntes(row, a) ? `Aluno saiu em ${fmtSaida(row.dt_saida)} — bloqueado.` : (row.bloqueado ? 'Aluno já possui notas conceituais nesta matéria.' : (isFutura(a) ? 'Avaliação agendada — lançamento liberado a partir da data.' : ''))"
                                            :class="[
                                                'h-8 w-16 rounded-md border bg-background px-1.5 text-center text-sm tabular-nums shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:opacity-60',
                                                statusDe(row.aln_id, a.ava_id) === 'invalid' || statusDe(row.aln_id, a.ava_id) === 'error' ? 'border-rose-400' : 'border-input',
                                            ]"
                                            @input="aoDigitarNota(row, a)"
                                            @blur="salvarNota(row, a)"
                                        />
                                        <span class="ml-1 w-3">
                                            <Loader2 v-if="statusDe(row.aln_id, a.ava_id) === 'saving'" class="size-3 animate-spin text-muted-foreground" />
                                            <CheckCircle2 v-else-if="statusDe(row.aln_id, a.ava_id) === 'saved'" class="size-3 text-emerald-500" />
                                            <TriangleAlert v-else-if="statusDe(row.aln_id, a.ava_id) === 'invalid' || statusDe(row.aln_id, a.ava_id) === 'error'" class="size-3 text-rose-500" />
                                        </span>
                                    </div>
                                </td>
                                <td class="border-b border-l-2 border-indigo-200 bg-indigo-50/40 px-3 py-1.5 text-center font-semibold tabular-nums text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/20 dark:text-indigo-300">
                                    {{ media(row) === null ? '—' : media(row)!.toFixed(2) }}
                                </td>
                            </tr>
                            <tr v-if="filtrados.length === 0">
                                <td :colspan="colunas.length + 2" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                    Nenhum aluno encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Média = soma das notas das avaliações regulares. Recuperação (âmbar) substitui a média se a nota dela for maior. Média final arredondada ao 0,5.
                    </p>
                </div>
            </template>
        </div>

        <!-- Modal nova/editar avaliação -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
            <div class="w-full max-w-md rounded-2xl bg-card shadow-2xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold">{{ editId ? 'Editar avaliação' : 'Nova avaliação' }}</h3>
                    <button type="button" class="rounded p-1 hover:bg-muted" @click="showModal = false"><X class="size-5" /></button>
                </div>
                <div class="flex flex-col gap-3 p-5">
                    <div>
                        <Label>Instrumento <span class="text-rose-600">*</span></Label>
                        <select
                            v-model.number="form.iavId"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option :value="null" disabled>Selecione o instrumento...</option>
                            <option v-for="i in instrumentos" :key="i.iav_id" :value="i.iav_id">{{ i.iav_nome }}</option>
                        </select>
                    </div>
                    <div>
                        <Label>Descrição (opcional)</Label>
                        <Input v-model="form.descricao" placeholder="Complemento, ex.: Capítulo 3..." maxlength="150" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Data <span class="text-rose-600">*</span></Label>
                            <Input v-model="form.dt" type="date" />
                        </div>
                        <div>
                            <Label>Valor <span class="text-rose-600">*</span></Label>
                            <Input v-model="form.valor" type="number" step="0.1" min="0.1" max="10" @input="limitarValor1Casa" />
                            <p v-if="!recuperacaoSelecionada" class="mt-1 text-[11px] text-muted-foreground">
                                Disponível p/ regulares: {{ valorDisponivel.toFixed(2) }}
                            </p>
                        </div>
                    </div>
                    <p v-if="recuperacaoSelecionada" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Instrumento de recuperação: vale até 10, fora da soma e da média.
                    </p>
                    <p v-if="erroAval" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ erroAval }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t px-5 py-3">
                    <Button type="button" variant="outline" @click="showModal = false">Cancelar</Button>
                    <Button type="button" class="bg-indigo-600 hover:bg-indigo-700" :disabled="!podeSalvarAval" @click="salvarAvaliacao">
                        <Loader2 v-if="enviandoAval" class="mr-2 size-4 animate-spin" />
                        Salvar
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
