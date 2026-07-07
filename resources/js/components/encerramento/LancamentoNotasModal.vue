<script setup lang="ts">
import { Dialog, DialogScrollContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Loader2, Lock, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{
    open: boolean;
    turId: number;
    aluno: { aln_id: number; aln_nome: string; matricula: string | null };
}>();
const emit = defineEmits<{ (e: 'update:open', v: boolean): void; (e: 'saved'): void }>();

interface Unidade { uni_id: number; label: string }
interface Conceito { cnc_id: number; cnc_sigla: string; cnc_descricao: string; cnc_peso?: number; cnc_limite_inferior?: number | string; cnc_limite_superior?: number | string }
interface Cel { media: number | null; cnc_id: number | null; faltas: number | null; tipo: string | null }
interface CalcCel { media: number | null; cnc_id: number | null; tipo: string | null; completo: boolean }
interface DisciplinaBloco {
    dis_id: number;
    dis_nome: string;
    valores: Record<string, Record<string, Cel>>;
    calc: Record<string, Record<string, CalcCel>>;
    recuperacoes?: Record<string, number>;
}

const carregando = ref(true);
const erro = ref<string | null>(null);
const erroSalvar = ref<string | null>(null);
const tipos = ref<string[]>([]);
const conceitoModo = ref<'faixa' | 'conceito'>('faixa');
const tipoSel = ref<'numerica' | 'conceitual'>('numerica');
const unidades = ref<Unidade[]>([]);
const conceitos = ref<Conceito[]>([]);
const disciplinas = ref<DisciplinaBloco[]>([]);

// Estado editável por célula (dis|uni): nota + faltas + status.
interface EditCel { media: number | string | null; cnc_id: number | null; faltas: number | string | null; tipo: string | null; auto: boolean; status: 'idle' | 'saving' | 'saved' | 'error' }
const cels = reactive<Record<string, EditCel>>({});
const ck = (dis: number, uni: number) => `${dis}|${uni}`;

// Recuperação final por disciplina (aluno único do modal): recs[dis] = valor.
const recs = reactive<Record<number, number | string | null>>({});
const recFinalNum = (dis: number): number | null => {
    const r = recs[dis];
    if (r === null || r === '' || r === undefined) return null;
    const n = Number(r);
    return Number.isNaN(n) ? null : n;
};

// Tipo já lançado (manual) por DISCIPLINA: o aluno pode ter numérica numa e conceitual noutra.
// Bloqueia só inserir o tipo diferente do já lançado naquela disciplina — o toggle fica livre.
// Usa o snapshot persistido (mesma fonte do backend p/ o erro) + os lançamentos ao vivo.
const disTipoLancado = (dis: number): string | null => {
    const aln = String(props.aluno.aln_id);
    const bloco = disciplinas.value.find((d) => d.dis_id === dis);
    const v = (bloco?.valores?.[aln] ?? {}) as Record<string, Cel>;
    for (const uni of Object.keys(v)) {
        const cell = v[uni];
        if (cell && cell.tipo && (cell.media !== null || cell.cnc_id !== null)) return cell.tipo;
    }
    for (const u of unidades.value) {
        const c = cels[ck(dis, u.uni_id)];
        if (c && !c.auto && c.tipo && ((c.media !== null && c.media !== '') || c.cnc_id !== null)) return c.tipo;
    }
    return null;
};
const bloqueadoDisc = (dis: number): boolean => {
    const t = disTipoLancado(dis);
    return t !== null && t !== tipoSel.value;
};
const bloqLabel = (dis: number) => (disTipoLancado(dis) === 'conceitual' ? 'conceituais' : 'numéricas');

// Aba inicial ao abrir: o tipo já lançado em alguma disciplina (só p/ abrir numa visão útil).
const tipoInicial = (): 'numerica' | 'conceitual' => {
    const aln = String(props.aluno.aln_id);
    const temTipo = (t: string) => disciplinas.value.some((d) => Object.values(d.valores?.[aln] ?? {}).some((v) => v.tipo === t && (v.media !== null || v.cnc_id !== null)));
    if (temTipo('numerica')) return 'numerica';
    if (temTipo('conceitual')) return 'conceitual';
    return tipos.value.includes('numerica') ? 'numerica' : 'conceitual';
};

const entrada = computed<'numerica' | 'faixa' | 'conceito' | null>(() => {
    if (!tipos.value.length) return null;
    if (tipoSel.value === 'numerica') return 'numerica';
    return conceitoModo.value === 'conceito' ? 'conceito' : 'faixa';
});

// ===== Média final do aluno na disciplina =====
// Soma os períodos e divide pela quantidade de períodos. Só calcula com todos preenchidos.
// Numérica/faixa → número (0,5 mais próximo); conceito (letra) → média dos pesos → letra.
const roundMeio = (v: number) => Math.round(v * 2) / 2;
const pesoConceito = (cncId: number | null): number | null => {
    if (cncId == null) return null;
    const c = conceitos.value.find((x) => x.cnc_id === cncId);
    return c && c.cnc_peso != null ? Number(c.cnc_peso) : null;
};
const conceitoPorPeso = (peso: number): Conceito | null => {
    if (!conceitos.value.length) return null;
    return [...conceitos.value].sort(
        (a, b) => Math.abs(Number(a.cnc_peso) - peso) - Math.abs(Number(b.cnc_peso) - peso),
    )[0] ?? null;
};
// Faixa: número → conceito de maior limite inferior ≤ média (mesma regra do diário).
const faixaSigla = (media: number): string | null => {
    if (!conceitos.value.length) return null;
    const ord = [...conceitos.value].sort((a, b) => Number(a.cnc_limite_inferior) - Number(b.cnc_limite_inferior));
    let sel: Conceito | null = null;
    for (const c of ord) if (Number(c.cnc_limite_inferior) <= media) sel = c;
    return (sel ?? ord[0])?.cnc_sigla ?? null;
};
const mediaFinal = (dis: number): string | null => {
    const us = unidades.value;
    if (!us.length || !entrada.value) return null;

    // Conceito direto: média dos pesos → letra.
    if (entrada.value === 'conceito') {
        const pesos: number[] = [];
        for (const u of us) {
            const p = pesoConceito(cels[ck(dis, u.uni_id)]?.cnc_id ?? null);
            if (p == null) return null;
            pesos.push(p);
        }
        const m = Math.round(pesos.reduce((s, v) => s + v, 0) / us.length);
        return conceitoPorPeso(m)?.cnc_sigla ?? null;
    }

    // Numérica e conceitual-faixa: média numérica (0,5). Faixa exibe a letra; numérica, o número.
    const notas: number[] = [];
    for (const u of us) {
        const raw = cels[ck(dis, u.uni_id)]?.media;
        if (raw === null || raw === '' || raw === undefined) return null;
        const n = Number(raw);
        if (Number.isNaN(n)) return null;
        notas.push(n);
    }
    let media = roundMeio(notas.reduce((s, v) => s + v, 0) / us.length);
    // Recuperação final (só numérica): vale como MF se lançada e MAIOR que a média obtida.
    if (entrada.value === 'numerica') {
        const rec = recFinalNum(dis);
        if (rec !== null && rec > media) media = roundMeio(rec);
    }
    return entrada.value === 'faixa' ? faixaSigla(media) : media.toFixed(1);
};

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};

