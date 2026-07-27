<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Contrato } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Lock, LoaderCircle, Save } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    situacoes: string[];
    initial?: Contrato;
    /** Contrato com aditivo/ordem: valor, prazo e objeto só mudam por aditivo. */
    temMovimento?: boolean;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<Record<string, any>>({
    ctr_frn_id:        props.initial?.ctr_frn_id ?? null,
    ctr_nr_contrato:   props.initial?.ctr_nr_contrato ?? '',
    ctr_ano:           props.initial?.ctr_ano ?? new Date().getFullYear(),
    ctr_objeto:        props.initial?.ctr_objeto ?? '',
    ctr_modalidade:    props.initial?.ctr_modalidade ?? '',
    ctr_dt_assinatura: props.initial?.ctr_dt_assinatura?.slice(0, 10) ?? '',
    ctr_dt_inicio:     props.initial?.ctr_dt_inicio?.slice(0, 10) ?? '',
    ctr_dt_fim:        props.initial?.ctr_dt_fim?.slice(0, 10) ?? '',
    ctr_vl_total:      props.initial?.ctr_vl_total ? Number(props.initial.ctr_vl_total) : '',
    ctr_situacao:      props.initial?.ctr_situacao ?? 'VIGENTE',
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const fornecedorLabel = ref<string | null>(props.initial?.fornecedor?.frn_razao_social ?? null);

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const submit = () => {
    if (isReadOnly.value) return;
    const opts = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/contratos', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/contratos/${props.initial.ctr_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar contrato' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/contratos">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <Button
                v-if="!isReadOnly"
                type="submit"
                :disabled="form.processing"
                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <div
            v-if="temMovimento"
            class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
        >
            <Lock class="mt-0.5 size-4 shrink-0" />
            <p>
                Este contrato já tem aditivos ou ordens de fornecimento. <strong>Valor, fim da vigência e objeto</strong>
                só mudam por aditivo — é o aditivo que registra o histórico da alteração.
            </p>
        </div>

        <fieldset :disabled="isReadOnly" class="contents">
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel :required="true">Fornecedor</FormLabel>
                    <RemoteCombobox
                        v-model="form.ctr_frn_id"
                        fetch-url="/api/alimentacao/fornecedores/search"
                        id-key="frn_id"
                        label-key="frn_razao_social"
                        placeholder="Buscar fornecedor..."
                        :invalid="!!form.errors.ctr_frn_id"
                        :selected-label="fornecedorLabel"
                    />
                    <InputError :message="form.errors.ctr_frn_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ctr_situacao" :required="true">Situação</FormLabel>
                    <select id="ctr_situacao" v-model="form.ctr_situacao" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">Só contrato VIGENTE aceita novas ordens.</p>
                    <InputError :message="form.errors.ctr_situacao" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ctr_nr_contrato" :required="true">Número do contrato</FormLabel>
                    <Input id="ctr_nr_contrato" v-model="form.ctr_nr_contrato" maxlength="30" autofocus class="uppercase" placeholder="001/2026" />
                    <InputError :message="form.errors.ctr_nr_contrato" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel for="ctr_ano" :required="true">Ano</FormLabel>
                    <Input id="ctr_ano" type="number" min="2000" max="2099" step="1" inputmode="numeric" v-model.number="form.ctr_ano" />
                    <InputError :message="form.errors.ctr_ano" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="ctr_modalidade">Modalidade</FormLabel>
                    <Input id="ctr_modalidade" v-model="form.ctr_modalidade" maxlength="40" class="uppercase" placeholder="PREGAO ELETRONICO" />
                    <InputError :message="form.errors.ctr_modalidade" />
                </div>

                <div class="grid gap-2 sm:col-span-6">
                    <FormLabel for="ctr_objeto" :required="true">Objeto</FormLabel>
                    <textarea
                        id="ctr_objeto"
                        v-model="form.ctr_objeto"
                        rows="3"
                        :disabled="isReadOnly || temMovimento"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.ctr_objeto" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ctr_dt_assinatura">Data de assinatura</FormLabel>
                    <Input id="ctr_dt_assinatura" type="date" v-model="form.ctr_dt_assinatura" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.ctr_dt_assinatura" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ctr_dt_inicio" :required="true">Início da vigência</FormLabel>
                    <Input id="ctr_dt_inicio" type="date" v-model="form.ctr_dt_inicio" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.ctr_dt_inicio" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ctr_dt_fim" :required="true">Fim da vigência</FormLabel>
                    <Input
                        id="ctr_dt_fim"
                        type="date"
                        v-model="form.ctr_dt_fim"
                        :disabled="isReadOnly || temMovimento"
                        min="2000-01-01"
                        max="2099-12-31"
                        @input="clampAno"
                    />
                    <InputError :message="form.errors.ctr_dt_fim" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ctr_vl_total" :required="true">Valor total (R$)</FormLabel>
                    <Input
                        id="ctr_vl_total"
                        type="number"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        v-model="form.ctr_vl_total"
                        :disabled="isReadOnly || temMovimento"
                    />
                    <InputError :message="form.errors.ctr_vl_total" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
