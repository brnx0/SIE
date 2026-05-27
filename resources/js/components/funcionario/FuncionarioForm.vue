<script setup lang="ts">
import AdmissaoLotacaoTab from '@/components/funcionario/AdmissaoLotacaoTab.vue';
import CharCounter from '@/components/common/CharCounter.vue';
import CpfDuplicadoDialog from '@/components/funcionario/CpfDuplicadoDialog.vue';
import FuncionarioHomonimoDialog, { type FuncionarioHomonimoMatch } from '@/components/funcionario/FuncionarioHomonimoDialog.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import Switch from '@/components/common/Switch.vue';
import Tabs from '@/components/common/Tabs.vue';
import TabsContent from '@/components/common/TabsContent.vue';
import TabsList from '@/components/common/TabsList.vue';
import TabsTrigger from '@/components/common/TabsTrigger.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useViaCep } from '@/composables/useViaCep';
import { COR_RACA } from '@/lib/corRaca';
import { PAISES } from '@/lib/paises';
import { UFS } from '@/lib/ufs';
import {
    ESCOLARIDADES,
    ESTADOS_CIVIS,
    NACIONALIDADES,
    RELIGIOES,
    FONTES_TRANSPORTE,
    MODELOS_CERTIDAO,
    TIPOS_CERTIDAO_CIVIL,
} from '@/lib/funcionarioEnums';
import type { SharedData, SystemParams } from '@/types';
import type { Municipio } from '@/types/aluno';
import type { Funcionario, FuncionarioFormData } from '@/types/funcionario';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Camera, ChevronLeft, ChevronRight, Loader2, LoaderCircle, Save, Trash2, Upload } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Funcionario;
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
const nomeUppercase = computed(() => params.value.nome_pessoa_caixa_alta);

const TABS = ['dados-pessoais', 'documentacao', 'endereco-contato', 'admissao-lotacao'] as const;
type TabId = (typeof TABS)[number];

const TAB_FIELDS: Record<TabId, string[]> = {
    'dados-pessoais': [
        'fun_nome', 'fun_nome_social', 'fun_dt_nascimento', 'fun_sexo', 'fun_cor_raca',
        'fun_nacionalidade', 'fun_pais_origem', 'fun_mun_id_nasc',
        'fun_cpf', 'fun_religiao', 'fun_escolaridade', 'fun_estado_civil',
        'fun_povo_indigena', 'fun_cd_censo',
    ],
    documentacao: [
        'fun_rg_numero', 'fun_rg_dt_emissao', 'fun_rg_uf', 'fun_rg_orgao_emissor',
        'fun_certidao_modelo', 'fun_certidao_tipo', 'fun_certidao_dt_emissao',
        'fun_certidao_numero', 'fun_certidao_livro', 'fun_certidao_pagina',
        'fun_certidao_mun_id', 'fun_certidao_cartorio',
        'fun_ctps_numero', 'fun_ctps_serie', 'fun_pis_pasep',
        'fun_titulo_eleitor', 'fun_titulo_zona', 'fun_titulo_secao',
        'fun_certificado_reservista',
    ],
    'endereco-contato': [
        'fun_cep', 'fun_logradouro', 'fun_numero', 'fun_complemento',
        'fun_bairro', 'fun_cidade', 'fun_uf',
        'fun_telefone', 'fun_celular', 'fun_email',
    ],
    'admissao-lotacao': [],
};

const formatDateBR = (iso: string | null | undefined): string => {
    if (!iso) return '';
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return iso;
    return `${m[3]}/${m[2]}/${m[1]}`;
};

const parseDateBR = (br: string): string => {
    const m = br.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : br;
};

const initialMunicipio = computed<Municipio | null>(
    () => props.initial?.municipio_nascimento ?? null,
);
const initialMunicipioCertidao = computed<Municipio | null>(
    () => props.initial?.municipio_certidao ?? null,
);

