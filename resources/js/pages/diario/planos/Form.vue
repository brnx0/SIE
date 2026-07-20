<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { usePermissao } from '@/composables/usePermissao';
import PdfPreviewModal from '@/components/common/PdfPreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    AnoLetivoResumo,
    IndicadorResumo,
    PlanoAula,
    PlanoDisciplinaResumo,
    PlanoStatus,
    PlanoUnidade,
    ProfessorResumoDiario,
} from '@/types/diario';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Save, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    plano: PlanoAula | null;
    professor: ProfessorResumoDiario;
    anosLetivos: AnoLetivoResumo[];
    indicadoresSelecionados: number[];
    podeEditar?: boolean;
}>(), { podeEditar: true });

const { isReadOnly } = usePermissao();

const isEdit = computed(() => !!props.plano);

// Leitura quando: status travado (aprovado/reprovado), sem permissão de edição
// (não é o dono / role só-visualização).
const statusAtual = computed<PlanoStatus>(() => props.plano?.dpa_status ?? 'pendente');
const readonly = computed(() =>
    ['aprovado', 'reprovado'].includes(statusAtual.value) || !props.podeEditar || isReadOnly.value,
);

// Ano letivo: trava se 1 só
const anlInicial = computed<number | null>(() => {
    if (props.plano) return props.plano.dpa_anl_id;
    if (props.anosLetivos.length === 1) return props.anosLetivos[0].anl_id;
    const exer = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
    return exer?.anl_id ?? null;
});

const form = useForm({
    dpa_esc_id: props.plano?.dpa_esc_id ?? null,
    dpa_anl_id: anlInicial.value,
    dpa_ser_id: props.plano?.dpa_ser_id ?? null,
    dpa_dis_id: props.plano?.dpa_dis_id ?? null,
    dpa_uni_id: props.plano?.dpa_uni_id ?? null,
    dpa_tema: props.plano?.dpa_tema ?? '',
    dpa_dt_inicio: props.plano?.dpa_dt_inicio?.substring(0, 10) ?? '',
    dpa_dt_fim: props.plano?.dpa_dt_fim?.substring(0, 10) ?? '',
    dpa_objeto_conhecimento: props.plano?.dpa_objeto_conhecimento ?? '',
    dpa_estrategias: props.plano?.dpa_estrategias ?? '',
    dpa_recursos: props.plano?.dpa_recursos ?? '',
    dpa_competencias: props.plano?.dpa_competencias ?? '',
    dpa_avaliacao: props.plano?.dpa_avaliacao ?? '',
    dpa_objetivos_complementares: props.plano?.dpa_objetivos_complementares ?? '',
    indicadores: [...props.indicadoresSelecionados] as number[],
});

const series = ref<{ ser_id: number; ser_nome: string }[]>([]);
const disciplinas = ref<PlanoDisciplinaResumo[]>([]);
const unidades = ref<PlanoUnidade[]>([]);
const indicadores = ref<IndicadorResumo[]>([]);
const escolas = ref<{ esc_id: number; esc_nome: string }[]>([]);

