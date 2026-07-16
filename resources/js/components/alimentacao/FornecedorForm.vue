<script setup lang="ts">
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import BairroCombobox from '@/components/escola/BairroCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import { useBairros } from '@/composables/useBairros';
import { useBrasilApiCnpj } from '@/composables/useBrasilApiCnpj';
import { useMunicipios } from '@/composables/useMunicipios';
import { useViaCep } from '@/composables/useViaCep';
import type { Municipio } from '@/types/aluno';
import type { Bairro } from '@/types/escola';
import type { Fornecedor } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Loader2, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Fornecedor;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

// Documentos e CEP são gravados sem máscara. Na edição chegam crus, então
// aplica a máscara na carga — senão o campo abre com "11222333000181".
//
// Tokens do padrão: '#' = dígito, '*' = [0-9A-Z]. O CNPJ alfanumérico
// (jul/2026) tem letra nas 12 primeiras posições, e só o DV é numérico.
const mascarar = (valor: string | null | undefined, padrao: string): string => {
    const limpo = (valor ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '');
    if (!limpo) return '';

    let i = 0;
    let out = '';
    for (const char of padrao) {
        if (i >= limpo.length) break;
        out += char === '#' || char === '*' ? limpo[i++] : char;
    }
    return out;
};

// Fixo tem 10 dígitos, celular 11 — a máscara certa depende do que veio gravado.
const mascararFone = (valor: string | null | undefined): string => {
    const digitos = (valor ?? '').replace(/\D/g, '');
    return mascarar(digitos, digitos.length > 10 ? '(##) #####-####' : '(##) ####-####');
};

