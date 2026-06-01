<script setup lang="ts">
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import CharCounter from '@/components/common/CharCounter.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import BairroCombobox from '@/components/escola/BairroCombobox.vue';
import GerenciaCombobox from '@/components/escola/GerenciaCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBairros } from '@/composables/useBairros';
import { useMunicipios } from '@/composables/useMunicipios';
import { useViaCep } from '@/composables/useViaCep';
import {
    DEP_ADMINISTRATIVA,
    FORMA_OCUPACAO,
    LOCALIZACAO_DIF,
    PROPRIETARIO_IMOVEL,
    SITUACAO_FUNC,
    TURNOS_ESCOLARES,
    ZONAS,
} from '@/lib/escolaEnums';
import type { SharedData, SystemParams } from '@/types';
import type { Municipio } from '@/types/aluno';
import type { Bairro, Escola, EscolaFormData, GerenciaRegional } from '@/types/escola';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Camera, ChevronLeft, ChevronRight, Loader2, LoaderCircle, Save, Trash2, Upload } from 'lucide-vue-next';
import EscolaSegmentosTab from '@/components/escola/tabs/EscolaSegmentosTab.vue';
import EscolaCensoTab from '@/components/escola/tabs/EscolaCensoTab.vue';
import type { AnoLetivoOption, EscolaSegmento } from '@/types/escola_segmento';
import type { CensoEscolarResumo } from '@/types/censo';
import type { AnoLetivo } from '@/types/parametro';
import type { Segmento } from '@/types/segmento';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Escola;
    escolaSegmentos?: EscolaSegmento[];
    segmentos?: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>[];
    anosLetivos?: AnoLetivoOption[];
    anoLetivoAtual?: Pick<AnoLetivo, 'anl_id' | 'anl_ano' | 'anl_dt_censo' | 'anl_fl_em_exercicio'> | null;
    censoHistorico?: CensoEscolarResumo[];
    censoAtual?: Pick<CensoEscolarResumo, 'cen_id' | 'cen_anl_id'> | null;
    censoPreviousExists?: boolean;
}>();

const page = usePage<SharedData>();
const DEFAULT_PARAMS: SystemParams = {
    nome_pessoa_caixa_alta: true,
    nome_escola_caixa_alta: true,
    alertar_homonimos: false,
    alertar_acentos_nomes: false,
    validar_idade_serie: false,
    gerar_matricula_auto: true,
    validar_carga_prof: false,
    cpf_obrigatorio: false,
    fardamento_obrigatorio: false,
    tipo_validacao_carga: 'avisar',
    
};
const params = computed<SystemParams>(() => page.props.params ?? DEFAULT_PARAMS);
const escolaUppercase = computed(() => params.value.nome_escola_caixa_alta);

type TabId = 'identificacao' | 'contato' | 'ata-autorizacao' | 'segmentos' | 'censo';
const TABS = computed<TabId[]>(() =>
    props.mode === 'edit'
        ? ['identificacao', 'contato', 'ata-autorizacao', 'segmentos', 'censo']
        : ['identificacao', 'contato', 'ata-autorizacao']
);

const TAB_FIELDS: Record<TabId, string[]> = {
    identificacao: [
        'esc_cd_inep', 'esc_cnpj', 'esc_nome', 'esc_apelido', 'esc_cd_escola', 'esc_logo',
        'esc_dep_administrativa', 'esc_proprietario_imovel', 'esc_forma_ocupacao',
        'esc_situacao_func', 'esc_regulamentada_conselho', 'esc_turno_escolar',
        'esc_ger_id', 'esc_orgao_regional_ensino',
        'esc_fl_predio_compartilhado', 'esc_fl_sorteio_vagas',
    ],
    contato: [
        'esc_cep', 'esc_logradouro', 'esc_numero', 'esc_complemento',
        'esc_bai_id', 'esc_mun_id', 'esc_zona', 'esc_localizacao_dif',
        'esc_latitude', 'esc_longitude', 'esc_caixa_postal',
        'esc_ddd', 'esc_telefone_fixo', 'esc_fax', 'esc_telefone_2', 'esc_telefone_3', 'esc_email', 'esc_site',
    ],
    'ata-autorizacao': [
        'esc_resolucao_num', 'esc_cme_portaria_num', 'esc_dt_publicacao', 'esc_fundamentacao_legal',
    ],
    segmentos: [],
    censo: [],
};