const carregar = async () => {
    carregando.value = true;
    erro.value = null;
    try {
        const url = new URL('/secretaria/lancamento-manual/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) { erro.value = `Não foi possível carregar (erro ${r.status}).`; return; }
        const j = await r.json();
        tipos.value = j.tipos ?? [];
        conceitoModo.value = j.conceito_modo ?? 'faixa';
        unidades.value = j.unidades ?? [];
        conceitos.value = j.conceitos ?? [];
        disciplinas.value = j.disciplinas ?? [];
        // Aba inicial só p/ abrir numa visão útil; o usuário pode trocar livremente.
        tipoSel.value = tipoInicial();

        const aln = String(props.aluno.aln_id);
        Object.keys(cels).forEach((k) => delete cels[k]);
        Object.keys(recs).forEach((k) => delete recs[Number(k)]);
        for (const d of disciplinas.value) {
            const rmap = (d.recuperacoes ?? {}) as Record<string, number>;
            if (rmap[aln] != null) recs[d.dis_id] = rmap[aln];
            for (const u of unidades.value) {
                const val = d.valores?.[aln]?.[u.uni_id];
                const calc = d.calc?.[aln]?.[u.uni_id];
                if (val) {
                    cels[ck(d.dis_id, u.uni_id)] = { media: val.media, cnc_id: val.cnc_id, faltas: val.faltas, tipo: val.tipo ?? null, auto: false, status: 'idle' };
                } else if (calc) {
                    cels[ck(d.dis_id, u.uni_id)] = { media: calc.media, cnc_id: calc.cnc_id, faltas: null, tipo: null, auto: true, status: 'idle' };
                } else {
                    cels[ck(d.dis_id, u.uni_id)] = { media: null, cnc_id: null, faltas: null, tipo: null, auto: false, status: 'idle' };
                }
            }
        }
    } catch {
        erro.value = 'Falha de conexão.';
    } finally {
        carregando.value = false;
    }
};

// Clampa 0..10; arredonda período: numérica 0,01 / faixa 0,5 (igual à tela Notas e Faltas).
const clamp10 = (n: number) => Math.min(10, Math.max(0, n));
const arredondarPeriodo = (v: number | string | null): number | null => {
    if (v === null || v === '') return null;
    let n = Number(v);
    if (Number.isNaN(n)) return null;
    n = clamp10(n);
    return entrada.value === 'faixa' ? Math.round(n * 2) / 2 : Math.round(n * 100) / 100;
};

// Bloqueia > 10 / < 0 ao vivo: NÃO clampa, remove o último dígito digitado (ex.: "40" → "4").
const onMediaInput = (dis: number, uni: number) => {
    const c = cels[ck(dis, uni)];
    if (!c) return;
    const v = c.media;
    if (v !== null && (v as any) !== '' && !Number.isNaN(Number(v)) && (Number(v) > 10 || Number(v) < 0)) {
        const s = String(v).slice(0, -1);
        c.media = (s === '' || s === '-' || s === '.') ? null : s;
        return;
    }
    c.auto = false;
};

// Ao sair do campo: arredonda e salva.
const onMediaChange = (dis: number, uni: number) => {
    const c = cels[ck(dis, uni)];
    if (!c) return;
    c.media = arredondarPeriodo(c.media);
    salvar(dis, uni);
};

// Recuperação final (numérica): bloqueia > 10 ao vivo + salva com debounce.
const recTimer: Record<number, ReturnType<typeof setTimeout>> = {};
const onRecInput = (dis: number, e: Event) => {
    const el = e.target as HTMLInputElement;
    const v = el.value;
    if (v !== '' && !Number.isNaN(Number(v)) && (Number(v) > 10 || Number(v) < 0)) {
        const s = String(v).slice(0, -1);
        el.value = (s === '' || s === '-' || s === '.') ? '' : s;
    }
    recs[dis] = el.value === '' ? null : el.value;
    if (recTimer[dis]) clearTimeout(recTimer[dis]);
    recTimer[dis] = setTimeout(() => salvarRecuperacao(dis), 1000);
};

const salvarRecuperacao = async (dis: number) => {
    const raw = recs[dis];
    const valor = raw === '' || raw === null || raw === undefined ? null : Math.round(Number(raw) * 100) / 100;
    try {
        await fetch('/secretaria/lancamento-manual/salvar-recuperacao', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, dis_id: dis, aln_id: props.aluno.aln_id, valor }),
        });
        emit('saved');
    } catch { /* ignora — status visual não crítico aqui */ }
};

