<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Prato, UnidadeMedida } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Prato;
    unidades?: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

// Vem como string do banco (decimal:2) e como '' quando não preenchido.
const num = (v: string | number | null | undefined): number | string =>
    v === null || v === undefined || v === '' ? '' : Number(v);

const form = useForm<Record<string, any>>({
    prt_nome:         props.initial?.prt_nome ?? '',
    prt_descricao:    props.initial?.prt_descricao ?? '',
    prt_modo_preparo: props.initial?.prt_modo_preparo ?? '',
    prt_fl_ativo:     props.initial?.prt_fl_ativo ?? true,

    prt_nut_porcao:             num(props.initial?.prt_nut_porcao),
    prt_nut_porcao_umd_id:      props.initial?.prt_nut_porcao_umd_id ?? null,
    prt_nut_energia_kcal:       num(props.initial?.prt_nut_energia_kcal),
    prt_nut_carboidrato_g:      num(props.initial?.prt_nut_carboidrato_g),
    prt_nut_acucares_g:         num(props.initial?.prt_nut_acucares_g),
    prt_nut_proteina_g:         num(props.initial?.prt_nut_proteina_g),
    prt_nut_gordura_total_g:    num(props.initial?.prt_nut_gordura_total_g),
    prt_nut_gordura_saturada_g: num(props.initial?.prt_nut_gordura_saturada_g),
    prt_nut_gordura_trans_g:    num(props.initial?.prt_nut_gordura_trans_g),
    prt_nut_fibra_g:            num(props.initial?.prt_nut_fibra_g),
    prt_nut_sodio_mg:           num(props.initial?.prt_nut_sodio_mg),
    prt_nut_observacao:         props.initial?.prt_nut_observacao ?? '',

    continue_new:     false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// A tabela é opcional: fica recolhida por padrão e só abre sozinha se já tem dado.
const nutricionalAberta = ref(!!props.initial?.tem_nutricional);

const NUTRIENTES: { campo: string; label: string; unidade: string; recuo?: boolean }[] = [
    { campo: 'prt_nut_energia_kcal',       label: 'Valor energético',      unidade: 'kcal' },
    { campo: 'prt_nut_carboidrato_g',      label: 'Carboidratos',          unidade: 'g' },
    { campo: 'prt_nut_acucares_g',         label: 'dos quais açúcares',    unidade: 'g', recuo: true },
    { campo: 'prt_nut_proteina_g',         label: 'Proteínas',             unidade: 'g' },
    { campo: 'prt_nut_gordura_total_g',    label: 'Gorduras totais',       unidade: 'g' },
    { campo: 'prt_nut_gordura_saturada_g', label: 'das quais saturadas',   unidade: 'g', recuo: true },
    { campo: 'prt_nut_gordura_trans_g',    label: 'das quais trans',       unidade: 'g', recuo: true },
    { campo: 'prt_nut_fibra_g',            label: 'Fibra alimentar',       unidade: 'g' },
    { campo: 'prt_nut_sodio_mg',           label: 'Sódio',                 unidade: 'mg' },
];

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.prt_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/pratos', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/pratos/${props.initial.prt_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar prato' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/pratos">
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
                    <Switch id="prt_fl_ativo" v-model="form.prt_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.prt_fl_ativo
                                ? 'Prato ativo — aparece como opção no cardápio'
                                : 'Prato inativo — não aparece como opção no cardápio' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.prt_fl_ativo
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.prt_fl_ativo ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="prt_nome" :required="true">Nome do prato</FormLabel>
                    <Input id="prt_nome" v-model="form.prt_nome" maxlength="120" autofocus class="uppercase" placeholder="ARROZ COM FRANGO E SALADA" />
                    <InputError :message="form.errors.prt_nome" />
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="prt_descricao">Descrição</FormLabel>
                    <textarea
                        id="prt_descricao"
                        v-model="form.prt_descricao"
                        rows="2"
                        placeholder="Opcional"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.prt_descricao" />
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="prt_modo_preparo">Modo de preparo</FormLabel>
                    <textarea
                        id="prt_modo_preparo"
                        v-model="form.prt_modo_preparo"
                        rows="4"
                        placeholder="Opcional — orientação para a cozinha da unidade"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.prt_modo_preparo" />
                </div>
            </div>

            <!-- Tabela nutricional — opcional -->
            <div class="rounded-xl border bg-card shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 p-6 text-left"
                    @click="nutricionalAberta = !nutricionalAberta"
                >
                    <div>
                        <h2 class="text-lg font-semibold">Tabela nutricional</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Opcional. Valores da porção servida, declarados pelo nutricionista — o prato funciona
                            normalmente sem eles.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            v-if="initial?.tem_nutricional"
                            class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                        >
                            Preenchida
                        </span>
                        <ChevronDown :class="['size-5 shrink-0 text-muted-foreground transition-transform', nutricionalAberta && 'rotate-180']" />
                    </div>
                </button>

                <div v-show="nutricionalAberta" class="grid gap-5 border-t p-6 sm:grid-cols-6 sm:items-start">
                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel for="prt_nut_porcao">Porção</FormLabel>
                        <Input
                            id="prt_nut_porcao"
                            type="number"
                            step="0.01"
                            min="0"
                            inputmode="decimal"
                            v-model="form.prt_nut_porcao"
                            placeholder="250"
                        />
                        <InputError :message="form.errors.prt_nut_porcao" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel for="prt_nut_porcao_umd_id">Unidade da porção</FormLabel>
                        <select
                            id="prt_nut_porcao_umd_id"
                            v-model="form.prt_nut_porcao_umd_id"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                        >
                            <option :value="null">—</option>
                            <option v-for="u in unidades ?? []" :key="u.umd_id" :value="u.umd_id">{{ u.umd_sigla }}</option>
                        </select>
                        <InputError :message="form.errors.prt_nut_porcao_umd_id" />
                    </div>

                    <div class="sm:col-span-6">
                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-2">Nutriente</th>
                                        <th class="px-4 py-2" style="width: 220px">Quantidade por porção</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="n in NUTRIENTES" :key="n.campo">
                                        <td :class="['px-4 py-2', n.recuo && 'pl-10 text-muted-foreground']">
                                            <label :for="n.campo">{{ n.label }}</label>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                <Input
                                                    :id="n.campo"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    inputmode="decimal"
                                                    v-model="form[n.campo]"
                                                    placeholder="—"
                                                />
                                                <span class="w-8 shrink-0 text-xs text-muted-foreground">{{ n.unidade }}</span>
                                            </div>
                                            <InputError :message="form.errors[n.campo]" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:col-span-6">
                        <FormLabel for="prt_nut_observacao">Observação</FormLabel>
                        <textarea
                            id="prt_nut_observacao"
                            v-model="form.prt_nut_observacao"
                            rows="2"
                            placeholder="Opcional — ex.: contém glúten; pode conter traços de leite"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                        ></textarea>
                        <InputError :message="form.errors.prt_nut_observacao" />
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
</template>
