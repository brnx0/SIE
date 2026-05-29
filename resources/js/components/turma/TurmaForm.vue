<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import TurmaHorariosTab from '@/components/turma/tabs/TurmaHorariosTab.vue';
import TurmaProfessoresTab from '@/components/turma/tabs/TurmaProfessoresTab.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSegmentosByEscola } from '@/composables/useSegmentosByEscola';
import { useSeriesByEscolaSegmento } from '@/composables/useSeriesByEscolaSegmento';
import type { AnoLetivo } from '@/types/parametro';
import type {
    DisciplinaResumo,
    EscolaResumo,
    GradeHorarioResumo,
    ProfessorResumo,
    Turma,
    TurmaFormData,
    TurmaHorario,
    TurmaProfessor,
} from '@/types/turma';
import {
    DIAS_SEMANA,
    LOCAIS_DIFERENCIADOS,
    SITUACOES_TURMA,
    TIPOS_ATENDIMENTO,
    TIPOS_MEDIACAO,
    TURNOS,
} from '@/types/turma';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from 'lucide-vue-next';
import { watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Turma;
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
    professores?: TurmaProfessor[];
    professoresDisponiveis?: ProfessorResumo[];
    disciplinas?: DisciplinaResumo[];
    horarios?: TurmaHorario[];
    gradeHorarios?: GradeHorarioResumo[];
}>();

const { items: segmentos, search: searchSegmentos, clear: clearSegmentos } = useSegmentosByEscola();
const { items: series, search: searchSeries, clear: clearSeries } = useSeriesByEscolaSegmento();

const initialEscola = props.initial?.escola
    ? { esc_id: props.initial.escola.esc_id, esc_nome: props.initial.escola.esc_nome }
    : (props.isAdmin ? null : props.userEscola ?? null);

const form = useForm<TurmaFormData>({
    tur_esc_id:             props.initial?.tur_esc_id ?? (props.isAdmin ? null : (props.userEscola?.esc_id ?? null)),
    tur_anl_id:             props.initial?.tur_anl_id ?? null,
    tur_seg_id:             props.initial?.tur_seg_id ?? null,
    tur_ser_id:             props.initial?.tur_ser_id ?? null,
    tur_cd_inep:            props.initial?.tur_cd_inep ?? '',
    tur_nome:               props.initial?.tur_nome ?? '',
    tur_turno:              props.initial?.tur_turno ?? '',
    tur_capacidade:         props.initial?.tur_capacidade ?? '',
    tur_tipo_atendimento:   props.initial?.tur_tipo_atendimento ?? 'NÃO SE APLICA',
    tur_situacao:           props.initial?.tur_situacao ?? 'ABERTA',
    tur_hora_inicio:        props.initial?.tur_hora_inicio ?? '',
    tur_hora_fim:           props.initial?.tur_hora_fim ?? '',
    tur_mediacao:           props.initial?.tur_mediacao ?? 'PRESENCIAL',
    tur_local_diferenciado: props.initial?.tur_local_diferenciado ?? 'NAO ESTA EM LOCAL DIFERENCIADO',
    tur_fl_especial:        props.initial?.tur_fl_especial ?? false,
    tur_dias_funcionamento: props.initial?.tur_dias_funcionamento ?? ['seg', 'ter', 'qua', 'qui', 'sex'],
    tur_obs:                props.initial?.tur_obs ?? '',
    _method:                props.mode === 'edit' ? 'put' : undefined,
});

// Cascata: escola + ano → segmentos
const triggerSegmentos = () => {
    if (form.tur_esc_id && form.tur_anl_id) {
        searchSegmentos(form.tur_esc_id, form.tur_anl_id);
    } else {
        clearSegmentos();
    }
    form.tur_seg_id = null;
    form.tur_ser_id = null;
    clearSeries();
};

// Cascata: segmento → series
const triggerSeries = () => {
    if (form.tur_esc_id && form.tur_anl_id && form.tur_seg_id) {
        searchSeries(form.tur_esc_id, form.tur_anl_id, form.tur_seg_id);
    } else {
        clearSeries();
    }
    form.tur_ser_id = null;
};

watch(() => form.tur_esc_id, triggerSegmentos);
watch(() => form.tur_anl_id, triggerSegmentos);
watch(() => form.tur_seg_id, triggerSeries);

// Se edit, pré-carrega segmentos e series
if (props.mode === 'edit' && props.initial) {
    if (props.initial.tur_esc_id && props.initial.tur_anl_id) {
        searchSegmentos(props.initial.tur_esc_id, props.initial.tur_anl_id).then(() => {
            if (props.initial!.tur_seg_id) {
                searchSeries(props.initial!.tur_esc_id, props.initial!.tur_anl_id, props.initial!.tur_seg_id);
            }
        });
    }
}

const onEscolaChange = (v: number | null) => {
    form.tur_esc_id = v;
};

