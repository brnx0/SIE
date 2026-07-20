<script setup lang="ts">
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import BairroCombobox from '@/components/escola/BairroCombobox.vue';
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { InstituicaoTransporte } from '@/types/transporte';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    esferas: Record<string, string>;
    initial?: InstituicaoTransporte;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const page = usePage();
const params = computed(() => (page.props as any).params ?? {});
const nomeCaixaAlta = computed(() => !!params.value.nome_escola_caixa_alta);

const form = useForm({
    tin_nome:      props.initial?.tin_nome ?? '',
    tin_esfera:    props.initial?.tin_esfera ?? (null as string | null),
    tin_esc_id:    props.initial?.tin_esc_id ?? (null as number | null),
    tin_endereco:  props.initial?.tin_endereco ?? '',
    tin_mun_id:    props.initial?.tin_mun_id ?? (null as number | null),
    tin_bai_id:    props.initial?.tin_bai_id ?? (null as number | null),
    tin_telefone:  props.initial?.tin_telefone ?? '',
    tin_latitude:  props.initial?.tin_latitude ?? (null as number | null),
    tin_longitude: props.initial?.tin_longitude ?? (null as number | null),
    tin_fl_ativo:  props.initial?.tin_fl_ativo ?? true,
    continue_new:  false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const stripAccents = (str: string) => str.normalize('NFD').replace(/[̀-ͯ]/g, '');
watch(() => form.tin_nome, (v) => {
    const s = stripAccents(v ?? '');
    if (s !== v) form.tin_nome = s;
});

const escolaObrigatoria = computed(() => form.tin_esfera === 'municipal');

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.tin_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/transporte/instituicoes', opts);
    } else if (props.initial) {
        form.post(`/transporte/instituicoes/${props.initial.tin_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar instituição' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/transporte/instituicoes">
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
        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">
            <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-4">
                <Switch id="tin_fl_ativo" v-model="form.tin_fl_ativo" />
                <div>
                    <p class="text-sm font-medium leading-none">Situação</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ form.tin_fl_ativo ? 'Instituição ativa — aparece como opção nos cadastros do transporte' : 'Instituição inativa — não aparece como opção nos cadastros do transporte' }}
                    </p>
                </div>
                <span
                    :class="[
                        'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        form.tin_fl_ativo
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ form.tin_fl_ativo ? 'Ativa' : 'Inativa' }}
                </span>
            </div>

            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="tin_nome" :required="true">Nome da instituição</FormLabel>
                <Input id="tin_nome" v-model="form.tin_nome" maxlength="150" autofocus :class="{ uppercase: nomeCaixaAlta }" />
                <InputError :message="form.errors.tin_nome" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tin_esfera" :required="true">Esfera</FormLabel>
                <select
                    id="tin_esfera"
                    v-model="form.tin_esfera"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione...</option>
                    <option v-for="(label, key) in esferas" :key="key" :value="key">{{ label }}</option>
                </select>
                <InputError :message="form.errors.tin_esfera" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="tin_esc_id" :required="escolaObrigatoria">Escola da rede municipal</FormLabel>
                <EscolaCombobox
                    v-model="form.tin_esc_id"
                    :initial="initial?.escola ?? null"
                    placeholder="Vincular unidade da rede..."
                    :invalid="!!form.errors.tin_esc_id"
                />
                <p v-if="escolaObrigatoria" class="text-xs text-muted-foreground">Instituição municipal deve ser vinculada a uma escola da rede.</p>
                <InputError :message="form.errors.tin_esc_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="tin_telefone">Telefone</FormLabel>
                <Input id="tin_telefone" v-model="form.tin_telefone" v-maska="'(##) #####-####'" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000" />
                <InputError :message="form.errors.tin_telefone" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="tin_endereco">Endereço</FormLabel>
                <Input id="tin_endereco" v-model="form.tin_endereco" maxlength="200" placeholder="Rua, número, referência..." />
                <InputError :message="form.errors.tin_endereco" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tin_mun_id">Município</FormLabel>
                <MunicipioCombobox
                    v-model="form.tin_mun_id"
                    :initial="initial?.municipio ?? null"
                    :invalid="!!form.errors.tin_mun_id"
                />
                <InputError :message="form.errors.tin_mun_id" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tin_bai_id">Bairro</FormLabel>
                <BairroCombobox
                    v-model="form.tin_bai_id"
                    :mun-id="form.tin_mun_id"
                    :initial="initial?.bairro ?? null"
                    :invalid="!!form.errors.tin_bai_id"
                />
                <InputError :message="form.errors.tin_bai_id" />
            </div>
        </div>

        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 sm:items-start">
            <div class="sm:col-span-2">
                <h3 class="text-sm font-semibold">Georreferenciamento</h3>
                <p class="text-xs text-muted-foreground">Coordenadas usadas no mapa e no simulador de rotas. Preenchimento pelo mapa chega na fase do simulador.</p>
            </div>

            <div class="grid gap-2">
                <FormLabel for="tin_latitude">Latitude</FormLabel>
                <Input id="tin_latitude" type="number" step="any" min="-90" max="90" v-model.number="form.tin_latitude" placeholder="-3.7436121" />
                <InputError :message="form.errors.tin_latitude" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tin_longitude">Longitude</FormLabel>
                <Input id="tin_longitude" type="number" step="any" min="-180" max="180" v-model.number="form.tin_longitude" placeholder="-38.5266704" />
                <InputError :message="form.errors.tin_longitude" />
            </div>
        </div>
        </fieldset>
    </form>
</template>
