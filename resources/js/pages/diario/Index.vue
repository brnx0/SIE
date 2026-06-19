<script setup lang="ts">
import AvaliacaoDescritivaPanel from '@/components/diario/AvaliacaoDescritivaPanel.vue';
import QuadroHorarioPanel from '@/components/diario/QuadroHorarioPanel.vue';
import NotasNumericaPanel from '@/components/diario/NotasNumericaPanel.vue';
import NotasConceitualPanel from '@/components/diario/NotasConceitualPanel.vue';
import FaltasPanel from '@/components/diario/FaltasPanel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    AnoLetivoResumo,
    PlanoDisciplinaResumo,
    PlanoTurmaResumo,
    PlanoUnidade,
    ProfessorResumoDiario,
} from '@/types/diario';
import { Head } from '@inertiajs/vue3';
import { BookOpenCheck, CalendarCheck, CalendarClock, Calculator, ClipboardList, ListChecks, Pencil } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    professor: ProfessorResumoDiario;
    anosLetivos: AnoLetivoResumo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Diário de Classe', href: '/diario' }];

// Contexto selecionado
const anlId = ref<number | null>(null);
const escId = ref<number | null>(null);
const turId = ref<number | null>(null);
const disId = ref<number | null>(null);
const uniId = ref<number | null>(null);

// Opções carregadas sob demanda
const escolas = ref<{ esc_id: number; esc_nome: string }[]>([]);
const turmas = ref<PlanoTurmaResumo[]>([]);
const disciplinas = ref<PlanoDisciplinaResumo[]>([]);
const unidades = ref<PlanoUnidade[]>([]);

// Ano letivo inicial: trava se 1 só; senão usa o em exercício
const anlInicial = computed<number | null>(() => {
    if (props.anosLetivos.length === 1) return props.anosLetivos[0].anl_id;
    const exer = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
    return exer?.anl_id ?? null;
});

