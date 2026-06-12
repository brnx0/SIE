<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import IndicadoresTab from '@/components/serie/IndicadoresTab.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import SerieCombobox from '@/components/serie/SerieCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Segmento } from '@/types/segmento';
import type { Serie, SerieFormData, SerieIndicador, SerieParaReplicar } from '@/types/serie';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    segmentos: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>[];
    disciplinas?: { dis_id: number; dis_nome: string }[];
    initial?: Serie;
    indicadores?: SerieIndicador[];
    seriesParaReplicar?: SerieParaReplicar[];
    anosLetivos?: { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }[];
    anlIdSelecionado?: number;
    anosReplicacao?: { anl_id: number; anl_ano: number }[];
}>();

const form = useForm<SerieFormData>({
    seg_id:                  props.initial?.seg_id ?? null,
    ser_cd_referencia:       props.initial?.ser_cd_referencia ?? '',
    ser_nome:                props.initial?.ser_nome ?? '',
    ser_carga_horaria:       props.initial?.ser_carga_horaria ?? null,
    ser_qt_aulas_semestrais: props.initial?.ser_qt_aulas_semestrais ?? null,
    ser_qt_aulas_anuais:     props.initial?.ser_qt_aulas_anuais ?? null,
    ser_idade:               props.initial?.ser_idade ?? null,
    ser_serie_equivalente:   props.initial?.ser_serie_equivalente ?? '',
    ser_nr_ordenacao:        props.initial?.ser_nr_ordenacao ?? 0,
    ser_ordem_no_segmento:   props.initial?.ser_ordem_no_segmento ?? null,
    ser_fl_ativo:            props.initial?.ser_fl_ativo ?? true,
    ser_tipo_avaliacao:      props.initial?.ser_tipo_avaliacao ?? [],
    ser_tipo_avaliacao_descritiva: props.initial?.ser_tipo_avaliacao_descritiva ?? '',
    ser_promo_ser_id_1:      props.initial?.ser_promo_ser_id_1 ?? null,
    ser_promo_ser_id_2:      props.initial?.ser_promo_ser_id_2 ?? null,
    ser_cons_ser_id_1:       props.initial?.ser_cons_ser_id_1 ?? null,
    ser_cons_ser_id_2:       props.initial?.ser_cons_ser_id_2 ?? null,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const submit = () => {
    const opts = { preserveScroll: true };
    if (props.mode === 'create') {
        form.post('/series', opts);
    } else if (props.initial) {
        form.post(`/series/${props.initial.ser_id}`, opts);
    }
};

const toggleAvaliacao = (val: string) => {
    const idx = form.ser_tipo_avaliacao.indexOf(val);
    if (idx >= 0) form.ser_tipo_avaliacao.splice(idx, 1);
    else form.ser_tipo_avaliacao.push(val);
};

const hasDescritiva = () => form.ser_tipo_avaliacao.includes('descritiva');

watch(() => form.ser_tipo_avaliacao, (v) => {
    if (!v.includes('descritiva')) form.ser_tipo_avaliacao_descritiva = '';
}, { deep: true });

const SEG_SEM_CONSERVACAO = ['Creche', 'Pré-escolar'];
const conservacaoBloqueada = computed(() => {
    const seg = props.segmentos.find(s => s.seg_id === form.seg_id);
    return !!seg && SEG_SEM_CONSERVACAO.includes(seg.seg_nome_reduzido);
});

watch(conservacaoBloqueada, (bloqueado) => {
    if (bloqueado) {
        form.ser_cons_ser_id_1 = null;
        form.ser_cons_ser_id_2 = null;
    }
});

const submitLabel = props.mode === 'create' ? 'Cadastrar série' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <!-- Botões topo -->
        <div class="flex items-center justify-between">
            <Link href="/series">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <Button
                type="submit"
                :disabled="form.processing"
                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <!-- Card principal -->
        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">

            <!-- Segmento -->
            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="seg_id" :required="true">Segmento</FormLabel>
                <select
                    id="seg_id"
                    v-model="form.seg_id"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione um segmento...</option>
                    <option v-for="seg in segmentos" :key="seg.seg_id" :value="seg.seg_id">
                        {{ seg.seg_nome_reduzido }}
                    </option>
                </select>
                <InputError :message="form.errors.seg_id" />
            </div>

            <!-- Código de Referência -->
            <div class="grid gap-2">
                <FormLabel for="ser_cd_referencia">Código de Referência</FormLabel>
                <Input
                    id="ser_cd_referencia"
                    v-model="form.ser_cd_referencia"
                    maxlength="20"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.ser_cd_referencia" />
            </div>

            <!-- Nome da Série -->
            <div class="grid gap-2 sm:col-span-4">
                <FormLabel for="ser_nome" :required="true">Nome da Série</FormLabel>
                <Input
                    id="ser_nome"
                    v-model="form.ser_nome"
                    maxlength="100"
                    autofocus
                    class="uppercase"
                />
                <InputError :message="form.errors.ser_nome" />
            </div>

            <!-- Carga Horária -->
            <div class="grid gap-2">
                <FormLabel for="ser_carga_horaria">Carga Horária (h)</FormLabel>
                <Input
                    id="ser_carga_horaria"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.ser_carga_horaria"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.ser_carga_horaria" />
            </div>

            <!-- Total aulas semestrais -->
            <div class="grid gap-2">
                <FormLabel for="ser_qt_aulas_semestrais">Total de aulas semestrais</FormLabel>
                <Input
                    id="ser_qt_aulas_semestrais"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.ser_qt_aulas_semestrais"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.ser_qt_aulas_semestrais" />
            </div>

            <!-- Total aulas anuais -->
            <div class="grid gap-2">
                <FormLabel for="ser_qt_aulas_anuais">Total de aulas anuais</FormLabel>
                <Input
                    id="ser_qt_aulas_anuais"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.ser_qt_aulas_anuais"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.ser_qt_aulas_anuais" />
            </div>

            <!-- Ativo -->
            <div class="flex items-center gap-3 self-end pb-1">
                <Switch id="ser_fl_ativo" v-model="form.ser_fl_ativo" />
                <FormLabel for="ser_fl_ativo" class="font-normal">Ativo</FormLabel>
            </div>

            <!-- Separador -->
            <div class="sm:col-span-4 border-t" />

            <!-- Tipo de Avaliação (multi-select) -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel>Tipo de Avaliação</FormLabel>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label v-for="opt in [
                        { value: 'diagnostico', label: 'Avaliação por Diagnóstico' },
                        { value: 'conceitual', label: 'Avaliação Conceitual' },
                        { value: 'numerica', label: 'Avaliação Numérica' },
                        { value: 'descritiva', label: 'Avaliação Descritiva' },
                    ]" :key="opt.value" class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            :checked="form.ser_tipo_avaliacao.includes(opt.value)"
                            @change="toggleAvaliacao(opt.value)"
                            class="size-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        {{ opt.label }}
                    </label>
                </div>
                <InputError :message="form.errors.ser_tipo_avaliacao" />
            </div>

            <!-- Tipo de Avaliação Descritiva -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="ser_tipo_avaliacao_descritiva" :required="hasDescritiva()">Tipo de Avaliação Descritiva</FormLabel>
                <select
                    id="ser_tipo_avaliacao_descritiva"
                    v-model="form.ser_tipo_avaliacao_descritiva"
                    :disabled="!hasDescritiva()"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione...</option>
                    <option value="por_aluno">Por Aluno</option>
                    <option value="por_unidade">Por Unidade</option>
                </select>
                <InputError :message="form.errors.ser_tipo_avaliacao_descritiva" />
            </div>

            <!-- Separador -->
            <div class="sm:col-span-4 border-t" />

            <!-- Idade na Série -->
            <div class="grid gap-2">
                <FormLabel for="ser_idade" :required="true">Idade na Série</FormLabel>
                <Input
                    id="ser_idade"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.ser_idade"
                />
                <InputError :message="form.errors.ser_idade" />
            </div>

            <!-- Série Equivalente -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="ser_serie_equivalente">Série Equivalente</FormLabel>
                <Input
                    id="ser_serie_equivalente"
                    v-model="form.ser_serie_equivalente"
                    maxlength="100"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.ser_serie_equivalente" />
            </div>

            <!-- Nº para ordenação -->
            <div class="grid gap-2">
                <FormLabel for="ser_nr_ordenacao">Nº para ordenação</FormLabel>
                <Input
                    id="ser_nr_ordenacao"
                    type="number"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.ser_nr_ordenacao"
                />
                <InputError :message="form.errors.ser_nr_ordenacao" />
            </div>

            <!-- Ordem da Série no Segmento -->
            <div class="grid gap-2 sm:col-span-4">
                <FormLabel for="ser_ordem_no_segmento" :required="true">Ordem da Série no Segmento</FormLabel>
                <Input
                    id="ser_ordem_no_segmento"
                    type="number"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.ser_ordem_no_segmento"
                    class="max-w-xs"
                />
                <InputError :message="form.errors.ser_ordem_no_segmento" />
            </div>

            <!-- Separador -->
            <div class="sm:col-span-4 border-t" />

            <!-- Promoção e Conservação -->
            <div class="grid gap-2 sm:col-span-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Promoção e Conservação
                </h3>
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    Defina as séries de destino para alunos promovidos ou conservados nesta série.
                </p>
            </div>

            <!-- Promoção 1 -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel>Promoção — 1ª opção</FormLabel>
                <SerieCombobox
                    v-model="form.ser_promo_ser_id_1"
                    :initial="initial?.promoSerie1 ?? null"
                    :exclude="initial?.ser_id ?? null"
                    :promo-seg-id="form.seg_id"
                    :promo-ser-ordem="form.ser_ordem_no_segmento"
                    placeholder="Selecionar série..."
                    :invalid="!!form.errors.ser_promo_ser_id_1"
                />
                <InputError :message="form.errors.ser_promo_ser_id_1" />
            </div>

            <!-- Promoção 2 -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel>Promoção — 2ª opção</FormLabel>
                <SerieCombobox
                    v-model="form.ser_promo_ser_id_2"
                    :initial="initial?.promoSerie2 ?? null"
                    :exclude="initial?.ser_id ?? null"
                    :promo-seg-id="form.seg_id"
                    :promo-ser-ordem="form.ser_ordem_no_segmento"
                    placeholder="Selecionar série..."
                    :invalid="!!form.errors.ser_promo_ser_id_2"
                />
                <InputError :message="form.errors.ser_promo_ser_id_2" />
            </div>

            <!-- Conservação 1 (aluno conservado pode permanecer na mesma série) -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel>Conservação — 1ª opção</FormLabel>
                <SerieCombobox
                    v-model="form.ser_cons_ser_id_1"
                    :initial="conservacaoBloqueada ? null : (initial?.consSerie1 ?? null)"
                    :disabled="conservacaoBloqueada"
                    placeholder="Selecionar série..."
                    :invalid="!!form.errors.ser_cons_ser_id_1"
                />
                <p v-if="conservacaoBloqueada" class="text-xs text-muted-foreground">Segmento sem conservação.</p>
                <InputError :message="form.errors.ser_cons_ser_id_1" />
            </div>

            <!-- Conservação 2 (aluno conservado pode permanecer na mesma série) -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel>Conservação — 2ª opção</FormLabel>
                <SerieCombobox
                    v-model="form.ser_cons_ser_id_2"
                    :initial="conservacaoBloqueada ? null : (initial?.consSerie2 ?? null)"
                    :disabled="conservacaoBloqueada"
                    placeholder="Selecionar série..."
                    :invalid="!!form.errors.ser_cons_ser_id_2"
                />
                <p v-if="conservacaoBloqueada" class="text-xs text-muted-foreground">Segmento sem conservação.</p>
                <InputError :message="form.errors.ser_cons_ser_id_2" />
            </div>
        </div>

        <!-- Indicadores (somente edit) -->
        <div v-if="mode === 'edit' && initial">
            <h2 class="mb-3 text-base font-semibold text-slate-800 dark:text-slate-100">Indicadores</h2>
            <IndicadoresTab
                :ser-id="initial.ser_id"
                :indicadores="indicadores ?? []"
                :disciplinas="disciplinas ?? []"
                :series-para-replicar="seriesParaReplicar ?? []"
                :anos-letivos="anosLetivos ?? []"
                :anl-id-selecionado="anlIdSelecionado ?? 0"
                :anos-replicacao="anosReplicacao ?? []"
            />
        </div>
    </form>
</template>
