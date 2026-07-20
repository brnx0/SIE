<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import FuncionarioCombobox from '@/components/usuario/FuncionarioCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { MonitorTransporte } from '@/types/transporte';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: MonitorTransporte;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const page = usePage();
const params = computed(() => (page.props as any).params ?? {});
const nomeCaixaAlta = computed(() => !!params.value.nome_pessoa_caixa_alta);

const form = useForm({
    tmo_nome:          props.initial?.tmo_nome ?? '',
    tmo_cpf:           props.initial?.tmo_cpf ?? '',
    tmo_dt_nascimento: props.initial?.tmo_dt_nascimento ?? '',
    tmo_telefone:      props.initial?.tmo_telefone ?? '',
    tmo_fun_id:        props.initial?.tmo_fun_id ?? (null as number | null),
    tmo_fl_ativo:      props.initial?.tmo_fl_ativo ?? true,
    continue_new:      false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const stripAccents = (str: string) => str.normalize('NFD').replace(/[̀-ͯ]/g, '');
watch(() => form.tmo_nome, (v) => {
    const s = stripAccents(v ?? '');
    if (s !== v) form.tmo_nome = s;
});

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.tmo_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/transporte/monitores', opts);
    } else if (props.initial) {
        form.post(`/transporte/monitores/${props.initial.tmo_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar monitor' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/transporte/monitores">
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
                <Switch id="tmo_fl_ativo" v-model="form.tmo_fl_ativo" />
                <div>
                    <p class="text-sm font-medium leading-none">Situação</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ form.tmo_fl_ativo ? 'Monitor ativo — aparece como opção nas rotas' : 'Monitor inativo — não aparece como opção nas rotas' }}
                    </p>
                </div>
                <span
                    :class="[
                        'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        form.tmo_fl_ativo
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ form.tmo_fl_ativo ? 'Ativo' : 'Inativo' }}
                </span>
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="tmo_nome" :required="true">Nome completo</FormLabel>
                <Input id="tmo_nome" v-model="form.tmo_nome" maxlength="150" autofocus :class="{ uppercase: nomeCaixaAlta }" />
                <InputError :message="form.errors.tmo_nome" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tmo_cpf">CPF</FormLabel>
                <Input id="tmo_cpf" v-model="form.tmo_cpf" v-maska="'###.###.###-##'" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" />
                <InputError :message="form.errors.tmo_cpf" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tmo_dt_nascimento">Data de nascimento</FormLabel>
                <Input id="tmo_dt_nascimento" type="date" v-model="form.tmo_dt_nascimento" />
                <InputError :message="form.errors.tmo_dt_nascimento" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="tmo_telefone">Telefone</FormLabel>
                <Input id="tmo_telefone" v-model="form.tmo_telefone" v-maska="'(##) #####-####'" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000" />
                <InputError :message="form.errors.tmo_telefone" />
            </div>

            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="tmo_fun_id">Vínculo funcional (opcional)</FormLabel>
                <FuncionarioCombobox
                    v-model="form.tmo_fun_id"
                    :initial="initial?.funcionario ?? null"
                    placeholder="Buscar servidor da rede..."
                    :invalid="!!form.errors.tmo_fun_id"
                />
                <InputError :message="form.errors.tmo_fun_id" />
            </div>
        </div>
        </fieldset>
    </form>
</template>