// ===== Lookups =====
const fetchEscolas = async () => {
    if (!anlId.value) {
        escolas.value = [];
        return;
    }
    const url = new URL('/api/diario/contexto/escolas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) escolas.value = await r.json();
};

const fetchTurmas = async () => {
    if (!anlId.value || !escId.value) {
        turmas.value = [];
        return;
    }
    const url = new URL('/api/diario/contexto/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    url.searchParams.set('esc_id', String(escId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};

const fetchDisciplinas = async () => {
    if (!turId.value) {
        disciplinas.value = [];
        return;
    }
    const url = new URL('/api/diario/contexto/disciplinas', window.location.origin);
    url.searchParams.set('tur_id', String(turId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) disciplinas.value = await r.json();
};

// Período (bimestre/trimestre) depende só do ano letivo.
const fetchUnidades = async () => {
    if (!anlId.value) {
        unidades.value = [];
        uniId.value = null;
        return;
    }
    const url = new URL('/api/diario/contexto/unidades', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    unidades.value = r.ok ? await r.json() : [];
    selecionarUnidadeCorrente();
};

// Data de hoje no fuso local (string YYYY-MM-DD p/ comparar com as datas do período).
const hojeStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// Default: período que contém hoje. Fallback: último já iniciado; senão o primeiro.
const selecionarUnidadeCorrente = () => {
    if (!unidades.value.length) {
        uniId.value = null;
        return;
    }
    const hoje = hojeStr();
    const fimDe = (u: PlanoUnidade) => u.uni_dt_fim_efetivo ?? u.uni_dt_fim;
    const corrente = unidades.value.find((u) => u.uni_dt_inicio <= hoje && hoje <= fimDe(u));
    const iniciada = [...unidades.value].reverse().find((u) => u.uni_dt_inicio <= hoje);
    uniId.value = (corrente ?? iniciada ?? unidades.value[0]).uni_id;
};

// ===== Itens p/ combobox =====
const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() =>
    turmas.value.map((t) => ({ id: t.tur_id, label: t.ser_nome ? `${t.ser_nome} / ${t.tur_nome}` : t.tur_nome })),
);
const itemsDisciplina = computed(() => disciplinas.value.map((d) => ({ id: d.dis_id, label: d.dis_nome })));
const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
const fmtBr = (d?: string | null) => {
    if (!d) return '';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};
const itemsUnidade = computed(() =>
    unidades.value.map((u) => ({
        id: u.uni_id,
        label: `${u.uni_numero}º ${capitalize(u.uni_tipo)} (${fmtBr(u.uni_dt_inicio)} a ${fmtBr(u.uni_dt_fim)})`,
    })),
);
// Rótulo do campo segue o tipo do ano (bimestre/trimestre/...); "Período" se misto/vazio.
const labelPeriodo = computed(() => {
    const tipos = new Set(unidades.value.map((u) => u.uni_tipo));
    return tipos.size === 1 ? capitalize([...tipos][0]) : 'Período';
});

// ===== Travas (1 opção => trava + auto-seleciona) =====
const anoTravado = computed(() => props.anosLetivos.length === 1);
const escTravada = computed(() => escolas.value.length === 1);
const turTravada = computed(() => turmas.value.length === 1);
const disTravada = computed(() => disciplinas.value.length === 1);
const uniTravada = computed(() => unidades.value.length === 1);

const labelDe = (items: { id: number; label: string }[], id: number | null) =>
    items.find((i) => i.id === id)?.label ?? '';

// Auto-seleção quando há exatamente 1 opção
watch(escolas, () => {
    if (escolas.value.length === 1) escId.value = escolas.value[0].esc_id;
});
watch(turmas, () => {
    if (turmas.value.length === 1) turId.value = turmas.value[0].tur_id;
});
watch(disciplinas, () => {
    if (disciplinas.value.length === 1) disId.value = disciplinas.value[0].dis_id;
});

// ===== Cascatas (reset dependentes) =====
watch(anlId, () => {
    escId.value = null;
    turId.value = null;
    disId.value = null;
    turmas.value = [];
    disciplinas.value = [];
    fetchEscolas();
    fetchUnidades();
});
watch(escId, () => {
    turId.value = null;
    disId.value = null;
    disciplinas.value = [];
    fetchTurmas();
});
watch(turId, () => {
    disId.value = null;
    fetchDisciplinas();
});

const contextoCompleto = computed(() => !!(anlId.value && escId.value && turId.value && disId.value && uniId.value));

// Tipos de avaliação configurados na série da turma selecionada.
const turmaSelecionada = computed(() => turmas.value.find((t) => Number(t.tur_id) === Number(turId.value)));
const tiposAvaliacao = computed<string[]>(() => {
    const v = turmaSelecionada.value?.ser_tipo_avaliacao as unknown;
    if (Array.isArray(v)) return v as string[];
    if (typeof v === 'string') {
        try {
            const p = JSON.parse(v);
            return Array.isArray(p) ? p : [];
        } catch {
            return [];
        }
    }
    return [];
});

// Módulos do diário (compartilham os mesmos filtros). Carregam só quando o
// usuário aciona o gatilho (clique no módulo) — nunca imediato.
// req: 'turma' = basta a turma selecionada · 'completo' = exige contexto completo.
const moduloAtivo = ref<string | null>(null);
const modulos = computed(() => [
    { key: 'quadro-horario', label: 'Quadro de Horário', icon: CalendarClock, req: 'turma', grupo: 'Consulta' },
    ...(tiposAvaliacao.value.includes('numerica')
        ? [{ key: 'notas-numerica', label: 'Avaliação Numérica', icon: Calculator, req: 'completo', grupo: 'Lançamentos' }]
        : []),
    ...(tiposAvaliacao.value.includes('conceitual')
        ? [{ key: 'notas-conceitual', label: 'Avaliação Conceitual', icon: ListChecks, req: 'completo', grupo: 'Lançamentos' }]
        : []),
    { key: 'avaliacao-descritiva', label: 'Avaliação Descritiva', icon: ClipboardList, req: 'completo', grupo: 'Lançamentos' },
    { key: 'faltas', label: 'Frequência', icon: CalendarCheck, req: 'periodo', grupo: 'Lançamentos' },
]);

// Seções de módulos, na ordem definida. Só grupos com itens aparecem.
const grupos = computed(() =>
    ['Consulta', 'Lançamentos']
        .map((titulo) => ({ titulo, itens: modulos.value.filter((m) => m.grupo === titulo) }))
        .filter((g) => g.itens.length > 0),
);
const moduloPronto = (req: string) =>
    req === 'turma'
        ? !!turId.value
        : req === 'periodo'
          ? !!(turId.value && uniId.value)
          : contextoCompleto.value;

// Reclicar no módulo ativo força recarregar o painel (ex.: trocou parâmetro do ano).
const recarregarSeq = ref(0);
const selecionarModulo = (m: { key: string; req: string }) => {
    if (!moduloPronto(m.req)) return;
    if (moduloAtivo.value === m.key) recarregarSeq.value++;
    else moduloAtivo.value = m.key;
};

// Painel renderizado de fato (módulo ativo + requisitos atendidos).
const painelVisivel = computed(() => {
    if (moduloAtivo.value === 'quadro-horario') return !!turId.value;
    if (moduloAtivo.value === 'notas-numerica') return contextoCompleto.value;
    if (moduloAtivo.value === 'notas-conceitual') return contextoCompleto.value;
    if (moduloAtivo.value === 'avaliacao-descritiva') return contextoCompleto.value;
    if (moduloAtivo.value === 'faltas') return !!(turId.value && uniId.value);
    return false;
});

// Trocar qualquer filtro descarta o módulo carregado (exige novo clique).
watch(() => [anlId.value, escId.value, turId.value, disId.value, uniId.value], () => {
    moduloAtivo.value = null;
});

// Recolhe o contexto quando um painel abre, dando destaque aos dados gerados.
const contextoExpandido = ref(true);
watch(painelVisivel, (v) => { if (v) contextoExpandido.value = false; });
const contextoChips = computed(() => {
    const c: { k: string; v: string }[] = [];
    if (anlId.value) c.push({ k: 'Ano', v: labelDe(itemsAno.value, anlId.value) });
    if (escId.value) c.push({ k: 'Escola', v: labelDe(itemsEscola.value, escId.value) });
    if (turId.value) c.push({ k: 'Turma', v: labelDe(itemsTurma.value, turId.value) });
    if (disId.value) c.push({ k: 'Disciplina', v: labelDe(itemsDisciplina.value, disId.value) });
    if (uniId.value) c.push({ k: labelPeriodo.value, v: labelDe(itemsUnidade.value, uniId.value) });
    return c;
});

onMounted(() => {
    anlId.value = anlInicial.value;
});

const semVinculo = computed(() => props.anosLetivos.length === 0);
</script>

<template>
    <Head title="Diário de Classe" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <div v-if="!painelVisivel" class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Diário de Classe</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Selecione o contexto para lançamentos do diário.</p>
                </div>
            </div>

            <!-- Aviso sem vínculo -->
            <div
                v-if="semVinculo"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                Você não possui turmas vinculadas em nenhum ano letivo. Procure a coordenação para regularizar sua lotação.
            </div>

            <!-- Barra de contexto compacta (quando recolhido) -->
            <section
                v-if="!semVinculo && !contextoExpandido"
                class="flex flex-wrap items-center gap-2 rounded-xl border bg-card px-4 py-2.5 shadow-sm"
            >
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ professor.fun_nome }}</span>
                <span class="text-muted-foreground/40">·</span>
                <span
                    v-for="chip in contextoChips"
                    :key="chip.k"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs"
                >
                    <span class="text-muted-foreground">{{ chip.k }}:</span>
                    <span class="font-medium">{{ chip.v }}</span>
                </span>
                <button
                    type="button"
                    class="ml-auto inline-flex items-center gap-1.5 rounded-md border border-input px-2.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="contextoExpandido = true"
                >
                    <Pencil class="size-3.5" /> Alterar
                </button>
            </section>

            <!-- Seletor de contexto -->
            <section v-if="!semVinculo && contextoExpandido" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground">Contexto</h2>
                    <button
                        v-if="painelVisivel"
                        type="button"
                        class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                        @click="contextoExpandido = false"
                    >
                        Recolher
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="md:col-span-12">
                        <Label>Professor</Label>
                        <Input :model-value="professor.fun_nome" readonly />
                    </div>

                    <div class="md:col-span-2">
                        <Label>Ano Letivo</Label>
                        <Input v-if="anoTravado" :model-value="labelDe(itemsAno, anlId)" readonly />
                        <LocalCombobox v-else v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                    </div>

                    <div class="md:col-span-4">
                        <Label>Escola</Label>
                        <Input v-if="escTravada" :model-value="labelDe(itemsEscola, escId)" readonly />
                        <LocalCombobox v-else v-model="escId" :items="itemsEscola" placeholder="Selecione a escola..." />
                    </div>

                    <div class="md:col-span-2">
                        <Label>Turma</Label>
                        <Input v-if="turTravada" :model-value="labelDe(itemsTurma, turId)" readonly />
                        <LocalCombobox v-else v-model="turId" :items="itemsTurma" placeholder="Selecione..." />
                    </div>

                    <div class="md:col-span-2">
                        <Label>Disciplina</Label>
                        <Input v-if="disTravada" :model-value="labelDe(itemsDisciplina, disId)" readonly />
                        <LocalCombobox v-else v-model="disId" :items="itemsDisciplina" placeholder="Selecione..." />
                    </div>

                    <div class="md:col-span-2">
                        <Label>{{ labelPeriodo }}</Label>
                        <Input v-if="uniTravada" :model-value="labelDe(itemsUnidade, uniId)" readonly />
                        <LocalCombobox v-else v-model="uniId" :items="itemsUnidade" placeholder="Selecione..." />
                    </div>
                </div>
            </section>

            <!-- Módulos do diário (mesmos filtros; carregam sob demanda) -->
            <section v-if="!semVinculo && turId" class="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-sm">
                <div v-for="grupo in grupos" :key="grupo.titulo">
                    <h2 class="mb-2 text-sm font-semibold text-muted-foreground">{{ grupo.titulo }}</h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="m in grupo.itens"
                            :key="m.key"
                            type="button"
                            :disabled="!moduloPronto(m.req)"
                            :title="!moduloPronto(m.req) ? 'Selecione disciplina e período para este módulo.' : ''"
                            :class="[
                                'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition',
                                moduloAtivo === m.key
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : moduloPronto(m.req)
                                        ? 'border-input text-foreground hover:border-indigo-300 hover:bg-muted/50'
                                        : 'cursor-not-allowed border-dashed border-input text-muted-foreground/50',
                            ]"
                            @click="selecionarModulo(m)"
                        >
                            <component :is="m.icon" class="size-4" />
                            {{ m.label }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Módulo ativo: Quadro de Horário (depende só da turma) -->
            <QuadroHorarioPanel
                v-if="!semVinculo && turId && moduloAtivo === 'quadro-horario'"
                :key="`qh-${turId}-${recarregarSeq}`"
                :tur-id="turId!"
            />

            <!-- Módulo ativo: Avaliação Numérica -->
            <NotasNumericaPanel
                v-if="!semVinculo && contextoCompleto && moduloAtivo === 'notas-numerica'"
                :key="`nn-${turId}-${disId}-${uniId}-${recarregarSeq}`"
                :anl-id="anlId!"
                :esc-id="escId!"
                :tur-id="turId!"
                :dis-id="disId!"
                :uni-id="uniId!"
            />

            <!-- Módulo ativo: Avaliação Conceitual -->
            <NotasConceitualPanel
                v-if="!semVinculo && contextoCompleto && moduloAtivo === 'notas-conceitual'"
                :key="`nc-${turId}-${disId}-${uniId}-${recarregarSeq}`"
                :anl-id="anlId!"
                :esc-id="escId!"
                :tur-id="turId!"
                :dis-id="disId!"
                :uni-id="uniId!"
            />

            <!-- Módulo ativo: Avaliação Descritiva -->
            <AvaliacaoDescritivaPanel
                v-if="!semVinculo && contextoCompleto && moduloAtivo === 'avaliacao-descritiva'"
                :key="`${anlId}-${escId}-${turId}-${disId}-${uniId}-${recarregarSeq}`"
                :anl-id="anlId!"
                :esc-id="escId!"
                :tur-id="turId!"
                :dis-id="disId!"
                :uni-id="uniId!"
            />

            <!-- Módulo ativo: Frequência (faltas) — turma + período, sem disciplina -->
            <FaltasPanel
                v-if="!semVinculo && turId && uniId && moduloAtivo === 'faltas'"
                :key="`fal-${turId}-${uniId}-${recarregarSeq}`"
                :anl-id="anlId!"
                :esc-id="escId!"
                :tur-id="turId!"
                :uni-id="uniId!"
            />

            <!-- Estados de espera (sem turma ou nenhum módulo acionado) -->
            <section
                v-if="!semVinculo && !painelVisivel"
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed bg-card/50 p-10 text-center"
            >
                <BookOpenCheck class="size-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    {{
                        turId
                            ? 'Selecione um módulo acima para carregar.'
                            : 'Selecione escola e turma para começar.'
                    }}
                </p>
            </section>
        </div>
    </AppLayout>
</template>