const mensagemErro = async (r: Response): Promise<string> => {
    try {
        const j = await r.json();
        if (j?.errors && typeof j.errors === 'object') {
            const first = Object.values(j.errors as Record<string, string[]>)[0];
            if (Array.isArray(first) && first[0]) return first[0];
        }
        if (j?.message) return j.message;
    } catch { /* sem corpo JSON */ }
    return `Erro ao salvar (${r.status}).`;
};

const salvar = async (dis: number, uni: number) => {
    const c = cels[ck(dis, uni)];
    if (!c) return;
    c.media = arredondarPeriodo(c.media);
    c.auto = false;
    const temValor = (c.media !== null && c.media !== '') || c.cnc_id !== null;
    c.status = 'saving';
    erroSalvar.value = null;
    try {
        const r = await fetch('/secretaria/lancamento-manual/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({
                tur_id: props.turId,
                dis_id: dis,
                uni_id: uni,
                aln_id: props.aluno.aln_id,
                tipo: tipoSel.value,
                media: entrada.value === 'conceito' ? null : (c.media === '' ? null : c.media),
                cnc_id: entrada.value === 'conceito' ? c.cnc_id : null,
                faltas: c.faltas === '' ? null : c.faltas,
            }),
        });
        if (r.ok) {
            // Só grava o tipo no sucesso — base do bloqueio por disciplina.
            c.tipo = temValor ? tipoSel.value : null;
            c.status = 'saved';
            emit('saved');
        } else {
            // Rejeitado (ex.: tipo diferente do já lançado na disciplina) → desfaz o que foi digitado.
            if (entrada.value === 'conceito') c.cnc_id = null; else c.media = null;
            c.status = 'error';
            erroSalvar.value = await mensagemErro(r);
        }
    } catch {
        c.status = 'error';
        erroSalvar.value = 'Falha de conexão ao salvar.';
    }
};