const fetchEscolas = async () => {
    if (!form.dpa_anl_id) {
        escolas.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/escolas', window.location.origin);
    url.searchParams.set('anl_id', String(form.dpa_anl_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) escolas.value = await r.json();
};

const fetchSeries = async () => {
    if (!form.dpa_anl_id || !form.dpa_esc_id) {
        series.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/series', window.location.origin);
    url.searchParams.set('anl_id', String(form.dpa_anl_id));
    url.searchParams.set('esc_id', String(form.dpa_esc_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) series.value = await r.json();
};

const fetchDisciplinas = async () => {
    if (!form.dpa_ser_id) {
        disciplinas.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/disciplinas', window.location.origin);
    url.searchParams.set('ser_id', String(form.dpa_ser_id));
    if (form.dpa_esc_id) url.searchParams.set('esc_id', String(form.dpa_esc_id));
    if (form.dpa_anl_id) url.searchParams.set('anl_id', String(form.dpa_anl_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) disciplinas.value = await r.json();
};

const fetchUnidades = async () => {
    if (!form.dpa_anl_id) {
        unidades.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/unidades', window.location.origin);
    url.searchParams.set('anl_id', String(form.dpa_anl_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) unidades.value = await r.json();
};

const filtroIndicador = ref('');
const fetchIndicadores = async () => {
    if (!form.dpa_ser_id) {
        indicadores.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/indicadores', window.location.origin);
    url.searchParams.set('ser_id', String(form.dpa_ser_id));
    if (form.dpa_dis_id) url.searchParams.set('dis_id', String(form.dpa_dis_id));
    if (form.dpa_anl_id) url.searchParams.set('anl_id', String(form.dpa_anl_id));
    if (filtroIndicador.value) url.searchParams.set('search', filtroIndicador.value);
    if (form.indicadores.length) url.searchParams.set('incluir_ids', form.indicadores.join(','));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) indicadores.value = await r.json();
};

let filtroTimer: ReturnType<typeof setTimeout> | null = null;
watch(filtroIndicador, () => {
    if (filtroTimer) clearTimeout(filtroTimer);
    filtroTimer = setTimeout(fetchIndicadores, 250);
});

// Cascatas
watch(() => form.dpa_anl_id, () => {
    if (!isEdit.value) {
        form.dpa_esc_id = null;
        form.dpa_ser_id = null;
        form.dpa_dis_id = null;
        form.dpa_uni_id = null;
        series.value = [];
    }
    fetchEscolas();
    fetchUnidades();
});
watch(() => form.dpa_esc_id, () => {
    if (!isEdit.value) {
        form.dpa_ser_id = null;
        form.dpa_dis_id = null;
    }
    fetchSeries();
});
watch(() => form.dpa_ser_id, () => {
    if (!isEdit.value) form.dpa_dis_id = null;
    fetchDisciplinas();
    fetchIndicadores();
});
watch(() => form.dpa_dis_id, fetchIndicadores);

onMounted(async () => {
    await Promise.all([fetchEscolas(), fetchUnidades()]);
    if (form.dpa_esc_id) await fetchSeries();
    if (form.dpa_ser_id) await fetchDisciplinas();
    if (form.dpa_ser_id) await fetchIndicadores();
});

const labelUnidade = computed(() => {
    if (unidades.value.length === 0) return 'Unidade';
    const t = (unidades.value[0].uni_tipo || '').toLowerCase();
    if (t.includes('bimes')) return 'Bimestre';
    if (t.includes('trim')) return 'Trimestre';
    return 'Unidade';
});
const artigoUnidade = computed(() => (labelUnidade.value === 'Unidade' ? 'a' : 'o'));

const formatDateTime = (d?: string | null) => {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return '—';
    return dt.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};
const fmtDate = (d?: string | null) => {
    if (!d) return '';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

// Garante que o valor salvo no registro sempre apareça no lookup, mesmo
// que o filtro (vínculo do professor) não o retorne — padrão incluir_ids.
const itemsSerie = computed(() => {
    const list = series.value.map((s) => ({ id: s.ser_id, label: s.ser_nome }));
    const p = (props.plano as any)?.serie;
    if (p && !list.some((i) => i.id === p.ser_id)) {
        list.unshift({ id: p.ser_id, label: p.ser_nome });
    }
    return list;
});
const itemsDisciplina = computed(() => {
    const list = disciplinas.value.map((d) => ({ id: d.dis_id, label: d.dis_nome }));
    const s = props.plano?.disciplina;
    if (s && !list.some((i) => i.id === s.dis_id)) list.unshift({ id: s.dis_id, label: s.dis_nome });
    return list;
});
const unidadeLabel = (u: PlanoUnidade) => `${u.uni_numero}º ${labelUnidade.value} (${fmtDate(u.uni_dt_inicio)} até ${fmtDate(u.uni_dt_fim)})`;
const itemsUnidade = computed(() => {
    const list = unidades.value.map((u) => ({ id: u.uni_id, label: unidadeLabel(u) }));
    const s = props.plano?.unidade;
    if (s && !list.some((i) => i.id === s.uni_id)) list.unshift({ id: s.uni_id, label: unidadeLabel(s) });
    return list;
});
const itemsEscola = computed(() => {
    const list = escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome }));
    const s = props.plano?.escola;
    if (s && !list.some((i) => i.id === s.esc_id)) list.unshift({ id: s.esc_id, label: s.esc_nome });
    return list;
});

// Período selecionado: limita o seletor de data ao intervalo da unidade.
const unidadeSelecionada = computed<PlanoUnidade | null>(() => {
    if (!form.dpa_uni_id) return null;
    return unidades.value.find((u) => u.uni_id === form.dpa_uni_id)
        ?? (props.plano?.unidade?.uni_id === form.dpa_uni_id ? props.plano.unidade : null);
});
const periodoMin = computed(() => unidadeSelecionada.value?.uni_dt_inicio?.substring(0, 10) ?? undefined);
const periodoMax = computed(() => unidadeSelecionada.value?.uni_dt_fim?.substring(0, 10) ?? undefined);

// Trocar de período invalida data já digitada fora dele — limpa em vez de deixar submeter e falhar.
const foraDoPeriodo = (d: string) => !!d && !!periodoMin.value && !!periodoMax.value
    && (d < periodoMin.value || d > periodoMax.value);

watch(() => form.dpa_uni_id, () => {
    if (foraDoPeriodo(form.dpa_dt_inicio)) form.dpa_dt_inicio = '';
    if (foraDoPeriodo(form.dpa_dt_fim)) form.dpa_dt_fim = '';
});
const itemsAno = computed(() => {
    const list = props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) }));
    const s = props.plano?.anoLetivo;
    if (s && !list.some((i) => i.id === s.anl_id)) list.unshift({ id: s.anl_id, label: String(s.anl_ano) });
    return list;
});

const toggleIndicador = (id: number) => {
    if (readonly.value) return;
    const idx = form.indicadores.indexOf(id);
    if (idx === -1) form.indicadores.push(id);
    else form.indicadores.splice(idx, 1);
};

const submit = () => {
    if (isEdit.value) {
        form.put(`/diario/planos/${props.plano!.dpa_id}`, { preserveScroll: true });
    } else {
        form.post('/diario/planos', { preserveScroll: true });
    }
};

const previewUrl = ref<string | null>(null);
const imprimir = () => {
    if (!props.plano?.dpa_id) return;
    previewUrl.value = `/diario/planos/${props.plano.dpa_id}/pdf`;
};

const statusBadge = (s: PlanoStatus) => ({
    pendente: 'bg-amber-50 text-amber-700',
    aprovado: 'bg-emerald-50 text-emerald-700',
    reprovado: 'bg-rose-50 text-rose-700',
    correcao: 'bg-sky-50 text-sky-700',
}[s]);

const statusLabel = (s: PlanoStatus) => ({
    pendente: 'Pendente',
    aprovado: 'Aprovado',
    reprovado: 'Reprovado',
    correcao: 'Em correção',
}[s]);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Plano de Aula', href: '/diario/planos' },
    { title: isEdit.value ? `Plano #${props.plano!.dpa_id}` : 'Novo plano', href: '#' },
];
</script>

<template>
    <Head :title="isEdit ? `Editar Plano #${plano!.dpa_id}` : 'Novo Plano de Aula'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <!-- Header + ações topo -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        {{ isEdit ? `Editar Plano de Aula` : 'Novo Plano de Aula' }}
                    </h1>
                    <div class="mt-1 flex items-center gap-2">
                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(statusAtual)]">
                            {{ statusLabel(statusAtual) }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="router.visit('/diario/planos')">
                        <ArrowLeft class="mr-2 size-4" /> Voltar
                    </Button>
                    <Button v-if="isEdit" variant="outline" @click="imprimir">
                        <Printer class="mr-2 size-4" /> Imprimir
                    </Button>
                    <Button v-if="!readonly" :disabled="form.processing"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="submit">
                        <Save class="mr-2 size-4" /> Salvar
                    </Button>
                    <span v-else class="flex items-center rounded-md bg-muted px-3 py-1.5 text-xs text-muted-foreground">Somente leitura</span>
                </div>
            </div>

            <!-- Cabeçalho -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">Cabeçalho</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="md:col-span-12">
                        <Label>Professor</Label>
                        <Input :model-value="professor.fun_nome" readonly />
                    </div>

                    <div class="md:col-span-12">
                        <Label>Tema <span class="text-rose-600">*</span></Label>
                        <Input v-model="form.dpa_tema" :disabled="readonly" maxlength="50" />
                        <InputError :message="form.errors.dpa_tema" />
                    </div>
                    <div class="md:col-span-4">
                        <Label>{{ labelUnidade }} <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_uni_id" :items="itemsUnidade"
                            placeholder="Selecione..." :invalid="!!form.errors.dpa_uni_id" />
                        <InputError :message="form.errors.dpa_uni_id" />
                    </div>

                    <div class="md:col-span-2">
                        <Label>Data Inicial <span class="text-rose-600">*</span></Label>
                        <Input type="date" v-model="form.dpa_dt_inicio" :disabled="readonly || !form.dpa_uni_id"
                            :min="periodoMin" :max="periodoMax" />
                        <InputError :message="form.errors.dpa_dt_inicio" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Data Final <span class="text-rose-600">*</span></Label>
                        <Input type="date" v-model="form.dpa_dt_fim" :disabled="readonly || !form.dpa_uni_id"
                            :min="periodoMin" :max="periodoMax" />
                        <InputError :message="form.errors.dpa_dt_fim" />
                    </div>
                    <p v-if="!readonly" class="text-xs text-muted-foreground md:col-span-8">
                        <template v-if="!form.dpa_uni_id">Selecione {{ artigoUnidade }} {{ labelUnidade.toLowerCase() }} acima para liberar as datas.</template>
                        <template v-else>Datas permitidas: {{ fmtDate(periodoMin) }} até {{ fmtDate(periodoMax) }}.</template>
                    </p>

                    <div class="md:col-span-3">
                        <Label>Ano Letivo <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_anl_id" :items="itemsAno"
                            placeholder="Selecione o ano..." :invalid="!!form.errors.dpa_anl_id" />
                        <InputError :message="form.errors.dpa_anl_id" />
                    </div>
                    <div class="md:col-span-4">
                        <Label>Escola <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_esc_id" :items="itemsEscola"
                            placeholder="Selecione a escola..." :invalid="!!form.errors.dpa_esc_id" />
                        <InputError :message="form.errors.dpa_esc_id" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Ano Escolar <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_ser_id" :items="itemsSerie"
                            placeholder="Selecione..." :invalid="!!form.errors.dpa_ser_id" />
                        <InputError :message="form.errors.dpa_ser_id" />
                    </div>
                    <div class="md:col-span-3">
                        <Label>Componente Curricular <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_dis_id" :items="itemsDisciplina"
                            placeholder="Selecione..." :invalid="!!form.errors.dpa_dis_id" />
                        <InputError :message="form.errors.dpa_dis_id" />
                    </div>
                </div>
            </section>

            <!-- Indicadores -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground">Objetivos de Aprendizagem e Desenvolvimento (Indicadores)</h2>
                    <span class="text-xs text-muted-foreground">{{ form.indicadores.length }} selecionado(s)</span>
                </div>
                <div class="relative mb-3 max-w-md">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filtroIndicador" placeholder="Filtrar indicadores..." class="pl-9" />
                </div>
                <div class="max-h-80 overflow-y-auto rounded-md border">
                    <div v-if="indicadores.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        {{ form.dpa_ser_id ? 'Nenhum indicador encontrado.' : 'Selecione uma série para listar indicadores.' }}
                    </div>
                    <label v-for="ind in indicadores" :key="ind.ind_id"
                        class="flex cursor-pointer items-start gap-3 border-b px-4 py-2 hover:bg-muted/40 last:border-b-0"
                        :class="{ 'opacity-60': readonly }">
                        <input type="checkbox"
                            :checked="form.indicadores.includes(ind.ind_id)"
                            :disabled="readonly"
                            @change="toggleIndicador(ind.ind_id)"
                            class="mt-1 size-4 accent-indigo-600" />
                        <span class="text-sm">{{ ind.ind_descricao }}</span>
                    </label>
                </div>
            </section>

            <!-- Detalhamento -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">Detalhamento</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <Label>Objetivos Complementares / Recomposição / Descritor</Label>
                        <textarea v-model="form.dpa_objetivos_complementares" rows="4" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dpa_objetivos_complementares" />
                    </div>
                    <div>
                        <Label>Objeto do conhecimento / Saberes <span class="text-rose-600">*</span></Label>
                        <textarea v-model="form.dpa_objeto_conhecimento" rows="4" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dpa_objeto_conhecimento" />
                    </div>
                    <div>
                        <Label>Estratégias / Metodologia</Label>
                        <textarea v-model="form.dpa_estrategias" rows="8" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm font-mono" />
                        <InputError :message="form.errors.dpa_estrategias" />
                    </div>
                    <div>
                        <Label>Recursos didáticos <span class="text-rose-600">*</span></Label>
                        <textarea v-model="form.dpa_recursos" rows="3" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dpa_recursos" />
                    </div>
                    <div>
                        <Label>Competências Gerais</Label>
                        <textarea v-model="form.dpa_competencias" rows="4" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dpa_competencias" />
                    </div>
                    <div>
                        <Label>Avaliação</Label>
                        <textarea v-model="form.dpa_avaliacao" rows="4" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dpa_avaliacao" />
                    </div>

                </div>
            </section>

            <!-- Coordenação (só se status != pendente) -->
            <section v-if="isEdit && statusAtual !== 'pendente'" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground">Observação do coordenador</h2>
                    <div v-if="(plano as any)?.validado_por || (plano as any)?.dpa_validado_em" class="text-xs text-muted-foreground">
                        Validado por <span class="font-medium">{{ (plano as any)?.validado_por?.name ?? '—' }}</span>
                        em <span class="font-medium">{{ formatDateTime((plano as any)?.dpa_validado_em) }}</span>
                    </div>
                </div>
                <textarea :value="plano?.dpa_obs_coordenador ?? ''" readonly rows="4"
                    class="w-full rounded-md border bg-muted/30 p-2 text-sm" />
            </section>

            <!-- Rodapé navegação -->
            <div class="flex justify-between border-t pt-4">
                <Button variant="outline" @click="router.visit('/diario/planos')">Voltar</Button>
                <Button :disabled="form.processing || readonly"
                    class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit">
                    <Save class="mr-2 size-4" /> Salvar
                </Button>
            </div>
        </div>
        <PdfPreviewModal v-if="previewUrl" :url="previewUrl" :filename="`plano_${plano?.dpa_id ?? ''}.pdf`" title="Pré-visualização do Plano" @close="previewUrl = null" />
    </AppLayout>
</template>
