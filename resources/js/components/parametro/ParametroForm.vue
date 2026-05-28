<script setup lang="ts">
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import AnoLetivoDialog from '@/components/parametro/AnoLetivoDialog.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Municipio } from '@/types/aluno';
import type { AnoLetivo, ParametroEntidade, ParametroEntidadeFormData, TipoUnidade, TipoUnidadeTipo } from '@/types/parametro';
import { TIPO_UNIDADE_LABELS } from '@/types/parametro';
import { router, useForm } from '@inertiajs/vue3';
import { Building2, Camera, CheckCircle2, LoaderCircle, Pencil, Plus, Save, Shield, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

const props = defineProps<{
    initial: ParametroEntidade;
    anosLetivos: AnoLetivo[];
    tipoUnidades: TipoUnidade[];
}>();

const TABS = ['entidade', 'ano_letivo', 'unidade', 'cadastros'] as const;
type TabId = (typeof TABS)[number];

const TAB_FIELDS: Record<TabId, string[]> = {
    entidade: [
        'par_nome_entidade', 'par_msg_cab_secretaria', 'par_msg_cab_estado',
        'par_endereco', 'par_mun_id', 'par_logomarca', 'par_brasao',
    ],
    ano_letivo: [],
    unidade: [],
    cadastros: [
        'par_fl_nome_pessoa_caixa_alta', 'par_fl_nome_escola_caixa_alta',
        'par_fl_alertar_homonimos', 'par_fl_alertar_acentos_nomes',
        'par_fl_validar_idade_serie', 'par_fl_gerar_matricula_auto',
        'par_fl_validar_carga_prof', 'par_fl_cpf_obrigatorio',
        'par_fl_fardamento_obrigatorio', 'par_tipo_validacao_carga',
    ],
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
    router.delete(`/parametros/anos-letivos/${anl.anl_id}`, { preserveScroll: true });
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

// Tipo de Unidade
const TIPO_UNIDADE_OPTIONS = Object.entries(TIPO_UNIDADE_LABELS) as [TipoUnidadeTipo, string][];

const emptyTunForm = () => ({
    tun_tipo: '' as TipoUnidadeTipo | '',
    tun_anl_id_inicio: null as number | null,
    tun_anl_id_fim: null as number | null,
});

const showTunForm = ref(false);
const editingTun = ref<TipoUnidade | null>(null);
const tunForm = reactive(emptyTunForm());
const tunProcessing = ref(false);
const tunErrors = ref<Record<string, string>>({});

const openCreateTun = () => {
    editingTun.value = null;
    Object.assign(tunForm, emptyTunForm());
    tunErrors.value = {};
    showTunForm.value = true;
};

const openEditTun = (tun: TipoUnidade) => {
    editingTun.value = tun;
    tunForm.tun_tipo = tun.tun_tipo;
    tunForm.tun_anl_id_inicio = tun.tun_anl_id_inicio;
    tunForm.tun_anl_id_fim = tun.tun_anl_id_fim;
    tunErrors.value = {};
    showTunForm.value = true;
};

const cancelTun = () => {
    showTunForm.value = false;
    editingTun.value = null;
    tunErrors.value = {};
};

const saveTun = () => {
    tunProcessing.value = true;
    tunErrors.value = {};
    const data: Record<string, any> = {
        tun_tipo: tunForm.tun_tipo,
        tun_anl_id_inicio: tunForm.tun_anl_id_inicio,
        tun_anl_id_fim: tunForm.tun_anl_id_fim ?? '',
    };

    if (editingTun.value) {
        data._method = 'put';
        router.post(`/parametros/unidades/${editingTun.value.tun_id}`, data, {
            preserveScroll: true,
            onSuccess: () => { showTunForm.value = false; editingTun.value = null; },
            onError: (errs) => { tunErrors.value = errs; },
            onFinish: () => { tunProcessing.value = false; },
        });
    } else {
        router.post('/parametros/unidades', data, {
            preserveScroll: true,
            onSuccess: () => { showTunForm.value = false; },
            onError: (errs) => { tunErrors.value = errs; },
            onFinish: () => { tunProcessing.value = false; },
        });
    }
};

const removeTun = (tun: TipoUnidade) => {
    const label = TIPO_UNIDADE_LABELS[tun.tun_tipo];
    if (!confirm(`Remover o tipo de unidade "${label}"?`)) return;
    router.delete(`/parametros/unidades/${tun.tun_id}`, { preserveScroll: true });
};

const anoLetivoLabel = (anlId: number | null): string => {
    if (!anlId) return '—';
    const anl = props.anosLetivos.find((a) => a.anl_id === anlId);
    return anl ? String(anl.anl_ano) : String(anlId);
};
</script>

<template>
    <div class="grid gap-6">
        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="entidade" :has-error="tabHasError('entidade')">1. Entidade</TabsTrigger>
                <TabsTrigger value="ano_letivo" :has-error="tabHasError('ano_letivo')">2. Ano Letivo</TabsTrigger>
                <TabsTrigger value="unidade" :has-error="tabHasError('unidade')">3. Unidade</TabsTrigger>
                <TabsTrigger value="cadastros" :has-error="tabHasError('cadastros')">4. Cadastros</TabsTrigger>
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
                        <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" @click="openCreate">
                            <Plus class="mr-2 size-4" /> Novo Ano Letivo
                        </Button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-indigo-600 text-white">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Ano</th>
                                    <th class="px-3 py-2 font-semibold">Atualizado</th>
                                    <th class="px-3 py-2 font-semibold">Login</th>
                                    <th class="px-3 py-2 font-semibold">Início Ano</th>
                                    <th class="px-3 py-2 font-semibold">1º Sem.</th>
                                    <th class="px-3 py-2 font-semibold">2º Sem.</th>
                                    <th class="px-3 py-2 font-semibold">Fim do Ano</th>
                                    <th class="px-3 py-2 font-semibold">Censo</th>
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
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_inicio_1sem) }}</td>
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_inicio_2sem) }}</td>
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_fim) }}</td>
                                    <td class="px-3 py-2">{{ fmtDate(anl.anl_dt_censo) }}</td>
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
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">Tipos de Unidade cadastrados</h3>
                        <Button v-if="!showTunForm" type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" @click="openCreateTun">
                            <Plus class="mr-2 size-4" /> Novo Tipo de Unidade
                        </Button>
                    </div>

                    <!-- Tabela -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-indigo-600 text-white">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Tipo</th>
                                    <th class="px-3 py-2 font-semibold">Ano Início</th>
                                    <th class="px-3 py-2 font-semibold">Ano Fim</th>
                                    <th class="px-3 py-2 text-right font-semibold">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="tipoUnidades.length === 0">
                                    <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">
                                        Nenhum tipo de unidade cadastrado.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(tun, idx) in tipoUnidades"
                                    :key="tun.tun_id"
                                    :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                                >
                                    <td class="px-3 py-2 font-medium">{{ TIPO_UNIDADE_LABELS[tun.tun_tipo] }}</td>
                                    <td class="px-3 py-2">{{ tun.ano_letivo_inicio?.anl_ano ?? anoLetivoLabel(tun.tun_anl_id_inicio) }}</td>
                                    <td class="px-3 py-2">{{ tun.ano_letivo_fim?.anl_ano ?? (tun.tun_anl_id_fim ? anoLetivoLabel(tun.tun_anl_id_fim) : '—') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button type="button" variant="ghost" size="sm" @click="openEditTun(tun)" aria-label="Editar">
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="removeTun(tun)" aria-label="Remover">
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Formulário inline de criação/edição -->
                    <div v-if="showTunForm" class="rounded-lg border bg-background p-4">
                        <h4 class="mb-4 text-sm font-semibold">
                            {{ editingTun ? 'Editar Tipo de Unidade' : 'Novo Tipo de Unidade' }}
                        </h4>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <!-- Tipo -->
                            <div class="grid gap-1.5">
                                <FormLabel for="tun_tipo" :required="true">Tipo</FormLabel>
                                <select
                                    id="tun_tipo"
                                    v-model="tunForm.tun_tipo"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                                    :class="{ 'border-red-500 ring-1 ring-red-500': tunErrors.tun_tipo }"
                                >
                                    <option value="">Selecione...</option>
                                    <option v-for="[val, label] in TIPO_UNIDADE_OPTIONS" :key="val" :value="val">{{ label }}</option>
                                </select>
                                <InputError :message="tunErrors.tun_tipo" />
                            </div>

                            <!-- Ano Início -->
                            <div class="grid gap-1.5">
                                <FormLabel for="tun_anl_id_inicio" :required="true">Ano Letivo Início</FormLabel>
                                <select
                                    id="tun_anl_id_inicio"
                                    v-model="tunForm.tun_anl_id_inicio"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                                    :class="{ 'border-red-500 ring-1 ring-red-500': tunErrors.tun_anl_id_inicio }"
                                >
                                    <option :value="null">Selecione...</option>
                                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">{{ anl.anl_ano }}</option>
                                </select>
                                <InputError :message="tunErrors.tun_anl_id_inicio" />
                            </div>

                            <!-- Ano Fim -->
                            <div class="grid gap-1.5">
                                <FormLabel for="tun_anl_id_fim">Ano Letivo Fim <span class="text-muted-foreground">(opcional)</span></FormLabel>
                                <select
                                    id="tun_anl_id_fim"
                                    v-model="tunForm.tun_anl_id_fim"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                                    :class="{ 'border-red-500 ring-1 ring-red-500': tunErrors.tun_anl_id_fim }"
                                >
                                    <option :value="null">Sem fim (em vigor)</option>
                                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">{{ anl.anl_ano }}</option>
                                </select>
                                <InputError :message="tunErrors.tun_anl_id_fim" />
                            </div>
                        </div>

                        <InputError v-if="tunErrors.overlap" :message="tunErrors.overlap" class="mt-3" />

                        <div class="mt-4 flex justify-end gap-2">
                            <Button type="button" variant="outline" size="sm" @click="cancelTun">
                                <X class="mr-2 size-4" /> Cancelar
                            </Button>
                            <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="tunProcessing" @click="saveTun">
                                <LoaderCircle v-if="tunProcessing" class="mr-2 size-4 animate-spin" />
                                <Save v-else class="mr-2 size-4" />
                                Salvar
                            </Button>
                        </div>
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 4: Cadastros -->
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
        </Tabs>

        <AnoLetivoDialog v-model:open="dialogOpen" :initial="editingAno" />
    </div>
</template>
