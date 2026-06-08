<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import TurmaAeeAlunosTab from '@/components/turma-aee/tabs/TurmaAeeAlunosTab.vue';
import TurmaAeeProfessoresTab from '@/components/turma-aee/tabs/TurmaAeeProfessoresTab.vue';
import TurmaAeeAtendimentosTab from '@/components/turma-aee/tabs/TurmaAeeAtendimentosTab.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AnoLetivo } from '@/types/parametro';
import type { EscolaResumo, ProfessorResumo, Turma, TurmaProfessor } from '@/types/turma';
import { DIAS_SEMANA, SEMESTRES_TURMA, SITUACOES_TURMA, TIPOS_MEDIACAO, TURNOS } from '@/types/turma';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Turma;
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
    professores?: TurmaProfessor[];
    professoresDisponiveis?: ProfessorResumo[];
}>();

const initialEscola = props.initial?.escola
    ? { esc_id: props.initial.escola.esc_id, esc_nome: props.initial.escola.esc_nome }
    : (props.isAdmin ? null : props.userEscola ?? null);

const form = useForm({
    tur_esc_id:      props.initial?.tur_esc_id ?? (props.isAdmin ? null : (props.userEscola?.esc_id ?? null)),
    tur_anl_id:      props.initial?.tur_anl_id ?? null,
    tur_cd_inep:     props.initial?.tur_cd_inep ?? '',
    tur_nome:        props.initial?.tur_nome ?? '',
    tur_turno:       props.initial?.tur_turno ?? '',
    tur_capacidade:  props.initial?.tur_capacidade ?? '',
    tur_semestre:    props.initial?.tur_semestre ?? '',
    tur_situacao:    props.initial?.tur_situacao ?? 'ABERTA',
    tur_hora_inicio: props.initial?.tur_hora_inicio ?? '',
    tur_hora_fim:    props.initial?.tur_hora_fim ?? '',
    tur_mediacao:    props.initial?.tur_mediacao ?? 'PRESENCIAL',
    tur_aee_sala:    props.initial?.tur_aee_sala ?? '',
    tur_dias_funcionamento: props.initial?.tur_dias_funcionamento ?? ['seg', 'ter', 'qua', 'qui', 'sex'],
    tur_obs:         props.initial?.tur_obs ?? '',
    _method:         props.mode === 'edit' ? 'put' : undefined,
});

const onEscolaChange = (v: number | null) => {
    form.tur_esc_id = v;
};

