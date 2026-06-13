<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
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
    PlanoTurmaResumo,
    PlanoUnidade,
    ProfessorResumoDiario,
} from '@/types/diario';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Save, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    plano: PlanoAula | null;
    professor: ProfessorResumoDiario;
    anosLetivos: AnoLetivoResumo[];
    indicadoresSelecionados: number[];
}>();

const isEdit = computed(() => !!props.plano);

const page = usePage();
const params = computed<any>(() => (page.props as any).params ?? {});

// Status -> readonly se aprovado/reprovado
const statusAtual = computed<PlanoStatus>(() => props.plano?.dpa_status ?? 'pendente');
const readonly = computed(() => ['aprovado', 'reprovado'].includes(statusAtual.value));

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
    dpa_tur_id: props.plano?.dpa_tur_id ?? null,
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
    indicadores: [...props.indicadoresSelecionados] as number[],
});

const turmas = ref<PlanoTurmaResumo[]>([]);
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

const fetchTurmas = async () => {
    if (!form.dpa_anl_id || !form.dpa_esc_id) {
        turmas.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(form.dpa_anl_id));
    url.searchParams.set('esc_id', String(form.dpa_esc_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};

const fetchDisciplinas = async () => {
    if (!form.dpa_tur_id) {
        disciplinas.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/disciplinas', window.location.origin);
    url.searchParams.set('tur_id', String(form.dpa_tur_id));
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
    if (!form.dpa_tur_id) {
        indicadores.value = [];
        return;
    }
    const url = new URL('/api/diario/planos/indicadores', window.location.origin);
    url.searchParams.set('tur_id', String(form.dpa_tur_id));
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
        form.dpa_tur_id = null;
        form.dpa_dis_id = null;
        form.dpa_uni_id = null;
        turmas.value = [];
    }
    fetchEscolas();
    fetchUnidades();
});
watch(() => form.dpa_esc_id, () => {
    if (!isEdit.value) {
        form.dpa_tur_id = null;
        form.dpa_dis_id = null;
    }
    fetchTurmas();
});
watch(() => form.dpa_tur_id, () => {
    if (!isEdit.value) form.dpa_dis_id = null;
    fetchDisciplinas();
    fetchIndicadores();
});
watch(() => form.dpa_dis_id, fetchIndicadores);

onMounted(async () => {
    await Promise.all([fetchEscolas(), fetchUnidades()]);
    if (form.dpa_esc_id) await fetchTurmas();
    if (form.dpa_tur_id) await fetchDisciplinas();
    if (form.dpa_tur_id) await fetchIndicadores();
});

const labelUnidade = computed(() => {
    if (unidades.value.length === 0) return 'Unidade';
    const t = (unidades.value[0].uni_tipo || '').toLowerCase();
    if (t.includes('bimes')) return 'Bimestre';
    if (t.includes('trim')) return 'Trimestre';
    return 'Unidade';
});

const formatDateTime = (d?: string | null) => {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return '—';
    return dt.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};
const fmtDate = (d: string) => {
    if (!d) return '';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

const itemsTurma = computed(() => turmas.value.map((t) => ({
    id: t.tur_id,
    label: t.ser_nome ? `${t.ser_nome} / ${t.tur_nome}` : t.tur_nome,
})));
const itemsDisciplina = computed(() => disciplinas.value.map((d) => ({ id: d.dis_id, label: d.dis_nome })));
const itemsUnidade = computed(() => unidades.value.map((u) => ({
    id: u.uni_id,
    label: `${u.uni_numero}º ${labelUnidade.value} (${fmtDate(u.uni_dt_inicio)} até ${fmtDate(u.uni_dt_fim)})`,
})));
const itemsEscola = computed(() => escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));

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
                    <Button :disabled="form.processing || readonly"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="submit">
                        <Save class="mr-2 size-4" /> Salvar
                    </Button>
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

                    <div class="md:col-span-8">
                        <Label>Tema <span class="text-rose-600">*</span></Label>
                        <Input v-model="form.dpa_tema" :disabled="readonly" maxlength="255" />
                        <InputError :message="form.errors.dpa_tema" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Data Inicial <span class="text-rose-600">*</span></Label>
                        <Input type="date" v-model="form.dpa_dt_inicio" :disabled="readonly" />
                        <InputError :message="form.errors.dpa_dt_inicio" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Data Final <span class="text-rose-600">*</span></Label>
                        <Input type="date" v-model="form.dpa_dt_fim" :disabled="readonly" />
                        <InputError :message="form.errors.dpa_dt_fim" />
                    </div>

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
                        <Label>Turma <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_tur_id" :items="itemsTurma"
                            placeholder="Selecione..." :invalid="!!form.errors.dpa_tur_id" />
                        <InputError :message="form.errors.dpa_tur_id" />
                    </div>
                    <div class="md:col-span-3">
                        <Label>Componente Curricular <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_dis_id" :items="itemsDisciplina"
                            placeholder="Selecione..." :invalid="!!form.errors.dpa_dis_id" />
                        <InputError :message="form.errors.dpa_dis_id" />
                    </div>

                    <div class="md:col-span-12">
                        <Label>{{ labelUnidade }} <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dpa_uni_id" :items="itemsUnidade"
                            placeholder="Selecione..." :invalid="!!form.errors.dpa_uni_id" />
                        <InputError :message="form.errors.dpa_uni_id" />
                    </div>
                </div>
            </section>

            <!-- Indicadores -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground">Objetivos Complementares / Recomposição / Descritor</h2>
                    <span class="text-xs text-muted-foreground">{{ form.indicadores.length }} selecionado(s)</span>
                </div>
                <div class="relative mb-3 max-w-md">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="filtroIndicador" placeholder="Filtrar indicadores..." class="pl-9" />
                </div>
                <div class="max-h-80 overflow-y-auto rounded-md border">
                    <div v-if="indicadores.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        {{ form.dpa_tur_id ? 'Nenhum indicador encontrado.' : 'Selecione uma turma para listar indicadores.' }}
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
