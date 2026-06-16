<script setup lang="ts">
import AvaliacaoDescritivaPanel from '@/components/diario/AvaliacaoDescritivaPanel.vue';
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
import { BookOpenCheck, ClipboardList } from 'lucide-vue-next';
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

// Módulos do diário (compartilham os mesmos filtros). Carregam só quando o
// usuário aciona o gatilho (clique no módulo) — nunca imediato.
const moduloAtivo = ref<string | null>(null);
const modulos = [{ key: 'avaliacao-descritiva', label: 'Avaliação Descritiva', icon: ClipboardList }];

// Trocar qualquer filtro descarta o módulo carregado (exige novo clique).
watch(() => [anlId.value, escId.value, turId.value, disId.value, uniId.value], () => {
    moduloAtivo.value = null;
});

onMounted(() => {
    anlId.value = anlInicial.value;
});

const semVinculo = computed(() => props.anosLetivos.length === 0);
</script>

<template>
    <Head title="Diário de Classe" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
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

            <!-- Seletor de contexto -->
            <section v-else class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">Contexto</h2>
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
            <section v-if="!semVinculo && contextoCompleto" class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">Diário</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="m in modulos"
                        :key="m.key"
                        type="button"
                        :class="[
                            'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition',
                            moduloAtivo === m.key
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                : 'border-input text-foreground hover:border-indigo-300 hover:bg-muted/50',
                        ]"
                        @click="moduloAtivo = m.key"
                    >
                        <component :is="m.icon" class="size-4" />
                        {{ m.label }}
                    </button>
                </div>
            </section>

            <!-- Módulo ativo: Avaliação Descritiva -->
            <AvaliacaoDescritivaPanel
                v-if="!semVinculo && contextoCompleto && moduloAtivo === 'avaliacao-descritiva'"
                :key="`${anlId}-${escId}-${turId}-${disId}-${uniId}`"
                :anl-id="anlId!"
                :esc-id="escId!"
                :tur-id="turId!"
                :dis-id="disId!"
                :uni-id="uniId!"
            />

            <!-- Estados de espera (sem contexto ou nenhum módulo acionado) -->
            <section
                v-if="!semVinculo && !(contextoCompleto && moduloAtivo)"
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed bg-card/50 p-10 text-center"
            >
                <BookOpenCheck class="size-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    {{
                        contextoCompleto
                            ? 'Selecione um módulo acima para carregar.'
                            : 'Selecione escola, turma, disciplina e período para começar.'
                    }}
                </p>
            </section>
        </div>
    </AppLayout>
</template>
