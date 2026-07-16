<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import type { AditivoLinha, Contrato, ContratoItemLinha } from '@/types/alimentacao';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Ban, LoaderCircle, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    contrato: Contrato;
    aditivos: AditivoLinha[];
    itens: ContratoItemLinha[];
    tipos: string[];
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.aditivos ?? []);

const form = useForm<Record<string, any>>({
    adt_nr_aditivo: '',
    adt_tipo: 'VALOR',
    adt_dt_assinatura: '',
    adt_justificativa: '',
    adt_vl_novo: '',
    adt_dt_fim_nova: '',
    adt_objeto_novo: '',
    adt_cti_id: null,
    adt_qtd_nova: '',
});

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

// Cancelamento: exige justificativa, então abre um painel em vez de um confirm().
const cancelandoId = ref<number | null>(null);
const formCancelamento = useForm<{ adt_justificativa_cancelamento: string }>({
    adt_justificativa_cancelamento: '',
});

const abrirCancelamento = (a: AditivoLinha) => {
    cancelandoId.value = a.adt_id;
    formCancelamento.reset();
    formCancelamento.clearErrors();
};

const confirmarCancelamento = (a: AditivoLinha) => {
    formCancelamento.post(`/alimentacao/contratos/${props.contrato.ctr_id}/aditivos/${a.adt_id}/cancelar`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            cancelandoId.value = null;
            formCancelamento.reset();
        },
    });
};

const lancar = () => {
    if (isReadOnly.value) return;
    form.post(`/alimentacao/contratos/${props.contrato.ctr_id}/aditivos`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset(),
    });
};

const brl = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
const qtd = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR', { maximumFractionDigits: 4 }));
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const CORES: Record<string, string> = {
    VALOR:  'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    PRAZO:  'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    OBJETO: 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    QTD:    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
};

