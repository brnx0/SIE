<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ChevronDown, ClipboardList, Loader2, RefreshCw, Search, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface Unidade { uni_id: number; label: string }
interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null }
interface Conceito { cnc_id: number; cnc_sigla: string; cnc_descricao: string }
interface CalcCel { media: number | null; cnc_id: number | null; tipo: string | null; completo: boolean }
interface DisciplinaBloco {
    dis_id: number;
    dis_nome: string;
    valores: Record<string, Record<string, { media: number | null; cnc_id: number | null; faltas: number | null; tipo: string | null }>>;
    calc: Record<string, Record<string, CalcCel>>;
}
// auto = média/conceito vindo do cálculo do diário (não é override salvo); vira override ao editar.
interface CelVal { media: number | string | null; cnc_id: number | null; faltas: number | string | null; tipo: string | null; auto: boolean }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notas e Faltas', href: '/secretaria/lancamento-manual' }];

const anlId = ref<number | null>(props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);
const disId = ref<number | null>(null);

const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);
const disciplinasLookup = ref<{ dis_id: number; dis_nome: string }[]>([]);

const tipos = ref<string[]>([]); // ['numerica'] | ['conceitual'] | ['numerica','conceitual']
const conceitoModo = ref<'faixa' | 'conceito'>('faixa');
const tipoSel = ref<'numerica' | 'conceitual'>('numerica');
// modo de entrada efetivo conforme o tipo escolhido + modo de conceito do ano
const entrada = computed<'numerica' | 'faixa' | 'conceito' | null>(() => {
    if (!tipos.value.length) return null;
    if (tipoSel.value === 'numerica') return 'numerica';
    return conceitoModo.value === 'conceito' ? 'conceito' : 'faixa';
});
const unidades = ref<Unidade[]>([]);
const alunos = ref<Aluno[]>([]);
const conceitos = ref<Conceito[]>([]);
const disciplinas = ref<DisciplinaBloco[]>([]);
const carregando = ref(false);
const erro = ref<string | null>(null);
const busca = ref('');

// vals[`${dis}|${aln}|${uni}`] = CelVal ; status idem
const vals = reactive<Record<string, CelVal>>({});
// calc[`${dis}|${aln}|${uni}`] = média calculada do diário (só células completas)
const calc = reactive<Record<string, CalcCel>>({});
const status = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});
const timers: Record<string, number> = {};

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};
const ck = (dis: number, aln: number, uni: number) => `${dis}|${aln}|${uni}`;

async function loadTurmas() {
    turmas.value = [];
    turId.value = null;
    disId.value = null;
    disciplinasLookup.value = [];
    resetGrade();
    if (!escId.value || !anlId.value) return;
    turmas.value = await getJson(`/secretaria/lancamento-manual/turmas?esc_id=${escId.value}&anl_id=${anlId.value}`);
}
async function loadDisciplinas() {
    disId.value = null;
    disciplinasLookup.value = [];
    if (!turId.value) return;
    disciplinasLookup.value = await getJson(`/secretaria/lancamento-manual/disciplinas?tur_id=${turId.value}`);
}

function resetGrade() {
    tipos.value = [];
    tipoSel.value = 'numerica';
    conceitoModo.value = 'faixa';
    unidades.value = [];
    alunos.value = [];
    conceitos.value = [];
    disciplinas.value = [];
    Object.keys(vals).forEach((k) => delete vals[k]);
    Object.keys(calc).forEach((k) => delete calc[k]);
    Object.keys(status).forEach((k) => delete status[k]);
}

// Cálculo aplicável à célula: completo E do mesmo tipo selecionado (numérica × conceitual).
const calcAtivo = (key: string): CalcCel | null => {
    const c = calc[key];
    return c && c.completo && c.tipo === tipoSel.value ? c : null;
};

