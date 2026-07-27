<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { CategoriaAlimento, Item, TipoItem, UnidadeMedida } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    tipos: Pick<TipoItem, 'tpi_id' | 'tpi_nome'>[];
    categorias: Pick<CategoriaAlimento, 'cat_id' | 'cat_nome'>[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
    initial?: Item;
    temConversoes?: boolean;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<{
    itm_tpi_id: number | null;
    itm_cat_id: number | null;
    itm_umd_id: number | null;
    itm_cd_item: string;
    itm_ncm: string;
    itm_nome: string;
    itm_especificacao: string;
    itm_fl_perecivel: boolean;
    itm_fl_agricultura_familiar: boolean;
    itm_validade_dias: number | string;
    itm_estoque_minimo: number | string;
    itm_fl_ativo: boolean;
    continue_new: boolean;
    _method?: string;
}>({
    itm_tpi_id:                  props.initial?.itm_tpi_id ?? null,
    itm_cat_id:                  props.initial?.itm_cat_id ?? null,
    itm_umd_id:                  props.initial?.itm_umd_id ?? null,
    itm_cd_item:                 props.initial?.itm_cd_item ?? '',
    itm_ncm:                     props.initial?.itm_ncm ?? '',
    itm_nome:                    props.initial?.itm_nome ?? '',
    itm_especificacao:           props.initial?.itm_especificacao ?? '',
    itm_fl_perecivel:            props.initial?.itm_fl_perecivel ?? false,
    itm_fl_agricultura_familiar: props.initial?.itm_fl_agricultura_familiar ?? false,
    itm_validade_dias:           props.initial?.itm_validade_dias ?? '',
    itm_estoque_minimo:          props.initial?.itm_estoque_minimo ?? '',
    itm_fl_ativo:                props.initial?.itm_fl_ativo ?? true,
    continue_new:                false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// Prazo de validade só existe para perecível — o backend rejeita a combinação,
// então limpa o campo aqui em vez de deixar o usuário levar erro na gravação.
watch(() => form.itm_fl_perecivel, (perecivel) => {
    if (!perecivel) form.itm_validade_dias = '';
});

const unidadeSelecionada = computed(() =>
    props.unidades.find((u) => u.umd_id === form.itm_umd_id),
);

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.itm_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/itens', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/itens/${props.initial.itm_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar item' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/itens">
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
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-6">
                    <Switch id="itm_fl_ativo" v-model="form.itm_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.itm_fl_ativo
                                ? 'Item ativo — aparece como opção nos cardápios, contratos e estoque'
                                : 'Item inativo — não aparece como opção nos cardápios, contratos e estoque' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.itm_fl_ativo
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.itm_fl_ativo ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel for="itm_cd_item">Código</FormLabel>
                    <Input id="itm_cd_item" v-model="form.itm_cd_item" maxlength="20" placeholder="Opcional" />
                    <InputError :message="form.errors.itm_cd_item" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel for="itm_ncm">Código NCM</FormLabel>
                    <Input
                        id="itm_ncm"
                        v-model="form.itm_ncm"
                        maxlength="8"
                        inputmode="numeric"
                        placeholder="Opcional"
                        @input="form.itm_ncm = form.itm_ncm.replace(/\D/g, '').slice(0, 8)"
                    />
                    <InputError :message="form.errors.itm_ncm" />
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="itm_nome" :required="true">Nome do item</FormLabel>
                    <Input id="itm_nome" v-model="form.itm_nome" maxlength="120" autofocus class="uppercase" placeholder="ARROZ BRANCO TIPO 1" />
                    <InputError :message="form.errors.itm_nome" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="itm_tpi_id" :required="true">Tipo de item</FormLabel>
                    <select id="itm_tpi_id" v-model="form.itm_tpi_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="t in tipos" :key="t.tpi_id" :value="t.tpi_id">{{ t.tpi_nome }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Que espécie de coisa é o item (ex.: Alimento, Material de limpeza, Descartável, Gás).
                    </p>
                    <InputError :message="form.errors.itm_tpi_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="itm_cat_id" :required="true">Categoria</FormLabel>
                    <select id="itm_cat_id" v-model="form.itm_cat_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="c in categorias" :key="c.cat_id" :value="c.cat_id">{{ c.cat_nome }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        Grupo do alimento para cardápio e PNAE (ex.: Cereais, Laticínios, Hortifruti, Carnes).
                    </p>
                    <InputError :message="form.errors.itm_cat_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="itm_umd_id" :required="true">Unidade de controle</FormLabel>
                    <select
                        id="itm_umd_id"
                        v-model="form.itm_umd_id"
                        :disabled="isReadOnly || temConversoes"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-background dark:text-foreground"
                    >
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="u in unidades" :key="u.umd_id" :value="u.umd_id">{{ u.umd_sigla }} — {{ u.umd_nome }}</option>
                    </select>
                    <p v-if="temConversoes" class="text-xs text-amber-600 dark:text-amber-400">
                        Bloqueado: há conversões cadastradas apontando para esta unidade. Remova-as antes de trocar.
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        Todo saldo e movimento do item são convertidos para esta unidade.
                    </p>
                    <InputError :message="form.errors.itm_umd_id" />
                </div>

                <div class="grid gap-2 sm:col-span-6">
                    <FormLabel for="itm_especificacao">Especificação técnica</FormLabel>
                    <textarea
                        id="itm_especificacao"
                        v-model="form.itm_especificacao"
                        rows="3"
                        placeholder="Descrição usada em contrato e conferência de recebimento (marca de referência, embalagem, teor, etc.)"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.itm_especificacao" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="itm_estoque_minimo">Estoque mínimo</FormLabel>
                    <div class="flex items-center gap-2">
                        <Input
                            id="itm_estoque_minimo"
                            type="number"
                            step="0.0001"
                            min="0"
                            inputmode="decimal"
                            v-model="form.itm_estoque_minimo"
                            placeholder="Opcional"
                        />
                        <span v-if="unidadeSelecionada" class="text-sm text-muted-foreground">{{ unidadeSelecionada.umd_sigla }}</span>
                    </div>
                    <InputError :message="form.errors.itm_estoque_minimo" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="itm_validade_dias">Prazo de validade (dias)</FormLabel>
                    <Input
                        id="itm_validade_dias"
                        type="number"
                        min="1"
                        max="3650"
                        step="1"
                        inputmode="numeric"
                        v-model="form.itm_validade_dias"
                        :disabled="isReadOnly || !form.itm_fl_perecivel"
                        :placeholder="form.itm_fl_perecivel ? 'Ex.: 30' : 'Só para item perecível'"
                    />
                    <InputError :message="form.errors.itm_validade_dias" />
                </div>

                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                    <Switch id="itm_fl_perecivel" v-model="form.itm_fl_perecivel" />
                    <div>
                        <p class="text-sm font-medium leading-none">Perecível</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Entra no relatório de itens próximos do vencimento.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                    <Switch id="itm_fl_agricultura_familiar" v-model="form.itm_fl_agricultura_familiar" />
                    <div>
                        <p class="text-sm font-medium leading-none">Agricultura familiar</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Item passível de aquisição via agricultura familiar (PNAE).
                        </p>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
</template>