/** O de/para de cada aditivo, na linguagem do tipo. */
const dePara = (a: AditivoLinha): { de: string; para: string } => {
    switch (a.adt_tipo) {
        case 'VALOR':  return { de: brl(a.adt_vl_anterior), para: brl(a.adt_vl_novo) };
        case 'PRAZO':  return { de: dataBr(a.adt_dt_fim_anterior), para: dataBr(a.adt_dt_fim_nova) };
        case 'QTD':    return { de: qtd(a.adt_qtd_anterior), para: qtd(a.adt_qtd_nova) };
        case 'OBJETO': return { de: a.adt_objeto_anterior ?? '—', para: a.adt_objeto_novo ?? '—' };
    }
};
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Aditivos</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Histórico das alterações do contrato. Lançar um aditivo <strong>atualiza o contrato</strong> e
                    registra o de/para — é assim que valor, prazo, objeto e quantidade mudam depois de assinado.
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Aditivo não é editável nem removível. Para desfazer, cancele: o contrato volta às condições
                    anteriores a ele, e o cancelamento fica registrado com autor e justificativa.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Aditivo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Assinatura</th>
                        <th class="px-4 py-3">Alteração</th>
                        <th class="px-4 py-3">Lançado por</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="linhas.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                            Nenhum aditivo lançado — o contrato está nas condições originais.
                        </td>
                    </tr>
                    <template v-for="a in linhas" :key="a.adt_id">
                        <tr :class="['transition-colors hover:bg-muted/30', a.adt_fl_cancelado && 'bg-muted/20 opacity-60']">
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span :class="['font-medium', a.adt_fl_cancelado && 'line-through']">{{ a.adt_nr_aditivo }}</span>
                                    <span
                                        v-if="a.adt_fl_cancelado"
                                        class="mt-0.5 inline-flex w-fit rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                                    >
                                        Cancelado
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[a.adt_tipo]]">
                                    {{ a.adt_tipo }}
                                </span>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(a.adt_dt_assinatura) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-muted-foreground line-through">{{ dePara(a).de }}</span>
                                        <ArrowRight class="size-3.5 shrink-0 text-muted-foreground" />
                                        <span :class="['font-medium', a.adt_fl_cancelado && 'line-through']">{{ dePara(a).para }}</span>
                                    </div>
                                    <span v-if="a.item_nome" class="text-xs text-muted-foreground">Item: {{ a.item_nome }}</span>
                                    <span v-if="a.adt_justificativa" class="text-xs text-muted-foreground">{{ a.adt_justificativa }}</span>
                                    <span v-if="a.adt_fl_cancelado" class="text-xs text-rose-600 dark:text-rose-400">
                                        Cancelado em {{ dataBr(a.adt_dt_cancelamento) }}
                                        <template v-if="a.usuario_cancelamento_nome"> por {{ a.usuario_cancelamento_nome }}</template>
                                        — {{ a.adt_justificativa_cancelamento }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ a.usuario_nome ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    <Button
                                        v-if="!isReadOnly && !a.adt_fl_cancelado"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="!a.pode_cancelar"
                                        :title="a.pode_cancelar
                                            ? 'Cancelar aditivo (reverte o contrato)'
                                            : `Existe um aditivo mais recente do tipo ${a.adt_tipo} — cancele-o primeiro`"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                        @click="abrirCancelamento(a)"
                                    >
                                        <Ban class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Cancelamento exige justificativa: painel inline em vez de confirm() -->
                        <tr v-if="cancelandoId === a.adt_id">
                            <td colspan="6" class="bg-rose-50/50 px-4 py-4 dark:bg-rose-900/10">
                                <div class="grid gap-3">
                                    <p class="text-sm text-rose-800 dark:text-rose-200">
                                        Cancelar <strong>{{ a.adt_nr_aditivo }}</strong> devolve o contrato para
                                        <strong>{{ dePara(a).de }}</strong>. O aditivo continua no histórico, marcado como cancelado.
                                    </p>
                                    <div class="grid gap-2">
                                        <FormLabel for="adt_justificativa_cancelamento" :required="true">
                                            Motivo do cancelamento
                                        </FormLabel>
                                        <Input
                                            id="adt_justificativa_cancelamento"
                                            v-model="formCancelamento.adt_justificativa_cancelamento"
                                            placeholder="Ex.: aditivo lançado em duplicidade"
                                        />
                                        <InputError :message="formCancelamento.errors.adt_justificativa_cancelamento" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            :disabled="formCancelamento.processing"
                                            class="bg-rose-600 text-white hover:bg-rose-700"
                                            @click="confirmarCancelamento(a)"
                                        >
                                            <LoaderCircle v-if="formCancelamento.processing" class="mr-2 size-4 animate-spin" />
                                            <Ban v-else class="mr-2 size-4" />
                                            Confirmar cancelamento
                                        </Button>
                                        <Button type="button" variant="outline" @click="cancelandoId = null">Voltar</Button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <fieldset v-if="!isReadOnly" :disabled="form.processing" class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-6 sm:items-start">
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="adt_nr_aditivo" :required="true">Número do aditivo</FormLabel>
                <Input id="adt_nr_aditivo" v-model="form.adt_nr_aditivo" maxlength="30" class="uppercase" placeholder="1º TERMO ADITIVO" />
                <InputError :message="form.errors.adt_nr_aditivo" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="adt_tipo" :required="true">Tipo</FormLabel>
                <select id="adt_tipo" v-model="form.adt_tipo" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option v-for="t in tipos" :key="t" :value="t">{{ t }}</option>
                </select>
                <InputError :message="form.errors.adt_tipo" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="adt_dt_assinatura" :required="true">Data de assinatura</FormLabel>
                <Input id="adt_dt_assinatura" type="date" v-model="form.adt_dt_assinatura" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <InputError :message="form.errors.adt_dt_assinatura" />
            </div>

            <!-- Cada tipo pede só o seu campo — o resto não faz sentido e o backend rejeita. -->
            <div v-if="form.adt_tipo === 'VALOR'" class="grid gap-2 sm:col-span-3">
                <FormLabel for="adt_vl_novo" :required="true">Novo valor do contrato (R$)</FormLabel>
                <Input id="adt_vl_novo" type="number" step="0.01" min="0" inputmode="decimal" v-model="form.adt_vl_novo" />
                <p class="text-xs text-muted-foreground">Atual: {{ brl(Number(contrato.ctr_vl_total)) }}</p>
                <InputError :message="form.errors.adt_vl_novo" />
            </div>

            <div v-if="form.adt_tipo === 'PRAZO'" class="grid gap-2 sm:col-span-3">
                <FormLabel for="adt_dt_fim_nova" :required="true">Nova data de fim da vigência</FormLabel>
                <Input id="adt_dt_fim_nova" type="date" v-model="form.adt_dt_fim_nova" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <p class="text-xs text-muted-foreground">Atual: {{ dataBr(contrato.ctr_dt_fim) }}</p>
                <InputError :message="form.errors.adt_dt_fim_nova" />
            </div>

            <div v-if="form.adt_tipo === 'OBJETO'" class="grid gap-2 sm:col-span-6">
                <FormLabel for="adt_objeto_novo" :required="true">Novo objeto</FormLabel>
                <textarea
                    id="adt_objeto_novo"
                    v-model="form.adt_objeto_novo"
                    rows="3"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                ></textarea>
                <InputError :message="form.errors.adt_objeto_novo" />
            </div>

            <template v-if="form.adt_tipo === 'QTD'">
                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="adt_cti_id" :required="true">Item do contrato</FormLabel>
                    <select id="adt_cti_id" v-model="form.adt_cti_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option :value="null" disabled>Selecione o item...</option>
                        <option v-for="i in itens" :key="i.cti_id" :value="i.cti_id">
                            {{ i.item_nome }} — atual: {{ qtd(i.cti_qtd) }} {{ i.umd_sigla }}
                        </option>
                    </select>
                    <InputError :message="form.errors.adt_cti_id" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="adt_qtd_nova" :required="true">Nova quantidade</FormLabel>
                    <Input id="adt_qtd_nova" type="number" step="0.0001" min="0" inputmode="decimal" v-model="form.adt_qtd_nova" />
                    <InputError :message="form.errors.adt_qtd_nova" />
                </div>
            </template>

            <div class="grid gap-2 sm:col-span-5">
                <FormLabel for="adt_justificativa">Justificativa</FormLabel>
                <Input id="adt_justificativa" v-model="form.adt_justificativa" placeholder="Opcional" />
                <InputError :message="form.errors.adt_justificativa" />
            </div>

            <div class="flex sm:col-span-1 sm:pt-7">
                <Button type="button" class="w-full bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400" @click="lancar">
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Plus v-else class="mr-2 size-4" />
                    Lançar
                </Button>
            </div>
        </fieldset>
    </div>
</template>