// (Re)constrói as células a partir dos overrides manuais (+ faltas) e das médias calculadas.
// Sem override e com cálculo aplicável → exibe a média calculada como "auto" (não salva até editar).
// Depende de tipoSel, por isso roda também ao alternar numérica/conceitual.
function montarCelulas() {
    Object.keys(vals).forEach((k) => delete vals[k]);
    for (const d of disciplinas.value) {
        const v = d.valores ?? {};
        for (const aln of Object.keys(v)) {
            for (const uni of Object.keys(v[aln])) {
                const key = ck(d.dis_id, Number(aln), Number(uni));
                const m = v[aln][uni];
                const c = calcAtivo(key);
                const temOverride = m.media != null || m.cnc_id != null;
                vals[key] = {
                    media: temOverride ? m.media : (c ? c.media : null),
                    cnc_id: temOverride ? m.cnc_id : (c ? c.cnc_id : null),
                    faltas: m.faltas,
                    tipo: m.tipo ?? null,
                    auto: !temOverride && !!c,
                };
            }
        }
    }
}

async function carregar() {
    if (!turId.value) return;
    carregando.value = true;
    erro.value = null;
    try {
        const url = new URL('/secretaria/lancamento-manual/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(turId.value));
        if (disId.value) url.searchParams.set('dis_id', String(disId.value));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) { erro.value = 'Não foi possível carregar os alunos.'; return; }
        const data = await r.json();
        resetGrade();
        tipos.value = data.tipos ?? [];
        conceitoModo.value = data.conceito_modo ?? 'faixa';
        tipoSel.value = tipos.value.includes('numerica') ? 'numerica' : 'conceitual';
        unidades.value = data.unidades ?? [];
        alunos.value = data.alunos ?? [];
        conceitos.value = data.conceitos ?? [];
        disciplinas.value = data.disciplinas ?? [];
        // Médias calculadas do diário (apenas células completas). Independem do tipo selecionado.
        for (const d of disciplinas.value) {
            const cmap = d.calc ?? {};
            for (const aln of Object.keys(cmap)) {
                for (const uni of Object.keys(cmap[aln])) {
                    const c = cmap[aln][uni];
                    calc[ck(d.dis_id, Number(aln), Number(uni))] = { media: c.media, cnc_id: c.cnc_id, tipo: c.tipo ?? null, completo: !!c.completo };
                }
            }
        }
        montarCelulas();
    } finally {
        carregando.value = false;
    }
}

onMounted(loadTurmas);
watch([escId, anlId], loadTurmas);
watch(turId, loadDisciplinas);
watch([turId, disId], carregar);
// Alternar numérica/conceitual re-deriva o auto (gate por tipo) sem refazer a busca.
watch(tipoSel, montarCelulas);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: (t.ser_nome ? t.ser_nome + ' - ' : '') + t.tur_nome })));
const itemsDisciplina = computed(() => disciplinasLookup.value.map((d) => ({ id: d.dis_id, label: d.dis_nome })));

const alunosFiltrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter((a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q));
});

const cel = (dis: number, aln: number, uni: number): CelVal => {
    const key = ck(dis, aln, uni);
    if (!vals[key]) {
        const c = calcAtivo(key);
        vals[key] = c
            ? { media: c.media, cnc_id: c.cnc_id, faltas: null, tipo: null, auto: true }
            : { media: null, cnc_id: null, faltas: null, tipo: null, auto: false };
    }
    return vals[key];
};

// Tipo já lançado (override manual) para o aluno nesta disciplina. Células "auto"
// (média calculada) não contam — não disparam exclusão numérica × conceitual.
const alunoTipoLancado = (dis: number, aln: number): string | null => {
    for (const u of unidades.value) {
        const c = vals[ck(dis, aln, u.uni_id)];
        if (c && !c.auto && (c.cnc_id != null || (c.media !== null && c.media !== ''))) return c.tipo ?? null;
    }
    return null;
};
// Aluno bloqueado para o tipo atual (já tem o outro tipo lançado nesta disciplina).
const bloqueado = (dis: number, aln: number): boolean => {
    const t = alunoTipoLancado(dis, aln);
    return t !== null && t !== tipoSel.value;
};