const dataNascBR = ref(formatDateBR(props.initial?.fun_dt_nascimento));
const dataRgBR = ref(formatDateBR(props.initial?.fun_rg_dt_emissao));
const dataCertidaoBR = ref(formatDateBR(props.initial?.fun_certidao_dt_emissao));

const form = useForm<FuncionarioFormData>({
    // Dados pessoais
    fun_nome: props.initial?.fun_nome ?? '',
    fun_nome_social: props.initial?.fun_nome_social ?? '',
    fun_dt_nascimento: props.initial?.fun_dt_nascimento ?? '',
    fun_sexo: props.initial?.fun_sexo ?? '',
    fun_cor_raca: props.initial?.fun_cor_raca ?? null,
    fun_nacionalidade: props.initial?.fun_nacionalidade ?? 'Brasileira',
    fun_pais_origem: props.initial?.fun_pais_origem ?? 'Brasil',
    fun_mun_id_nasc: props.initial?.fun_mun_id_nasc ?? null,
    fun_cpf: props.initial?.fun_cpf ?? '',
    fun_religiao: props.initial?.fun_religiao ?? '',
    fun_escolaridade: props.initial?.fun_escolaridade ?? '',
    fun_estado_civil: props.initial?.fun_estado_civil ?? '',
    fun_povo_indigena: props.initial?.fun_povo_indigena ?? '',
    fun_cd_censo: props.initial?.fun_cd_censo ?? '',

    // Documentação
    fun_rg_numero: props.initial?.fun_rg_numero ?? '',
    fun_rg_dt_emissao: props.initial?.fun_rg_dt_emissao ?? '',
    fun_rg_uf: props.initial?.fun_rg_uf ?? '',
    fun_rg_orgao_emissor: props.initial?.fun_rg_orgao_emissor ?? '',
    fun_certidao_modelo: props.initial?.fun_certidao_modelo ?? '',
    fun_certidao_tipo: props.initial?.fun_certidao_tipo ?? '',
    fun_certidao_dt_emissao: props.initial?.fun_certidao_dt_emissao ?? '',
    fun_certidao_numero: props.initial?.fun_certidao_numero ?? '',
    fun_certidao_livro: props.initial?.fun_certidao_livro ?? '',
    fun_certidao_pagina: props.initial?.fun_certidao_pagina ?? '',
    fun_certidao_mun_id: props.initial?.fun_certidao_mun_id ?? null,
    fun_certidao_cartorio: props.initial?.fun_certidao_cartorio ?? '',
    fun_ctps_numero: props.initial?.fun_ctps_numero ?? '',
    fun_ctps_serie: props.initial?.fun_ctps_serie ?? '',
    fun_pis_pasep: props.initial?.fun_pis_pasep ?? '',
    fun_titulo_eleitor: props.initial?.fun_titulo_eleitor ?? '',
    fun_titulo_zona: props.initial?.fun_titulo_zona ?? '',
    fun_titulo_secao: props.initial?.fun_titulo_secao ?? '',
    fun_certificado_reservista: props.initial?.fun_certificado_reservista ?? '',

    // Endereço
    fun_cep: props.initial?.fun_cep ?? '',
    fun_logradouro: props.initial?.fun_logradouro ?? '',
    fun_numero: props.initial?.fun_numero ?? '',
    fun_complemento: props.initial?.fun_complemento ?? '',
    fun_bairro: props.initial?.fun_bairro ?? '',
    fun_cidade: props.initial?.fun_cidade ?? '',
    fun_uf: props.initial?.fun_uf ?? '',

    // Contato
    fun_telefone: props.initial?.fun_telefone ?? '',
    fun_celular: props.initial?.fun_celular ?? '',
    fun_email: props.initial?.fun_email ?? '',

    fun_fl_usa_transporte: props.initial?.fun_fl_usa_transporte ?? false,
    fun_transporte_tipo: props.initial?.fun_transporte_tipo ?? '',

    fun_foto: null,
    fun_fl_ativo: props.initial?.fun_fl_ativo ?? true,
    confirm_homonimo: false,
    _method: props.mode === 'edit' ? 'put' : 'post',
});

