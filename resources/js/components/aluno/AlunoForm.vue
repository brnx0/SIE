<script setup lang="ts">
import CharCounter from '@/components/common/CharCounter.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import HomonimoDialog, { type HomonimoMatch } from '@/components/aluno/HomonimoDialog.vue';
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
import { TIPOS_SANGUINEOS } from '@/lib/tiposSanguineos';
import {
    PATOLOGIAS, PATOLOGIAS_INFANCIA, DEFICIENCIAS, TRANSTORNOS_GLOBAIS,
    TRANSTORNOS_APRENDIZAGEM, CLINICAS, RECURSOS_INEP,
} from '@/lib/alunoSaudeEnums';
import { UFS } from '@/lib/ufs';
import type { SharedData, SystemParams } from '@/types';
import type { Aluno, AlunoFormData, Municipio } from '@/types/aluno';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Camera, ChevronLeft, ChevronRight, Loader2, LoaderCircle, Save, Trash2, Upload } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

interface MatriculaResumo {
    tma_id: number;
    tma_situacao: string;
    tma_dt_matricula: string | null;
    situacao_saida: string | null;
    anl_ano: number | null;
    esc_nome: string | null;
    ser_nome: string | null;
    tur_nome: string | null;
    tur_turno: string | null;
    seg_nome: string | null;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Aluno;
    matriculas?: MatriculaResumo[];
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
const cpfRequired = computed(() => params.value.cpf_obrigatorio);
const nomeUppercase = computed(() => params.value.nome_pessoa_caixa_alta);
const alertarAcentos = computed(() => params.value.alertar_acentos_nomes);

const stripAccents = (str: string) => str.normalize('NFD').replace(/[̀-ͯ]/g, '');

const TABS = ['dados-pessoais', 'documentacao', 'filiacao-contato', 'complementares', 'turmas'] as const;
type TabId = (typeof TABS)[number];

// "turmas" só existe no modo edit e não participa da navegação Anterior/Próximo
const NAV_TABS = TABS.filter(t => t !== 'turmas') as unknown as typeof TABS;

const TAB_FIELDS: Record<TabId, string[]> = {
    'dados-pessoais': ['aln_nome', 'aln_nome_social', 'aln_dt_nascimento', 'aln_sexo', 'aln_cor_raca', 'aln_pais_origem', 'aln_mun_id_nasc'],
    documentacao: ['aln_cpf', 'aln_cd_inep', 'aln_nr_matricula', 'aln_nr_certidao'],
    'filiacao-contato': [
        'aln_filiacao_1',
        'aln_filiacao_1_tipo',
        'aln_filiacao_2',
        'aln_filiacao_2_tipo',
        'aln_cep',
        'aln_logradouro',
        'aln_numero',
        'aln_complemento',
        'aln_bairro',
        'aln_cidade',
        'aln_uf',
        'aln_telefone',
        'aln_email',
    ],
    complementares: [
        'saude.als_tipo_sanguineo', 'saude.als_ds_alergias', 'saude.als_fl_pcd',
        'saude.als_contato_emergencia', 'saude.als_telefone_emergencia',
        'saude.als_plano_saude', 'saude.als_cartao_sus',
        'saude.als_alergia_a', 'saude.als_remedio_febre', 'saude.als_remedio_cefaleia',
        'saude.als_patologias', 'saude.als_outra_doenca',
        'saude.als_patologias_infancia', 'saude.als_outra_doenca_infancia',
        'saude.als_deficiencias', 'saude.als_transtornos_globais', 'saude.als_transtornos_aprendizagem',
        'saude.als_deficiencia_outro', 'saude.als_fl_altas_habilidades', 'saude.als_cid', 'saude.als_observacao',
        'saude.als_clinicas', 'saude.als_recursos_inep',
    ],
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

const dataBR = ref(formatDateBR(props.initial?.aln_dt_nascimento));

const form = useForm<AlunoFormData>({
    aln_nome: props.initial?.aln_nome ?? '',
    aln_nome_social: props.initial?.aln_nome_social ?? '',
    aln_dt_nascimento: props.initial?.aln_dt_nascimento ?? '',
    aln_sexo: props.initial?.aln_sexo ?? '',
    aln_cor_raca: props.initial?.aln_cor_raca ?? null,
    aln_pais_origem: props.initial?.aln_pais_origem ?? 'Brasil',
    aln_mun_id_nasc: props.initial?.aln_mun_id_nasc ?? null,

    aln_cpf: props.initial?.aln_cpf ?? '',
    aln_cd_inep: props.initial?.aln_cd_inep ?? '',
    aln_nr_matricula: props.initial?.aln_nr_matricula ?? null,
    aln_nr_certidao: props.initial?.aln_nr_certidao ?? '',

    aln_filiacao_1:      props.initial?.aln_filiacao_1 ?? '',
    aln_filiacao_1_tipo: props.initial?.aln_filiacao_1_tipo ?? '',
    aln_filiacao_2:      props.initial?.aln_filiacao_2 ?? '',
    aln_filiacao_2_tipo: props.initial?.aln_filiacao_2_tipo ?? '',

    aln_cep: props.initial?.aln_cep ?? '',
    aln_logradouro: props.initial?.aln_logradouro ?? '',
    aln_numero: props.initial?.aln_numero ?? '',
    aln_complemento: props.initial?.aln_complemento ?? '',
    aln_bairro: props.initial?.aln_bairro ?? '',
    aln_cidade: props.initial?.aln_cidade ?? '',
    aln_uf: props.initial?.aln_uf ?? '',
    aln_telefone: props.initial?.aln_telefone ?? '',
    aln_email: props.initial?.aln_email ?? '',

    aln_foto: null,
    aln_fl_ativo: props.initial?.aln_fl_ativo ?? true,
    confirm_homonimo: false,
    _method: props.mode === 'edit' ? 'put' : 'post',

    saude: {
        als_tipo_sanguineo: props.initial?.saude?.als_tipo_sanguineo ?? '',
        als_ds_alergias: props.initial?.saude?.als_ds_alergias ?? '',
        als_fl_pcd: props.initial?.saude?.als_fl_pcd ?? false,
        als_contato_emergencia: props.initial?.saude?.als_contato_emergencia ?? '',
        als_telefone_emergencia: props.initial?.saude?.als_telefone_emergencia ?? '',
        als_plano_saude: props.initial?.saude?.als_plano_saude ?? '',
        als_cartao_sus: props.initial?.saude?.als_cartao_sus ?? '',
        als_alergia_a: props.initial?.saude?.als_alergia_a ?? '',
        als_remedio_febre: props.initial?.saude?.als_remedio_febre ?? '',
        als_remedio_cefaleia: props.initial?.saude?.als_remedio_cefaleia ?? '',
        als_patologias: props.initial?.saude?.als_patologias ?? [],
        als_outra_doenca: props.initial?.saude?.als_outra_doenca ?? '',
        als_patologias_infancia: props.initial?.saude?.als_patologias_infancia ?? [],
        als_outra_doenca_infancia: props.initial?.saude?.als_outra_doenca_infancia ?? '',
        als_deficiencias: props.initial?.saude?.als_deficiencias ?? [],
        als_transtornos_globais: props.initial?.saude?.als_transtornos_globais ?? [],
        als_transtornos_aprendizagem: props.initial?.saude?.als_transtornos_aprendizagem ?? [],
        als_deficiencia_outro: props.initial?.saude?.als_deficiencia_outro ?? '',
        als_fl_altas_habilidades: props.initial?.saude?.als_fl_altas_habilidades ?? false,
        als_cid: props.initial?.saude?.als_cid ?? '',
        als_observacao: props.initial?.saude?.als_observacao ?? '',
        als_clinicas: props.initial?.saude?.als_clinicas ?? [],
        als_recursos_inep: props.initial?.saude?.als_recursos_inep ?? [],
    },
});

watch(dataBR, (v) => {
    form.aln_dt_nascimento = v && v.length === 10 ? parseDateBR(v) : '';
});

watch(
    () => form.saude.als_fl_pcd,
    (v) => {
        if (!v) {
            form.saude.als_deficiencias = [];
            form.saude.als_transtornos_globais = [];
            form.saude.als_transtornos_aprendizagem = [];
            form.saude.als_deficiencia_outro = '';
            form.saude.als_fl_altas_habilidades = false;
            form.saude.als_cid = '';
            form.saude.als_observacao = '';
        }
    },
);

watch(
    () => form.aln_nome,
    (v) => {
        if (typeof v !== 'string') return;
        let next = v;
        if (alertarAcentos.value) next = stripAccents(next);
        if (nomeUppercase.value) next = next.toLocaleUpperCase('pt-BR');
        if (next !== v) form.aln_nome = next;
    },
);

const ALUNO_TEXT_FIELDS_ACCENT = [
    'aln_nome_social', 'aln_filiacao_1', 'aln_filiacao_2',
    'aln_logradouro', 'aln_bairro', 'aln_cidade', 'aln_complemento',
] as const;
ALUNO_TEXT_FIELDS_ACCENT.forEach((field) => {
    watch(
        () => form[field] as string,
        (v) => {
            if (!alertarAcentos.value || typeof v !== 'string') return;
            const next = stripAccents(v);
            if (next !== v) (form as any)[field] = next;
        },
    );
});

const activeTab = ref<TabId>('dados-pessoais');

const tabHasError = (tab: TabId): boolean => {
    const fields = TAB_FIELDS[tab];
    return fields.some((f) => Boolean((form.errors as Record<string, string>)[f]));
};

const numeroInput = ref<HTMLInputElement | null>(null);
const { loading: cepLoading, error: cepError, lookup: cepLookup } = useViaCep();

watch(
    () => form.aln_cep,
    async (v) => {
        const digits = (v ?? '').replace(/\D/g, '');
        if (digits.length === 8) {
            const result = await cepLookup(digits);
            if (result) {
                form.aln_logradouro = result.logradouro || form.aln_logradouro;
                form.aln_bairro = result.bairro || form.aln_bairro;
                form.aln_cidade = result.cidade || form.aln_cidade;
                form.aln_uf = result.uf || form.aln_uf;
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

const submitLabel = computed(() => (props.mode === 'create' ? 'Cadastrar aluno' : 'Salvar alterações'));

const toggleArrayItem = (arr: string[], item: string) => {
    const idx = arr.indexOf(item);
    if (idx >= 0) arr.splice(idx, 1);
    else arr.push(item);
};

// Homônimo flow — detecta via ValidationException com chave 'homonimo' (JSON)
const homonimoOpen = ref(false);
const homonimoMatches = ref<HomonimoMatch[]>([]);

const handleErrors = (errors: Record<string, string>) => {
    const raw = errors.homonimo;
    if (raw) {
        try {
            const parsed = JSON.parse(raw) as HomonimoMatch[];
            homonimoMatches.value = parsed;
            homonimoOpen.value = true;
            delete form.errors.homonimo;
            return;
        } catch {
            // não era JSON — segue fluxo normal
        }
    }
    goToFirstErrorTab();
};

const submit = () => {
    const opts = {
        onError: (errors: Record<string, string>) => handleErrors(errors),
        preserveScroll: true,
        forceFormData: true,
    };

    if (props.mode === 'create') {
        form.post('/alunos', opts);
    } else if (props.initial) {
        form.post(`/alunos/${props.initial.aln_id}`, opts);
    }
};

const confirmHomonimo = () => {
    form.confirm_homonimo = true;
    homonimoOpen.value = false;
    submit();
};

const cancelHomonimo = () => {
    homonimoOpen.value = false;
    form.confirm_homonimo = false;
};

const next = () => {
    const idx = NAV_TABS.indexOf(activeTab.value as any);
    if (idx >= 0 && idx < NAV_TABS.length - 1) activeTab.value = NAV_TABS[idx + 1];
};
const prev = () => {
    const idx = NAV_TABS.indexOf(activeTab.value as any);
    if (idx > 0) activeTab.value = NAV_TABS[idx - 1];
};

const isFirst = computed(() => activeTab.value === NAV_TABS[0]);
const isLast  = computed(() => activeTab.value === NAV_TABS[NAV_TABS.length - 1] || activeTab.value === 'turmas');

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(props.initial?.aln_foto_url ?? null);
let objectUrl: string | null = null;

const onFotoChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.aln_foto = file;

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    if (file) {
        objectUrl = URL.createObjectURL(file);
        previewUrl.value = objectUrl;
    } else {
        previewUrl.value = props.initial?.aln_foto_url ?? null;
    }
};

const clearFoto = () => {
    form.aln_foto = null;
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
    previewUrl.value = props.initial?.aln_foto_url ?? null;
    if (fileInput.value) fileInput.value.value = '';
};

onBeforeUnmount(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
});

const initials = computed(() => {
    const parts = (form.aln_nome || '').trim().split(/\s+/);
    return parts
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase() || '?';
});
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alunos">
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
                <TabsTrigger value="dados-pessoais" :has-error="tabHasError('dados-pessoais')">
                    1. Dados Pessoais
                </TabsTrigger>
                <TabsTrigger value="documentacao" :has-error="tabHasError('documentacao')">
                    2. Documentação
                </TabsTrigger>
                <TabsTrigger value="filiacao-contato" :has-error="tabHasError('filiacao-contato')">
                    3. Filiação e Contato
                </TabsTrigger>
                <TabsTrigger value="complementares" :has-error="tabHasError('complementares')">
                    4. Saúde e Acessibilidade
                </TabsTrigger>
                <TabsTrigger v-if="mode === 'edit'" value="turmas">
                    5. Turmas
                </TabsTrigger>
            </TabsList>

            <!-- Aba 1 -->
            <TabsContent value="dados-pessoais" class="grid gap-6">
                <div class="flex flex-col items-center gap-4 rounded-xl border bg-card p-6 shadow-sm sm:flex-row sm:items-start sm:gap-6">
                    <div class="relative">
                        <div class="grid size-32 place-items-center overflow-hidden rounded-full border-4 border-sky-100 bg-sky-50 dark:border-sky-900/40 dark:bg-sky-900/20">
                            <img
                                v-if="previewUrl"
                                :src="previewUrl"
                                alt="Foto do aluno"
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
                        <Label for="aln_foto">Foto do aluno</Label>
                        <p class="text-xs text-muted-foreground">JPG, PNG ou WEBP — até 5 MB.</p>
                        <input
                            id="aln_foto"
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onFotoChange"
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                                <Upload class="mr-2 size-4" />
                                {{ form.aln_foto || initial?.aln_foto ? 'Trocar foto' : 'Selecionar foto' }}
                            </Button>
                            <Button
                                v-if="form.aln_foto || previewUrl"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                @click="clearFoto"
                            >
                                <Trash2 class="mr-2 size-4" /> Remover
                            </Button>
                        </div>
                        <InputError :message="(form.errors as Record<string, string>)['aln_foto']" />
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :for="'aln_nome'" :required="true">Nome completo</FormLabel>
                        <Input
                            id="aln_nome"
                            v-model="form.aln_nome"
                            maxlength="100"
                            :required="true"
                            autofocus
                            placeholder="Ex.: Maria da Silva Santos"
                        />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.aln_nome" />
                            <CharCounter :value="form.aln_nome" :max="100" />
                        </div>
                        <p v-if="alertarAcentos" class="flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-400">
                            <span class="font-semibold">Atenção:</span> sistema configurado para nomes sem acentuação — acentos são removidos automaticamente.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel for="aln_nome_social">Nome social</FormLabel>
                        <Input
                            id="aln_nome_social"
                            v-model="form.aln_nome_social"
                            maxlength="100"
                            placeholder="Preencha apenas se o aluno utiliza nome social"
                        />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.aln_nome_social" />
                            <CharCounter :value="form.aln_nome_social" :max="100" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'aln_dt_nascimento'" :required="true">Data de nascimento</FormLabel>
                        <Input
                            id="aln_dt_nascimento"
                            v-model="dataBR"
                            v-maska="'##/##/####'"
                            :required="true"
                            inputmode="numeric"
                            placeholder="DD/MM/AAAA"
                            maxlength="10"
                        />
                        <InputError :message="form.errors.aln_dt_nascimento" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'aln_sexo'" :required="true">Sexo</FormLabel>
                        <select
                            id="aln_sexo"
                            v-model="form.aln_sexo"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                        </select>
                        <InputError :message="form.errors.aln_sexo" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'aln_cor_raca'" :required="true">Cor / Raça (Censo)</FormLabel>
                        <select
                            id="aln_cor_raca"
                            :value="form.aln_cor_raca ?? ''"
                            @change="(e) => (form.aln_cor_raca = (e.target as HTMLSelectElement).value === '' ? null : Number((e.target as HTMLSelectElement).value) as any)"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="opt in COR_RACA" :key="opt.value" :value="opt.value">
                                {{ opt.value }} — {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.aln_cor_raca" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'aln_pais_origem'" :required="true">País de origem</FormLabel>
                        <select
                            id="aln_pais_origem"
                            v-model="form.aln_pais_origem"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option v-for="p in PAISES" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <InputError :message="form.errors.aln_pais_origem" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel :required="true">Município de nascimento</FormLabel>
                        <MunicipioCombobox
                            :model-value="form.aln_mun_id_nasc"
                            :initial="initialMunicipio"
                            :invalid="!!form.errors.aln_mun_id_nasc"
                            @update:model-value="(v) => (form.aln_mun_id_nasc = v)"
                        />
                        <InputError :message="form.errors.aln_mun_id_nasc" />
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 2 -->
            <TabsContent value="documentacao">
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="aln_nr_matricula">Matrícula</Label>
                        <Input
                            id="aln_nr_matricula"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            v-model.number="form.aln_nr_matricula"
                            placeholder="Deixe vazio para gerar automaticamente"
                            class="font-mono tracking-wider"
                        />
                        <p class="text-xs text-muted-foreground">
                            Em branco: gerada automaticamente. Editável, mas não pode ser duplicada.
                        </p>
                        <InputError :message="form.errors.aln_nr_matricula" />
                    </div>

                    <div class="grid gap-2">
                        <FormLabel :for="'aln_cpf'" :required="cpfRequired">CPF</FormLabel>
                        <Input
                            id="aln_cpf"
                            v-model="form.aln_cpf"
                            v-maska="'###.###.###-##'"
                            inputmode="numeric"
                            placeholder="000.000.000-00"
                            maxlength="14"
                            :required="cpfRequired"
                        />
                        <InputError :message="form.errors.aln_cpf" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="aln_cd_inep">Identificação INEP</Label>
                        <Input
                            id="aln_cd_inep"
                            v-model="form.aln_cd_inep"
                            v-maska="'############'"
                            inputmode="numeric"
                            placeholder="12 dígitos"
                            maxlength="12"
                        />
                        <InputError :message="form.errors.aln_cd_inep" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="aln_nr_certidao">Nº Matrícula da Certidão de Nascimento</Label>
                        <Input
                            id="aln_nr_certidao"
                            v-model="form.aln_nr_certidao"
                            maxlength="32"
                            placeholder="Até 32 caracteres"
                        />
                        <div class="flex justify-between gap-2">
                            <InputError :message="form.errors.aln_nr_certidao" />
                            <CharCounter :value="form.aln_nr_certidao" :max="32" />
                        </div>
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 3 -->
            <TabsContent value="filiacao-contato">
                <div class="grid gap-6">
                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <h3 class="text-sm font-semibold">Filiação</h3>
                        </div>
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between gap-4">
                                <FormLabel for="aln_filiacao_1" :required="true">Filiação 1</FormLabel>
                                <div class="flex items-center gap-4 text-sm">
                                    <label class="flex cursor-pointer items-center gap-1.5">
                                        <input type="radio" v-model="form.aln_filiacao_1_tipo" value="MAE" class="accent-indigo-600" />
                                        Mãe
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-1.5">
                                        <input type="radio" v-model="form.aln_filiacao_1_tipo" value="PAI" class="accent-indigo-600" />
                                        Pai
                                    </label>
                                </div>
                            </div>
                            <Input id="aln_filiacao_1" v-model="form.aln_filiacao_1" maxlength="100" />
                            <div class="flex justify-between gap-2">
                                <div>
                                    <InputError :message="(form.errors as any).aln_filiacao_1" />
                                    <InputError :message="(form.errors as any).aln_filiacao_1_tipo" />
                                </div>
                                <CharCounter :value="form.aln_filiacao_1" :max="100" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between gap-4">
                                <Label for="aln_filiacao_2">Filiação 2</Label>
                                <div class="flex items-center gap-4 text-sm">
                                    <label class="flex cursor-pointer items-center gap-1.5">
                                        <input type="radio" v-model="form.aln_filiacao_2_tipo" value="MAE" class="accent-indigo-600" />
                                        Mãe
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-1.5">
                                        <input type="radio" v-model="form.aln_filiacao_2_tipo" value="PAI" class="accent-indigo-600" />
                                        Pai
                                    </label>
                                </div>
                            </div>
                            <Input id="aln_filiacao_2" v-model="form.aln_filiacao_2" maxlength="100" />
                            <div class="flex justify-between gap-2">
                                <div>
                                    <InputError :message="(form.errors as any).aln_filiacao_2" />
                                    <InputError :message="(form.errors as any).aln_filiacao_2_tipo" />
                                </div>
                                <CharCounter :value="form.aln_filiacao_2" :max="100" />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6">
                        <div class="sm:col-span-6">
                            <h3 class="text-sm font-semibold">Endereço</h3>
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="aln_cep" :required="true">CEP</FormLabel>
                            <div class="relative">
                                <Input
                                    id="aln_cep"
                                    v-model="form.aln_cep"
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
                            <InputError :message="form.errors.aln_cep || cepError" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <Label for="aln_logradouro">Rua / Logradouro</Label>
                            <Input id="aln_logradouro" v-model="form.aln_logradouro" maxlength="150" />
                            <InputError :message="form.errors.aln_logradouro" />
                        </div>

                        <div class="grid gap-2 sm:col-span-1">
                            <Label for="aln_numero">Número</Label>
                            <Input id="aln_numero" ref="numeroInput" v-model="form.aln_numero" maxlength="10" />
                            <InputError :message="form.errors.aln_numero" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <Label for="aln_complemento">Complemento</Label>
                            <Input id="aln_complemento" v-model="form.aln_complemento" maxlength="100" />
                            <InputError :message="form.errors.aln_complemento" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <Label for="aln_bairro">Bairro</Label>
                            <Input id="aln_bairro" v-model="form.aln_bairro" maxlength="100" />
                            <InputError :message="form.errors.aln_bairro" />
                        </div>

                        <div class="grid gap-2 sm:col-span-4">
                            <Label for="aln_cidade">Cidade</Label>
                            <Input id="aln_cidade" v-model="form.aln_cidade" maxlength="100" />
                            <InputError :message="form.errors.aln_cidade" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="aln_uf">UF</Label>
                            <select
                                id="aln_uf"
                                v-model="form.aln_uf"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="">—</option>
                                <option v-for="uf in UFS" :key="uf" :value="uf">{{ uf }}</option>
                            </select>
                            <InputError :message="form.errors.aln_uf" />
                        </div>
                    </div>

                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <h3 class="text-sm font-semibold">Contato</h3>
                        </div>
                        <div class="grid gap-2">
                            <Label for="aln_telefone">Telefone celular</Label>
                            <Input
                                id="aln_telefone"
                                v-model="form.aln_telefone"
                                v-maska="'(##) #####-####'"
                                inputmode="numeric"
                                placeholder="(00) 00000-0000"
                                maxlength="15"
                            />
                            <InputError :message="form.errors.aln_telefone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="aln_email">E-mail</Label>
                            <Input
                                id="aln_email"
                                type="email"
                                v-model="form.aln_email"
                                maxlength="150"
                                placeholder="email@exemplo.com"
                            />
                            <InputError :message="form.errors.aln_email" />
                        </div>
                    </div>
                </div>
            </TabsContent>

            <!-- Aba 4: Saúde e Acessibilidade -->
            <TabsContent value="complementares">
                <div class="space-y-6">

                    <!-- Emergência / Contato -->
                    <div class="rounded-xl border bg-card p-6 shadow-sm">
                        <h3 class="mb-4 text-sm font-semibold">Contato de Emergência</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="als_contato_emergencia">Contato (Emergência)</Label>
                                <Input id="als_contato_emergencia" v-model="form.saude.als_contato_emergencia" maxlength="150" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_contato_emergencia']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="als_telefone_emergencia">Telefone (Emergência)</Label>
                                <Input id="als_telefone_emergencia" v-model="form.saude.als_telefone_emergencia" v-maska="'(##) #####-####'" inputmode="numeric" maxlength="20" placeholder="(00) 00000-0000" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_telefone_emergencia']" />
                            </div>
                        </div>
                    </div>

                    <!-- Saúde Básica -->
                    <div class="rounded-xl border bg-card p-6 shadow-sm">
                        <h3 class="mb-4 text-sm font-semibold">Saúde</h3>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div class="grid gap-2">
                                <Label for="als_tipo_sanguineo">Tipo de Sangue</Label>
                                <select
                                    id="als_tipo_sanguineo"
                                    v-model="form.saude.als_tipo_sanguineo"
                                    class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                                >
                                    <option value="">Selecione...</option>
                                    <option v-for="t in TIPOS_SANGUINEOS" :key="t" :value="t">{{ t }}</option>
                                </select>
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_tipo_sanguineo']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="als_plano_saude">Plano de Saúde</Label>
                                <Input id="als_plano_saude" v-model="form.saude.als_plano_saude" maxlength="100" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_plano_saude']" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="als_cartao_sus">Número do Cartão do SUS</Label>
                                <Input id="als_cartao_sus" v-model="form.saude.als_cartao_sus" maxlength="20" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_cartao_sus']" />
                            </div>

                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="als_alergia_a">Alergia a</Label>
                                <Input id="als_alergia_a" v-model="form.saude.als_alergia_a" maxlength="500" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_alergia_a']" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="als_ds_alergias">Alergias / Restrições Alimentares</Label>
                                <Input id="als_ds_alergias" v-model="form.saude.als_ds_alergias" maxlength="500" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_ds_alergias']" />
                            </div>
                        </div>

                        <h4 class="mb-2 mt-4 text-xs font-semibold text-muted-foreground">Remédios Indicados</h4>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="als_remedio_febre">Febre</Label>
                                <Input id="als_remedio_febre" v-model="form.saude.als_remedio_febre" maxlength="200" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_remedio_febre']" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="als_remedio_cefaleia">Cefaléia</Label>
                                <Input id="als_remedio_cefaleia" v-model="form.saude.als_remedio_cefaleia" maxlength="200" />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_remedio_cefaleia']" />
                            </div>
                        </div>
                    </div>

                    <!-- Patologias -->
                    <fieldset class="rounded-xl border bg-card p-6 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Patologia</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
                            <label
                                v-for="p in PATOLOGIAS"
                                :key="p"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.saude.als_patologias.includes(p)"
                                    @change="toggleArrayItem(form.saude.als_patologias, p)"
                                    class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                />
                                {{ p }}
                            </label>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <Label for="als_outra_doenca">Outra Doença</Label>
                            <Input id="als_outra_doenca" v-model="form.saude.als_outra_doenca" maxlength="500" />
                            <InputError :message="(form.errors as Record<string, string>)['saude.als_outra_doenca']" />
                        </div>
                    </fieldset>

                    <!-- Patologias Infância -->
                    <fieldset class="rounded-xl border bg-card p-6 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Patologias Contraídas na Infância</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
                            <label
                                v-for="p in PATOLOGIAS_INFANCIA"
                                :key="p"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.saude.als_patologias_infancia.includes(p)"
                                    @change="toggleArrayItem(form.saude.als_patologias_infancia, p)"
                                    class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                />
                                {{ p }}
                            </label>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <Label for="als_outra_doenca_infancia">Outra Doença</Label>
                            <Input id="als_outra_doenca_infancia" v-model="form.saude.als_outra_doenca_infancia" maxlength="500" />
                            <InputError :message="(form.errors as Record<string, string>)['saude.als_outra_doenca_infancia']" />
                        </div>
                    </fieldset>

                    <!-- Deficiência / Transtornos / Altas Habilidades -->
                    <fieldset class="rounded-xl border bg-card p-6 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Tipo de Deficiência, Transtorno Global do Desenvolvimento ou Altas Habilidades/Superdotação</legend>

                        <!-- Flag PCD — vem primeiro -->
                        <div class="mb-5 flex items-center gap-3">
                            <Switch
                                id="als_fl_pcd"
                                v-model="form.saude.als_fl_pcd"
                            />
                            <Label for="als_fl_pcd" class="text-sm font-medium">
                                Aluno com Deficiência (PCD), TGD ou Altas Habilidades
                            </Label>
                        </div>

                        <!-- Conteúdo desabilitado quando flag inativa -->
                        <div :class="{ 'pointer-events-none opacity-40': !form.saude.als_fl_pcd }">
                            <div class="grid gap-6 sm:grid-cols-3">
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold text-muted-foreground">Deficiência</h4>
                                    <div class="space-y-2">
                                        <label
                                            v-for="d in DEFICIENCIAS"
                                            :key="d"
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="form.saude.als_deficiencias.includes(d)"
                                                @change="toggleArrayItem(form.saude.als_deficiencias, d)"
                                                class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                            />
                                            {{ d }}
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-2 text-xs font-semibold text-muted-foreground">Transtorno Global de Desenvolvimento</h4>
                                    <div class="space-y-2">
                                        <label
                                            v-for="t in TRANSTORNOS_GLOBAIS"
                                            :key="t"
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="form.saude.als_transtornos_globais.includes(t)"
                                                @change="toggleArrayItem(form.saude.als_transtornos_globais, t)"
                                                class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                            />
                                            {{ t }}
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-2 text-xs font-semibold text-muted-foreground">Transtornos que Impactam a Aprendizagem</h4>
                                    <div class="space-y-2">
                                        <label
                                            v-for="t in TRANSTORNOS_APRENDIZAGEM"
                                            :key="t"
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="form.saude.als_transtornos_aprendizagem.includes(t)"
                                                @change="toggleArrayItem(form.saude.als_transtornos_aprendizagem, t)"
                                                class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                            />
                                            {{ t }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="als_deficiencia_outro">Outro(a)</Label>
                                    <Input id="als_deficiencia_outro" v-model="form.saude.als_deficiencia_outro" maxlength="500" />
                                    <InputError :message="(form.errors as Record<string, string>)['saude.als_deficiencia_outro']" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                <div class="flex items-center gap-3">
                                    <Switch
                                        id="als_fl_altas_habilidades"
                                        v-model="form.saude.als_fl_altas_habilidades"
                                    />
                                    <Label for="als_fl_altas_habilidades" class="text-sm font-normal">
                                        Altas Habilidades/Superdotação
                                    </Label>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="als_cid">CID</Label>
                                    <Input id="als_cid" v-model="form.saude.als_cid" maxlength="20" />
                                    <InputError :message="(form.errors as Record<string, string>)['saude.als_cid']" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2">
                                <Label for="als_observacao">Observação</Label>
                                <textarea
                                    id="als_observacao"
                                    v-model="form.saude.als_observacao"
                                    rows="3"
                                    maxlength="2000"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                                />
                                <InputError :message="(form.errors as Record<string, string>)['saude.als_observacao']" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Clínica -->
                    <fieldset class="rounded-xl border bg-card p-6 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Clínica</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                            <label
                                v-for="c in CLINICAS"
                                :key="c"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.saude.als_clinicas.includes(c)"
                                    @change="toggleArrayItem(form.saude.als_clinicas, c)"
                                    class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                />
                                {{ c }}
                            </label>
                        </div>
                    </fieldset>

                    <!-- Recursos INEP -->
                    <fieldset class="rounded-xl border bg-card p-6 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Recursos necessários para a participação do aluno em avaliações do INEP (Prova Brasil, SAEB, outros)</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                            <label
                                v-for="r in RECURSOS_INEP"
                                :key="r"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.saude.als_recursos_inep.includes(r)"
                                    @change="toggleArrayItem(form.saude.als_recursos_inep, r)"
                                    class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                />
                                {{ r }}
                            </label>
                        </div>
                    </fieldset>

                </div>
            </TabsContent>

            <!-- Aba 5 — Turmas -->
            <TabsContent v-if="mode === 'edit'" value="turmas">
                <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-2.5">
                        <span class="text-sm font-medium">Histórico de Matrículas</span>
                        <RefreshButton />
                    </div>
                    <div v-if="!matriculas?.length" class="py-10 text-center text-sm text-muted-foreground">
                        Nenhuma matrícula registrada.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-indigo-600 text-white">
                                <tr>
                                    <th class="px-3 py-2.5 text-left font-semibold">Ano</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">Dt. Matrícula</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">Escola</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">Série</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Turma</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">Turno</th>
                                    <th class="px-3 py-2.5 text-left font-semibold">Segmento</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Resultado Final</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Situação de Saída</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="(m, idx) in matriculas"
                                    :key="m.tma_id"
                                    :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                                >
                                    <td class="px-3 py-2.5 tabular-nums font-medium">{{ m.anl_ano ?? '—' }}</td>
                                    <td class="px-3 py-2.5 tabular-nums text-muted-foreground">
                                        {{ m.tma_dt_matricula ? new Date(m.tma_dt_matricula + 'T00:00:00').toLocaleDateString('pt-BR') : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 max-w-[200px] truncate">{{ m.esc_nome ?? '—' }}</td>
                                    <td class="px-3 py-2.5">{{ m.ser_nome ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-center font-semibold">{{ m.tur_nome ?? '—' }}</td>
                                    <td class="px-3 py-2.5 capitalize text-muted-foreground">{{ m.tur_turno?.toLowerCase() ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ m.seg_nome ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            m.tma_situacao === 'ATIVA'        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' :
                                            m.tma_situacao === 'CANCELADA'    ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' :
                                            m.tma_situacao === 'TRANSFERIDA'  ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' :
                                            m.tma_situacao === 'EVADIDO'      ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' :
                                            m.tma_situacao === 'FALECIDO'     ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' :
                                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                        ]">
                                            {{ m.tma_situacao }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span v-if="m.situacao_saida" class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ m.situacao_saida }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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

    <HomonimoDialog
        v-model:open="homonimoOpen"
        :matches="homonimoMatches"
        :processing="form.processing"
        @confirm="confirmHomonimo"
        @cancel="cancelHomonimo"
    />
</template>