const currentMunicipio = ref<Municipio | null>(props.initial?.municipio ?? null);
const currentBairro = ref<Bairro | null>(props.initial?.bairro ?? null);
const initialGerencia = computed<GerenciaRegional | null>(() => props.initial?.gerencia ?? null);

const onMunicipioChange = (v: number | null) => {
    form.esc_mun_id = v;
    if (v !== currentMunicipio.value?.mun_id) {
        currentMunicipio.value = null;
        form.esc_bai_id = null;
        currentBairro.value = null;
    }
};

const onBairroChange = (v: number | null) => {
    form.esc_bai_id = v;
    if (v !== currentBairro.value?.bai_id) currentBairro.value = null;
};

const form = useForm<EscolaFormData>({
    esc_cd_inep: props.initial?.esc_cd_inep ?? '',
    esc_cnpj: props.initial?.esc_cnpj ?? '',
    esc_nome: props.initial?.esc_nome ?? '',
    esc_apelido: props.initial?.esc_apelido ?? '',
    esc_cd_escola: props.initial?.esc_cd_escola ?? '',

    esc_cep: props.initial?.esc_cep ?? '',
    esc_logradouro: props.initial?.esc_logradouro ?? '',
    esc_numero: props.initial?.esc_numero ?? '',
    esc_complemento: props.initial?.esc_complemento ?? '',
    esc_bai_id: props.initial?.esc_bai_id ?? null,
    esc_mun_id: props.initial?.esc_mun_id ?? null,
    esc_zona: props.initial?.esc_zona ?? '',
    esc_localizacao_dif: props.initial?.esc_localizacao_dif ?? null,
    esc_latitude: props.initial?.esc_latitude != null ? String(props.initial.esc_latitude) : '',
    esc_longitude: props.initial?.esc_longitude != null ? String(props.initial.esc_longitude) : '',
    esc_caixa_postal: props.initial?.esc_caixa_postal ?? '',

    esc_ddd: props.initial?.esc_ddd ?? '',
    esc_telefone_fixo: props.initial?.esc_telefone_fixo ?? '',
    esc_fax: props.initial?.esc_fax ?? '',
    esc_telefone_2: props.initial?.esc_telefone_2 ?? '',
    esc_telefone_3: props.initial?.esc_telefone_3 ?? '',
    esc_email: props.initial?.esc_email ?? '',
    esc_site: props.initial?.esc_site ?? '',

    esc_dep_administrativa: props.initial?.esc_dep_administrativa ?? '',
    esc_proprietario_imovel: props.initial?.esc_proprietario_imovel ?? '',
    esc_forma_ocupacao: props.initial?.esc_forma_ocupacao ?? '',
    esc_situacao_func: props.initial?.esc_situacao_func ?? '',
    esc_regulamentada_conselho: props.initial?.esc_regulamentada_conselho ?? null,
    esc_turno_escolar: props.initial?.esc_turno_escolar ?? '',
    esc_ger_id: props.initial?.esc_ger_id ?? null,
    esc_orgao_regional_ensino: props.initial?.esc_orgao_regional_ensino ?? '',
    esc_fl_predio_compartilhado: props.initial?.esc_fl_predio_compartilhado ?? false,
    esc_fl_sorteio_vagas: props.initial?.esc_fl_sorteio_vagas ?? false,
    esc_fl_ativo: props.initial?.esc_fl_ativo ?? true,

    esc_resolucao_num: props.initial?.esc_resolucao_num ?? '',
    esc_cme_portaria_num: props.initial?.esc_cme_portaria_num ?? '',
    esc_dt_publicacao: props.initial?.esc_dt_publicacao ?? '',
    esc_fundamentacao_legal: props.initial?.esc_fundamentacao_legal ?? '',

    esc_logo: null,
    _method: props.mode === 'edit' ? 'put' : 'post',
});

