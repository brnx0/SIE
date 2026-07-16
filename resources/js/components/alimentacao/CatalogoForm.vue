<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { CatalogoConfig, CatalogoRegistro } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    config: CatalogoConfig;
    initial?: CatalogoRegistro;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const base = computed(() => '/' + props.config.rota.replace(/\./g, '/'));

// As colunas variam por catálogo (cat_nome, mrc_nome...), então o form é montado
// com as chaves que o backend informou — é o que permite uma tela servir os quatro.
const colNome  = props.config.col_nome;
const colAtivo = props.config.col_ativo;

const form = useForm<Record<string, any>>({
    [colNome]:  props.initial?.[colNome] ?? '',
    [colAtivo]: props.initial?.[colAtivo] ?? true,
    continue_new: false,
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
                form[colAtivo] = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post(base.value, opts);
    } else if (props.initial) {
        form.post(`${base.value}/${props.initial[props.config.col_id]}`, opts);
    }
};

const submitLabel = computed(() =>
    props.mode === 'create' ? `Cadastrar ${props.config.singular.toLowerCase()}` : 'Salvar alterações',
);
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link :href="base">
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
                    <Switch :id="colAtivo" v-model="form[colAtivo] as boolean" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form[colAtivo]
                                ? 'Ativo — aparece como opção nos cadastros'
                                : 'Inativo — não aparece como opção nos cadastros' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form[colAtivo]
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form[colAtivo] ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel :for="colNome" :required="true">Nome</FormLabel>
                    <Input
                        :id="colNome"
                        v-model="form[colNome] as string"
                        maxlength="80"
                        autofocus
                        class="uppercase"
                    />
                    <InputError :message="form.errors[colNome]" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
