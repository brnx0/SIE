<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import FuncionarioCombobox from '@/components/usuario/FuncionarioCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Motorista } from '@/types/transporte';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Motorista;
}>();

const CATEGORIAS = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'];

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const page = usePage();
const params = computed(() => (page.props as any).params ?? {});
const nomeCaixaAlta = computed(() => !!params.value.nome_pessoa_caixa_alta);

const form = useForm({
    mot_nome:            props.initial?.mot_nome ?? '',
    mot_cpf:             props.initial?.mot_cpf ?? '',
    mot_dt_nascimento:   props.initial?.mot_dt_nascimento ?? '',
    mot_telefone:        props.initial?.mot_telefone ?? '',
    mot_fun_id:          props.initial?.mot_fun_id ?? (null as number | null),
    mot_cnh_numero:      props.initial?.mot_cnh_numero ?? '',
    mot_cnh_categoria:   props.initial?.mot_cnh_categoria ?? (null as string | null),
    mot_cnh_dt_validade: props.initial?.mot_cnh_dt_validade ?? '',
    mot_fl_ativo:        props.initial?.mot_fl_ativo ?? true,
    continue_new:        false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// Strip de acentos sempre aplicado em nomes (padrão do sistema).
const stripAccents = (str: string) => str.normalize('NFD').replace(/[̀-ͯ]/g, '');
watch(() => form.mot_nome, (v) => {
    const s = stripAccents(v ?? '');
    if (s !== v) form.mot_nome = s;
});

// N3: CNH fora de D/E ou vencida → aviso (não bloqueia).
const avisoCategoria = computed(() => {
    const c = form.mot_cnh_categoria ?? '';
    return !!c && !c.includes('D') && !c.includes('E');
});
const avisoValidade = computed(() => {
    if (!form.mot_cnh_dt_validade) return false;
    return new Date(`${form.mot_cnh_dt_validade}T00:00:00`) < new Date(new Date().setHours(0, 0, 0, 0));
});

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.mot_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/transporte/motoristas', opts);
    } else if (props.initial) {
        form.post(`/transporte/motoristas/${props.initial.mot_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar motorista' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/transporte/motoristas">
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

        <div
            v-if="avisoCategoria || avisoValidade"
            class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <p v-if="avisoCategoria">O transporte escolar exige condutor com CNH categoria <strong>D</strong> ou <strong>E</strong> (CTB, art. 138). O cadastro será permitido, mas a pendência ficará destacada.</p>
                <p v-if="avisoValidade">A CNH informada está <strong>vencida</strong>. Regularize a habilitação do motorista.</p>
            </div>
        </div>

        <fieldset :disabled="isReadOnly" class="contents">
        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">
            <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-4">
                <Switch id="mot_fl_ativo" v-model="form.mot_fl_ativo" />
                <div>
                    <p class="text-sm font-medium leading-none">Situação</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ form.mot_fl_ativo ? 'Motorista ativo — aparece como opção nas rotas' : 'Motorista inativo — não aparece como opção nas rotas' }}
                    </p>
                </div>
                <span
                    :class="[
                        'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        form.mot_fl_ativo
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ form.mot_fl_ativo ? 'Ativo' : 'Inativo' }}
                </span>
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="mot_nome" :required="true">Nome completo</FormLabel>
                <Input id="mot_nome" v-model="form.mot_nome" maxlength="150" autofocus :class="{ uppercase: nomeCaixaAlta }" />
                <InputError :message="form.errors.mot_nome" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="mot_cpf">CPF</FormLabel>
                <Input id="mot_cpf" v-model="form.mot_cpf" v-maska="'###.###.###-##'" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" />
                <InputError :message="form.errors.mot_cpf" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="mot_dt_nascimento">Data de nascimento</FormLabel>
                <Input id="mot_dt_nascimento" type="date" v-model="form.mot_dt_nascimento" />
                <InputError :message="form.errors.mot_dt_nascimento" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="mot_telefone">Telefone</FormLabel>
                <Input id="mot_telefone" v-model="form.mot_telefone" v-maska="'(##) #####-####'" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000" />
                <InputError :message="form.errors.mot_telefone" />
            </div>

            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="mot_fun_id">Vínculo funcional (opcional)</FormLabel>
                <FuncionarioCombobox
                    v-model="form.mot_fun_id"
                    :initial="initial?.funcionario ?? null"
                    placeholder="Buscar servidor da rede..."
                    :invalid="!!form.errors.mot_fun_id"
                />
                <InputError :message="form.errors.mot_fun_id" />
            </div>
        </div>

        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3 sm:items-start">
            <div class="sm:col-span-3">
                <h3 class="text-sm font-semibold">Habilitação (CNH)</h3>
                <p class="text-xs text-muted-foreground">Número, categoria e validade da habilitação do condutor.</p>
            </div>

            <div class="grid gap-2">
                <FormLabel for="mot_cnh_numero" :required="true">Número da CNH</FormLabel>
                <Input id="mot_cnh_numero" v-model="form.mot_cnh_numero" v-maska="'###########'" inputmode="numeric" maxlength="11" />
                <InputError :message="form.errors.mot_cnh_numero" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="mot_cnh_categoria" :required="true">Categoria</FormLabel>
                <select
                    id="mot_cnh_categoria"
                    v-model="form.mot_cnh_categoria"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione...</option>
                    <option v-for="c in CATEGORIAS" :key="c" :value="c">{{ c }}</option>
                </select>
                <InputError :message="form.errors.mot_cnh_categoria" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="mot_cnh_dt_validade" :required="true">Validade da CNH</FormLabel>
                <Input id="mot_cnh_dt_validade" type="date" v-model="form.mot_cnh_dt_validade" />
                <InputError :message="form.errors.mot_cnh_dt_validade" />
            </div>
        </div>
        </fieldset>
    </form>
</template>