const tituloCel = (dis: number, aln: number, uni: number, msgBloqueio: string): string => {
    if (bloqueado(dis, aln)) return msgBloqueio;
    return cel(dis, aln, uni).auto ? 'Média calculada das notas do diário. Edite para sobrescrever.' : '';
};

async function salvar(dis: number, aln: number, uni: number) {
    const key = ck(dis, aln, uni);
    const c = cel(dis, aln, uni);
    status[key] = 'saving';
    const norm = (v: number | string | null) => (v === '' || v === null || v === undefined ? null : v);
    // Célula "auto" (média calculada não editada) NÃO vira override: envia média/conceito nulos,
    // preservando só as faltas. A secretaria editar a média/conceito desmarca o auto.
    let media = c.auto || entrada.value === 'conceito' ? null : norm(c.media);
    // Média final arredondada ao 0,5 (mesma regra do diário).
    if (media !== null && media !== '') {
        media = Math.round(Number(media) * 2) / 2;
        c.media = media;
    }
    const cncId = !c.auto && entrada.value === 'conceito' ? norm(c.cnc_id) : null;
    try {
        const r = await fetch('/secretaria/lancamento-manual/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({
                tur_id: turId.value,
                dis_id: dis,
                uni_id: uni,
                aln_id: aln,
                tipo: tipoSel.value,
                media,
                cnc_id: cncId,
                faltas: norm(c.faltas),
            }),
        });
        if (r.ok) {
            if (media === null && cncId === null) {
                // Override removido (ou só faltas) → se há cálculo aplicável, volta a exibir a média auto.
                const cc = calcAtivo(key);
                if (cc) { c.auto = true; c.media = cc.media; c.cnc_id = cc.cnc_id; c.tipo = null; }
            } else {
                c.auto = false;
                c.tipo = tipoSel.value;
            }
            status[key] = 'saved';
        } else {
            status[key] = 'error';
        }
    } catch {
        status[key] = 'error';
    }
}

function onInput(dis: number, aln: number, uni: number) {
    const key = ck(dis, aln, uni);
    status[key] = 'idle';
    if (timers[key]) clearTimeout(timers[key]);
    timers[key] = window.setTimeout(() => salvar(dis, aln, uni), 1000);
}

// Edição da média/conceito desmarca o auto (passa a ser override salvo).
function onInputMedia(dis: number, aln: number, uni: number) {
    cel(dis, aln, uni).auto = false;
    onInput(dis, aln, uni);
}

const pronto = computed(() => !!turId.value);
const stKey = (dis: number, aln: number, uni: number) => `${dis}|${aln}|${uni}`;

// Minimizar/expandir blocos de disciplina (útil ao listar todas).
const colapsados = reactive<Record<number, boolean>>({});
const toggle = (dis: number) => { colapsados[dis] = !colapsados[dis]; };
const todasColapsadas = computed(() => disciplinas.value.length > 0 && disciplinas.value.every((d) => colapsados[d.dis_id]));
const toggleTodas = () => {
    const novo = !todasColapsadas.value;
    for (const d of disciplinas.value) colapsados[d.dis_id] = novo;
};
</script>