// Date watchers
watch(dataNascBR, (v) => {
    form.fun_dt_nascimento = v && v.length === 10 ? parseDateBR(v) : '';
});
watch(dataRgBR, (v) => {
    form.fun_rg_dt_emissao = v && v.length === 10 ? parseDateBR(v) : '';
});
watch(dataCertidaoBR, (v) => {
    form.fun_certidao_dt_emissao = v && v.length === 10 ? parseDateBR(v) : '';
});

// Uppercase conforme parâmetro
watch(
    () => form.fun_nome,
    (v) => {
        if (!nomeUppercase.value || typeof v !== 'string') return;
        const up = v.toLocaleUpperCase('pt-BR');
        if (up !== v) form.fun_nome = up;
    },
);

const activeTab = ref<TabId>('dados-pessoais');

// CPF duplicado — dialog
const CPF_DUP_PREFIX = 'CPF já cadastrado para: ';
const cpfDuplicadoOpen = ref(false);
const cpfDuplicadoOwner = computed(() => {
    const err = form.errors.fun_cpf ?? '';
    if (!err.startsWith(CPF_DUP_PREFIX)) return null;
    return err.slice(CPF_DUP_PREFIX.length).replace(/\.$/, '');
});
watch(cpfDuplicadoOwner, (owner) => {
    if (owner) {
        activeTab.value = 'documentacao';
        cpfDuplicadoOpen.value = true;
    }
});

// Homônimo flow — detecta via ValidationException com chave 'homonimo' (JSON)
const homonimoOpen = ref(false);
const homonimoMatches = ref<FuncionarioHomonimoMatch[]>([]);

const handleHomonimo = (errors: Record<string, string>) => {
    const raw = errors.homonimo;
    if (raw) {
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed) && parsed.length) {
                homonimoMatches.value = parsed;
                homonimoOpen.value = true;
                delete form.errors.homonimo;
            }
        } catch {}
    }
};

const confirmarHomonimo = () => {
    form.confirm_homonimo = true;
    homonimoOpen.value = false;
    submit();
};

const cancelarHomonimo = () => {
    homonimoOpen.value = false;
    form.confirm_homonimo = false;
};

const tabHasError = (tab: TabId): boolean => {
    const fields = TAB_FIELDS[tab];
    return fields.some((f) => Boolean((form.errors as Record<string, string>)[f]));
};

// CEP autopreencher
const numeroInput = ref<HTMLInputElement | null>(null);
const { loading: cepLoading, error: cepError, lookup: cepLookup } = useViaCep();

watch(
    () => form.fun_cep,
    async (v) => {
        const digits = (v ?? '').replace(/\D/g, '');
        if (digits.length === 8) {
            const result = await cepLookup(digits);
            if (result) {
                form.fun_logradouro = result.logradouro || form.fun_logradouro;
                form.fun_bairro = result.bairro || form.fun_bairro;
                form.fun_cidade = result.cidade || form.fun_cidade;
                form.fun_uf = result.uf || form.fun_uf;
                await nextTick();
                numeroInput.value?.focus();
            }
        }
    },
);

const goToFirstErrorTab = () => {
    for (const tab of TABS) {
        if (tabHasError(tab)) {
            activeTab.value = tab;
            return;
        }
    }
};

const submitLabel = computed(() => (props.mode === 'create' ? 'Cadastrar funcionário' : 'Salvar alterações'));

const submit = () => {
    const opts = {
        onError: (errors: Record<string, string>) => {
            handleHomonimo(errors);
            goToFirstErrorTab();
        },
        onSuccess: () => {
            form.confirm_homonimo = false;
        },
        preserveScroll: true,
        forceFormData: true,
    };

    if (props.mode === 'create') {
        form.post('/funcionarios', opts);
    } else if (props.initial) {
        form.post(`/funcionarios/${props.initial.fun_id}`, opts);
    }
};

