<script setup lang="ts">
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import AnoLetivoDialog from '@/components/parametro/AnoLetivoDialog.vue';
import DiasNaoLetivosTab from '@/components/parametro/DiasNaoLetivosTab.vue';
import ConceitoSection from '@/components/parametro/ConceitoSection.vue';
import SituacaoBloqueioSection from '@/components/parametro/SituacaoBloqueioSection.vue';
import DiasLetivosSection from '@/components/parametro/DiasLetivosSection.vue';
import MediaEscolaTab from '@/components/parametro/MediaEscolaTab.vue';
import GradeHorariosTab from '@/components/parametro/GradeHorariosTab.vue';
import UnidadeTab from '@/components/parametro/UnidadeTab.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Municipio } from '@/types/aluno';
import type { AnoLetivo, Conceito, DiaNaoLetivo, DiasLetivos, GradeHorario, MediaEscola, ParametroEntidade, ParametroEntidadeFormData, SegmentoResumo, SituacaoBloqueio, TurmaAlunoSituacaoResumo, Unidade } from '@/types/parametro';
import { router, useForm } from '@inertiajs/vue3';
import { Building2, Camera, CheckCircle2, LoaderCircle, Pencil, Plus, Save, Shield, Trash2, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    initial: ParametroEntidade;
    anosLetivos: AnoLetivo[];
    unidades: Unidade[];
    diasNaoLetivos: DiaNaoLetivo[];
    conceitos: Conceito[];
    situacoesBloqueio: SituacaoBloqueio[];
    situacoesAluno: TurmaAlunoSituacaoResumo[];
    diasLetivos: DiasLetivos[];
    mediasEscola: MediaEscola[];
    segmentos: SegmentoResumo[];
    gradeHorarios: GradeHorario[];
}>();

const TABS = ['entidade', 'ano_letivo', 'unidade', 'dias_nao_letivos', 'diario', 'cadastros', 'grade'] as const;
type TabId = (typeof TABS)[number];

const TAB_FIELDS: Record<TabId, string[]> = {
    entidade: [
        'par_nome_entidade', 'par_msg_cab_secretaria', 'par_msg_cab_estado',
        'par_endereco', 'par_mun_id', 'par_logomarca', 'par_brasao',
    ],
    ano_letivo: [],
    unidade: [],
    dias_nao_letivos: [],
    diario: [],
    cadastros: [
        'par_fl_nome_pessoa_caixa_alta', 'par_fl_nome_escola_caixa_alta',
        'par_fl_alertar_homonimos', 'par_fl_alertar_acentos_nomes',
        'par_fl_validar_idade_serie', 'par_fl_gerar_matricula_auto',
        'par_fl_validar_carga_prof', 'par_fl_cpf_obrigatorio',
        'par_fl_fardamento_obrigatorio', 'par_tipo_validacao_carga',
    ],
    grade: [],
};

const currentMunicipio = ref<Municipio | null>(props.initial.municipio ?? null);

const form = useForm<ParametroEntidadeFormData>({
    par_nome_entidade: props.initial.par_nome_entidade ?? '',
    par_msg_cab_secretaria: props.initial.par_msg_cab_secretaria ?? '',
    par_msg_cab_estado: props.initial.par_msg_cab_estado ?? '',
    par_endereco: props.initial.par_endereco ?? '',
    par_mun_id: props.initial.par_mun_id ?? null,
    par_logomarca: null,
    par_brasao: null,
    par_fl_nome_pessoa_caixa_alta: props.initial.par_fl_nome_pessoa_caixa_alta ?? true,
    par_fl_nome_escola_caixa_alta: props.initial.par_fl_nome_escola_caixa_alta ?? true,
    par_fl_alertar_homonimos: props.initial.par_fl_alertar_homonimos ?? false,
    par_fl_alertar_acentos_nomes: props.initial.par_fl_alertar_acentos_nomes ?? false,
    par_fl_validar_idade_serie: props.initial.par_fl_validar_idade_serie ?? false,
    par_fl_gerar_matricula_auto: props.initial.par_fl_gerar_matricula_auto ?? true,
    par_fl_validar_carga_prof: props.initial.par_fl_validar_carga_prof ?? false,
    par_fl_cpf_obrigatorio: props.initial.par_fl_cpf_obrigatorio ?? false,
    par_fl_fardamento_obrigatorio: props.initial.par_fl_fardamento_obrigatorio ?? false,
    par_tipo_validacao_carga: props.initial.par_tipo_validacao_carga ?? 'avisar',
    _method: 'put',
});

