<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Empenho } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    situacoes: string[];
    initial?: Empenho;
    /** Com ordens vinculadas, o contrato do empenho não pode ser trocado. */
    temOrdens?: boolean;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<Record<string, any>>({
    emp_ctr_id:       props.initial?.emp_ctr_id ?? null,
    emp_nr_empenho:   props.initial?.emp_nr_empenho ?? '',
    emp_dt:           props.initial?.emp_dt?.slice(0, 10) ?? '',
    emp_vl_empenhado: props.initial?.emp_vl_empenhado ? Number(props.initial.emp_vl_empenhado) : '',
    emp_situacao:     props.initial?.emp_situacao ?? 'ATIVO',
    emp_obs:          props.initial?.emp_obs ?? '',
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// Mesmo formato do `rotulo` da API de contratos (contrato + fornecedor). Sem o
// fornecedor aqui, ao voltar da edição (inclusive logo após cadastrar) o combobox
// mostraria só o número do contrato, perdendo o nome do fornecedor concatenado.
const contratoLabel = ref<string | null>(
    props.initial?.contrato
        ? `${props.initial.contrato.ctr_nr_contrato}/${props.initial.contrato.ctr_ano} — ${props.initial.contrato.fornecedor?.frn_razao_social ?? 'sem fornecedor'}`
        : null,
);

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
        form.post('/alimentacao/empenhos', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/empenhos/${props.initial.emp_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar empenho' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/empenhos">
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

        <fieldset :disabled="isReadOnly" class="contents">
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel :required="true">Contrato</FormLabel>
                    <RemoteCombobox
                        v-model="form.emp_ctr_id"
                        fetch-url="/api/alimentacao/contratos/search"
                        id-key="ctr_id"
                        label-key="rotulo"
                        placeholder="Buscar contrato..."
                        :invalid="!!form.errors.emp_ctr_id"
                        :disabled="isReadOnly || temOrdens"
                        :selected-label="contratoLabel"
                    />
                    <p v-if="temOrdens" class="text-xs text-amber-600 dark:text-amber-400">
                        Bloqueado: este empenho já custeia ordens de fornecimento.
                    </p>
                    <InputError :message="form.errors.emp_ctr_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="emp_situacao" :required="true">Situação</FormLabel>
                    <select id="emp_situacao" v-model="form.emp_situacao" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <InputError :message="form.errors.emp_situacao" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="emp_nr_empenho" :required="true">Número do empenho</FormLabel>
                    <Input id="emp_nr_empenho" v-model="form.emp_nr_empenho" maxlength="30" autofocus class="uppercase" placeholder="NE 2026/0001" />
                    <InputError :message="form.errors.emp_nr_empenho" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="emp_dt" :required="true">Data</FormLabel>
                    <Input id="emp_dt" type="date" v-model="form.emp_dt" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.emp_dt" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="emp_vl_empenhado" :required="true">Valor empenhado (R$)</FormLabel>
                    <Input id="emp_vl_empenhado" type="number" step="0.01" min="0" inputmode="decimal" v-model="form.emp_vl_empenhado" />
                    <InputError :message="form.errors.emp_vl_empenhado" />
                </div>

                <div class="grid gap-2 sm:col-span-6">
                    <FormLabel for="emp_obs">Observação</FormLabel>
                    <textarea
                        id="emp_obs"
                        v-model="form.emp_obs"
                        rows="2"
                        placeholder="Opcional — dotação orçamentária, elemento de despesa, etc."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.emp_obs" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
