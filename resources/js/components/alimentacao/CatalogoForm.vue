<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useViewMode } from '@/composables/useViewMode';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { CatalogoConfig, CatalogoRegistro } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    config: CatalogoConfig;
    initial?: CatalogoRegistro;
    /** Catálogo ordenável: posição sugerida (fim da lista) ao cadastrar. */
    proximaOrdem?: number | null;
}>();

const { isReadOnly } = usePermissao();
const { viewMode, readonly } = useViewMode();
const { focusFirstError } = useErrorFocus();

const base = computed(() => '/' + props.config.rota.replace(/\./g, '/'));

// As colunas variam por catálogo (cat_nome, mrc_nome...), então o form é montado
// com as chaves que o backend informou — é o que permite uma tela servir os quatro.
const colNome  = props.config.col_nome;
const colAtivo = props.config.col_ativo;
const colOrdem = props.config.col_ordem;

const form = useForm<Record<string, any>>({
    [colNome]:  props.initial?.[colNome] ?? '',
    [colAtivo]: props.initial?.[colAtivo] ?? true,
    // Ordenável: na criação já vem com a próxima posição livre; na edição, a atual.
    ...(colOrdem ? { [colOrdem]: props.initial?.[colOrdem] ?? props.proximaOrdem ?? 1 } : {}),
    continue_new: false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// Alguns catálogos (ex.: marcas) não aceitam acentos — removemos os diacríticos
// enquanto o usuário digita, para o valor nunca chegar acentuado ao backend.
if (props.config.sem_acento) {
    watch(
        () => form[colNome],
        (valor) => {
            const semAcento = String(valor ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '');
            if (semAcento !== valor) form[colNome] = semAcento;
        },
    );
}

const submit = (continueNew = false) => {
    if (readonly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form[colAtivo] = true;
                // A próxima posição livre já mudou após o cadastro — repõe a sugestão.
                if (colOrdem) form[colOrdem] = props.proximaOrdem ?? 1;
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
            <div class="flex items-center gap-2">
                <span v-if="viewMode && !isReadOnly" class="text-xs font-medium text-muted-foreground">
                    Modo consulta
                </span>
                <Link v-if="viewMode && !isReadOnly" :href="`${base}/${initial?.[config.col_id]}/edit`">
                    <Button type="button" class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        Editar
                    </Button>
                </Link>
                <template v-if="!readonly">
                    <Button
                        v-if="mode === 'create'"
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        class="border-brand-600 text-brand-700 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-900/30"
                        @click="submit(true)"
                    >
                        <LoaderCircle v-if="form.processing && form.continue_new" class="mr-2 size-4 animate-spin" />
                        <PlusCircle v-else class="mr-2 size-4" />
                        Salvar e novo
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-brand-600 text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-400"
                    >
                        <LoaderCircle v-if="form.processing && !form.continue_new" class="mr-2 size-4 animate-spin" />
                        <Save v-else class="mr-2 size-4" />
                        {{ submitLabel }}
                    </Button>
                </template>
            </div>
        </div>

        <fieldset :disabled="readonly" class="contents">
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

                <div class="grid gap-2" :class="colOrdem ? 'sm:col-span-3' : 'sm:col-span-4'">
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

                <div v-if="colOrdem" class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="colOrdem" :required="true">Ordem</FormLabel>
                    <Input
                        :id="colOrdem"
                        v-model.number="form[colOrdem] as number"
                        type="number"
                        min="1"
                        step="1"
                        inputmode="numeric"
                    />
                    <p class="text-xs text-muted-foreground">Posição de exibição. Repetir uma posição empurra as seguintes.</p>
                    <InputError :message="form.errors[colOrdem]" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
