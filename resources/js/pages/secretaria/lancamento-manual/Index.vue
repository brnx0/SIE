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
interface DisciplinaBloco { dis_id: number; dis_nome: string; valores: Record<string, Record<string, { media: number | null; cnc_id: number | null; faltas: number | null; tipo: string | null }>> }
interface CelVal { media: number | string | null; cnc_id: number | null; faltas: number | string | null; tipo: string | null }

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
    Object.keys(status).forEach((k) => delete status[k]);
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
        for (const d of disciplinas.value) {
            const v = d.valores ?? {};
            for (const aln of Object.keys(v)) {
                for (const uni of Object.keys(v[aln])) {
                    vals[ck(d.dis_id, Number(aln), Number(uni))] = {
                        media: v[aln][uni].media,
                        cnc_id: v[aln][uni].cnc_id,
                        faltas: v[aln][uni].faltas,
                        tipo: v[aln][uni].tipo ?? null,
                    };
                }
            }
        }
    } finally {
        carregando.value = false;
    }
}

onMounted(loadTurmas);
watch([escId, anlId], loadTurmas);
watch(turId, loadDisciplinas);
watch([turId, disId], carregar);

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
    if (!vals[key]) vals[key] = { media: null, cnc_id: null, faltas: null, tipo: null };
    return vals[key];
};

// Tipo já lançado para o aluno nesta disciplina (qualquer unidade com valor).
const alunoTipoLancado = (dis: number, aln: number): string | null => {
    for (const u of unidades.value) {
        const c = vals[ck(dis, aln, u.uni_id)];
        if (c && (c.cnc_id != null || (c.media !== null && c.media !== ''))) return c.tipo ?? null;
    }
    return null;
};
// Aluno bloqueado para o tipo atual (já tem o outro tipo lançado nesta disciplina).
const bloqueado = (dis: number, aln: number): boolean => {
    const t = alunoTipoLancado(dis, aln);
    return t !== null && t !== tipoSel.value;
};

async function salvar(dis: number, aln: number, uni: number) {
    const key = ck(dis, aln, uni);
    const c = cel(dis, aln, uni);
    status[key] = 'saving';
    const norm = (v: number | string | null) => (v === '' || v === null || v === undefined ? null : v);
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
                media: entrada.value === 'conceito' ? null : norm(c.media),
                cnc_id: entrada.value === 'conceito' ? norm(c.cnc_id) : null,
                faltas: norm(c.faltas),
            }),
        });
        if (r.ok) {
            c.tipo = tipoSel.value;
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
            <p class="mb-6 text-sm text-muted-foreground">Para escolas que controlam fora do sistema: lance a média e as faltas por aluno em cada período. Tem precedência sobre a nota calculada.</p>

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
                                                :title="bloqueado(bloco.dis_id, a.aln_id) ? 'Aluno já tem lançamento numérico nesta disciplina.' : ''"
                                                class="h-8 w-20 rounded-md border border-input bg-background px-1 text-center text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                @change="onInput(bloco.dis_id, a.aln_id, u.uni_id)"
                                            >
                                                <option :value="null">—</option>
                                                <option v-for="c in conceitos" :key="c.cnc_id" :value="c.cnc_id">{{ c.cnc_sigla }}</option>
                                            </select>
                                            <input
                                                v-else
                                                v-model="cel(bloco.dis_id, a.aln_id, u.uni_id).media"
                                                type="number" step="0.01" min="0" max="10"
                                                :disabled="bloqueado(bloco.dis_id, a.aln_id)"
                                                :title="bloqueado(bloco.dis_id, a.aln_id) ? 'Aluno já tem lançamento conceitual nesta disciplina.' : ''"
                                                class="h-8 w-16 rounded-md border border-input bg-background px-1.5 text-center text-sm tabular-nums focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                @input="onInput(bloco.dis_id, a.aln_id, u.uni_id)"
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
