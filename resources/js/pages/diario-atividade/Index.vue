<script setup lang="ts">
import AtividadeFrequenciaPanel from '@/components/diario/AtividadeFrequenciaPanel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivoResumo, PlanoUnidade, ProfessorResumoDiario } from '@/types/diario';
import { Head } from '@inertiajs/vue3';
import { Activity, CalendarCheck, CalendarDays, Pencil } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface TurmaAtividade {
    tur_id: number;
    tur_nome: string;
    tur_esc_id: number;
    tur_anl_id: number;
    esc_nome: string;
    tur_dias_funcionamento: string[];
    tur_hora_inicio: string | null;
    tur_hora_fim: string | null;
    tur_aee_sala: string | null;
}

const DIAS_LABEL: Record<string, string> = { seg: 'Segunda', ter: 'Terça', qua: 'Quarta', qui: 'Quinta', sex: 'Sexta', sab: 'Sábado', dom: 'Domingo' };
const DIAS_ORDEM = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];

const props = defineProps<{
    professor: ProfessorResumoDiario;
    anosLetivos: AnoLetivoResumo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Diário Atividade', href: '/diario-atividade' }];

const anlId = ref<number | null>(null);
const escId = ref<number | null>(null);
const turId = ref<number | null>(null);
const uniId = ref<number | null>(null);

const escolas = ref<{ esc_id: number; esc_nome: string }[]>([]);
const turmas = ref<TurmaAtividade[]>([]);
const unidades = ref<PlanoUnidade[]>([]);

const anlInicial = computed<number | null>(() => {
    if (props.anosLetivos.length === 1) return props.anosLetivos[0].anl_id;
    const exer = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
    return exer?.anl_id ?? props.anosLetivos[0]?.anl_id ?? null;
});

const fetchEscolas = async () => {
    if (!anlId.value) { escolas.value = []; return; }
    const url = new URL('/api/diario-atividade/contexto/escolas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) escolas.value = await r.json();
};
const fetchTurmas = async () => {
    if (!anlId.value || !escId.value) { turmas.value = []; return; }
    const url = new URL('/api/diario-atividade/contexto/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    url.searchParams.set('esc_id', String(escId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};
const fetchUnidades = async () => {
    if (!anlId.value) { unidades.value = []; uniId.value = null; return; }
    const url = new URL('/api/diario-atividade/contexto/unidades', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    unidades.value = r.ok ? await r.json() : [];
    selecionarUnidadeCorrente();
};

const hojeStr = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const selecionarUnidadeCorrente = () => {
    if (!unidades.value.length) { uniId.value = null; return; }
    const hoje = hojeStr();
    const fimDe = (u: PlanoUnidade) => u.uni_dt_fim_efetivo ?? u.uni_dt_fim;
    const corrente = unidades.value.find((u) => u.uni_dt_inicio <= hoje && hoje <= fimDe(u));
    const iniciada = [...unidades.value].reverse().find((u) => u.uni_dt_inicio <= hoje);
    uniId.value = (corrente ?? iniciada ?? unidades.value[0]).uni_id;
};

const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
const fmtBr = (d?: string | null) => {
    if (!d) return '';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: t.tur_nome })));
const itemsUnidade = computed(() => unidades.value.map((u) => ({ id: u.uni_id, label: `${u.uni_numero}º ${capitalize(u.uni_tipo)} (${fmtBr(u.uni_dt_inicio)} a ${fmtBr(u.uni_dt_fim)})` })));
const labelPeriodo = computed(() => {
    const tipos = new Set(unidades.value.map((u) => u.uni_tipo));
    return tipos.size === 1 ? capitalize([...tipos][0]) : 'Período';
});

const anoTravado = computed(() => props.anosLetivos.length === 1);
const escTravada = computed(() => escolas.value.length === 1);
const turTravada = computed(() => turmas.value.length === 1);
const uniTravada = computed(() => unidades.value.length === 1);
const labelDe = (items: { id: number; label: string }[], id: number | null) => items.find((i) => i.id === id)?.label ?? '';

watch(escolas, () => { if (escolas.value.length === 1) escId.value = escolas.value[0].esc_id; });
watch(turmas, () => { if (turmas.value.length === 1) turId.value = turmas.value[0].tur_id; });

watch(anlId, () => {
    escId.value = null;
    turId.value = null;
    turmas.value = [];
    fetchEscolas();
    fetchUnidades();
});
watch(escId, () => {
    turId.value = null;
    fetchTurmas();
});

onMounted(() => { anlId.value = anlInicial.value; });

const semVinculo = computed(() => props.anosLetivos.length === 0);

const turmaSel = computed(() => turmas.value.find((t) => t.tur_id === turId.value) ?? null);
const diasAtendimento = computed(() => {
    const dias = turmaSel.value?.tur_dias_funcionamento ?? [];
    return DIAS_ORDEM.filter((d) => dias.includes(d)).map((d) => DIAS_LABEL[d]);
});
const horarioAtendimento = computed(() => {
    const t = turmaSel.value;
    if (!t?.tur_hora_inicio && !t?.tur_hora_fim) return null;
    return `${t?.tur_hora_inicio ?? '—'} às ${t?.tur_hora_fim ?? '—'}`;
});

const moduloAtivo = ref<string | null>(null);
const recarregarSeq = ref(0);
const podeFrequencia = computed(() => !!(turId.value && uniId.value));
const selecionarFrequencia = () => {
    if (!podeFrequencia.value) return;
    if (moduloAtivo.value === 'frequencia') recarregarSeq.value++;
    else moduloAtivo.value = 'frequencia';
};
watch(() => [anlId.value, escId.value, turId.value, uniId.value], () => { moduloAtivo.value = null; });

const painelVisivel = computed(() => moduloAtivo.value === 'frequencia' && podeFrequencia.value);
const contextoExpandido = ref(true);
watch(painelVisivel, (v) => { if (v) contextoExpandido.value = false; });
const contextoChips = computed(() => {
    const c: { k: string; v: string }[] = [];
    if (anlId.value) c.push({ k: 'Ano', v: labelDe(itemsAno.value, anlId.value) });
    if (escId.value) c.push({ k: 'Escola', v: labelDe(itemsEscola.value, escId.value) });
    if (turId.value) c.push({ k: 'Turma', v: labelDe(itemsTurma.value, turId.value) });
    if (uniId.value) c.push({ k: labelPeriodo.value, v: labelDe(itemsUnidade.value, uniId.value) });
    return c;
});
</script>

<template>
    <Head title="Diário Atividade" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        <Activity class="size-6 text-indigo-600" /> Diário de Atividade
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">Selecione a escola e a turma de atividade para os lançamentos.</p>
                </div>
            </div>

            <div v-if="semVinculo" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                Nenhum ano letivo em exercício. Procure a administração.
            </div>

            <!-- Barra de contexto compacta -->
            <section v-if="!semVinculo && turId && !contextoExpandido" class="flex flex-wrap items-center gap-2 rounded-xl border bg-card px-4 py-2.5 shadow-sm">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ professor.fun_nome }}</span>
                <span class="text-muted-foreground/40">·</span>
                <span v-for="chip in contextoChips" :key="chip.k" class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs">
                    <span class="text-muted-foreground">{{ chip.k }}:</span><span class="font-medium">{{ chip.v }}</span>
                </span>
                <span v-if="diasAtendimento.length" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                    <CalendarDays class="size-3.5" /> {{ diasAtendimento.join(', ') }}<template v-if="horarioAtendimento"> · {{ horarioAtendimento }}</template>
                </span>
                <button type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-md border border-input px-2.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted" @click="contextoExpandido = true">
                    <Pencil class="size-3.5" /> Alterar
                </button>
            </section>

            <!-- Seletor de contexto -->
            <section v-if="!semVinculo && contextoExpandido" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground">Contexto</h2>
                    <button v-if="painelVisivel" type="button" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400" @click="contextoExpandido = false">Recolher</button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="md:col-span-12">
                        <Label>Professor / Monitor</Label>
                        <Input :model-value="professor.fun_nome" readonly />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Ano Letivo</Label>
                        <Input v-if="anoTravado" :model-value="labelDe(itemsAno, anlId)" readonly />
                        <LocalCombobox v-else v-model="anlId" :items="itemsAno" placeholder="Ano..." />
                    </div>
                    <div class="md:col-span-5">
                        <Label>Escola</Label>
                        <Input v-if="escTravada" :model-value="labelDe(itemsEscola, escId)" readonly />
                        <LocalCombobox v-else v-model="escId" :items="itemsEscola" placeholder="Selecione a escola..." />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Turma</Label>
                        <Input v-if="turTravada" :model-value="labelDe(itemsTurma, turId)" readonly />
                        <LocalCombobox v-else v-model="turId" :items="itemsTurma" placeholder="Turma..." />
                    </div>
                    <div class="md:col-span-3">
                        <Label>{{ labelPeriodo }}</Label>
                        <Input v-if="uniTravada" :model-value="labelDe(itemsUnidade, uniId)" readonly />
                        <LocalCombobox v-else v-model="uniId" :items="itemsUnidade" placeholder="Selecione..." />
                    </div>
                </div>
            </section>

            <!-- Dias de atendimento (read-only) + módulos -->
            <section v-if="!semVinculo && turId" class="space-y-4">
                <div v-if="contextoExpandido" class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        <CalendarDays class="size-4 text-indigo-600" /> Dias de atendimento — {{ turmaSel?.tur_nome }}
                    </h2>
                    <div v-if="diasAtendimento.length" class="flex flex-wrap gap-2">
                        <span
                            v-for="d in diasAtendimento"
                            :key="d"
                            class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300"
                        >{{ d }}</span>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Nenhum dia de atendimento definido para esta turma.</p>
                    <div v-if="horarioAtendimento || turmaSel?.tur_aee_sala" class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground">
                        <span v-if="horarioAtendimento"><span class="font-medium text-foreground">Horário:</span> {{ horarioAtendimento }}</span>
                        <span v-if="turmaSel?.tur_aee_sala"><span class="font-medium text-foreground">Local:</span> {{ turmaSel.tur_aee_sala }}</span>
                    </div>
                </div>

                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="mb-2 text-sm font-semibold text-muted-foreground">Lançamentos</h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :disabled="!podeFrequencia"
                            :title="!podeFrequencia ? 'Selecione o período.' : ''"
                            :class="[
                                'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition',
                                moduloAtivo === 'frequencia'
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : podeFrequencia
                                        ? 'border-input text-foreground hover:border-indigo-300 hover:bg-muted/50'
                                        : 'cursor-not-allowed border-dashed border-input text-muted-foreground/50',
                            ]"
                            @click="selecionarFrequencia"
                        >
                            <CalendarCheck class="size-4" /> Frequência
                        </button>
                    </div>
                </div>

                <AtividadeFrequenciaPanel
                    v-if="moduloAtivo === 'frequencia' && podeFrequencia"
                    :key="`atf-${turId}-${uniId}-${recarregarSeq}`"
                    :anl-id="anlId!"
                    :esc-id="escId!"
                    :tur-id="turId!"
                    :uni-id="uniId!"
                />
            </section>
            <section v-else-if="!semVinculo" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed bg-card/50 p-10 text-center">
                <Activity class="size-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">Selecione escola e turma para começar.</p>
            </section>
        </div>
    </AppLayout>
</template>