const form = useForm<{
    frn_cnpj: string;
    frn_cpf: string;
    frn_razao_social: string;
    frn_nome_fantasia: string;
    frn_fl_agricultura_familiar: boolean;
    frn_email: string;
    frn_fone: string;
    frn_cep: string;
    frn_logradouro: string;
    frn_numero: string;
    frn_complemento: string;
    frn_mun_id: number | null;
    frn_bai_id: number | null;
    frn_fl_ativo: boolean;
    continue_new: boolean;
    _method?: string;
}>({
    frn_cnpj:                    mascarar(props.initial?.frn_cnpj, '**.***.***/****-##'),
    frn_cpf:                     mascarar(props.initial?.frn_cpf, '###.###.###-##'),
    frn_razao_social:            props.initial?.frn_razao_social ?? '',
    frn_nome_fantasia:           props.initial?.frn_nome_fantasia ?? '',
    frn_fl_agricultura_familiar: props.initial?.frn_fl_agricultura_familiar ?? false,
    frn_email:                   props.initial?.frn_email ?? '',
    frn_fone:                    mascararFone(props.initial?.frn_fone),
    frn_cep:                     mascarar(props.initial?.frn_cep, '#####-###'),
    frn_logradouro:              props.initial?.frn_logradouro ?? '',
    frn_numero:                  props.initial?.frn_numero ?? '',
    frn_complemento:             props.initial?.frn_complemento ?? '',
    frn_mun_id:                  props.initial?.frn_mun_id ?? null,
    frn_bai_id:                  props.initial?.frn_bai_id ?? null,
    frn_fl_ativo:                props.initial?.frn_fl_ativo ?? true,
    continue_new:                false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// CNPJ alfanumérico (novo formato jul/2026): letras sempre em maiúsculo.
watch(
    () => form.frn_cnpj,
    (v) => {
        if (typeof v !== 'string') return;
        const up = v.toUpperCase();
        if (up !== v) form.frn_cnpj = up;
    },
);

const currentMunicipio = ref<Municipio | null>((props.initial?.municipio as Municipio) ?? null);
const currentBairro    = ref<Bairro | null>((props.initial?.bairro as Bairro) ?? null);

const { loading: cepLoading, error: cepError, lookup: cepLookup } = useViaCep();
const { loading: cnpjLoading, error: cnpjError, lookup: cnpjLookup } = useBrasilApiCnpj();
const { byIbge: municipioByIbge } = useMunicipios();
const { create: createBairro } = useBairros();

/** Aviso da Receita quando a empresa não está ATIVA (baixada, suspensa, inapta). */
const situacaoCnpj = ref<string | null>(null);

// Preenche o cadastro a partir do CNPJ (Receita, via BrasilAPI). Só dispara com
// o CNPJ completo, e só na inclusão: em edição o cadastro já foi conferido por
// alguém, e sobrescrever o que está gravado seria surpresa.
watch(
    () => form.frn_cnpj,
    async (v) => {
        if (props.mode === 'edit') return;

        const limpo = (v ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '');
        if (limpo.length !== 14) {
            situacaoCnpj.value = null;
            return;
        }

        const d = await cnpjLookup(limpo);
        if (!d) return;

        situacaoCnpj.value = d.situacao && d.situacao !== 'ATIVA' ? d.situacao : null;

        if (d.razaoSocial) form.frn_razao_social = d.razaoSocial.toUpperCase();
        if (d.nomeFantasia) form.frn_nome_fantasia = d.nomeFantasia.toUpperCase();
        if (d.numero) form.frn_numero = d.numero;
        if (d.complemento) form.frn_complemento = d.complemento.toUpperCase();
        if (d.telefone) form.frn_fone = mascararFone(d.telefone);
        if (d.email) form.frn_email = d.email;

        // Por último: escrever o CEP dispara o watcher abaixo, que resolve
        // logradouro, município e bairro pelo mesmo caminho do preenchimento
        // manual — sem uma segunda cópia dessa lógica aqui.
        if (d.cep.length === 8) form.frn_cep = mascarar(d.cep, '#####-###');
    },
);

// Preenche endereço ao completar o CEP (mesmo fluxo do cadastro de escola):
// ViaCEP → município pelo código IBGE → bairro (firstOrCreate naquele município).
watch(
    () => form.frn_cep,
    async (v) => {
        const digits = (v ?? '').replace(/\D/g, '');
        if (digits.length !== 8) return;

        const result = await cepLookup(digits);
        if (!result) return;

        if (result.logradouro) form.frn_logradouro = result.logradouro.toUpperCase();

        if (result.ibge) {
            const mun = await municipioByIbge(result.ibge);
            if (mun) {
                form.frn_mun_id = mun.mun_id;
                currentMunicipio.value = mun;

                if (result.bairro) {
                    const bairro = await createBairro(mun.mun_id, result.bairro);
                    if (bairro) {
                        form.frn_bai_id = bairro.bai_id;
                        currentBairro.value = bairro;
                    }
                }
            }
        }
    },
);

// Trocar o município invalida o bairro — o backend rejeita bairro de outro município.
const onMunicipioChange = (v: number | null) => {
    form.frn_mun_id = v;
    if (v !== currentMunicipio.value?.mun_id) {
        currentMunicipio.value = null;
        form.frn_bai_id = null;
        currentBairro.value = null;
    }
};
const onBairroChange = (v: number | null) => {
    form.frn_bai_id = v;
    if (v !== currentBairro.value?.bai_id) currentBairro.value = null;
};

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.frn_fl_ativo = true;
                form.continue_new = false;
                currentMunicipio.value = null;
                currentBairro.value = null;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/fornecedores', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/fornecedores/${props.initial.frn_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar fornecedor' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/fornecedores">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <div v-if="!isReadOnly" class="flex items-center gap-2">
                <Button
                    v-if="mode === 'create'"
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    class="border-indigo-600 text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                    @click="submit(true)"
                >
                    <LoaderCircle v-if="form.processing && form.continue_new" class="mr-2 size-4 animate-spin" />
                    <PlusCircle v-else class="mr-2 size-4" />
                    Salvar e novo
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                >
                    <LoaderCircle v-if="form.processing && !form.continue_new" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ submitLabel }}
                </Button>
            </div>
        </div>

        <fieldset :disabled="isReadOnly" class="contents">
            <!-- Identificação -->
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-6">
                    <Switch id="frn_fl_ativo" v-model="form.frn_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.frn_fl_ativo
                                ? 'Fornecedor ativo — aparece como opção em contratos e entradas'
                                : 'Fornecedor inativo — não aparece como opção em contratos e entradas' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.frn_fl_ativo
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.frn_fl_ativo ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-6">
                    <Switch id="frn_fl_agricultura_familiar" v-model="form.frn_fl_agricultura_familiar" />
                    <div>
                        <p class="text-sm font-medium leading-none">Agricultura familiar</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Entra na apuração do percentual do PNAE aplicado em agricultura familiar.
                        </p>
                    </div>
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_cnpj">CNPJ</FormLabel>
                    <div class="relative">
                        <Input
                            id="frn_cnpj"
                            v-model="form.frn_cnpj"
                            v-maska="'**.***.***/****-##'"
                            maxlength="18"
                            placeholder="00.000.000/0000-00"
                        />
                        <Loader2 v-if="cnpjLoading" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                    </div>
                    <p v-if="mode === 'create'" class="text-xs text-muted-foreground">
                        Preenche razão social, endereço e telefone pela Receita Federal.
                    </p>
                    <p v-if="situacaoCnpj" class="text-xs font-medium text-amber-600 dark:text-amber-400">
                        Situação cadastral na Receita: {{ situacaoCnpj }}. Confira antes de contratar.
                    </p>
                    <InputError :message="form.errors.frn_cnpj || cnpjError" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_cpf">CPF</FormLabel>
                    <Input
                        id="frn_cpf"
                        v-model="form.frn_cpf"
                        v-maska="'###.###.###-##'"
                        maxlength="14"
                        inputmode="numeric"
                        placeholder="000.000.000-00"
                    />
                    <InputError :message="form.errors.frn_cpf" />
                    <p class="text-xs text-muted-foreground">
                        Informe CNPJ ou CPF. Fornecedor da agricultura familiar costuma ser pessoa física.
                    </p>
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_razao_social" :required="true">Razão social / Nome</FormLabel>
                    <Input id="frn_razao_social" v-model="form.frn_razao_social" maxlength="150" autofocus class="uppercase" />
                    <InputError :message="form.errors.frn_razao_social" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_nome_fantasia">Nome fantasia</FormLabel>
                    <Input id="frn_nome_fantasia" v-model="form.frn_nome_fantasia" maxlength="150" class="uppercase" placeholder="Opcional" />
                    <InputError :message="form.errors.frn_nome_fantasia" />
                </div>
            </div>

            <!-- Contato e endereço -->
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <h2 class="text-lg font-semibold sm:col-span-6">Contato e endereço</h2>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_email">E-mail</FormLabel>
                    <Input id="frn_email" type="email" v-model="form.frn_email" maxlength="120" placeholder="Opcional" />
                    <InputError :message="form.errors.frn_email" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_fone">Telefone</FormLabel>
                    <Input
                        id="frn_fone"
                        v-model="form.frn_fone"
                        v-maska="{ mask: ['(##) ####-####', '(##) #####-####'] }"
                        maxlength="15"
                        inputmode="numeric"
                        placeholder="(00) 00000-0000"
                    />
                    <InputError :message="form.errors.frn_fone" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="frn_cep">CEP</FormLabel>
                    <div class="relative">
                        <Input
                            id="frn_cep"
                            v-model="form.frn_cep"
                            v-maska="'#####-###'"
                            maxlength="9"
                            inputmode="numeric"
                            placeholder="00000-000"
                        />
                        <Loader2 v-if="cepLoading" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                    </div>
                    <p class="text-xs text-muted-foreground">Preenche endereço, município e bairro automaticamente.</p>
                    <InputError :message="form.errors.frn_cep || cepError" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel>Município</FormLabel>
                    <MunicipioCombobox
                        :model-value="form.frn_mun_id"
                        :initial="currentMunicipio"
                        :invalid="!!form.errors.frn_mun_id"
                        @update:model-value="onMunicipioChange"
                    />
                    <InputError :message="form.errors.frn_mun_id" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel>Bairro</FormLabel>
                    <BairroCombobox
                        :model-value="form.frn_bai_id"
                        :mun-id="form.frn_mun_id"
                        :initial="currentBairro"
                        :invalid="!!form.errors.frn_bai_id"
                        @update:model-value="onBairroChange"
                    />
                    <InputError :message="form.errors.frn_bai_id" />
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="frn_logradouro">Logradouro</FormLabel>
                    <Input id="frn_logradouro" v-model="form.frn_logradouro" maxlength="150" class="uppercase" placeholder="Opcional" />
                    <InputError :message="form.errors.frn_logradouro" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel for="frn_numero">Número</FormLabel>
                    <Input id="frn_numero" v-model="form.frn_numero" maxlength="10" placeholder="Opcional" />
                    <InputError :message="form.errors.frn_numero" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="frn_complemento">Complemento</FormLabel>
                    <Input id="frn_complemento" v-model="form.frn_complemento" maxlength="60" class="uppercase" placeholder="Opcional" />
                    <InputError :message="form.errors.frn_complemento" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