// Uppercase em tempo real conforme parâmetros.
watch(
    () => form.esc_nome,
    (v) => {
        if (!escolaUppercase.value || typeof v !== 'string') return;
        const up = v.toLocaleUpperCase('pt-BR');
        if (up !== v) form.esc_nome = up;
    },
);
watch(
    () => form.esc_apelido,
    (v) => {
        if (!escolaUppercase.value || typeof v !== 'string') return;
        const up = v.toLocaleUpperCase('pt-BR');
        if (up !== v) form.esc_apelido = up;
    },
);

const activeTab = ref<TabId>('identificacao');

const tabHasError = (tab: TabId): boolean => {
    const fields = TAB_FIELDS[tab];
    return fields.some((f) => Boolean((form.errors as Record<string, string>)[f]));
};

// CEP autopreencher
const numeroInput = ref<HTMLInputElement | null>(null);
const { loading: cepLoading, error: cepError, lookup: cepLookup } = useViaCep();
const { byIbge: municipioByIbge } = useMunicipios();
const { create: createBairro } = useBairros();

watch(
    () => form.esc_cep,
    async (v) => {
        const digits = (v ?? '').replace(/\D/g, '');
        if (digits.length !== 8) return;

        const result = await cepLookup(digits);
        if (!result) return;

        if (result.logradouro) form.esc_logradouro = result.logradouro;
        if (result.ddd && !form.esc_ddd) form.esc_ddd = result.ddd;

        // Resolve município via código IBGE
        if (result.ibge) {
            const mun = await municipioByIbge(result.ibge);
            if (mun) {
                form.esc_mun_id = mun.mun_id;
                currentMunicipio.value = mun;

                // firstOrCreate bairro daquele município
                if (result.bairro) {
                    const bairro = await createBairro(mun.mun_id, result.bairro);
                    if (bairro) {
                        form.esc_bai_id = bairro.bai_id;
                        currentBairro.value = bairro;
                    }
                }
            }
        }

        await nextTick();
        numeroInput.value?.focus();
    },
);

// Logo upload
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(props.initial?.esc_logo_url ?? null);
let objectUrl: string | null = null;

const onLogoChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.esc_logo = file;

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    if (file) {
        objectUrl = URL.createObjectURL(file);
        previewUrl.value = objectUrl;
    } else {
        previewUrl.value = props.initial?.esc_logo_url ?? null;
    }
};

const clearLogo = () => {
    form.esc_logo = null;
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
    previewUrl.value = props.initial?.esc_logo_url ?? null;
    if (fileInput.value) fileInput.value.value = '';
};

onBeforeUnmount(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
});

const goToFirstErrorTab = () => {
    for (const tab of TABS.value) {
        if (tabHasError(tab)) {
            activeTab.value = tab;
            return;
        }
    }
};

const submitLabel = computed(() => (props.mode === 'create' ? 'Cadastrar escola' : 'Salvar alterações'));

const submit = () => {
    const opts = {
        onError: () => goToFirstErrorTab(),
        preserveScroll: true,
        forceFormData: true,
    };
    if (props.mode === 'create') form.post('/escolas', opts);
    else if (props.initial) form.post(`/escolas/${props.initial.esc_id}`, opts);
};