<template>
    <Head title="Notas e Faltas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ClipboardList class="size-5 text-indigo-600" /> Notas e Faltas
            </h1>
            <p class="mb-3 text-sm text-muted-foreground">Lance a média e as faltas por aluno em cada período. A média é arredondada ao 0,5 mais próximo. Se o professor alterar as notas depois, o ajuste é descartado e a média volta a ser recalculada.</p>

            <!-- Legenda do destaque -->
            <div class="mb-6 flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50/40 px-3 py-2 text-xs text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-200">
                <span class="grid h-7 w-12 place-items-center rounded-md border border-dashed border-indigo-300 italic text-indigo-500/80 dark:border-indigo-800">8,5</span>
                <span><strong>Nota lançada pelo professor</strong> — média calculada do diário (em itálico/tracejado). Edite o campo para sobrescrever.</span>
            </div>

            <!-- Filtros -->
            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Selecione a escola..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Selecione a turma..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Disciplina <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <LocalCombobox v-model="disId" :items="itemsDisciplina" placeholder="Todas as disciplinas" />
                </div>
            </div>

            <!-- Grade -->
            <div v-if="pronto" class="mt-4 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="busca" type="text" placeholder="Filtrar aluno..." class="h-9 w-48 rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                        <div v-if="tipos.length > 1" class="inline-flex items-center gap-2">
                            <span class="text-xs font-medium text-muted-foreground">Lançar:</span>
                            <div class="inline-flex overflow-hidden rounded-md border border-input">
                                <button type="button" class="px-3 py-1.5 text-xs font-medium transition-colors" :class="tipoSel === 'numerica' ? 'bg-indigo-600 text-white' : 'bg-background hover:bg-muted'" @click="tipoSel = 'numerica'">Numérica</button>
                                <button type="button" class="border-l border-input px-3 py-1.5 text-xs font-medium transition-colors" :class="tipoSel === 'conceitual' ? 'bg-indigo-600 text-white' : 'bg-background hover:bg-muted'" @click="tipoSel = 'conceitual'">Conceitual</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button v-if="disciplinas.length > 1" variant="outline" size="sm" class="gap-1.5" @click="toggleTodas">
                            <ChevronDown :class="['size-4 transition-transform', todasColapsadas && '-rotate-90']" />
                            {{ todasColapsadas ? 'Expandir todas' : 'Recolher todas' }}
                        </Button>
                        <Button variant="outline" size="sm" class="gap-1.5" :disabled="carregando" @click="carregar">
                            <RefreshCw :class="['size-4', carregando && 'animate-spin']" /> Atualizar
                        </Button>
                    </div>
                </div>

                <div v-if="carregando" class="rounded-xl border bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">Carregando...</div>
                <div v-else-if="erro" class="rounded-xl border bg-card py-12 text-center text-sm text-rose-600 shadow-sm">{{ erro }}</div>
                <div v-else-if="!entrada" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 shadow-sm dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>A série desta turma não possui avaliação numérica/conceitual configurada.</span>
                </div>
                <div v-else-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">Nenhum aluno ativo nesta turma.</div>

                <!-- 1 bloco por disciplina -->
                <div v-for="bloco in disciplinas" v-else :key="bloco.dis_id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <button type="button" class="flex w-full items-center justify-between border-b bg-muted/40 px-4 py-2 text-left transition hover:bg-muted/60" @click="toggle(bloco.dis_id)">
                        <span class="flex items-center gap-2 text-sm font-semibold">
                            <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="colapsados[bloco.dis_id] && '-rotate-90'" />
                            {{ bloco.dis_nome }}
                        </span>
                        <span class="text-xs text-muted-foreground">{{ entrada === 'conceito' ? 'Conceito' : 'Média (0–10)' }} + faltas · autosalvar</span>
                    </button>
                    <div v-show="!colapsados[bloco.dis_id]" class="max-h-[70vh] overflow-auto">
                        <table class="w-full border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="sticky left-0 top-0 z-30 min-w-[220px] border-b bg-muted/90 px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur">Aluno</th>
                                    <th v-for="u in unidades" :key="u.uni_id" colspan="2" class="border-b border-l bg-muted/90 px-2 py-1 text-center text-xs font-semibold backdrop-blur">{{ u.label }}</th>
                                </tr>
                                <tr>
                                    <template v-for="u in unidades" :key="`s-${u.uni_id}`">
                                        <th class="sticky top-7 z-20 border-b border-l bg-muted/70 px-2 py-1 text-center text-[11px] font-medium text-muted-foreground backdrop-blur">{{ entrada === 'conceito' ? 'Conceito' : 'Média' }}</th>
                                        <th class="sticky top-7 z-20 border-b bg-muted/70 px-2 py-1 text-center text-[11px] font-medium text-muted-foreground backdrop-blur">Faltas</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!alunosFiltrados.length">
                                    <td :colspan="unidades.length * 2 + 1" class="px-4 py-8 text-center text-sm text-muted-foreground">Nenhum aluno encontrado.</td>
                                </tr>
                                <tr v-for="(a, i) in alunosFiltrados" :key="a.aln_id" class="group">
                                    <td :class="['sticky left-0 z-10 border-b px-3 py-1.5', i % 2 ? 'bg-muted/30' : 'bg-card', 'group-hover:bg-indigo-50/70 dark:group-hover:bg-indigo-950/40']">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 shrink-0 text-right text-[11px] tabular-nums text-muted-foreground">{{ i + 1 }}</span>
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-medium text-slate-900 dark:text-slate-50">{{ a.aln_nome }}</div>
                                                <div v-if="a.aln_nr_matricula" class="text-[10px] text-muted-foreground">Mat. {{ a.aln_nr_matricula }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <template v-for="u in unidades" :key="`c-${u.uni_id}`">
                                        <td :class="['relative border-b border-l px-1.5 py-1 text-center', i % 2 ? 'bg-muted/20' : '']">
                                            <select
                                                v-if="entrada === 'conceito'"
                                                v-model="cel(bloco.dis_id, a.aln_id, u.uni_id).cnc_id"
                                                :disabled="bloqueado(bloco.dis_id, a.aln_id)"
                                                :title="tituloCel(bloco.dis_id, a.aln_id, u.uni_id, 'Aluno já tem lançamento numérico nesta disciplina.')"
                                                class="h-8 w-20 rounded-md border bg-background px-1 text-center text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="cel(bloco.dis_id, a.aln_id, u.uni_id).auto ? 'border-dashed border-indigo-300 italic text-indigo-500/80 dark:border-indigo-800' : 'border-input'"
                                                @change="onInputMedia(bloco.dis_id, a.aln_id, u.uni_id)"
                                            >
                                                <option :value="null">—</option>
                                                <option v-for="c in conceitos" :key="c.cnc_id" :value="c.cnc_id">{{ c.cnc_sigla }}</option>
                                            </select>
                                            <input
                                                v-else
                                                v-model="cel(bloco.dis_id, a.aln_id, u.uni_id).media"
                                                type="number" step="0.01" min="0" max="10"
                                                :disabled="bloqueado(bloco.dis_id, a.aln_id)"
                                                :title="tituloCel(bloco.dis_id, a.aln_id, u.uni_id, 'Aluno já tem lançamento conceitual nesta disciplina.')"
                                                class="h-8 w-16 rounded-md border bg-background px-1.5 text-center text-sm tabular-nums focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="cel(bloco.dis_id, a.aln_id, u.uni_id).auto ? 'border-dashed border-indigo-300 italic text-indigo-500/80 dark:border-indigo-800' : 'border-input'"
                                                @input="onInputMedia(bloco.dis_id, a.aln_id, u.uni_id)"
                                            />
                                            <span v-if="status[stKey(bloco.dis_id, a.aln_id, u.uni_id)] === 'saving'" class="absolute right-0.5 top-0.5"><Loader2 class="size-3 animate-spin text-amber-500" /></span>
                                        </td>
                                        <td :class="['border-b px-1.5 py-1 text-center', i % 2 ? 'bg-muted/20' : '']">
                                            <input
                                                v-model="cel(bloco.dis_id, a.aln_id, u.uni_id).faltas"
                                                type="number" min="0" max="999"
                                                class="h-8 w-14 rounded-md border border-input bg-background px-1.5 text-center text-sm tabular-nums focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                                :class="status[stKey(bloco.dis_id, a.aln_id, u.uni_id)] === 'error' ? 'border-rose-400' : ''"
                                                @input="onInput(bloco.dis_id, a.aln_id, u.uni_id)"
                                            />
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-else class="mt-4 rounded-xl border bg-card px-6 py-10 text-center text-sm text-muted-foreground shadow-sm">
                Selecione ano, escola e turma para lançar. A disciplina é opcional (em branco lista todas).
            </div>
        </div>
    </AppLayout>
</template>