const activeTab = ref<TabId>('entidade');

const tabHasError = (tab: TabId): boolean => {
    const fields = TAB_FIELDS[tab];
    return fields.some((f) => Boolean((form.errors as Record<string, string>)[f]));
};

const onMunicipioChange = (v: number | null) => {
    form.par_mun_id = v;
    if (v !== currentMunicipio.value?.mun_id) currentMunicipio.value = null;
};

// Uploads
const logoInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(props.initial.par_logomarca_url ?? null);
let logoObjectUrl: string | null = null;

const onLogoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.par_logomarca = file;
    if (logoObjectUrl) { URL.revokeObjectURL(logoObjectUrl); logoObjectUrl = null; }
    if (file) { logoObjectUrl = URL.createObjectURL(file); logoPreview.value = logoObjectUrl; }
    else logoPreview.value = props.initial.par_logomarca_url ?? null;
};

const clearLogo = () => {
    form.par_logomarca = null;
    if (logoObjectUrl) { URL.revokeObjectURL(logoObjectUrl); logoObjectUrl = null; }
    logoPreview.value = props.initial.par_logomarca_url ?? null;
    if (logoInput.value) logoInput.value.value = '';
};

const brasaoInput = ref<HTMLInputElement | null>(null);
const brasaoPreview = ref<string | null>(props.initial.par_brasao_url ?? null);
let brasaoObjectUrl: string | null = null;

const onBrasaoChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.par_brasao = file;
    if (brasaoObjectUrl) { URL.revokeObjectURL(brasaoObjectUrl); brasaoObjectUrl = null; }
    if (file) { brasaoObjectUrl = URL.createObjectURL(file); brasaoPreview.value = brasaoObjectUrl; }
    else brasaoPreview.value = props.initial.par_brasao_url ?? null;
};

const clearBrasao = () => {
    form.par_brasao = null;
    if (brasaoObjectUrl) { URL.revokeObjectURL(brasaoObjectUrl); brasaoObjectUrl = null; }
    brasaoPreview.value = props.initial.par_brasao_url ?? null;
    if (brasaoInput.value) brasaoInput.value.value = '';
};

onBeforeUnmount(() => {
    if (logoObjectUrl) URL.revokeObjectURL(logoObjectUrl);
    if (brasaoObjectUrl) URL.revokeObjectURL(brasaoObjectUrl);
});

const goToFirstErrorTab = () => {
    for (const tab of TABS) {
        if (tabHasError(tab)) { activeTab.value = tab; return; }
    }
};

const submit = () => {
    form.post('/parametros', {
        onError: () => goToFirstErrorTab(),
        preserveScroll: true,
        forceFormData: true,
    });
};

const submitLabel = computed(() => 'Salvar alterações');

// Ano letivo
const dialogOpen = ref(false);
const editingAno = ref<AnoLetivo | null>(null);

const openCreate = () => {
    editingAno.value = null;
    dialogOpen.value = true;
};

const openEdit = (anl: AnoLetivo) => {
    editingAno.value = anl;
    dialogOpen.value = true;
};

const remove = (anl: AnoLetivo) => {
    if (!confirm(`Remover o ano letivo ${anl.anl_ano}?`)) return;
    router.delete(`/parametros/anos-letivos/${anl.anl_id}`, { preserveScroll: true, preserveState: true });
};

const fmtDate = (s?: string | null) => {
    if (!s) return '';
    const d = s.slice(0, 10);
    const [y, m, day] = d.split('-');
    return `${day}/${m}/${y}`;
};