const toggleDia = (dia: string) => {
    const idx = form.tur_dias_funcionamento.indexOf(dia as any);
    if (idx >= 0) {
        form.tur_dias_funcionamento.splice(idx, 1);
    } else {
        form.tur_dias_funcionamento.push(dia as any);
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.post('/turmas');
    } else {
        form.post(`/turmas/${props.initial?.tur_id}`);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar Turma' : 'Salvar Alterações';

const selectClass = (hasError: boolean) =>
    `flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring ${hasError ? 'border-red-500 ring-1 ring-red-500' : 'border-input'}`;
</script>

<template>
    <div class="grid gap-6">
        <!-- Botão salvar no topo -->
        <div class="flex justify-end">
            <Button type="button" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700" @click="submit">
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <!-- Abas (edit) ou form direto (create) -->
        <Tabs default-value="dados">
            <TabsList v-if="mode === 'edit'">
                <TabsTrigger value="dados" :has-error="Object.keys(form.errors).length > 0">Dados Gerais</TabsTrigger>
                <TabsTrigger value="professores">Professores</TabsTrigger>
                <TabsTrigger value="horarios">Horários</TabsTrigger>
            </TabsList>

            <TabsContent value="dados">
                <div class="grid gap-6">
                    <!-- Identificação -->
                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Identificação</h3>
                        <div class="grid gap-4 sm:grid-cols-12">
                            <!-- Escola -->
                            <div class="grid gap-1.5 sm:col-span-6">
                                <FormLabel :required="true">Escola</FormLabel>
                                <template v-if="isAdmin">
                                    <EscolaCombobox
                                        :model-value="form.tur_esc_id"
                                        :initial="initialEscola as any"
                                        :invalid="!!form.errors.tur_esc_id"
                                        @update:model-value="onEscolaChange"
                                    />
                                </template>
                                <template v-else>
                                    <Input :value="userEscola?.esc_nome ?? ''" disabled class="bg-muted" />
                                </template>
                                <InputError :message="form.errors.tur_esc_id" />
                            </div>

                            <!-- Ano Letivo -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel :required="true">Ano Letivo</FormLabel>
                                <select v-model="form.tur_anl_id" :class="selectClass(!!form.errors.tur_anl_id)">
                                    <option :value="null">Selecione...</option>
                                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">{{ anl.anl_ano }}</option>
                                </select>
                                <InputError :message="form.errors.tur_anl_id" />
                            </div>

                            <!-- Código INEP -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel>Código INEP</FormLabel>
                                <Input v-model="form.tur_cd_inep" maxlength="20" placeholder="Ex.: 32649083" />
                                <InputError :message="form.errors.tur_cd_inep" />
                            </div>

                            <!-- Segmento -->
                            <div class="grid gap-1.5 sm:col-span-5">
                                <FormLabel :required="true">Segmento</FormLabel>
                                <select
                                    v-model="form.tur_seg_id"
                                    :disabled="!form.tur_esc_id || !form.tur_anl_id"
                                    :class="selectClass(!!form.errors.tur_seg_id)"
                                >
                                    <option :value="null">{{ !form.tur_esc_id || !form.tur_anl_id ? 'Selecione escola e ano primeiro' : 'Selecione...' }}</option>
                                    <option v-for="seg in segmentos" :key="seg.seg_id" :value="seg.seg_id">{{ seg.seg_nome }}</option>
                                </select>
                                <InputError :message="form.errors.tur_seg_id" />
                            </div>

                            <!-- Série -->
                            <div class="grid gap-1.5 sm:col-span-4">
                                <FormLabel :required="true">Série / Ano Escolarização</FormLabel>
                                <select
                                    v-model="form.tur_ser_id"
                                    :disabled="!form.tur_seg_id"
                                    :class="selectClass(!!form.errors.tur_ser_id)"
                                >
                                    <option :value="null">{{ !form.tur_seg_id ? 'Selecione segmento primeiro' : 'Selecione...' }}</option>
                                    <option v-for="ser in series" :key="ser.ser_id" :value="ser.ser_id">{{ ser.ser_nome }}</option>
                                </select>
                                <InputError :message="form.errors.tur_ser_id" />
                            </div>

                            <!-- Turno -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel :required="true">Turno</FormLabel>
                                <select v-model="form.tur_turno" :class="selectClass(!!form.errors.tur_turno)">
                                    <option value="">Selecione...</option>
                                    <option v-for="(label, val) in TURNOS" :key="val" :value="val">{{ label }}</option>
                                </select>
                                <InputError :message="form.errors.tur_turno" />
                            </div>

                            <!-- Nome da Turma -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel :required="true">Nome da Turma</FormLabel>
                                <Input v-model="form.tur_nome" maxlength="20" placeholder="A" :class="{ 'border-red-500': form.errors.tur_nome }" />
                                <InputError :message="form.errors.tur_nome" />
                            </div>

                            <!-- Capacidade -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel :required="true">Capacidade</FormLabel>
                                <Input v-model.number="form.tur_capacidade" type="number" min="1" max="999" placeholder="35" :required="true" />
                                <InputError :message="form.errors.tur_capacidade" />
                            </div>

                            <!-- Situação -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel :required="true">Situação</FormLabel>
                                <select v-model="form.tur_situacao" :class="selectClass(!!form.errors.tur_situacao)">
                                    <option v-for="s in SITUACOES_TURMA" :key="s" :value="s">{{ s }}</option>
                                </select>
                                <InputError :message="form.errors.tur_situacao" />
                            </div>

                            <!-- Turma Especial -->
                            <div class="flex items-end gap-2 sm:col-span-2">
                                <Switch id="tur_fl_especial" v-model="form.tur_fl_especial" />
                                <Label for="tur_fl_especial" class="text-sm font-normal">Turma Especial</Label>
                            </div>
                        </div>
                    </div>

                    <!-- Funcionamento -->
                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Funcionamento</h3>
                        <div class="grid gap-4 sm:grid-cols-12">
                            <!-- Hora Início / Fim -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel>Hora Início</FormLabel>
                                <Input v-model="form.tur_hora_inicio" type="time" />
                                <InputError :message="form.errors.tur_hora_inicio" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel>Hora Fim</FormLabel>
                                <Input v-model="form.tur_hora_fim" type="time" />
                                <InputError :message="form.errors.tur_hora_fim" />
                            </div>

                            <!-- Tipo de Mediação -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel>Tipo de Mediação</FormLabel>
                                <select v-model="form.tur_mediacao" :class="selectClass(!!form.errors.tur_mediacao)">
                                    <option value="">Selecione...</option>
                                    <option v-for="m in TIPOS_MEDIACAO" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <InputError :message="form.errors.tur_mediacao" />
                            </div>

                            <!-- Tipo de Atendimento -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel :required="true">Tipo de Atendimento</FormLabel>
                                <select v-model="form.tur_tipo_atendimento" :class="selectClass(!!form.errors.tur_tipo_atendimento)">
                                    <option v-for="t in TIPOS_ATENDIMENTO" :key="t" :value="t">{{ t }}</option>
                                </select>
                                <InputError :message="form.errors.tur_tipo_atendimento" />
                            </div>

                            <!-- Local Diferenciado -->
                            <div class="grid gap-1.5 sm:col-span-6">
                                <FormLabel>Local de Funcionamento Diferenciado</FormLabel>
                                <select v-model="form.tur_local_diferenciado" :class="selectClass(!!form.errors.tur_local_diferenciado)">
                                    <option value="">Selecione...</option>
                                    <option v-for="l in LOCAIS_DIFERENCIADOS" :key="l" :value="l">{{ l }}</option>
                                </select>
                                <InputError :message="form.errors.tur_local_diferenciado" />
                            </div>

                            <!-- Dias de Funcionamento -->
                            <div class="sm:col-span-12">
                                <FormLabel>Dias de Funcionamento</FormLabel>
                                <div class="mt-2 flex flex-wrap gap-4">
                                    <label
                                        v-for="dia in DIAS_SEMANA"
                                        :key="dia.value"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="dia.value"
                                            :checked="form.tur_dias_funcionamento.includes(dia.value as any)"
                                            class="size-4 accent-indigo-600"
                                            @change="toggleDia(dia.value)"
                                        />
                                        {{ dia.label }}
                                    </label>
                                </div>
                                <InputError :message="form.errors.tur_dias_funcionamento" />
                            </div>
                        </div>
                    </div>

                    <!-- Observação -->
                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                        <div class="grid gap-1.5">
                            <FormLabel>Observação</FormLabel>
                            <textarea
                                v-model="form.tur_obs"
                                maxlength="2000"
                                rows="3"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="Informações adicionais sobre a turma..."
                            />
                            <InputError :message="form.errors.tur_obs" />
                        </div>
                    </div>
                </div>
            </TabsContent>

            <TabsContent v-if="mode === 'edit'" value="professores">
                <TurmaProfessoresTab
                    :turma="initial!"
                    :professores="professores ?? []"
                    :professores-disponiveis="professoresDisponiveis ?? []"
                    :disciplinas="disciplinas ?? []"
                />
            </TabsContent>

            <TabsContent v-if="mode === 'edit'" value="horarios">
                <TurmaHorariosTab
                    :turma="initial!"
                    :grade-horarios="gradeHorarios ?? []"
                    :horarios="horarios ?? []"
                    :professores-disponiveis="professoresDisponiveis ?? []"
                    :disciplinas="disciplinas ?? []"
                />
            </TabsContent>
        </Tabs>

        <!-- Rodapé: só navegação -->
        <div>
            <Link href="/turmas">
                <Button type="button" variant="outline">Voltar à lista</Button>
            </Link>
        </div>
    </div>
</template>
