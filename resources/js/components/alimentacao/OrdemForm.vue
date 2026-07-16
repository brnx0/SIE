<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Contrato, Empenho, OrdemFornecimento } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save, Warehouse } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    contratos: Contrato[];
    empenhos: Pick<Empenho, 'emp_id' | 'emp_ctr_id' | 'emp_nr_empenho' | 'emp_vl_empenhado'>[];
    initial?: OrdemFornecimento;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

// ofn_situacao não entra no payload: é derivada do recebimento no backend.
const form = useForm<Record<string, any>>({
    ofn_ctr_id:              props.initial?.ofn_ctr_id ?? null,
    ofn_emp_id:              props.initial?.ofn_emp_id ?? null,
    ofn_nr_ordem:            props.initial?.ofn_nr_ordem ?? '',
    ofn_dt_emissao:          props.initial?.ofn_dt_emissao?.slice(0, 10) ?? '',
    ofn_dt_entrega_prevista: props.initial?.ofn_dt_entrega_prevista?.slice(0, 10) ?? '',
    ofn_obs:                 props.initial?.ofn_obs ?? '',
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const situacaoAtual = computed(() => props.initial?.ofn_situacao ?? 'EMITIDA');

const CORES_SITUACAO: Record<string, string> = {
    EMITIDA:   'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    PARCIAL:   'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    ENTREGUE:  'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    CANCELADA: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const ROTULOS_SITUACAO: Record<string, string> = {
    EMITIDA:   'Emitida',
    PARCIAL:   'Entrega parcial',
    ENTREGUE:  'Entregue',
    CANCELADA: 'Cancelada',
};

// Empenho pertence a um contrato — o backend rejeita empenho de outro contrato,
// então a lista só oferece os do contrato escolhido.
const empenhosDoContrato = computed(() =>
    props.empenhos.filter((e) => e.emp_ctr_id === form.ofn_ctr_id),
);

watch(() => form.ofn_ctr_id, (novo, antigo) => {
    if (antigo !== undefined && novo !== antigo) form.ofn_emp_id = null;
});

const contratoSelecionado = computed(() => props.contratos.find((c) => c.ctr_id === form.ofn_ctr_id));

// A lista já vem inteira do backend (só contratos VIGENTES), então o combobox
// é local: abre listando tudo, e o filtro por digitação é opcional.
const contratoItems = computed(() =>
    props.contratos.map((c) => ({
        id: c.ctr_id,
        label: `${c.ctr_nr_contrato}/${c.ctr_ano} — ${c.fornecedor?.frn_razao_social ?? 'sem fornecedor'}`,
    })),
);

const empenhoItems = computed(() =>
    empenhosDoContrato.value.map((e) => ({ id: e.emp_id, label: e.emp_nr_empenho })),
);

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const dataBr = (iso?: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '');

const submit = () => {
    if (isReadOnly.value) return;
    const opts = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/ordens-fornecimento', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/ordens-fornecimento/${props.initial.ofn_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar ordem' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/ordens-fornecimento">
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
                    <FormLabel for="ofn_ctr_id" :required="true">Contrato</FormLabel>
                    <LocalCombobox
                        id="ofn_ctr_id"
                        v-model="form.ofn_ctr_id"
                        :items="contratoItems"
                        :disabled="isReadOnly || mode === 'edit'"
                        :invalid="!!form.errors.ofn_ctr_id"
                        placeholder="Selecione o contrato..."
                    />
                    <p v-if="contratoSelecionado" class="text-xs text-muted-foreground">
                        Vigência {{ dataBr(contratoSelecionado.ctr_dt_inicio) }} a {{ dataBr(contratoSelecionado.ctr_dt_fim) }}
                        — a emissão precisa estar dentro dela.
                    </p>
                    <p v-else class="text-xs text-muted-foreground">Só contratos VIGENTES aceitam novas ordens.</p>
                    <InputError :message="form.errors.ofn_ctr_id" />
                </div>

                <!-- Situação não se escolhe: sai do recebimento (nada recebido =
                     EMITIDA, parte = PARCIAL, tudo = ENTREGUE). Só o cancelamento
                     é ato de gente, e tem botão próprio. -->
                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel>Situação</FormLabel>
                    <div class="flex h-9 items-center">
                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES_SITUACAO[situacaoAtual] ?? CORES_SITUACAO.EMITIDA]">
                            {{ ROTULOS_SITUACAO[situacaoAtual] ?? situacaoAtual }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ mode === 'create'
                            ? 'A ordem nasce emitida e avança conforme o recebimento.'
                            : 'Definida pelo Recebimento de Itens.' }}
                    </p>
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="ofn_emp_id">Empenho</FormLabel>
                    <LocalCombobox
                        id="ofn_emp_id"
                        v-model="form.ofn_emp_id"
                        :items="empenhoItems"
                        :disabled="isReadOnly || !form.ofn_ctr_id"
                        :invalid="!!form.errors.ofn_emp_id"
                        placeholder="Sem empenho vinculado"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ form.ofn_ctr_id
                            ? 'Com empenho vinculado, os itens da ordem ficam limitados ao saldo dele.'
                            : 'Escolha o contrato primeiro.' }}
                    </p>
                    <InputError :message="form.errors.ofn_emp_id" />
                </div>

                <!-- Entrega é sempre no estoque central: não há escolha, então
                     não é campo — só a informação de para onde vai. -->
                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                    <Warehouse class="size-4 shrink-0 text-muted-foreground" />
                    <div>
                        <p class="text-sm font-medium leading-none">Entrega no estoque central</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            As escolas recebem por transferência a partir do central.
                        </p>
                    </div>
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ofn_nr_ordem" :required="true">Número da ordem</FormLabel>
                    <Input id="ofn_nr_ordem" v-model="form.ofn_nr_ordem" maxlength="30" autofocus class="uppercase" placeholder="OF 2026/0001" />
                    <InputError :message="form.errors.ofn_nr_ordem" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ofn_dt_emissao" :required="true">Data de emissão</FormLabel>
                    <Input id="ofn_dt_emissao" type="date" v-model="form.ofn_dt_emissao" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.ofn_dt_emissao" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ofn_dt_entrega_prevista">Entrega prevista</FormLabel>
                    <Input id="ofn_dt_entrega_prevista" type="date" v-model="form.ofn_dt_entrega_prevista" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.ofn_dt_entrega_prevista" />
                </div>

                <div class="grid gap-2 sm:col-span-6">
                    <FormLabel for="ofn_obs">Observação</FormLabel>
                    <textarea
                        id="ofn_obs"
                        v-model="form.ofn_obs"
                        rows="2"
                        placeholder="Opcional"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.ofn_obs" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
