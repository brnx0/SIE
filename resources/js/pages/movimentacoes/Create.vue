<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeftRight, GraduationCap, Info, Loader2, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const TIPO_META: Record<number, { titulo: string; descricao: string; cor: string; icon: any }> = {
    5: {
        titulo: 'Remanejamento',
        descricao: 'Mover aluno entre turmas da MESMA escola e MESMA série (ex.: troca de turno).',
        cor: 'amber',
        icon: ArrowLeftRight,
    },
    7: {
        titulo: 'Reclassificação',
        descricao: 'Avanço/recuo de série na mesma escola após avaliação. Destino deve ser série DIFERENTE da origem.',
        cor: 'violet',
        icon: GraduationCap,
    },
};

interface Tipo {
    tmv_cod: number;
    tmv_descricao: string;
    tmv_tas_cod_entrada: number | null;
    tmv_tas_cod_saida: number | null;
}

const props = defineProps<{
    tipos: Tipo[];
    anosLetivos: { anl_id: number; anl_ano: number }[];
    escolas: { esc_id: number; esc_nome: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Movimentações', href: '/movimentacoes' },
    { title: 'Nova', href: '/movimentacoes/create' },
];

const { focusFirstError } = useErrorFocus();

const form = useForm({
    tmv_cod: null as number | null,
    tma_id_origem: null as number | null,
    tur_id_destino: null as number | null,
    dt_movimentacao: new Date().toISOString().slice(0, 10),
    protocolo: '',
    observacao: '',
    migrar_notas: true,
    migrar_faltas: true,
});

const tipoSelecionado = computed(() => props.tipos.find(t => t.tmv_cod === form.tmv_cod) ?? null);
const exigeDestino   = computed(() => !!tipoSelecionado.value?.tmv_tas_cod_entrada);
const tipoMeta       = computed(() => (form.tmv_cod != null ? TIPO_META[form.tmv_cod] : null) ?? null);
const isReclassificacao = computed(() => form.tmv_cod === 7);
const isRemanejamento   = computed(() => form.tmv_cod === 5);

// ── Cascata Origem ────────────────────────────────────────────────────────────
const anosLetivos    = ref<{ anl_id: number; anl_ano: number }[]>(props.anosLetivos);
const segmentos      = ref<{ seg_id: number; seg_nome: string }[]>([]);
const series         = ref<{ ser_id: number; ser_nome: string; ser_fl_multi?: boolean }[]>([]);
const turmas         = ref<{ tur_id: number; tur_nome: string }[]>([]);
const alunos         = ref<{ tma_id: number; aln_id: number; aln_nome: string }[]>([]);

const oAnl = ref<number | ''>('');
const oEsc = ref<number | ''>('');
const escolaItems = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const oSeg = ref<number | ''>('');
const oSer = ref<number | ''>('');
const oTur = ref<number | ''>('');

async function loadSegmentos(escId: number, anlId: number) {
    const r = await fetch(`/api/segmentos/by-escola?esc_id=${escId}&anl_id=${anlId}`);
    if (r.ok) segmentos.value = await r.json();
}
async function loadSeries(escId: number, anlId: number, segId: number) {
    const r = await fetch(`/api/series/by-turmas-abertas?esc_id=${escId}&anl_id=${anlId}&seg_id=${segId}`);
    if (r.ok) series.value = await r.json();
}
async function loadTurmas(escId: number, anlId: number, segId: number, serId: number) {
    const p = new URLSearchParams({ esc_id: String(escId), anl_id: String(anlId), seg_id: String(segId), ser_id: String(serId) });
    const r = await fetch(`/api/matriculas/turmas?${p}`);
    if (r.ok) turmas.value = (await r.json()).filter((t: any) => t.tur_situacao === 'ABERTA');
}
async function loadAlunos(turId: number) {
    const r = await fetch(`/api/turmas/${turId}/alunos`);
    if (r.ok) {
        const lista: any[] = await r.json();
        // Lista apenas matriculas sem situacao de saida (ainda ativas na turma).
        alunos.value = lista.filter(a => a.tma_tas_cod_saida == null);
    }
}

watch([oEsc, oAnl], async ([e, a]) => {
    segmentos.value = []; series.value = []; turmas.value = []; alunos.value = [];
    oSeg.value = ''; oSer.value = ''; oTur.value = ''; form.tma_id_origem = null;
    if (e && a) await loadSegmentos(+e, +a);
});
watch(oSeg, async (v) => {
    series.value = []; turmas.value = []; alunos.value = [];
    oSer.value = ''; oTur.value = ''; form.tma_id_origem = null;
    if (v && oEsc.value && oAnl.value) await loadSeries(+oEsc.value, +oAnl.value, +v);
});
watch(oSer, async (v) => {
    turmas.value = []; alunos.value = [];
    oTur.value = ''; form.tma_id_origem = null;
    if (v && oEsc.value && oAnl.value && oSeg.value) await loadTurmas(+oEsc.value, +oAnl.value, +oSeg.value, +v);
});
watch(oTur, async (v) => {
    alunos.value = []; form.tma_id_origem = null;
    if (v) await loadAlunos(+v);
});

// ── Cascata Destino ────────────────────────────────────────────────────────────
const segmentosD = ref<{ seg_id: number; seg_nome: string }[]>([]);
const seriesD    = ref<{ ser_id: number; ser_nome: string; ser_fl_multi?: boolean }[]>([]);
// Séries REAIS do segmento (sem a pseudo-série MULTI) — usadas quando a turma de
// destino é multi e é preciso informar o ano escolar do aluno.
const seriesReaisD = ref<{ ser_id: number; ser_nome: string }[]>([]);
const turmasD    = ref<{ tur_id: number; tur_nome: string }[]>([]);

const dSeg = ref<number | ''>('');
const dSer = ref<number | ''>('');
// Ano escolar do aluno — obrigatório quando a turma de destino é multi, pois lá a
// série da turma é a pseudo-série "MULTI" e não representa o ano do aluno.
const dSerAluno = ref<number | ''>('');

// Turma de destino é multi? (a flag mora na SÉRIE apontada pela turma).
const destinoMulti = computed(
    () => !!seriesD.value.find((s) => s.ser_id === Number(dSer.value))?.ser_fl_multi,
);

async function loadSeriesReaisDestino() {
    if (!dSeg.value) { seriesReaisD.value = []; return; }
    const r = await fetch(`/api/series?seg_id=${dSeg.value}&excluir_multi=1`);
    if (r.ok) seriesReaisD.value = await r.json();
}

async function loadSegmentosDestino() {
    if (!oEsc.value || !oAnl.value) { segmentosD.value = []; return; }
    const r = await fetch(`/api/segmentos/by-escola?esc_id=${oEsc.value}&anl_id=${oAnl.value}`);
    if (r.ok) segmentosD.value = await r.json();
}

watch([exigeDestino, oEsc, oAnl], async ([v]) => {
    if (v) {
        await loadSegmentosDestino();
    } else {
        segmentosD.value = []; seriesD.value = []; turmasD.value = []; seriesReaisD.value = [];
        dSeg.value = ''; dSer.value = ''; dSerAluno.value = ''; form.tur_id_destino = null;
    }
});
watch(dSeg, async (v) => {
    seriesD.value = []; turmasD.value = [];
    dSer.value = ''; dSerAluno.value = ''; form.tur_id_destino = null;
    loadSeriesReaisDestino();
    if (v && oEsc.value && oAnl.value) {
        // Reclassificação: lista todas as séries do segmento (pode ir p/ série sem turma aberta ainda).
        // Demais: somente séries com turmas abertas.
        const url = isReclassificacao.value
            ? `/api/series?seg_id=${v}`
            : `/api/series/by-turmas-abertas?esc_id=${oEsc.value}&anl_id=${oAnl.value}&seg_id=${v}`;
        const r = await fetch(url);
        if (r.ok) seriesD.value = await r.json();
    }
});
watch(dSer, async (v) => {
    turmasD.value = []; form.tur_id_destino = null; dSerAluno.value = '';
    if (v && oEsc.value && oAnl.value && dSeg.value) {
        const p = new URLSearchParams({ esc_id: String(oEsc.value), anl_id: String(oAnl.value), seg_id: String(dSeg.value), ser_id: String(v) });
        const r = await fetch(`/api/matriculas/turmas?${p}`);
        if (r.ok) {
            const lista = (await r.json()).filter((t: any) => t.tur_situacao === 'ABERTA');
            // Remanejamento e Reclassificação: turma destino deve diferir da origem.
            turmasD.value = (isRemanejamento.value || isReclassificacao.value)
                ? lista.filter((t: any) => t.tur_id !== Number(oTur.value))
                : lista;
        }
    }
});

// Lookups derivados p/ resumo
const escolaNome   = computed(() => props.escolas.find(e => e.esc_id === Number(oEsc.value))?.esc_nome ?? '');
const segmentoNome = computed(() => segmentos.value.find(s => s.seg_id === Number(oSeg.value))?.seg_nome ?? '');
const serieNome    = computed(() => series.value.find(s => s.ser_id === Number(oSer.value))?.ser_nome ?? '');
const turmaNome    = computed(() => turmas.value.find(t => t.tur_id === Number(oTur.value))?.tur_nome ?? '');
const alunoNome    = computed(() => alunos.value.find(a => a.tma_id === form.tma_id_origem)?.aln_nome ?? '');

const segmentoDestNome = computed(() => segmentosD.value.find(s => s.seg_id === Number(dSeg.value))?.seg_nome ?? '');
const serieDestNome    = computed(() => seriesD.value.find(s => s.ser_id === Number(dSer.value))?.ser_nome ?? '');
const turmaDestNome    = computed(() => turmasD.value.find(t => t.tur_id === form.tur_id_destino)?.tur_nome ?? '');

// Remanejamento: segmento/série destino TRAVADOS na origem — só a turma pode
// mudar. Força o valor (sem guard de "vazio") p/ re-sincronizar mesmo ao trocar
// de Reclassificação p/ Remanejamento. Roda após segmentosD/seriesD carregarem
// para garantir que a option exista na lista.
watch([isRemanejamento, exigeDestino, segmentosD, oSeg], ([rem, exige, segs, seg]) => {
    if (!rem || !exige) return;
    if (seg && (segs as any[]).some(s => s.seg_id === Number(seg))) {
        dSeg.value = seg as any;
    }
});
watch([isRemanejamento, seriesD, oSer], ([rem, sers, ser]) => {
    if (!rem) return;
    if (ser && (sers as any[]).some(s => s.ser_id === Number(ser))) {
        dSer.value = ser as any;
    }
});

// Reclassificação: série destino deve diferir da origem.
const seriesDestinoFiltradas = computed(() =>
    isReclassificacao.value && oSer.value
        ? seriesD.value.filter(s => s.ser_id !== Number(oSer.value))
        : seriesD.value,
);

// Itens p/ os comboboxes filtráveis (LocalCombobox usa {id,label}).
const segmentoItems  = computed(() => segmentos.value.map(s => ({ id: s.seg_id, label: s.seg_nome })));
const serieItems     = computed(() => series.value.map(s => ({ id: s.ser_id, label: s.ser_nome })));
const turmaItems     = computed(() => turmas.value.map(t => ({ id: t.tur_id, label: t.tur_nome })));
const alunoItems     = computed(() => alunos.value.map(a => ({ id: a.tma_id, label: a.aln_nome })));
const segmentoDItems = computed(() => segmentosD.value.map(s => ({ id: s.seg_id, label: s.seg_nome })));
const serieDItems    = computed(() => seriesDestinoFiltradas.value.map(s => ({ id: s.ser_id, label: s.ser_nome })));
const turmaDItems    = computed(() => turmasD.value.map(t => ({ id: t.tur_id, label: t.tur_nome })));
const serieAlunoItems = computed(() => seriesReaisD.value.map(s => ({ id: s.ser_id, label: s.ser_nome })));

const destinoLabel = computed(() => {
    if (isRemanejamento.value) return 'Destino — mesma série, outra turma';
    if (isReclassificacao.value) return 'Destino — nova série na mesma escola';
    return 'Destino (mesma escola)';
});

// Turma destino multi: no REMANEJAMENTO o ano escolar não muda, então sugere o da
// origem (quando é série real). Na RECLASSIFICAÇÃO o ano MUDA por definição — não
// pré-preencher: sugerir a série de origem faria a reclassificação virar um no-op
// silencioso, gravando o ano que o aluno está justamente deixando.
watch(destinoMulti, (multi) => {
    if (!multi) { dSerAluno.value = ''; return; }
    if (dSerAluno.value || isReclassificacao.value) return;
    const origem = series.value.find((s) => s.ser_id === Number(oSer.value));
    if (origem && !origem.ser_fl_multi) dSerAluno.value = origem.ser_id;
});

function submit() {
    // Backend redireciona para movimentacoes.show do registro salvo — deixa a
    // navegação seguir o redirect (não forçar volta pra listagem).
    form.transform((data) => ({
        ...data,
        // Só faz sentido em turma multi; fora disso o backend deriva sozinho.
        tma_ser_id_destino: destinoMulti.value && dSerAluno.value ? Number(dSerAluno.value) : null,
    }));
    form.post('/movimentacoes', {
        preserveScroll: true,
        // Padrão do projeto: leva o usuário até o primeiro campo inválido.
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    });
}
</script>

<template>
    <Head title="Nova Movimentação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Nova Movimentação</h1>
                <div class="flex gap-2">
                    <Button variant="outline" @click="router.visit('/movimentacoes')">Cancelar</Button>
                    <Button :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit">
                        <Loader2 v-if="form.processing" class="mr-1 size-4 animate-spin" /> Salvar
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 rounded-xl border bg-card p-6 shadow-sm">
                <!-- Tipo + Data + Protocolo -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Tipo de Movimentação</FormLabel>
                        <select v-model="form.tmv_cod" class="rounded-md border bg-background px-3 py-2 text-sm">
                            <option :value="null">Selecione...</option>
                            <option v-for="t in tipos" :key="t.tmv_cod" :value="t.tmv_cod">{{ t.tmv_descricao }}</option>
                        </select>
                        <InputError :message="form.errors.tmv_cod" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Data</FormLabel>
                        <Input type="date" v-model="form.dt_movimentacao" />
                        <InputError :message="form.errors.dt_movimentacao" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel>Protocolo nº</FormLabel>
                        <Input v-model="form.protocolo" maxlength="50" placeholder="Ex.: 2026/0001" />
                        <InputError :message="form.errors.protocolo" />
                    </div>
                </div>

                <!-- Info do tipo selecionado -->
                <div
                    v-if="tipoMeta"
                    class="flex items-start gap-3 rounded-lg border p-4"
                    :class="{
                        'border-amber-200 bg-amber-50/60 dark:bg-amber-900/10':  tipoMeta.cor === 'amber',
                        'border-violet-200 bg-violet-50/60 dark:bg-violet-900/10': tipoMeta.cor === 'violet',
                    }"
                >
                    <component :is="tipoMeta.icon" class="mt-0.5 size-5 shrink-0"
                        :class="{
                            'text-amber-600':  tipoMeta.cor === 'amber',
                            'text-violet-600': tipoMeta.cor === 'violet',
                        }"
                    />
                    <div class="text-sm">
                        <p class="font-semibold"
                            :class="{
                                'text-amber-900 dark:text-amber-200':  tipoMeta.cor === 'amber',
                                'text-violet-900 dark:text-violet-200': tipoMeta.cor === 'violet',
                            }"
                        >
                            {{ tipoMeta.titulo }}
                        </p>
                        <p class="text-muted-foreground">{{ tipoMeta.descricao }}</p>
                    </div>
                </div>

                <div v-else-if="form.tmv_cod" class="flex items-start gap-3 rounded-lg border bg-slate-50/60 p-4 text-sm dark:bg-slate-900/20">
                    <Info class="mt-0.5 size-5 shrink-0 text-slate-500" />
                    <p class="text-muted-foreground">
                        {{ tipoSelecionado?.tmv_descricao }} — saída do aluno da turma de origem.
                    </p>
                </div>

                <!-- Origem -->
                <div class="grid gap-3 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                    <h3 class="text-sm font-semibold text-indigo-800">Origem</h3>
                    <div class="grid items-start gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Ano Letivo</FormLabel>
                            <select v-model="oAnl" class="rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">—</option>
                                <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Escola</FormLabel>
                            <LocalCombobox
                                :model-value="oEsc === '' ? null : oEsc"
                                :items="escolaItems"
                                placeholder="Selecione..."
                                @update:model-value="(v) => (oEsc = v ?? '')"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Segmento</FormLabel>
                            <LocalCombobox
                                :model-value="oSeg === '' ? null : oSeg"
                                :items="segmentoItems"
                                placeholder="Selecione..."
                                :disabled="!segmentos.length"
                                @update:model-value="(v) => (oSeg = v ?? '')"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Série</FormLabel>
                            <LocalCombobox
                                :model-value="oSer === '' ? null : oSer"
                                :items="serieItems"
                                placeholder="Selecione..."
                                :disabled="!series.length"
                                @update:model-value="(v) => (oSer = v ?? '')"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Turma</FormLabel>
                            <LocalCombobox
                                :model-value="oTur === '' ? null : oTur"
                                :items="turmaItems"
                                placeholder="Selecione..."
                                :disabled="!turmas.length"
                                @update:model-value="(v) => (oTur = v ?? '')"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Aluno</FormLabel>
                            <LocalCombobox
                                :model-value="form.tma_id_origem"
                                :items="alunoItems"
                                placeholder="Selecione..."
                                :disabled="!alunos.length"
                                :invalid="!!form.errors.tma_id_origem"
                                @update:model-value="(v) => (form.tma_id_origem = v)"
                            />
                            <InputError :message="form.errors.tma_id_origem" />
                        </div>
                    </div>
                </div>

                <!-- Seta visual origem → destino -->
                <div v-if="exigeDestino" class="flex justify-center">
                    <div class="grid size-9 place-items-center rounded-full border bg-background shadow-sm">
                        <ArrowDown class="size-4 text-muted-foreground" />
                    </div>
                </div>

                <!-- Destino -->
                <div v-if="exigeDestino" class="grid gap-3 rounded-lg border border-fuchsia-200 bg-fuchsia-50/40 p-4">
                    <h3 class="text-sm font-semibold text-fuchsia-800">{{ destinoLabel }}</h3>
                    <div class="grid items-start gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Segmento</FormLabel>
                            <LocalCombobox
                                :model-value="dSeg === '' ? null : dSeg"
                                :items="segmentoDItems"
                                placeholder="Selecione..."
                                :disabled="isRemanejamento || !segmentosD.length"
                                @update:model-value="(v) => (dSeg = v ?? '')"
                            />
                            <p v-if="isRemanejamento" class="text-xs text-muted-foreground">Travado na origem — no remanejamento só a turma muda.</p>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Série</FormLabel>
                            <LocalCombobox
                                :model-value="dSer === '' ? null : dSer"
                                :items="serieDItems"
                                placeholder="Selecione..."
                                :disabled="isRemanejamento || !seriesDestinoFiltradas.length"
                                @update:model-value="(v) => (dSer = v ?? '')"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Turma</FormLabel>
                            <LocalCombobox
                                :model-value="form.tur_id_destino"
                                :items="turmaDItems"
                                placeholder="Selecione..."
                                :disabled="!turmasD.length"
                                :invalid="!!form.errors.tur_id_destino"
                                @update:model-value="(v) => (form.tur_id_destino = v)"
                            />
                            <InputError :message="form.errors.tur_id_destino" />
                        </div>

                        <!-- Turma multi: a série da turma é a pseudo-série "MULTI",
                             então o ano escolar do aluno precisa ser informado. -->
                        <!-- id casa com a chave do erro: o focusFirstError rola até aqui. -->
                        <div v-if="destinoMulti" id="tma_ser_id_destino" class="grid gap-1.5 sm:col-span-3">
                            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                                <FormLabel :required="true" class="text-amber-900 dark:text-amber-200">
                                    Ano escolar do aluno na turma multi
                                </FormLabel>
                                <LocalCombobox
                                    :model-value="dSerAluno === '' ? null : dSerAluno"
                                    :items="serieAlunoItems"
                                    placeholder="Selecione o ano escolar..."
                                    :disabled="!seriesReaisD.length"
                                    :invalid="!!(form.errors as Record<string, string>).tma_ser_id_destino"
                                    @update:model-value="(v) => (dSerAluno = v ?? '')"
                                />
                                <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                                    A turma de destino é multisseriada — informe em que ano escolar o aluno fica.
                                </p>
                                <InputError :message="(form.errors as Record<string, string>).tma_ser_id_destino" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transferência de diário (só remanejamento) -->
                <div v-if="isRemanejamento && exigeDestino" class="grid gap-2 rounded-lg border border-amber-200 bg-amber-50/40 p-4 dark:bg-amber-900/10">
                    <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">Transferir diário para a turma de destino</h3>
                    <p class="text-xs text-muted-foreground">O histórico continua na turma de origem e é copiado para a turma nova, onde fica <strong>editável</strong> e <strong>entra no cálculo da média</strong> (com destaque de origem).</p>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.migrar_notas" class="size-4" />
                        Transferir notas
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.migrar_faltas" class="size-4" />
                        Transferir faltas
                    </label>
                </div>

                <!-- Resumo -->
                <div
                    v-if="alunoNome && (exigeDestino ? form.tur_id_destino : form.tma_id_origem)"
                    class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 dark:bg-emerald-900/10"
                >
                    <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                        <User class="size-4" /> Resumo da Movimentação
                    </div>
                    <div class="text-sm">
                        <p><span class="font-medium">Aluno:</span> {{ alunoNome }}</p>
                        <p class="text-muted-foreground">
                            <span class="font-medium text-foreground">De:</span>
                            {{ escolaNome }} • {{ segmentoNome }} • {{ serieNome }} • Turma {{ turmaNome }}
                        </p>
                        <p v-if="exigeDestino && form.tur_id_destino" class="text-muted-foreground">
                            <span class="font-medium text-foreground">Para:</span>
                            {{ escolaNome }} • {{ segmentoDestNome }} • {{ serieDestNome }} • Turma {{ turmaDestNome }}
                        </p>
                    </div>
                </div>

                <!-- Observação -->
                <div class="grid gap-1.5">
                    <FormLabel>Observação</FormLabel>
                    <textarea v-model="form.observacao" rows="3" maxlength="1000" class="rounded-md border bg-background px-3 py-2 text-sm resize-none" />
                    <InputError :message="form.errors.observacao" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
