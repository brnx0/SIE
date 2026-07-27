<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import DiarioChamada from '@/components/transporte/DiarioChamada.vue';
import DiarioOcorrencias from '@/components/transporte/DiarioOcorrencias.vue';
import FuncionarioTransporteCombobox from '@/components/transporte/FuncionarioTransporteCombobox.vue';
import VeiculoCombobox from '@/components/transporte/VeiculoCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import { type BreadcrumbItem } from '@/types';
import { ROTA_TURNOS, type ChamadaItem, type DiarioBordo, type OcorrenciaItem } from '@/types/transporte';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Download, Lock, LockOpen, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    diario: DiarioBordo;
    chamada: ChamadaItem[];
    ocorrencias: OcorrenciaItem[];
    pendentesSync: number;
    tiposOcorrencia: Record<string, string>;
}>();

const { isReadOnly, hasRole } = usePermissao();
const { focusFirstError } = useErrorFocus();

const activeTab = ref('registro');

// Trava do registro: fechado = somente-leitura em todas as abas.
const bloqueado = computed(() => props.diario.tdb_fl_fechado);
const somenteLeitura = computed(() => isReadOnly.value || bloqueado.value);
const podeReabrir = computed(() => hasRole('admin'));

const fechando = ref(false);
const fechar = () => {
    if (somenteLeitura.value) return;
    if (!confirm('Fechar este registro? Ele ficará somente-leitura até ser reaberto por um administrador.')) return;
    fechando.value = true;
    router.put(`/transporte/diario-bordo/${props.diario.tdb_id}/fechar`, {}, {
        preserveScroll: true,
        onFinish: () => { fechando.value = false; },
    });
};

const reabrir = () => {
    if (!confirm('Reabrir este registro para edição?')) return;
    fechando.value = true;
    router.put(`/transporte/diario-bordo/${props.diario.tdb_id}/reabrir`, {}, {
        preserveScroll: true,
        onFinish: () => { fechando.value = false; },
    });
};

const form = useForm<{
    tdb_vei_id: number | null;
    tdb_fun_id_motorista: number | null;
    tdb_fun_id_monitor: number | null;
    tdb_hr_saida: string;
    tdb_hr_chegada: string;
    tdb_km_inicial: number | null;
    tdb_km_final: number | null;
    tdb_obs: string;
    _method: string;
}>({
    tdb_vei_id:     props.diario.tdb_vei_id ?? null,
    tdb_fun_id_motorista: props.diario.tdb_fun_id_motorista ?? null,
    tdb_fun_id_monitor:   props.diario.tdb_fun_id_monitor ?? null,
    tdb_hr_saida:   props.diario.tdb_hr_saida ?? '',
    tdb_hr_chegada: props.diario.tdb_hr_chegada ?? '',
    tdb_km_inicial: props.diario.tdb_km_inicial ?? null,
    tdb_km_final:   props.diario.tdb_km_final ?? null,
    tdb_obs:        props.diario.tdb_obs ?? '',
    _method: 'put',
});

const salvar = () => {
    if (somenteLeitura.value) return;
    form.post(`/transporte/diario-bordo/${props.diario.tdb_id}`, {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            activeTab.value = 'registro';
            focusFirstError(Object.keys(errors));
        },
    });
};

const fmtData = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('pt-BR');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Diário de Bordo', href: '/transporte/diario-bordo' },
    { title: `${props.diario.rota?.rot_nome ?? 'Registro'} · ${fmtData(props.diario.tdb_dt_viagem)}`, href: `/transporte/diario-bordo/${props.diario.tdb_id}/edit` },
];

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground';
</script>

