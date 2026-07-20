<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import InstituicaoCombobox from '@/components/transporte/InstituicaoCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { UsuarioTransporte } from '@/types/transporte';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: UsuarioTransporte;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const page = usePage();
const params = computed(() => (page.props as any).params ?? {});
const nomeCaixaAlta = computed(() => !!params.value.nome_pessoa_caixa_alta);

const form = useForm({
    tue_nome:          props.initial?.tue_nome ?? '',
    tue_cpf:           props.initial?.tue_cpf ?? '',
    tue_dt_nascimento: props.initial?.tue_dt_nascimento ?? '',
    tue_telefone:      props.initial?.tue_telefone ?? '',
    tue_tin_id:        props.initial?.tue_tin_id ?? (null as number | null),
    tue_justificativa: props.initial?.tue_justificativa ?? '',
    tue_fl_ativo:      props.initial?.tue_fl_ativo ?? true,
    continue_new:      false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const stripAccents = (str: string) => str.normalize('NFD').replace(/[̀-ͯ]/g, '');
watch(() => form.tue_nome, (v) => {
    const s = stripAccents(v ?? '');
    if (s !== v) form.tue_nome = s;
});

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.tue_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/transporte/usuarios', opts);
    } else if (props.initial) {
        form.post(`/transporte/usuarios/${props.initial.tue_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar usuário' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/transporte/usuarios">
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
                <Switch id="tue_fl_ativo" v-model="form.tue_fl_ativo" />
                <div>
                    <p class="text-sm font-medium leading-none">Situação</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ form.tue_fl_ativo ? 'Usuário ativo — pode ser vinculado como passageiro de rotas' : 'Usuário inativo — não pode ser vinculado como passageiro' }}
                    </p>
                </div>
                <span
                    :class="[
                        'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        form.tue_fl_ativo
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ form.tue_fl_ativo ? 'Ativo' : 'Inativo' }}
                </span>
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="tue_nome" :required="true">Nome completo</FormLabel>
                <Input id="tue_nome" v-model="form.tue_nome" maxlength="150" autofocus :class="{ uppercase: nomeCaixaAlta }" />
                <InputError :message="form.errors.tue_nome" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tue_cpf">CPF</FormLabel>
                <Input id="tue_cpf" v-model="form.tue_cpf" v-maska="'###.###.###-##'" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" />
                <InputError :message="form.errors.tue_cpf" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tue_dt_nascimento">Data de nascimento</FormLabel>
                <Input id="tue_dt_nascimento" type="date" v-model="form.tue_dt_nascimento" />
                <InputError :message="form.errors.tue_dt_nascimento" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tue_telefone">Telefone</FormLabel>
                <Input id="tue_telefone" v-model="form.tue_telefone" v-maska="'(##) #####-####'" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000" />
                <InputError :message="form.errors.tue_telefone" />
            </div>

            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="tue_tin_id">Instituição de ensino que frequenta</FormLabel>
                <InstituicaoCombobox
                    v-model="form.tue_tin_id"
                    :initial="initial?.instituicao ?? null"
                    :invalid="!!form.errors.tue_tin_id"
                />
                <InputError :message="form.errors.tue_tin_id" />
            </div>

            <div class="grid gap-2 sm:col-span-4">
                <FormLabel for="tue_justificativa" :required="true">Justificativa de utilização</FormLabel>
                <textarea
                    id="tue_justificativa"
                    v-model="form.tue_justificativa"
                    rows="4"
                    maxlength="2000"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    placeholder="Por que este usuário, não vinculado à matrícula regular, utiliza o transporte escolar?"
                ></textarea>
                <InputError :message="form.errors.tue_justificativa" />
            </div>
        </div>
        </fieldset>
    </form>
</template>
