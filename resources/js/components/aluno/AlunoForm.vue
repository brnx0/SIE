<script setup lang="ts">
import CharCounter from '@/components/common/CharCounter.vue';
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
import { TIPOS_SANGUINEOS } from '@/lib/tiposSanguineos';
import { UFS } from '@/lib/ufs';
import type { Aluno, AlunoFormData, Municipio } from '@/types/aluno';
import { Link, useForm } from '@inertiajs/vue3';
import { Camera, ChevronLeft, ChevronRight, Loader2, LoaderCircle, Save, Trash2, Upload } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Aluno;
}>();

const TABS = ['dados-pessoais', 'documentacao', 'filiacao-contato', 'complementares'] as const;
type TabId = (typeof TABS)[number];

const TAB_FIELDS: Record<TabId, string[]> = {
    'dados-pessoais': ['aln_nome', 'aln_dt_nascimento', 'aln_sexo', 'aln_cor_raca', 'aln_pais_origem', 'aln_mun_id_nasc'],
    documentacao: ['aln_cpf', 'aln_cd_inep', 'aln_nr_certidao'],
    'filiacao-contato': [
        'aln_filiacao_1',
        'aln_filiacao_2',
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
    complementares: ['saude.als_tipo_sanguineo', 'saude.als_ds_alergias', 'saude.als_fl_pcd'],
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
    aln_dt_nascimento: props.initial?.aln_dt_nascimento ?? '',
    aln_sexo: props.initial?.aln_sexo ?? '',
    aln_cor_raca: props.initial?.aln_cor_raca ?? null,
    aln_pais_origem: props.initial?.aln_pais_origem ?? 'Brasil',
    aln_mun_id_nasc: props.initial?.aln_mun_id_nasc ?? null,

    aln_cpf: props.initial?.aln_cpf ?? '',
    aln_cd_inep: props.initial?.aln_cd_inep ?? '',
    aln_nr_certidao: props.initial?.aln_nr_certidao ?? '',

    aln_filiacao_1: props.initial?.aln_filiacao_1 ?? '',
    aln_filiacao_2: props.initial?.aln_filiacao_2 ?? '',

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
    _method: props.mode === 'edit' ? 'put' : 'post',

    saude: {
        als_tipo_sanguineo: props.initial?.saude?.als_tipo_sanguineo ?? '',
        als_ds_alergias: props.initial?.saude?.als_ds_alergias ?? '',
        als_fl_pcd: props.initial?.saude?.als_fl_pcd ?? false,
    },
});

watch(dataBR, (v) => {
    form.aln_dt_nascimento = v && v.length === 10 ? parseDateBR(v) : '';
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

const submit = () => {
    const opts = {
        onError: () => goToFirstErrorTab(),
        preserveScroll: true,
        forceFormData: true,
    };

    if (props.mode === 'create') {
        form.post('/alunos', opts);
    } else if (props.initial) {
        form.post(`/alunos/${props.initial.aln_id}`, opts);
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
                    <div class="grid gap-2">
                        <FormLabel :for="'aln_cpf'" :required="true">CPF</FormLabel>
                        <Input
                            id="aln_cpf"
                            v-model="form.aln_cpf"
                            v-maska="'###.###.###-##'"
                            inputmode="numeric"
                            placeholder="000.000.000-00"
                            maxlength="14"
                            :required="true"
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
                            <Label for="aln_filiacao_1">Filiação 1</Label>
                            <Input id="aln_filiacao_1" v-model="form.aln_filiacao_1" maxlength="100" />
                            <div class="flex justify-between gap-2">
                                <InputError :message="form.errors.aln_filiacao_1" />
                                <CharCounter :value="form.aln_filiacao_1" :max="100" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="aln_filiacao_2">Filiação 2</Label>
                            <Input id="aln_filiacao_2" v-model="form.aln_filiacao_2" maxlength="100" />
                            <div class="flex justify-between gap-2">
                                <InputError :message="form.errors.aln_filiacao_2" />
                                <CharCounter :value="form.aln_filiacao_2" :max="100" />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6">
                        <div class="sm:col-span-6">
                            <h3 class="text-sm font-semibold">Endereço</h3>
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="aln_cep">CEP</Label>
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

            <!-- Aba 4 -->
            <TabsContent value="complementares">
                <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <h3 class="text-sm font-semibold">Saúde e Acessibilidade</h3>
                        <p class="text-xs text-muted-foreground">Informações opcionais — preencha se houver dados disponíveis.</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="als_tipo_sanguineo">Tipo Sanguíneo / Fator RH</Label>
                        <select
                            id="als_tipo_sanguineo"
                            v-model="form.saude.als_tipo_sanguineo"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                        >
                            <option value="">Não informado</option>
                            <option v-for="t in TIPOS_SANGUINEOS" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <InputError :message="(form.errors as Record<string, string>)['saude.als_tipo_sanguineo']" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="als_ds_alergias">Alergias / Restrições</Label>
                        <textarea
                            id="als_ds_alergias"
                            v-model="form.saude.als_ds_alergias"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                            placeholder="Descreva alergias alimentares, medicamentosas, restrições alimentares..."
                        />
                        <InputError :message="(form.errors as Record<string, string>)['saude.als_ds_alergias']" />
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <Switch
                            id="als_fl_pcd"
                            v-model="form.saude.als_fl_pcd"
                        />
                        <Label for="als_fl_pcd" class="text-sm font-normal">
                            Aluno com Deficiência (PCD), TGD ou Altas Habilidades
                        </Label>
                    </div>
                </div>
            </TabsContent>
        </Tabs>

        <!-- Rodapé -->
        <div class="flex flex-col-reverse items-stretch justify-between gap-2 border-t pt-4 sm:flex-row sm:items-center">
            <Link href="/alunos">
                <Button type="button" variant="outline" class="w-full sm:w-auto">Cancelar</Button>
            </Link>

            <div class="flex gap-2">
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
                    @click="next"
                    class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                >
                    Próximo <ChevronRight class="ml-1 size-4" />
                </Button>
                <Button
                    v-else
                    type="submit"
                    :disabled="form.processing"
                    class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ submitLabel }}
                </Button>
            </div>
        </div>
    </form>
</template>