<template>
    <Head :title="`Diário · ${diario.rota?.rot_nome ?? ''}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <!-- Cabeçalho -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ diario.rota?.rot_nome ?? 'Registro de viagem' }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ fmtData(diario.tdb_dt_viagem) }}
                        <template v-if="diario.rota"> · {{ ROTA_TURNOS[diario.rota.rot_turno] ?? diario.rota.rot_turno }}</template>
                        <template v-if="diario.rota?.anl_ano"> · {{ diario.rota.anl_ano }}</template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/transporte/diario-bordo">
                        <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <a :href="`/transporte/diario-bordo/${diario.tdb_id}/pdf`" target="_blank" rel="noopener noreferrer">
                        <Button type="button" variant="outline"><Download class="mr-1 size-4" /> Baixar PDF</Button>
                    </a>
                    <template v-if="!bloqueado">
                        <Button v-if="!isReadOnly" type="button" variant="outline" :disabled="fechando" class="border-amber-400 text-amber-700 hover:bg-amber-50 dark:text-amber-300" @click="fechar">
                            <LoaderCircle v-if="fechando" class="mr-2 size-4 animate-spin" />
                            <Lock v-else class="mr-2 size-4" />
                            Fechar registro
                        </Button>
                        <Button v-if="!isReadOnly" type="button" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="salvar">
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Save v-else class="mr-2 size-4" />
                            Salvar registro
                        </Button>
                    </template>
                    <Button v-else-if="podeReabrir" type="button" variant="outline" :disabled="fechando" @click="reabrir">
                        <LoaderCircle v-if="fechando" class="mr-2 size-4 animate-spin" />
                        <LockOpen v-else class="mr-2 size-4" />
                        Reabrir
                    </Button>
                </div>
            </div>

            <div v-if="bloqueado" class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                <Lock class="size-4 shrink-0" />
                <span>Registro <strong>fechado</strong> — somente leitura.<template v-if="podeReabrir"> Use "Reabrir" para voltar a editar.</template><template v-else> Apenas um administrador pode reabri-lo.</template></span>
            </div>

            <Tabs v-model="activeTab">
                <TabsList>
                    <TabsTrigger value="registro" :has-error="Object.keys(form.errors).length > 0">Registro</TabsTrigger>
                    <TabsTrigger value="chamada">Chamada</TabsTrigger>
                    <TabsTrigger value="ocorrencias">Ocorrências</TabsTrigger>
                </TabsList>

                <fieldset :disabled="somenteLeitura" class="contents">
                <TabsContent value="registro">
                    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                        <div>
                            <h3 class="text-sm font-semibold">Equipe da viagem</h3>
                            <p class="text-xs text-muted-foreground">Padrão é o titular da rota; troque em caso de reserva/substituição.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-12">
                            <div class="grid gap-2 sm:col-span-4">
                                <FormLabel for="tdb_vei_id" :required="true">Veículo</FormLabel>
                                <VeiculoCombobox
                                    :model-value="form.tdb_vei_id"
                                    :initial="diario.veiculo ? { vei_id: diario.veiculo.vei_id, vei_placa: diario.veiculo.vei_placa } : null"
                                    :invalid="!!form.errors.tdb_vei_id"
                                    @update:model-value="(v) => (form.tdb_vei_id = v)"
                                />
                                <InputError :message="form.errors.tdb_vei_id" />
                            </div>
                            <div class="grid gap-2 sm:col-span-4">
                                <FormLabel for="tdb_fun_id_motorista" :required="true">Motorista</FormLabel>
                                <FuncionarioTransporteCombobox
                                    papel="motoristas"
                                    placeholder="Buscar motorista..."
                                    :model-value="form.tdb_fun_id_motorista"
                                    :initial="diario.motorista ? { fun_id: diario.motorista.fun_id, fun_nome: diario.motorista.fun_nome } : null"
                                    :invalid="!!form.errors.tdb_fun_id_motorista"
                                    @update:model-value="(v) => (form.tdb_fun_id_motorista = v)"
                                />
                                <InputError :message="form.errors.tdb_fun_id_motorista" />
                            </div>
                            <div class="grid gap-2 sm:col-span-4">
                                <FormLabel for="tdb_fun_id_monitor">Monitor</FormLabel>
                                <FuncionarioTransporteCombobox
                                    papel="monitores"
                                    placeholder="Buscar monitor (opcional)..."
                                    :model-value="form.tdb_fun_id_monitor"
                                    :initial="diario.monitor ? { fun_id: diario.monitor.fun_id, fun_nome: diario.monitor.fun_nome } : null"
                                    :invalid="!!form.errors.tdb_fun_id_monitor"
                                    @update:model-value="(v) => (form.tdb_fun_id_monitor = v)"
                                />
                                <InputError :message="form.errors.tdb_fun_id_monitor" />
                            </div>
                        </div>

                        <div class="border-t pt-5">
                            <h3 class="text-sm font-semibold">Horários e quilometragem</h3>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-12">
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="tdb_hr_saida">Hora de saída</FormLabel>
                                <Input id="tdb_hr_saida" v-model="form.tdb_hr_saida" type="time" />
                                <InputError :message="form.errors.tdb_hr_saida" />
                            </div>
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="tdb_hr_chegada">Hora de chegada</FormLabel>
                                <Input id="tdb_hr_chegada" v-model="form.tdb_hr_chegada" type="time" />
                                <InputError :message="form.errors.tdb_hr_chegada" />
                            </div>
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="tdb_km_inicial">Km inicial</FormLabel>
                                <Input id="tdb_km_inicial" v-model.number="form.tdb_km_inicial" type="number" step="0.1" min="0" placeholder="0,0" />
                                <InputError :message="form.errors.tdb_km_inicial" />
                            </div>
                            <div class="grid gap-2 sm:col-span-3">
                                <FormLabel for="tdb_km_final">Km final</FormLabel>
                                <Input id="tdb_km_final" v-model.number="form.tdb_km_final" type="number" step="0.1" min="0" placeholder="0,0" />
                                <InputError :message="form.errors.tdb_km_final" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <FormLabel for="tdb_obs">Observações</FormLabel>
                            <textarea id="tdb_obs" v-model="form.tdb_obs" rows="3" maxlength="2000" :class="selectClass + ' py-2'" placeholder="Opcional"></textarea>
                            <InputError :message="form.errors.tdb_obs" />
                        </div>
                    </div>
                </TabsContent>
                </fieldset>

                <TabsContent value="chamada">
                    <DiarioChamada :diario="diario" :chamada="chamada" :pendentes-sync="pendentesSync" :bloqueado="somenteLeitura" />
                </TabsContent>

                <TabsContent value="ocorrencias">
                    <DiarioOcorrencias :diario="diario" :ocorrencias="ocorrencias" :tipos="tiposOcorrencia" :bloqueado="somenteLeitura" />
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
