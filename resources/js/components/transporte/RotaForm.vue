<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import MonitorCombobox from '@/components/transporte/MonitorCombobox.vue';
import MotoristaCombobox from '@/components/transporte/MotoristaCombobox.vue';
import VeiculoCombobox from '@/components/transporte/VeiculoCombobox.vue';
import RotaPontosTab from '@/components/transporte/RotaPontosTab.vue';
import RotaPassageirosTab from '@/components/transporte/RotaPassageirosTab.vue';
import MapaRota from '@/components/transporte/MapaRota.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { AnoLetivo } from '@/types/parametro';
import type { PassageiroRota, PontoRota, Rota, VeiculoResumo } from '@/types/transporte';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Map as MapIcon, Save } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Rota;
    pontos?: PontoRota[];
    passageiros?: PassageiroRota[];
    anosLetivos: AnoLetivo[];
    turnos: Record<string, string>;
    zonas: Record<string, string>;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const activeTab = ref('dados');

// Mapa da aba Trajeto vive num painel v-show; recalcula tamanho ao ativar.
const mapaRef = ref<InstanceType<typeof MapaRota> | null>(null);
watch(activeTab, (v) => {
    if (v === 'trajeto') nextTick(() => mapaRef.value?.invalidate());
});

const form = useForm({
    rot_anl_id:  props.initial?.rot_anl_id ?? (props.anosLetivos[0]?.anl_id ?? null),
    rot_nome:    props.initial?.rot_nome ?? '',
    rot_turno:   props.initial?.rot_turno ?? '',
    rot_zona:    props.initial?.rot_zona ?? '',
    rot_vei_id:  props.initial?.rot_vei_id ?? (null as number | null),
    rot_mot_id:  props.initial?.rot_mot_id ?? (null as number | null),
    rot_tmo_id:  props.initial?.rot_tmo_id ?? (null as number | null),
    rot_obs:     props.initial?.rot_obs ?? '',
    rot_fl_ativo: props.initial?.rot_fl_ativo ?? true,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// Ocupação (N4): passageiros vinculados / capacidade do veículo titular.
const capacidade = ref<number | null>(props.initial?.veiculo?.vei_capacidade ?? null);
const totalPassageiros = computed(() => props.passageiros?.length ?? 0);
const excedeCapacidade = computed(
    () => capacidade.value != null && capacidade.value > 0 && totalPassageiros.value > capacidade.value,
);

const onVeiculoSelected = (v: VeiculoResumo | null) => {
    capacidade.value = v?.vei_capacidade ?? null;
};

const submit = () => {
    if (isReadOnly.value) return;
    form.transform((data) => ({
        ...data,
        rot_zona: data.rot_zona === '' ? null : data.rot_zona,
    }));

    const opts = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            activeTab.value = 'dados';
            focusFirstError(Object.keys(errors));
        },
    };
    if (props.mode === 'create') {
        form.post('/transporte/rotas', opts);
    } else if (props.initial) {
        form.post(`/transporte/rotas/${props.initial.rot_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar rota' : 'Salvar alterações';

const selectClass = (hasError: boolean) =>
    `flex h-10 w-full rounded-md border bg-background px-3 text-sm shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground ${hasError ? 'border-rose-500' : 'border-input'}`;
</script>

<template>
    <div class="grid gap-6">
        <!-- Botões no topo -->
        <div class="flex items-center justify-between">
            <Link href="/transporte/rotas">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <Button v-if="!isReadOnly" type="button" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400" @click="submit">
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <Tabs v-model="activeTab">
            <TabsList v-if="mode === 'edit'">
                <TabsTrigger value="dados" :has-error="Object.keys(form.errors).length > 0">Dados</TabsTrigger>
                <TabsTrigger value="pontos">Pontos de Parada</TabsTrigger>
                <TabsTrigger value="passageiros">Passageiros</TabsTrigger>
                <TabsTrigger value="trajeto">Trajeto</TabsTrigger>
            </TabsList>

            <fieldset :disabled="isReadOnly" class="contents">
            <TabsContent value="dados">
                <div class="grid gap-6">
                    <!-- Situação -->
                    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                        <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3">
                            <Switch id="rot_fl_ativo" v-model="form.rot_fl_ativo" />
                            <div>
                                <p class="text-sm font-medium leading-none">Situação</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ form.rot_fl_ativo ? 'Rota ativa' : 'Rota inativa — não aparece nos lookups' }}
                                </p>
                            </div>
                            <span :class="['ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold', form.rot_fl_ativo ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                {{ form.rot_fl_ativo ? 'Ativa' : 'Inativa' }}
                            </span>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-12">
                            <!-- Ano letivo -->
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="rot_anl_id" :required="true">Ano letivo</FormLabel>
                                <select id="rot_anl_id" v-model.number="form.rot_anl_id" :class="selectClass(!!form.errors.rot_anl_id)">
                                    <option :value="null" disabled>Selecione...</option>
                                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">{{ anl.anl_ano }}</option>
                                </select>
                                <InputError :message="form.errors.rot_anl_id" />
                            </div>

                            <!-- Nome -->
                            <div class="grid gap-2 sm:col-span-6">
                                <FormLabel for="rot_nome" :required="true">Nome da rota</FormLabel>
                                <Input id="rot_nome" v-model="form.rot_nome" maxlength="120" placeholder="Ex.: Rota 03 — Zona Rural Norte" />
                                <InputError :message="form.errors.rot_nome" />
                            </div>

                            <!-- Turno -->
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="rot_turno" :required="true">Turno</FormLabel>
                                <select id="rot_turno" v-model="form.rot_turno" :class="selectClass(!!form.errors.rot_turno)">
                                    <option value="" disabled>Selecione...</option>
                                    <option v-for="(label, key) in turnos" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <InputError :message="form.errors.rot_turno" />
                            </div>

                            <!-- Zona -->
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="rot_zona">Zona</FormLabel>
                                <select id="rot_zona" v-model="form.rot_zona" :class="selectClass(!!form.errors.rot_zona)">
                                    <option value="">Não informada</option>
                                    <option v-for="(label, key) in zonas" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <InputError :message="form.errors.rot_zona" />
                            </div>

                            <!-- Veículo -->
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="rot_vei_id" :required="true">Veículo titular</FormLabel>
                                <VeiculoCombobox
                                    id="rot_vei_id"
                                    :model-value="form.rot_vei_id"
                                    :initial="initial?.veiculo ? { vei_id: initial.veiculo.vei_id, vei_placa: initial.veiculo.vei_placa } : null"
                                    :invalid="!!form.errors.rot_vei_id"
                                    @update:model-value="(v) => (form.rot_vei_id = v)"
                                    @selected="onVeiculoSelected"
                                />
                                <InputError :message="form.errors.rot_vei_id" />
                            </div>

                            <!-- Motorista -->
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="rot_mot_id" :required="true">Motorista titular</FormLabel>
                                <MotoristaCombobox
                                    id="rot_mot_id"
                                    :model-value="form.rot_mot_id"
                                    :initial="initial?.motorista ? { mot_id: initial.motorista.mot_id, mot_nome: initial.motorista.mot_nome } : null"
                                    :invalid="!!form.errors.rot_mot_id"
                                    @update:model-value="(v) => (form.rot_mot_id = v)"
                                />
                                <InputError :message="form.errors.rot_mot_id" />
                            </div>

                            <!-- Monitor -->
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="rot_tmo_id">Monitor</FormLabel>
                                <MonitorCombobox
                                    id="rot_tmo_id"
                                    :model-value="form.rot_tmo_id"
                                    :initial="initial?.monitor ? { tmo_id: initial.monitor.tmo_id, tmo_nome: initial.monitor.tmo_nome } : null"
                                    :invalid="!!form.errors.rot_tmo_id"
                                    @update:model-value="(v) => (form.rot_tmo_id = v)"
                                />
                                <InputError :message="form.errors.rot_tmo_id" />
                            </div>
                        </div>

                        <!-- Km / tempo (preenchidos pelo simulador na Fase 5) -->
                        <div v-if="mode === 'edit'" class="grid gap-5 sm:grid-cols-12">
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel>Distância estimada</FormLabel>
                                <Input :model-value="initial?.rot_km != null ? `${initial.rot_km} km` : 'A definir no simulador'" readonly class="bg-muted cursor-default" />
                            </div>
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel>Tempo estimado</FormLabel>
                                <Input :model-value="initial?.rot_tempo_min != null ? `${initial.rot_tempo_min} min` : 'A definir no simulador'" readonly class="bg-muted cursor-default" />
                            </div>
                            <div v-if="capacidade != null" class="grid gap-2 sm:col-span-6">
                                <FormLabel>Ocupação</FormLabel>
                                <div class="flex h-10 items-center gap-2">
                                    <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-semibold', excedeCapacidade ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300']">
                                        {{ totalPassageiros }} / {{ capacidade }}
                                    </span>
                                    <span v-if="excedeCapacidade" class="text-xs text-rose-600">Capacidade do veículo excedida.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Observações -->
                        <div class="grid gap-2">
                            <FormLabel for="rot_obs">Observações</FormLabel>
                            <textarea
                                id="rot_obs"
                                v-model="form.rot_obs"
                                rows="3"
                                maxlength="2000"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                                placeholder="Opcional"
                            ></textarea>
                            <InputError :message="form.errors.rot_obs" />
                        </div>
                    </div>

                    <p v-if="mode === 'create'" class="text-sm text-muted-foreground">
                        Após cadastrar, a rota abrirá em modo de edição com as abas <strong>Pontos de Parada</strong> e <strong>Passageiros</strong>.
                    </p>
                </div>
            </TabsContent>
            </fieldset>

            <TabsContent v-if="mode === 'edit'" value="pontos">
                <RotaPontosTab :rota="initial!" :pontos="pontos ?? []" />
            </TabsContent>

            <TabsContent v-if="mode === 'edit'" value="passageiros">
                <RotaPassageirosTab
                    :rota="initial!"
                    :pontos="pontos ?? []"
                    :passageiros="passageiros ?? []"
                    :capacidade="capacidade"
                />
            </TabsContent>

            <TabsContent v-if="mode === 'edit'" value="trajeto">
                <div class="grid gap-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Trajeto da rota</h3>
                            <p class="text-xs text-muted-foreground">Visualização dos pontos e do percurso. Edite no simulador georreferenciado.</p>
                        </div>
                        <Link :href="`/transporte/simulador?rot_id=${initial!.rot_id}`">
                            <Button type="button" variant="outline" size="sm">
                                <MapIcon class="mr-1 size-4" /> Abrir no Simulador
                            </Button>
                        </Link>
                    </div>
                    <MapaRota
                        ref="mapaRef"
                        :pontos="(pontos ?? []) as any"
                        :trajeto="initial?.rot_trajeto ?? null"
                        :editable="false"
                        height="560px"
                    />
                    <p v-if="!(pontos ?? []).some((p) => p.pto_latitude != null)" class="text-center text-xs text-muted-foreground">
                        Nenhum ponto georreferenciado ainda. Use o simulador para posicionar os pontos no mapa.
                    </p>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