const toggleDia = (dia: string) => {
    const idx = form.tur_dias_funcionamento.indexOf(dia as any);
    if (idx >= 0) form.tur_dias_funcionamento.splice(idx, 1);
    else form.tur_dias_funcionamento.push(dia as any);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        tur_mediacao: data.tur_mediacao === '' ? null : data.tur_mediacao,
    }));

    if (props.mode === 'create') {
        form.post('/turmas-aee');
    } else {
        form.post(`/turmas-aee/${props.initial?.tur_id}`);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar Turma AEE' : 'Salvar Alterações';

const selectClass = (hasError: boolean) =>
    `flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring ${hasError ? 'border-red-500 ring-1 ring-red-500' : 'border-input'}`;

const matriculados = computed(() => props.initial?.total_matriculados ?? 0);
</script>

<template>
    <div class="grid gap-6">
        <!-- Botões no topo -->
        <div class="flex items-center justify-between">
            <Link href="/turmas-aee">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar à lista
                </Button>
            </Link>
            <Button type="button" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700" @click="submit">
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <Tabs default-value="dados">
            <TabsList v-if="mode === 'edit'">
                <TabsTrigger value="dados" :has-error="Object.keys(form.errors).length > 0">Cadastro</TabsTrigger>
                <TabsTrigger value="alunos">Alunos Alocados</TabsTrigger>
                <TabsTrigger value="professores">Alocação de Professores</TabsTrigger>
                <TabsTrigger value="atendimentos">Atendimentos Oferecidos</TabsTrigger>
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
                                    <Input :model-value="(initialEscola?.esc_nome ?? userEscola?.esc_nome) ?? ''" disabled class="bg-muted" />
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
                                <FormLabel>Código INEP da Turma</FormLabel>
                                <Input v-model="form.tur_cd_inep" maxlength="20" placeholder="Ex.: 28208837" />
                                <InputError :message="form.errors.tur_cd_inep" />
                            </div>

                            <!-- Nome da Turma -->
                            <div class="grid gap-1.5 sm:col-span-4">
                                <FormLabel :required="true">Nome da Turma</FormLabel>
                                <Input v-model="form.tur_nome" maxlength="20" placeholder="AEE TURMA J" :class="{ 'border-red-500': form.errors.tur_nome }" />
                                <InputError :message="form.errors.tur_nome" />
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

                            <!-- Dependência (Capacidade) — sem lógica por ora -->
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel>Dependência (Capacidade)</FormLabel>
                                <Input :model-value="''" disabled placeholder="—" class="bg-muted cursor-not-allowed" />
                            </div>

                            <!-- Capacidade -->
                            <div class="grid gap-1.5 sm:col-span-1">
                                <FormLabel :required="true">Capac.</FormLabel>
                                <Input v-model.number="form.tur_capacidade" type="number" min="1" max="999" placeholder="0" />
                                <InputError :message="form.errors.tur_capacidade" />
                            </div>

                            <!-- Cursando (readonly) -->
                            <div v-if="mode === 'edit'" class="grid gap-1.5 sm:col-span-1">
                                <FormLabel>Cursando</FormLabel>
                                <Input :model-value="matriculados" readonly class="bg-muted cursor-default" />
                            </div>

                            <!-- Semestre -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel :required="true">Semestre</FormLabel>
                                <select v-model.number="form.tur_semestre" :class="selectClass(!!form.errors.tur_semestre)">
                                    <option :value="''">Selecione...</option>
                                    <option v-for="s in SEMESTRES_TURMA" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                                <InputError :message="form.errors.tur_semestre" />
                            </div>

                            <!-- Situação -->
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel :required="true">Situação</FormLabel>
                                <select v-model="form.tur_situacao" :class="selectClass(!!form.errors.tur_situacao)">
                                    <option v-for="s in SITUACOES_TURMA" :key="s" :value="s">{{ s }}</option>
                                </select>
                                <InputError :message="form.errors.tur_situacao" />
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

                            <!-- Tipo de Atendimento (fixo AEE) -->
                            <div class="grid gap-1.5 sm:col-span-6">
                                <FormLabel>Tipo de Atendimento</FormLabel>
                                <Input model-value="ATENDIMENTO EDUCACIONAL ESPECIALIZADO - AEE" disabled class="bg-muted cursor-not-allowed" />
                            </div>

                            <!-- Tipo de Mediação -->
                            <div class="grid gap-1.5 sm:col-span-4">
                                <FormLabel>Tipo de mediação didático-pedagógica</FormLabel>
                                <select v-model="form.tur_mediacao" :class="selectClass(!!form.errors.tur_mediacao)">
                                    <option value="">Selecione...</option>
                                    <option v-for="m in TIPOS_MEDIACAO" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <InputError :message="form.errors.tur_mediacao" />
                            </div>

                            <!-- Dias de Funcionamento -->
                            <div class="sm:col-span-12">
                                <FormLabel>Dias da Semana</FormLabel>
                                <div class="mt-2 flex flex-wrap gap-4">
                                    <label v-for="dia in DIAS_SEMANA" :key="dia.value" class="flex items-center gap-2 text-sm">
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

            <TabsContent v-if="mode === 'edit'" value="alunos">
                <TurmaAeeAlunosTab :tur-id="initial!.tur_id" />
            </TabsContent>

            <TabsContent v-if="mode === 'edit'" value="professores">
                <TurmaAeeProfessoresTab
                    :turma="initial!"
                    :professores="professores ?? []"
                    :professores-disponiveis="professoresDisponiveis ?? []"
                />
            </TabsContent>

            <TabsContent v-if="mode === 'edit'" value="atendimentos">
                <TurmaAeeAtendimentosTab :tur-id="initial!.tur_id" />
            </TabsContent>
        </Tabs>
    </div>
</template>
