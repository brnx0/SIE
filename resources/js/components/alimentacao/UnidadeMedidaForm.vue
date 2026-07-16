<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { UnidadeMedida } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: UnidadeMedida;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<{
    umd_sigla: string;
    umd_nome: string;
    umd_fl_fracionavel: boolean;
    umd_fl_ativo: boolean;
    continue_new: boolean;
    _method?: string;
}>({
    umd_sigla:          props.initial?.umd_sigla ?? '',
    umd_nome:           props.initial?.umd_nome ?? '',
    umd_fl_fracionavel: props.initial?.umd_fl_fracionavel ?? true,
    umd_fl_ativo:       props.initial?.umd_fl_ativo ?? true,
    continue_new:       false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.umd_fl_ativo = true;
                form.umd_fl_fracionavel = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/unidades-medida', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/unidades-medida/${props.initial.umd_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar unidade' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/unidades-medida">
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
                    <Switch id="umd_fl_ativo" v-model="form.umd_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.umd_fl_ativo
                                ? 'Unidade ativa — aparece como opção nos cadastros'
                                : 'Unidade inativa — não aparece como opção nos cadastros' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.umd_fl_ativo
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.umd_fl_ativo ? 'Ativa' : 'Inativa' }}
                    </span>
                </div>

                <div class="grid gap-2">
                    <FormLabel for="umd_sigla" :required="true">Sigla</FormLabel>
                    <Input id="umd_sigla" v-model="form.umd_sigla" maxlength="10" autofocus class="uppercase" placeholder="KG" />
                    <InputError :message="form.errors.umd_sigla" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="umd_nome" :required="true">Nome</FormLabel>
                    <Input id="umd_nome" v-model="form.umd_nome" maxlength="60" class="uppercase" placeholder="QUILOGRAMA" />
                    <InputError :message="form.errors.umd_nome" />
                </div>

                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-4">
                    <Switch id="umd_fl_fracionavel" v-model="form.umd_fl_fracionavel" />
                    <div>
                        <p class="text-sm font-medium leading-none">Aceita fração</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.umd_fl_fracionavel
                                ? 'Permite quantidade quebrada (ex.: 2,5 KG)'
                                : 'Só permite quantidade inteira (ex.: 3 caixas — meia caixa não existe)' }}
                        </p>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
</template>