const next = () => {
    const idx = TABS.indexOf(activeTab.value);
    if (idx < TABS.length - 1) activeTab.value = TABS[idx + 1];
};
const prev = () => {
    const idx = TABS.indexOf(activeTab.value);
    if (idx > 0) activeTab.value = TABS[idx - 1];
};

const isFirst = computed(() => activeTab.value === TABS[0]);
const isLast = computed(() => activeTab.value === TABS[TABS.length - 1]);

// Foto
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(props.initial?.fun_foto_url ?? null);
let objectUrl: string | null = null;

const onFotoChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.fun_foto = file;

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    if (file) {
        objectUrl = URL.createObjectURL(file);
        previewUrl.value = objectUrl;
    } else {
        previewUrl.value = props.initial?.fun_foto_url ?? null;
    }
};

const clearFoto = () => {
    form.fun_foto = null;
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
    previewUrl.value = props.initial?.fun_foto_url ?? null;
    if (fileInput.value) fileInput.value.value = '';
};

onBeforeUnmount(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
});

const initials = computed(() => {
    const parts = (form.fun_nome || '').trim().split(/\s+/);
    return parts
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase() || '?';
});
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <!-- Topo: Cancelar + Salvar -->
        <div class="flex items-center justify-between">
            <Link href="/funcionarios">
                <Button type="button" variant="outline">Cancelar</Button>
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
                <TabsTrigger value="dados-pessoais" :has-error="tabHasError('dados-pessoais')">
                    1. Dados Pessoais
                </TabsTrigger>
                <TabsTrigger value="documentacao" :has-error="tabHasError('documentacao')">
                    2. Documentação
                </TabsTrigger>
                <TabsTrigger value="endereco-contato" :has-error="tabHasError('endereco-contato')">
                    3. Endereço e Contato
                </TabsTrigger>
                <TabsTrigger value="admissao-lotacao" :has-error="tabHasError('admissao-lotacao')">
                    4. Admissão / Lotação
                </TabsTrigger>
            </TabsList>

            <!-- Aba 1: Dados Pessoais -->
            <TabsContent value="dados-pessoais" class="grid gap-6">
                <!-- Foto + Status ativo -->
                <div class="flex flex-col items-center gap-4 rounded-xl border bg-card p-6 shadow-sm sm:flex-row sm:items-start sm:gap-6">
                    <div class="relative">
                        <div class="grid size-32 place-items-center overflow-hidden rounded-full border-4 border-sky-100 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-900/20">
                            <img
                                v-if="previewUrl"
                                :src="previewUrl"
                                alt="Foto do funcionário"
                                class="size-full object-cover"
                            />
                            <span v-else class="text-3xl font-semibold text-sky-700 dark:text-sky-300">
                                {{ initials }}
                            </span>
                        </div>
                        <button
                            type="button"
                            class="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-sky-600 text-white shadow-md transition hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                            @click="fileInput?.click()"
                            aria-label="Selecionar foto"
                        >
                            <Camera class="size-4" />
                        </button>
                    </div>

                    <div class="flex flex-1 flex-col gap-2">
                        <Label for="fun_foto">Foto do funcionário</Label>
                        <p class="text-xs text-muted-foreground">JPG, PNG ou WEBP — até 5 MB.</p>
                        <input
                            id="fun_foto"
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onFotoChange"
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                                <Upload class="mr-2 size-4" />
                                {{ form.fun_foto || initial?.fun_foto ? 'Trocar foto' : 'Selecionar foto' }}
                            </Button>
                            <Button
                                v-if="form.fun_foto || previewUrl"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                @click="clearFoto"
                            >
                                <Trash2 class="mr-2 size-4" /> Remover
                            </Button>
                        </div>
                        <InputError :message="(form.errors as Record<string, string>)['fun_foto']" />
                    </div>

                    <div class="flex items-center gap-3 self-start rounded-lg border px-4 py-3">
                        <Switch id="fun_fl_ativo" v-model="form.fun_fl_ativo" />
                        <Label for="fun_fl_ativo" class="text-sm font-normal whitespace-nowrap">
                            Funcionário ativo
                        </Label>
                    </div>
                </div>

                <!-- Campos pessoais -->
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :for="'fun_nome'" :required="true">Nome completo</FormLabel>
                        <Input
                            id="fun_nome"
                            v-model="form.fun_nome"
                            maxlength="100"
                            :required="true"
                            autofocus
                            placeholder="Ex.: Maria da Silva Santos"
                        />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.fun_nome" />
                            <CharCounter :value="form.fun_nome" :max="100" />
                        </div>
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :for="'fun_nome_social'">Nome social</FormLabel>
                        <Input
                            id="fun_nome_social"
                            v-model="form.fun_nome_social"
                            maxlength="100"
                            placeholder="Preencha apenas se o funcionário utiliza nome social"
                        />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.fun_nome_social" />
                            <CharCounter :value="form.fun_nome_social" :max="100" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_dt_nascimento'" :required="true">Data de nascimento</FormLabel>
                        <Input
                            id="fun_dt_nascimento"
                            v-model="dataNascBR"
                            v-maska="'##/##/####'"
                            :required="true"
                            inputmode="numeric"
                            placeholder="DD/MM/AAAA"
                            maxlength="10"
                        />
                        <InputError :message="form.errors.fun_dt_nascimento" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_sexo'" :required="true">Sexo</FormLabel>
                        <select
                            id="fun_sexo"
                            v-model="form.fun_sexo"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                        </select>
                        <InputError :message="form.errors.fun_sexo" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_cpf'" :required="true">CPF</FormLabel>
                        <Input
                            id="fun_cpf"
                            v-model="form.fun_cpf"
                            v-maska="'###.###.###-##'"
                            inputmode="numeric"
                            placeholder="000.000.000-00"
                            maxlength="14"
                            :required="true"
                        />
                        <InputError :message="cpfDuplicadoOwner ? '' : form.errors.fun_cpf" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_cor_raca'" :required="true">Etnia / Cor / Raça (Censo)</FormLabel>
                        <select
                            id="fun_cor_raca"
                            :value="form.fun_cor_raca ?? ''"
                            @change="(e) => (form.fun_cor_raca = (e.target as HTMLSelectElement).value === '' ? null : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="opt in COR_RACA" :key="opt.value" :value="opt.value">
                                {{ opt.value }} — {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.fun_cor_raca" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_povo_indigena">Povo Indígena</Label>
                        <Input id="fun_povo_indigena" v-model="form.fun_povo_indigena" maxlength="60" />
                        <InputError :message="form.errors.fun_povo_indigena" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_nacionalidade'" :required="true">Nacionalidade</FormLabel>
                        <select
                            id="fun_nacionalidade"
                            v-model="form.fun_nacionalidade"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option v-for="n in NACIONALIDADES" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <InputError :message="form.errors.fun_nacionalidade" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_pais_origem'" :required="true">País de Origem</FormLabel>
                        <select
                            id="fun_pais_origem"
                            v-model="form.fun_pais_origem"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option v-for="p in PAISES" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <InputError :message="form.errors.fun_pais_origem" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :required="true">Naturalidade (Município)</FormLabel>
                        <MunicipioCombobox
                            :model-value="form.fun_mun_id_nasc"
                            :initial="initialMunicipio"
                            :invalid="!!form.errors.fun_mun_id_nasc"
                            @update:model-value="(v) => (form.fun_mun_id_nasc = v)"
                        />
                        <InputError :message="form.errors.fun_mun_id_nasc" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_escolaridade'" :required="true">Escolaridade</FormLabel>
                        <select
                            id="fun_escolaridade"
                            :value="form.fun_escolaridade === '' ? '' : form.fun_escolaridade"
                            @change="(e) => (form.fun_escolaridade = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="opt in ESCOLARIDADES" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.fun_escolaridade" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'fun_estado_civil'" :required="true">Estado Civil</FormLabel>
                        <select
                            id="fun_estado_civil"
                            :value="form.fun_estado_civil === '' ? '' : form.fun_estado_civil"
                            @change="(e) => (form.fun_estado_civil = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="opt in ESTADOS_CIVIS" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.fun_estado_civil" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_religiao">Religião</Label>
                        <select
                            id="fun_religiao"
                            v-model="form.fun_religiao"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Não informada</option>
                            <option v-for="r in RELIGIOES" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <InputError :message="form.errors.fun_religiao" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_cd_censo">Código CENSO</Label>
                        <Input
                            id="fun_cd_censo"
                            v-model="form.fun_cd_censo"
                            v-maska="'############'"
                            inputmode="numeric"
                            placeholder="12 dígitos"
                            maxlength="12"
                        />
                        <InputError :message="form.errors.fun_cd_censo" />
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 2: Documentação -->
            <TabsContent value="documentacao" class="grid gap-6">
                <!-- RG -->
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4">
                    <h3 class="text-sm font-semibold sm:col-span-4">Registro Geral (RG)</h3>

                    <div class="grid gap-2">
                        <Label for="fun_rg_numero">Número do RG</Label>
                        <Input id="fun_rg_numero" v-model="form.fun_rg_numero" maxlength="20" />
                        <InputError :message="form.errors.fun_rg_numero" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_rg_dt_emissao">Data de Emissão</Label>
                        <Input
                            id="fun_rg_dt_emissao"
                            v-model="dataRgBR"
                            v-maska="'##/##/####'"
                            inputmode="numeric"
                            placeholder="DD/MM/AAAA"
                            maxlength="10"
                        />
                        <InputError :message="form.errors.fun_rg_dt_emissao" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_rg_uf">UF</Label>
                        <select
                            id="fun_rg_uf"
                            v-model="form.fun_rg_uf"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">—</option>
                            <option v-for="uf in UFS" :key="uf" :value="uf">{{ uf }}</option>
                        </select>
                        <InputError :message="form.errors.fun_rg_uf" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_rg_orgao_emissor">Órgão Emissor</Label>
                        <Input id="fun_rg_orgao_emissor" v-model="form.fun_rg_orgao_emissor" maxlength="20" placeholder="SSP, IFP, etc." />
                        <InputError :message="form.errors.fun_rg_orgao_emissor" />
                    </div>
                </div>

                <!-- Certidão Civil -->
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4">
                    <h3 class="text-sm font-semibold sm:col-span-4">Certidão Civil</h3>

                    <div class="grid gap-2">
                        <Label for="fun_certidao_modelo">Modelo</Label>
                        <select
                            id="fun_certidao_modelo"
                            v-model="form.fun_certidao_modelo"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="m in MODELOS_CERTIDAO" :key="m" :value="m">{{ m }}</option>
                        </select>
                        <InputError :message="form.errors.fun_certidao_modelo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_certidao_tipo">Tipo</Label>
                        <select
                            id="fun_certidao_tipo"
                            v-model="form.fun_certidao_tipo"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="t in TIPOS_CERTIDAO_CIVIL" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <InputError :message="form.errors.fun_certidao_tipo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_certidao_dt_emissao">Data de Emissão</Label>
                        <Input
                            id="fun_certidao_dt_emissao"
                            v-model="dataCertidaoBR"
                            v-maska="'##/##/####'"
                            inputmode="numeric"
                            placeholder="DD/MM/AAAA"
                            maxlength="10"
                        />
                        <InputError :message="form.errors.fun_certidao_dt_emissao" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_certidao_numero">Número</Label>
                        <Input id="fun_certidao_numero" v-model="form.fun_certidao_numero" maxlength="32" />
                        <InputError :message="form.errors.fun_certidao_numero" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_certidao_livro">Livro</Label>
                        <Input id="fun_certidao_livro" v-model="form.fun_certidao_livro" maxlength="10" />
                        <InputError :message="form.errors.fun_certidao_livro" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_certidao_pagina">Página</Label>
                        <Input id="fun_certidao_pagina" v-model="form.fun_certidao_pagina" maxlength="10" />
                        <InputError :message="form.errors.fun_certidao_pagina" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label>Município</Label>
                        <MunicipioCombobox
                            :model-value="form.fun_certidao_mun_id"
                            :initial="initialMunicipioCertidao"
                            :invalid="!!form.errors.fun_certidao_mun_id"
                            @update:model-value="(v) => (form.fun_certidao_mun_id = v)"
                        />
                        <InputError :message="form.errors.fun_certidao_mun_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="fun_certidao_cartorio">Cartório</Label>
                        <Input id="fun_certidao_cartorio" v-model="form.fun_certidao_cartorio" maxlength="100" />
                        <InputError :message="form.errors.fun_certidao_cartorio" />
                    </div>
                </div>

                <!-- CTPS / PIS / Título / Reservista -->
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                    <h3 class="text-sm font-semibold sm:col-span-3">Outros Documentos</h3>

                    <div class="grid gap-2">
                        <Label for="fun_ctps_numero">Carteira de Trabalho Nº</Label>
                        <Input id="fun_ctps_numero" v-model="form.fun_ctps_numero" maxlength="20" />
                        <InputError :message="form.errors.fun_ctps_numero" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_ctps_serie">Série</Label>
                        <Input id="fun_ctps_serie" v-model="form.fun_ctps_serie" maxlength="10" />
                        <InputError :message="form.errors.fun_ctps_serie" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_pis_pasep">PIS/PASEP</Label>
                        <Input id="fun_pis_pasep" v-model="form.fun_pis_pasep" maxlength="20" />
                        <InputError :message="form.errors.fun_pis_pasep" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_titulo_eleitor">Título de Eleitor</Label>
                        <Input id="fun_titulo_eleitor" v-model="form.fun_titulo_eleitor" maxlength="15" />
                        <InputError :message="form.errors.fun_titulo_eleitor" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_titulo_zona">Zona</Label>
                        <Input id="fun_titulo_zona" v-model="form.fun_titulo_zona" maxlength="5" />
                        <InputError :message="form.errors.fun_titulo_zona" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_titulo_secao">Seção</Label>
                        <Input id="fun_titulo_secao" v-model="form.fun_titulo_secao" maxlength="5" />
                        <InputError :message="form.errors.fun_titulo_secao" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fun_certificado_reservista">Certificado Reservista</Label>
                        <Input id="fun_certificado_reservista" v-model="form.fun_certificado_reservista" maxlength="20" />
                        <InputError :message="form.errors.fun_certificado_reservista" />
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 3: Endereço e Contato -->
            <TabsContent value="endereco-contato" class="grid gap-6">
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <h3 class="text-sm font-semibold">Endereço</h3>
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="fun_cep">CEP</Label>
                        <div class="relative">
                            <Input
                                id="fun_cep"
                                v-model="form.fun_cep"
                                v-maska="'#####-###'"
                                inputmode="numeric"
                                placeholder="00000-000"
                                maxlength="9"
                            />
                            <Loader2
                                v-if="cepLoading"
                                class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                            />
                        </div>
                        <InputError :message="form.errors.fun_cep || cepError" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="fun_logradouro">Rua / Logradouro</Label>
                        <Input id="fun_logradouro" v-model="form.fun_logradouro" maxlength="150" />
                        <InputError :message="form.errors.fun_logradouro" />
                    </div>

                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="fun_numero">Número</Label>
                        <Input id="fun_numero" ref="numeroInput" v-model="form.fun_numero" maxlength="10" />
                        <InputError :message="form.errors.fun_numero" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="fun_complemento">Complemento</Label>
                        <Input id="fun_complemento" v-model="form.fun_complemento" maxlength="100" />
                        <InputError :message="form.errors.fun_complemento" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="fun_bairro">Bairro</Label>
                        <Input id="fun_bairro" v-model="form.fun_bairro" maxlength="100" />
                        <InputError :message="form.errors.fun_bairro" />
                    </div>

                    <div class="grid gap-2 sm:col-span-4">
                        <Label for="fun_cidade">Cidade</Label>
                        <Input id="fun_cidade" v-model="form.fun_cidade" maxlength="100" />
                        <InputError :message="form.errors.fun_cidade" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="fun_uf">UF</Label>
                        <select
                            id="fun_uf"
                            v-model="form.fun_uf"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">—</option>
                            <option v-for="uf in UFS" :key="uf" :value="uf">{{ uf }}</option>
                        </select>
                        <InputError :message="form.errors.fun_uf" />
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <h3 class="text-sm font-semibold">Contato</h3>
                    </div>
                    <div class="grid gap-2">
                        <Label for="fun_telefone">Telefone</Label>
                        <Input
                            id="fun_telefone"
                            v-model="form.fun_telefone"
                            v-maska="'(##) ####-####'"
                            inputmode="numeric"
                            placeholder="(00) 0000-0000"
                            maxlength="14"
                        />
                        <InputError :message="form.errors.fun_telefone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fun_celular">Celular</Label>
                        <Input
                            id="fun_celular"
                            v-model="form.fun_celular"
                            v-maska="'(##) #####-####'"
                            inputmode="numeric"
                            placeholder="(00) 00000-0000"
                            maxlength="15"
                        />
                        <InputError :message="form.errors.fun_celular" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fun_email">E-mail</Label>
                        <Input
                            id="fun_email"
                            type="email"
                            v-model="form.fun_email"
                            maxlength="150"
                            placeholder="email@exemplo.com"
                        />
                        <InputError :message="form.errors.fun_email" />
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <h3 class="text-sm font-semibold">Transporte</h3>
                    </div>
                    <div class="flex items-center gap-3 sm:col-span-2">
                        <Switch id="fun_fl_usa_transporte" v-model="form.fun_fl_usa_transporte" />
                        <Label for="fun_fl_usa_transporte" class="text-sm font-normal">
                            Usa transporte
                        </Label>
                    </div>
                    <div v-if="form.fun_fl_usa_transporte" class="grid gap-2">
                        <Label for="fun_transporte_tipo">Fonte do transporte</Label>
                        <select
                            id="fun_transporte_tipo"
                            v-model="form.fun_transporte_tipo"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="ft in FONTES_TRANSPORTE" :key="ft" :value="ft">{{ ft }}</option>
                        </select>
                        <InputError :message="form.errors.fun_transporte_tipo" />
                    </div>
                </div>

            </TabsContent>

            <!-- Aba 4: Admissão / Lotação -->
            <TabsContent value="admissao-lotacao">
                <div v-if="mode === 'create'" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
                    Salve o funcionário primeiro para gerenciar admissões e lotações.
                </div>
                <AdmissaoLotacaoTab v-else-if="initial" :funcionario="initial" />
            </TabsContent>
        </Tabs>

        <!-- Navegação entre abas -->
        <div class="flex justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                :disabled="isFirst"
                @click="prev"
            >
                <ChevronLeft class="mr-1 size-4" /> Anterior
            </Button>
            <Button
                v-if="!isLast"
                type="button"
                variant="outline"
                @click="next"
            >
                Próximo <ChevronRight class="ml-1 size-4" />
            </Button>
        </div>
    </form>

    <CpfDuplicadoDialog
        v-if="cpfDuplicadoOwner"
        :open="cpfDuplicadoOpen"
        :owner="cpfDuplicadoOwner"
        @update:open="cpfDuplicadoOpen = $event"
    />

    <FuncionarioHomonimoDialog
        :open="homonimoOpen"
        :matches="homonimoMatches"
        :processing="form.processing"
        @update:open="homonimoOpen = $event"
        @confirm="confirmarHomonimo"
        @cancel="cancelarHomonimo"
    />
</template>