// Remove todos os valores lançados (manuais) do aluno nesta turma.
// Esvaziar a célula faz o backend apagar o registro (volta à média calculada).
const limpando = ref(false);
const temLancado = computed(() =>
    Object.values(cels).some((c) => !c.auto && (c.media !== null && c.media !== '' || c.cnc_id !== null || c.faltas !== null && c.faltas !== '')),
);
const limparTodos = async () => {
    if (!confirm(`Remover TODOS os lançamentos de notas e faltas de ${props.aluno.aln_nome} nesta turma?`)) return;
    limpando.value = true;
    erroSalvar.value = null;
    try {
        for (const [key, c] of Object.entries(cels)) {
            const temValor = !c.auto && (c.media !== null && c.media !== '' || c.cnc_id !== null || c.faltas !== null && c.faltas !== '');
            if (!temValor) continue;
            const [dis, uni] = key.split('|').map(Number);
            c.media = null;
            c.cnc_id = null;
            c.faltas = null;
            await salvar(dis, uni);
        }
        await carregar(); // repopula com as médias calculadas
        emit('saved');
    } finally {
        limpando.value = false;
    }
};

const close = () => emit('update:open', false);

onMounted(carregar);
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogScrollContent class="sm:max-w-6xl">
            <DialogHeader>
                <DialogTitle>Notas e Faltas — {{ aluno.aln_nome }}</DialogTitle>
                <DialogDescription>
                    Lançamento manual por disciplina e período<span v-if="aluno.matricula"> · Matr. {{ aluno.matricula }}</span>.
                    Valores em cinza são a média calculada do diário (viram lançamento ao editar).
                </DialogDescription>
            </DialogHeader>

            <div v-if="carregando" class="flex items-center gap-2 py-10 text-sm text-muted-foreground">
                <Loader2 class="size-4 animate-spin" /> Carregando...
            </div>
            <div v-else-if="erro" class="py-10 text-center text-sm text-rose-600">{{ erro }}</div>
            <div v-else-if="!tipos.length" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>A série desta turma não usa avaliação numérica/conceitual (sem notas a lançar).</span>
            </div>

            <template v-else>
                <!-- Toggle de tipo quando a série permite ambos. Livre: o aluno pode ter
                     numérica numa disciplina e conceitual noutra; o bloqueio é por disciplina. -->
                <div v-if="tipos.length > 1" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-muted-foreground">Lançar como:</span>
                    <button
                        type="button"
                        :class="['rounded-md px-3 py-1 text-xs font-medium', tipoSel === 'numerica' ? 'bg-indigo-600 text-white' : 'border']"
                        @click="tipoSel = 'numerica'"
                    >Numérica</button>
                    <button
                        type="button"
                        :class="['rounded-md px-3 py-1 text-xs font-medium', tipoSel === 'conceitual' ? 'bg-indigo-600 text-white' : 'border']"
                        @click="tipoSel = 'conceitual'"
                    >Conceitual</button>
                </div>

                <div v-if="erroSalvar" class="mb-3 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ erroSalvar }}</span>
                </div>

                <div class="max-h-[68vh] overflow-auto rounded-lg border">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-20 min-w-[220px] border-b bg-muted/90 px-4 py-3 text-left text-xs font-semibold uppercase text-muted-foreground backdrop-blur">Disciplina</th>
                                <th v-for="u in unidades" :key="u.uni_id" class="sticky top-0 z-10 border-b border-l bg-muted/90 px-3 py-3 text-center text-xs font-semibold text-muted-foreground backdrop-blur">
                                    {{ u.label }}
                                    <div class="mt-0.5 text-[10px] font-normal normal-case text-muted-foreground/70">nota · faltas</div>
                                </th>
                                <th v-if="entrada === 'numerica'" class="sticky top-0 z-10 border-b border-l bg-amber-500 px-3 py-3 text-center text-xs font-semibold uppercase text-white backdrop-blur">Rec.<br />Final</th>
                                <th class="sticky right-0 top-0 z-20 border-b border-l bg-indigo-600 px-3 py-3 text-center text-xs font-semibold uppercase text-white backdrop-blur">Média<br />Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(d, i) in disciplinas" :key="d.dis_id" :class="i % 2 ? 'bg-muted/20' : ''">
                                <td class="sticky left-0 z-10 border-b px-4 py-3 font-medium" :class="i % 2 ? 'bg-muted/40' : 'bg-card'">{{ d.dis_nome }}</td>
                                <template v-if="bloqueadoDisc(d.dis_id)">
                                    <td :colspan="unidades.length + 1 + (entrada === 'numerica' ? 1 : 0)" class="border-b border-l px-4 py-3 text-center">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                            <Lock class="size-3.5" /> Notas {{ bloqLabel(d.dis_id) }} lançadas nesta disciplina
                                        </span>
                                    </td>
                                </template>
                                <template v-else>
                                <td v-for="u in unidades" :key="u.uni_id" class="border-b border-l px-3 py-2.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Conceito (modo conceito) -->
                                        <select
                                            v-if="entrada === 'conceito'"
                                            :value="cels[ck(d.dis_id, u.uni_id)]?.cnc_id ?? ''"
                                            class="h-10 w-24 rounded-md border bg-background px-2 text-sm"
                                            @change="cels[ck(d.dis_id, u.uni_id)].cnc_id = ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null; salvar(d.dis_id, u.uni_id)"
                                        >
                                            <option value="">—</option>
                                            <option v-for="c in conceitos" :key="c.cnc_id" :value="c.cnc_id">{{ c.cnc_sigla }}</option>
                                        </select>
                                        <!-- Nota numérica / faixa -->
                                        <input
                                            v-else
                                            v-model="cels[ck(d.dis_id, u.uni_id)].media"
                                            type="number" min="0" max="10" step="0.01"
                                            :class="['h-10 w-20 rounded-md border bg-background px-2 text-center text-sm', cels[ck(d.dis_id, u.uni_id)]?.auto ? 'text-muted-foreground' : '']"
                                            placeholder="—"
                                            @input="onMediaInput(d.dis_id, u.uni_id)"
                                            @change="onMediaChange(d.dis_id, u.uni_id)"
                                        />
                                        <input
                                            v-model="cels[ck(d.dis_id, u.uni_id)].faltas"
                                            type="number" min="0" max="999" step="1"
                                            class="h-10 w-16 rounded-md border bg-background px-2 text-center text-sm"
                                            placeholder="F"
                                            title="Faltas"
                                            @change="salvar(d.dis_id, u.uni_id)"
                                        />
                                        <Loader2 v-if="cels[ck(d.dis_id, u.uni_id)]?.status === 'saving'" class="size-4 animate-spin text-amber-600" />
                                        <span v-else-if="cels[ck(d.dis_id, u.uni_id)]?.status === 'error'" class="text-[11px] text-rose-600">erro</span>
                                    </div>
                                </td>
                                <td v-if="entrada === 'numerica'" class="border-b border-l px-3 py-2.5 text-center" :class="i % 2 ? 'bg-amber-50/40 dark:bg-amber-950/10' : 'bg-amber-50/20 dark:bg-amber-950/5'">
                                    <input
                                        :value="recs[d.dis_id] ?? ''"
                                        type="number" min="0" max="10" step="0.01"
                                        class="h-10 w-20 rounded-md border border-amber-300 bg-background px-2 text-center text-sm tabular-nums focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-800"
                                        placeholder="—"
                                        title="Recuperação final — vale como Média Final se maior que a média obtida."
                                        @input="onRecInput(d.dis_id, $event)"
                                    />
                                </td>
                                <td class="sticky right-0 z-10 border-b border-l px-3 py-2.5 text-center text-sm font-semibold tabular-nums" :class="i % 2 ? 'bg-muted/40' : 'bg-card'">
                                    <span v-if="mediaFinal(d.dis_id) !== null" class="text-indigo-700 dark:text-indigo-300">{{ mediaFinal(d.dis_id) }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                </template>
                            </tr>
                            <tr v-if="!disciplinas.length">
                                <td :colspan="unidades.length + 2 + (entrada === 'numerica' ? 1 : 0)" class="px-3 py-8 text-center text-muted-foreground">Sem disciplinas na grade.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm text-muted-foreground">Cada campo: <b>nota</b> e <b>F</b> = faltas. Salva automaticamente ao sair do campo. Apague o campo para remover o lançamento.</p>
            </template>

            <div class="mt-4 flex items-center justify-between gap-2">
                <button
                    v-if="!carregando && tipos.length"
                    type="button"
                    :disabled="!temLancado || limpando"
                    class="inline-flex items-center gap-1.5 rounded-md border border-rose-300 px-3 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                    @click="limparTodos"
                >
                    <Loader2 v-if="limpando" class="size-4 animate-spin" />
                    <Trash2 v-else class="size-4" />
                    Remover todos os lançamentos
                </button>
                <span v-else></span>
                <button type="button" class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-muted" @click="close">Fechar</button>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