const fmtDateTime = (s?: string | null) => {
    if (!s) return '';
    const d = new Date(s);
    return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

</script>

<template>
    <div class="grid gap-6">
        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="entidade" :has-error="tabHasError('entidade')">1. Entidade</TabsTrigger>
                <TabsTrigger value="ano_letivo" :has-error="tabHasError('ano_letivo')">2. Ano Letivo</TabsTrigger>
                <TabsTrigger value="unidade" :has-error="tabHasError('unidade')">3. Unidade</TabsTrigger>
                <TabsTrigger value="dias_nao_letivos" :has-error="tabHasError('dias_nao_letivos')">4. Dias Não Letivos</TabsTrigger>
                <TabsTrigger value="diario" :has-error="tabHasError('diario')">5. Diário</TabsTrigger>
                <TabsTrigger value="cadastros" :has-error="tabHasError('cadastros')">6. Cadastros</TabsTrigger>
                <TabsTrigger value="grade">7. Grade de Horários</TabsTrigger>
            </TabsList>

            <!-- Aba 1: Entidade -->
            <TabsContent value="entidade">
                <form @submit.prevent="submit" novalidate class="grid gap-6">
                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                        <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start sm:gap-6">
                            <div class="relative">
                                <div class="grid size-32 place-items-center overflow-hidden rounded-xl border-4 border-indigo-100 bg-indigo-50 dark:border-indigo-900/40 dark:bg-indigo-900/20">
                                    <img v-if="logoPreview" :src="logoPreview" alt="Logomarca" class="size-full object-contain" />
                                    <Building2 v-else class="size-12 text-indigo-700 dark:text-indigo-300" />
                                </div>
                                <button type="button" class="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-indigo-600 text-white shadow-md transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400" @click="logoInput?.click()" aria-label="Selecionar logomarca">
                                    <Camera class="size-4" />
                                </button>
                            </div>
                            <div class="flex flex-1 flex-col gap-2">
                                <Label for="par_logomarca">Logomarca</Label>
                                <p class="text-xs text-muted-foreground">JPG, PNG, WEBP ou SVG — até 5 MB.</p>
                                <input id="par_logomarca" ref="logoInput" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="hidden" @change="onLogoChange" />
                                <div class="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="logoInput?.click()">
                                        <Upload class="mr-2 size-4" />
                                        {{ form.par_logomarca || initial.par_logomarca ? 'Trocar' : 'Selecionar' }}
                                    </Button>
                                    <Button v-if="form.par_logomarca || logoPreview" type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="clearLogo">
                                        <Trash2 class="mr-2 size-4" /> Remover
                                    </Button>
                                </div>
                                <InputError :message="(form.errors as Record<string, string>)['par_logomarca']" />
                            </div>
                        </div>

                        <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start sm:gap-6">
                            <div class="relative">
                                <div class="grid size-32 place-items-center overflow-hidden rounded-xl border-4 border-fuchsia-100 bg-fuchsia-50 dark:border-fuchsia-900/40 dark:bg-fuchsia-900/20">
                                    <img v-if="brasaoPreview" :src="brasaoPreview" alt="Brasão" class="size-full object-contain" />
                                    <Shield v-else class="size-12 text-fuchsia-700 dark:text-fuchsia-300" />
                                </div>
                                <button type="button" class="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-fuchsia-600 text-white shadow-md transition hover:bg-fuchsia-700 dark:bg-fuchsia-500 dark:hover:bg-fuchsia-400" @click="brasaoInput?.click()" aria-label="Selecionar brasão">
                                    <Camera class="size-4" />
                                </button>
                            </div>
                            <div class="flex flex-1 flex-col gap-2">
                                <Label for="par_brasao">Brasão</Label>
                                <p class="text-xs text-muted-foreground">JPG, PNG, WEBP ou SVG — até 5 MB.</p>
                                <input id="par_brasao" ref="brasaoInput" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="hidden" @change="onBrasaoChange" />
                                <div class="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="brasaoInput?.click()">
                                        <Upload class="mr-2 size-4" />
                                        {{ form.par_brasao || initial.par_brasao ? 'Trocar' : 'Selecionar' }}
                                    </Button>
                                    <Button v-if="form.par_brasao || brasaoPreview" type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="clearBrasao">
                                        <Trash2 class="mr-2 size-4" /> Remover
                                    </Button>
                                </div>
                                <InputError :message="(form.errors as Record<string, string>)['par_brasao']" />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-12">
                        <div class="grid gap-2 sm:col-span-12">
                            <FormLabel :for="'par_nome_entidade'" :required="true">Nome da Entidade</FormLabel>
                            <Input id="par_nome_entidade" v-model="form.par_nome_entidade" maxlength="255" :required="true" autofocus />
                            <InputError :message="form.errors.par_nome_entidade" />
                        </div>

                        <div class="grid gap-2 sm:col-span-12">
                            <FormLabel :for="'par_msg_cab_secretaria'" :required="true">Mensagem Cabeçalho Relatório (Secretaria)</FormLabel>
                            <Input id="par_msg_cab_secretaria" v-model="form.par_msg_cab_secretaria" maxlength="255" :required="true" />
                            <InputError :message="form.errors.par_msg_cab_secretaria" />
                        </div>

                        <div class="grid gap-2 sm:col-span-12">
                            <FormLabel :for="'par_msg_cab_estado'" :required="true">Mensagem Cabeçalho Relatório (Estado)</FormLabel>
                            <Input id="par_msg_cab_estado" v-model="form.par_msg_cab_estado" maxlength="255" :required="true" />
                            <InputError :message="form.errors.par_msg_cab_estado" />
                        </div>

                        <div class="grid gap-2 sm:col-span-8">
                            <FormLabel :for="'par_endereco'" :required="true">Endereço</FormLabel>
                            <Input id="par_endereco" v-model="form.par_endereco" maxlength="255" :required="true" />
                            <InputError :message="form.errors.par_endereco" />
                        </div>

                        <div class="grid gap-2 sm:col-span-4">
                            <FormLabel>Cidade / UF</FormLabel>
                            <MunicipioCombobox
                                :model-value="form.par_mun_id"
                                :initial="currentMunicipio"
                                :invalid="!!form.errors.par_mun_id"
                                @update:model-value="onMunicipioChange"
                            />
                            <InputError :message="form.errors.par_mun_id" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700">
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Save v-else class="mr-2 size-4" />
                            {{ submitLabel }}
                        </Button>
                    </div>
                </form>
            </TabsContent>

            <!-- Aba 2: Ano Letivo -->
            <TabsContent value="ano_letivo">
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">Anos Letivos cadastrados</h3>
                        <div class="flex items-center gap-2">
                            <RefreshButton />
                            <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" @click="openCreate">
                                <Plus class="mr-2 size-4" /> Novo Ano Letivo
                            </Button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-indigo-600 text-white">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Ano</th>
                                    <th class="px-3 py-2 font-semibold">Atualizado</th>
                                    <th class="px-3 py-2 font-semibold">Login</th>
                                    <th class="px-3 py-2 font-semibold">Início Ano</th>
                                    <th class="px-3 py-2 font-semibold">Fim do Ano</th>
                                    <th class="px-3 py-2 font-semibold">Censo</th>
                                    <th class="px-3 py-2 text-center font-semibold">Freq. Mín.</th>
                                    <th class="px-3 py-2 text-center font-semibold">Média Geral</th>
                                    <th class="px-3 py-2 text-center font-semibold">Em Exercício</th>
                                    <th class="px-3 py-2 text-center font-semibold">Prog. Parcial</th>
                                    <th class="px-3 py-2 text-center font-semibold">Aprov. Conselho</th>
                                    <th class="px-3 py-2 text-right font-semibold">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="anosLetivos.length === 0">
                                    <td colspan="12" class="px-3 py-6 text-center text-muted-foreground">
                                        Nenhum ano letivo cadastrado.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(anl, idx) in anosLetivos"
                                    :key="anl.anl_id"
                                    :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                                >
                                    <td class="px-3 py-2 font-semibold">{{ anl.anl_ano }}</td>
                                    <td class="px-3 py-2 text-muted-foreground">{{ fmtDateTime(anl.anl_updated_at) }}</td>
                                    <td class="px-3 py-2 text-muted-foreground">{{ anl.updated_by?.name ?? anl.created_by?.name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_inicio_ano) }}</td>
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_fim) }}</td>
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_censo) }}</td>
                                    <td class="px-3 py-2 text-center">{{ anl.anl_frequencia_minima != null ? `${Number(anl.anl_frequencia_minima)}%` : '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ anl.anl_media_geral != null ? Number(anl.anl_media_geral).toFixed(1) : '—' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <CheckCircle2 v-if="anl.anl_fl_em_exercicio" class="mx-auto size-4 text-emerald-600" />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <CheckCircle2 v-if="anl.anl_fl_progressao_parcial" class="mx-auto size-4 text-emerald-600" />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <CheckCircle2 v-if="anl.anl_fl_aprovacao_conselho_freq" class="mx-auto size-4 text-emerald-600" />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button type="button" variant="ghost" size="sm" @click="openEdit(anl)" aria-label="Editar">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(anl)" aria-label="Remover">
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 3: Unidade -->
            <TabsContent value="unidade">
                <UnidadeTab :unidades="unidades" :anos-letivos="anosLetivos" />
            </TabsContent>

            <!-- Aba 4: Dias Não Letivos -->
            <TabsContent value="dias_nao_letivos">
                <DiasNaoLetivosTab :dias-nao-letivos="diasNaoLetivos" :anos-letivos="anosLetivos" />
            </TabsContent>

            <!-- Aba 5: Diário (Conceitos, Situações de bloqueio, Média por Escola) -->
            <TabsContent value="diario">
                <div class="grid gap-6">
                    <ConceitoSection :conceitos="conceitos" />
                    <SituacaoBloqueioSection :situacoes-bloqueio="situacoesBloqueio" :situacoes="situacoesAluno" />
                    <DiasLetivosSection :dias-letivos="diasLetivos" :anos-letivos="anosLetivos" :segmentos="segmentos" :unidades="unidades" />
                    <MediaEscolaTab :medias-escola="mediasEscola" :anos-letivos="anosLetivos" />
                </div>
            </TabsContent>

            <!-- Aba 6: Cadastros -->
            <TabsContent value="cadastros">
                <form @submit.prevent="submit" novalidate class="grid gap-6">
                    <div class="grid gap-6 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                        <!-- Coluna esquerda -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_nome_pessoa_caixa_alta" v-model="form.par_fl_nome_pessoa_caixa_alta" />
                                <Label for="par_fl_nome_pessoa_caixa_alta" class="text-sm font-normal">Cadastro do nome da pessoa em Caixa alta</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_alertar_homonimos" v-model="form.par_fl_alertar_homonimos" />
                                <Label for="par_fl_alertar_homonimos" class="text-sm font-normal">Alertar Homônimos</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_validar_idade_serie" v-model="form.par_fl_validar_idade_serie" />
                                <Label for="par_fl_validar_idade_serie" class="text-sm font-normal">Validar a Idade / Série do aluno</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_validar_carga_prof" v-model="form.par_fl_validar_carga_prof" />
                                <Label for="par_fl_validar_carga_prof" class="text-sm font-normal">Validar a carga horária do professor</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_fardamento_obrigatorio" v-model="form.par_fl_fardamento_obrigatorio" />
                                <Label for="par_fl_fardamento_obrigatorio" class="text-sm font-normal">Tornar fardamento do aluno obrigatório</Label>
                            </div>

                            <fieldset class="rounded-lg border bg-background p-4">
                                <legend class="px-2 text-xs font-semibold text-muted-foreground">Tipo de Validação</legend>
                                <div class="flex flex-col gap-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" value="bloquear" v-model="form.par_tipo_validacao_carga" class="size-4 accent-indigo-600" />
                                        Bloquear carga horária excedente
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" value="avisar" v-model="form.par_tipo_validacao_carga" class="size-4 accent-indigo-600" />
                                        Avisar somente
                                    </label>
                                </div>
                                <InputError :message="form.errors.par_tipo_validacao_carga" />
                            </fieldset>
                        </div>

                        <!-- Coluna direita -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_nome_escola_caixa_alta" v-model="form.par_fl_nome_escola_caixa_alta" />
                                <Label for="par_fl_nome_escola_caixa_alta" class="text-sm font-normal">Cadastro do nome da escola em Caixa alta</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_alertar_acentos_nomes" v-model="form.par_fl_alertar_acentos_nomes" />
                                <Label for="par_fl_alertar_acentos_nomes" class="text-sm font-normal">Alertar sobre acentos em nomes</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_gerar_matricula_auto" v-model="form.par_fl_gerar_matricula_auto" />
                                <Label for="par_fl_gerar_matricula_auto" class="text-sm font-normal">Gerar o número de matrícula automático</Label>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch id="par_fl_cpf_obrigatorio" v-model="form.par_fl_cpf_obrigatorio" />
                                <Label for="par_fl_cpf_obrigatorio" class="text-sm font-normal">Tornar CPF do aluno obrigatório</Label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700">
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Save v-else class="mr-2 size-4" />
                            {{ submitLabel }}
                        </Button>
                    </div>
                </form>
            </TabsContent>

            <!-- Aba 5: Grade de Horários -->
            <TabsContent value="grade">
                <GradeHorariosTab :grade-horarios="gradeHorarios" :segmentos="segmentos" />
            </TabsContent>

        </Tabs>

        <AnoLetivoDialog v-model:open="dialogOpen" :initial="editingAno" />
    </div>
</template>