const next = () => {
    const idx = TABS.value.indexOf(activeTab.value);
    if (idx < TABS.value.length - 1) activeTab.value = TABS.value[idx + 1];
};
const prev = () => {
    const idx = TABS.value.indexOf(activeTab.value);
    if (idx > 0) activeTab.value = TABS.value[idx - 1];
};
const isFirst = computed(() => activeTab.value === TABS.value[0]);
const isLast = computed(() => activeTab.value === TABS.value[TABS.value.length - 1]);
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/escolas">
                <Button type="button" variant="outline">Voltar para listagem</Button>
            </Link>
            <Button
                type="submit"
                :disabled="form.processing"
                class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="identificacao" :has-error="tabHasError('identificacao')">1. Identificação</TabsTrigger>
                <TabsTrigger value="contato" :has-error="tabHasError('contato')">2. Endereço e Contato</TabsTrigger>
                <TabsTrigger value="ata-autorizacao" :has-error="tabHasError('ata-autorizacao')">3. Ata de Autorização</TabsTrigger>
                <TabsTrigger v-if="mode === 'edit'" value="segmentos">4. Segmentos</TabsTrigger>
                <TabsTrigger v-if="mode === 'edit'" value="censo">5. Censo</TabsTrigger>
            </TabsList>

            <!-- Aba 1: Identificação -->
            <TabsContent value="identificacao" class="grid gap-6">
                <div class="flex flex-col items-center gap-4 rounded-xl border bg-card p-6 shadow-sm sm:flex-row sm:items-start sm:gap-6">
                    <div class="relative">
                        <div class="grid size-32 place-items-center overflow-hidden rounded-xl border-4 border-sky-100 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-900/20">
                            <img v-if="previewUrl" :src="previewUrl" alt="Logo" class="size-full object-cover" />
                            <Building2 v-else class="size-12 text-sky-700 dark:text-sky-300" />
                        </div>
                        <button
                            type="button"
                            class="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-sky-600 text-white shadow-md transition hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                            @click="fileInput?.click()"
                            aria-label="Selecionar logo"
                        >
                            <Camera class="size-4" />
                        </button>
                    </div>

                    <div class="flex flex-1 flex-col gap-2">
                        <Label for="esc_logo">Logo da escola</Label>
                        <p class="text-xs text-muted-foreground">JPG, PNG ou WEBP — até 5 MB.</p>
                        <input
                            id="esc_logo"
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onLogoChange"
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                                <Upload class="mr-2 size-4" />
                                {{ form.esc_logo || initial?.esc_logo ? 'Trocar logo' : 'Selecionar logo' }}
                            </Button>
                            <Button v-if="form.esc_logo || previewUrl" type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="clearLogo">
                                <Trash2 class="mr-2 size-4" /> Remover
                            </Button>
                        </div>
                        <InputError :message="(form.errors as Record<string, string>)['esc_logo']" />
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :for="'esc_nome'" :required="true">Nome</FormLabel>
                        <Input id="esc_nome" v-model="form.esc_nome" maxlength="150" :required="true" autofocus />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.esc_nome" />
                            <CharCounter :value="form.esc_nome" :max="150" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_cd_inep'" :required="true">Código Censo (INEP)</FormLabel>
                        <Input id="esc_cd_inep" v-model="form.esc_cd_inep" v-maska="'########'" :required="true" inputmode="numeric" placeholder="8 dígitos" maxlength="8" />
                        <InputError :message="form.errors.esc_cd_inep" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_cnpj'" :required="true">CNPJ</FormLabel>
                        <Input id="esc_cnpj" v-model="form.esc_cnpj" v-maska="'##.###.###/####-##'" :required="true" inputmode="numeric" placeholder="00.000.000/0000-00" maxlength="18" />
                        <InputError :message="form.errors.esc_cnpj" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_apelido'" :required="true">Apelido</FormLabel>
                        <Input id="esc_apelido" v-model="form.esc_apelido" maxlength="100" :required="true" />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.esc_apelido" />
                            <CharCounter :value="form.esc_apelido" :max="100" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="esc_cd_escola">Cód. Escola (interno)</Label>
                        <Input id="esc_cd_escola" v-model="form.esc_cd_escola" maxlength="20" />
                        <InputError :message="form.errors.esc_cd_escola" />
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                    <h3 class="text-sm font-semibold sm:col-span-2">Dados Administrativos</h3>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_dep_administrativa'" :required="true">Dependência Administrativa</FormLabel>
                        <select
                            id="esc_dep_administrativa"
                            :value="form.esc_dep_administrativa === '' ? '' : form.esc_dep_administrativa"
                            @change="(e) => (form.esc_dep_administrativa = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="o in DEP_ADMINISTRATIVA" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="form.errors.esc_dep_administrativa" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_situacao_func'" :required="true">Situação de Funcionamento</FormLabel>
                        <select
                            id="esc_situacao_func"
                            :value="form.esc_situacao_func === '' ? '' : form.esc_situacao_func"
                            @change="(e) => (form.esc_situacao_func = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="o in SITUACAO_FUNC" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="form.errors.esc_situacao_func" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_proprietario_imovel'" :required="true">Proprietário do Imóvel</FormLabel>
                        <select
                            id="esc_proprietario_imovel"
                            :value="form.esc_proprietario_imovel === '' ? '' : form.esc_proprietario_imovel"
                            @change="(e) => (form.esc_proprietario_imovel = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="o in PROPRIETARIO_IMOVEL" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="form.errors.esc_proprietario_imovel" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'esc_forma_ocupacao'" :required="true">Forma de Ocupação</FormLabel>
                        <select
                            id="esc_forma_ocupacao"
                            :value="form.esc_forma_ocupacao === '' ? '' : form.esc_forma_ocupacao"
                            @change="(e) => (form.esc_forma_ocupacao = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="o in FORMA_OCUPACAO" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError :message="form.errors.esc_forma_ocupacao" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel>Gerência Regional</FormLabel>
                        <GerenciaCombobox
                            :model-value="form.esc_ger_id"
                            :initial="initialGerencia"
                            :invalid="!!form.errors.esc_ger_id"
                            @update:model-value="(v) => (form.esc_ger_id = v)"
                        />
                        <InputError :message="form.errors.esc_ger_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="esc_orgao_regional_ensino">Órgão Regional de Ensino</Label>
                        <Input id="esc_orgao_regional_ensino" v-model="form.esc_orgao_regional_ensino" maxlength="120" />
                        <InputError :message="form.errors.esc_orgao_regional_ensino" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel for="esc_regulamentada_conselho" :required="true"> Regulamentada pelo Conselho</FormLabel>
                        <select
                            id="esc_regulamentada_conselho"
                            :value="form.esc_regulamentada_conselho != null ? form.esc_regulamentada_conselho ? 1 : 0 : ''"
                            @change="(e) => { const v = (e.target as HTMLSelectElement).value; form.esc_regulamentada_conselho = v === '' ? null : v === '1'; }"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </select>
                        <InputError :message="form.errors.esc_regulamentada_conselho" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="esc_turno_escolar">Turno Escolar</Label>
                        <select
                            id="esc_turno_escolar"
                            v-model="form.esc_turno_escolar"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Não informado</option>
                            <option v-for="t in TURNOS_ESCOLARES" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <InputError :message="form.errors.esc_turno_escolar" />
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <Switch id="esc_fl_predio_compartilhado" v-model="form.esc_fl_predio_compartilhado" />
                        <Label for="esc_fl_predio_compartilhado" class="text-sm font-normal">Prédio Compartilhado</Label>
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <Switch id="esc_fl_sorteio_vagas" v-model="form.esc_fl_sorteio_vagas" />
                        <Label for="esc_fl_sorteio_vagas" class="text-sm font-normal">Participa do Sorteio de Vagas</Label>
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <Switch id="esc_fl_ativo" v-model="form.esc_fl_ativo" />
                        <Label for="esc_fl_ativo" class="text-sm font-normal">Escola ativa no sistema</Label>
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 2: Endereço e Contato -->
            <TabsContent value="contato">
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6">
                    <h3 class="text-sm font-semibold sm:col-span-6">Endereço</h3>
                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :for="'esc_cep'" :required="true">CEP</FormLabel>
                        <div class="relative">
                            <Input id="esc_cep" v-model="form.esc_cep" v-maska="'#####-###'" :required="true" inputmode="numeric" placeholder="00000-000" maxlength="9" />
                            <Loader2 v-if="cepLoading" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                        </div>
                        <InputError :message="form.errors.esc_cep || cepError" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <FormLabel :for="'esc_logradouro'" :required="true">Endereço</FormLabel>
                        <Input id="esc_logradouro" v-model="form.esc_logradouro" maxlength="150" :required="true" />
                        <InputError :message="form.errors.esc_logradouro" />
                    </div>

                    <div class="grid gap-2 sm:col-span-1">
                        <FormLabel :for="'esc_numero'" :required="true">Número</FormLabel>
                        <Input id="esc_numero" ref="numeroInput" v-model="form.esc_numero" maxlength="10" :required="true" />
                        <InputError :message="form.errors.esc_numero" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="esc_complemento">Complemento</Label>
                        <Input id="esc_complemento" v-model="form.esc_complemento" maxlength="100" />
                        <InputError :message="form.errors.esc_complemento" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <FormLabel :required="true">Cidade / UF</FormLabel>
                        <MunicipioCombobox
                            :model-value="form.esc_mun_id"
                            :initial="currentMunicipio"
                            :invalid="!!form.errors.esc_mun_id"
                            @update:model-value="onMunicipioChange"
                        />
                        <InputError :message="form.errors.esc_mun_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <FormLabel :required="true">Bairro</FormLabel>
                        <BairroCombobox
                            :model-value="form.esc_bai_id"
                            :mun-id="form.esc_mun_id"
                            :initial="currentBairro"
                            :invalid="!!form.errors.esc_bai_id"
                            @update:model-value="onBairroChange"
                        />
                        <InputError :message="form.errors.esc_bai_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :for="'esc_zona'" :required="true">Zona</FormLabel>
                        <select id="esc_zona" v-model="form.esc_zona" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value="">Selecione...</option>
                            <option v-for="z in ZONAS" :key="z.value" :value="z.value">{{ z.label }}</option>
                        </select>
                        <InputError :message="form.errors.esc_zona" />
                    </div>

                    <div class="grid gap-2 sm:col-span-4">
                        <FormLabel :for="'esc_localizacao_dif'" :required="true">Localização Diferenciada (Censo)</FormLabel>
                        <select
                            id="esc_localizacao_dif"
                            :value="form.esc_localizacao_dif ?? ''"
                            @change="(e) => (form.esc_localizacao_dif = (e.target as HTMLSelectElement).value === '' ? null : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="opt in LOCALIZACAO_DIF" :key="opt.value" :value="opt.value">{{ opt.value }} — {{ opt.label }}</option>
                        </select>
                        <InputError :message="form.errors.esc_localizacao_dif" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="esc_latitude">Latitude</Label>
                        <Input id="esc_latitude" v-model="form.esc_latitude" inputmode="decimal" placeholder="-12.7654321" />
                        <InputError :message="form.errors.esc_latitude" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="esc_longitude">Longitude</Label>
                        <Input id="esc_longitude" v-model="form.esc_longitude" inputmode="decimal" placeholder="-38.1234567" />
                        <InputError :message="form.errors.esc_longitude" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="esc_caixa_postal">Caixa Postal</Label>
                        <Input id="esc_caixa_postal" v-model="form.esc_caixa_postal" maxlength="20" />
                        <InputError :message="form.errors.esc_caixa_postal" />
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6">
                    <h3 class="text-sm font-semibold sm:col-span-6">Contato</h3>
                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="esc_ddd">DDD</Label>
                        <Input id="esc_ddd" v-model="form.esc_ddd" v-maska="'##'" inputmode="numeric" maxlength="2" />
                        <InputError :message="form.errors.esc_ddd" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="esc_telefone_fixo">Telefone Fixo</Label>
                        <Input id="esc_telefone_fixo" v-model="form.esc_telefone_fixo" v-maska="'(##) #####-####'" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" />
                        <InputError :message="form.errors.esc_telefone_fixo" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="esc_fax">Fax</Label>
                        <Input id="esc_fax" v-model="form.esc_fax" v-maska="'(##) ####-####'" inputmode="numeric" placeholder="(00) 0000-0000" maxlength="14" />
                        <InputError :message="form.errors.esc_fax" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="esc_telefone_2">Telefone 2</Label>
                        <Input id="esc_telefone_2" v-model="form.esc_telefone_2" v-maska="'(##) #####-####'" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" />
                        <InputError :message="form.errors.esc_telefone_2" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="esc_telefone_3">Telefone 3</Label>
                        <Input id="esc_telefone_3" v-model="form.esc_telefone_3" v-maska="'(##) #####-####'" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" />
                        <InputError :message="form.errors.esc_telefone_3" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <FormLabel :for="'esc_email'" :required="true">E-mail</FormLabel>
                        <Input id="esc_email" type="email" v-model="form.esc_email" maxlength="150" :required="true" />
                        <InputError :message="form.errors.esc_email" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="esc_site">Site</Label>
                        <Input id="esc_site" type="url" v-model="form.esc_site" maxlength="200" placeholder="https://..." />
                        <InputError :message="form.errors.esc_site" />
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 3: Ata de Autorização -->
            <TabsContent value="ata-autorizacao">
                <div class="grid gap-6 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="esc_resolucao_num">Resolução Nº</Label>
                        <Input id="esc_resolucao_num" v-model="form.esc_resolucao_num" maxlength="50" />
                        <InputError :message="form.errors.esc_resolucao_num" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="esc_cme_portaria_num">CME Portaria Nº</Label>
                        <Input id="esc_cme_portaria_num" v-model="form.esc_cme_portaria_num" maxlength="50" />
                        <InputError :message="form.errors.esc_cme_portaria_num" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="esc_dt_publicacao">Data de Publicação</Label>
                        <Input id="esc_dt_publicacao" type="date" v-model="form.esc_dt_publicacao" />
                        <InputError :message="form.errors.esc_dt_publicacao" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="esc_fundamentacao_legal">Fundamentação Legal</Label>
                        <textarea
                            id="esc_fundamentacao_legal"
                            v-model="form.esc_fundamentacao_legal"
                            maxlength="2000"
                            rows="5"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                        <InputError :message="form.errors.esc_fundamentacao_legal" />
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 4: Segmentos (apenas modo edição) -->
            <TabsContent v-if="mode === 'edit' && initial" value="segmentos">
                <EscolaSegmentosTab
                    :esc-id="initial.esc_id"
                    :escola-segmentos="escolaSegmentos ?? []"
                    :segmentos="segmentos ?? []"
                    :anos-letivos="anosLetivos ?? []"
                />
            </TabsContent>

            <!-- Aba 5: Censo (apenas modo edição) -->
            <TabsContent v-if="mode === 'edit' && initial" value="censo">
                <EscolaCensoTab
                    :esc-id="initial.esc_id"
                    :ano-letivo-atual="anoLetivoAtual ?? null"
                    :censo-historico="censoHistorico ?? []"
                    :censo-atual="censoAtual ?? null"
                    :censo-previous-exists="censoPreviousExists ?? false"
                />
            </TabsContent>

        </Tabs>

        <!-- Navegação entre abas -->
        <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" :disabled="isFirst" @click="prev">
                <ChevronLeft class="mr-1 size-4" /> Anterior
            </Button>
            <Button v-if="!isLast" type="button" variant="outline" @click="next">
                Próximo <ChevronRight class="ml-1 size-4" />
            </Button>
        </div>
    </form>
</template>
