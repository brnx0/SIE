<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useErrorFocus } from '@/composables/useErrorFocus';
import { usePermissao } from '@/composables/usePermissao';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, ClipboardCheck, Loader2, Lock, LockOpen, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Unidade { uni_id: number; label: string; parametrizada: boolean }
interface Instrumento {
    iav_id: number;
    iav_nome: string;
    iav_fl_recuperacao: boolean;
    iav_fl_ativo: boolean;
    selecionado: boolean;
    valor: number | null;
}

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    anlId: number | null;
    unidades: Unidade[];
    uniId: number | null;
    instrumentos: Instrumento[];
    bloqueado: boolean;
    updatedAt: string | null;
    updatedBy: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Parametrização de Avaliações', href: '/diario/parametrizacao-avaliacoes' },
];

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

// Valor de cada instrumento em chave plana (valor_<iav_id>) = id do input = chave do erro.
const valoresDe = () => ({
    uni_id: props.uniId,
    bloqueado: props.bloqueado,
    selecionados: props.instrumentos.filter((i) => i.selecionado).map((i) => i.iav_id),
    ...Object.fromEntries(props.instrumentos.map((i) => [`valor_${i.iav_id}`, i.valor ?? ''])),
});

const form = useForm<Record<string, any>>(valoresDe());

// Troca de unidade/ano ou gravação: o servidor manda outra parametrização.
let ultimoServidor = JSON.stringify(valoresDe());
watch(() => [props.uniId, props.instrumentos, props.bloqueado], () => {
    const v = valoresDe();
    const serializado = JSON.stringify(v);
    if (serializado === ultimoServidor) return;
    ultimoServidor = serializado;
    form.clearErrors();
    Object.assign(form, v);
    form.defaults(v);
});

const selecionado = (id: number) => (form.selecionados as number[]).includes(id);
const alternar = (i: Instrumento) => {
    if (isReadOnly.value) return;
    const lista = form.selecionados as number[];
    form.selecionados = selecionado(i.iav_id) ? lista.filter((x) => x !== i.iav_id) : [...lista, i.iav_id];
    if (!selecionado(i.iav_id)) form.clearErrors(`valor_${i.iav_id}`);
};

// Soma dos regulares selecionados — referência para a regra de soma ≤ 10 do diário.
const somaRegulares = computed(() =>
    props.instrumentos
        .filter((i) => selecionado(i.iav_id) && !i.iav_fl_recuperacao)
        .reduce((s, i) => s + (Number(form[`valor_${i.iav_id}`]) || 0), 0),
);

const navegar = (params: Record<string, number | null>) => {
    if (form.isDirty && !confirm('Há alterações não salvas nesta unidade. Descartar?')) return;
    router.get('/diario/parametrizacao-avaliacoes', params, { preserveScroll: true });
};
const trocarAno = (e: Event) => navegar({ anl_id: Number((e.target as HTMLSelectElement).value) });
const trocarUnidade = (u: Unidade) => {
    if (u.uni_id !== props.uniId) navegar({ anl_id: props.anlId, uni_id: u.uni_id });
};

const salvar = () => {
    form.transform((d) => ({
        uni_id: d.uni_id,
        bloqueado: d.bloqueado,
        selecionados: d.selecionados,
        // Só os valores dos selecionados vão para o servidor.
        ...Object.fromEntries((d.selecionados as number[]).map((id) => [`valor_${id}`, d[`valor_${id}`]])),
    })).put('/diario/parametrizacao-avaliacoes', {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => focusFirstError(Object.keys(errors)),
    });
};

const fmtDateTime = (d: string | null) =>
    d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '';
</script>

<template>
    <Head title="Parametrização de Avaliações" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <!-- Cabeçalho + Salvar no topo -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Parametrização de Avaliações
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Instrumentos avaliativos de cada unidade e o valor máximo de cada um, para toda a rede.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <RefreshButton />
                    <Button
                        v-if="!isReadOnly && uniId"
                        type="button"
                        class="bg-indigo-600 hover:bg-indigo-700"
                        :disabled="form.processing || !form.isDirty"
                        @click="salvar"
                    >
                        <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <Save v-else class="mr-2 size-4" />
                        Salvar
                    </Button>
                </div>
            </div>

            <!-- Ano letivo + unidades -->
            <div class="flex flex-col gap-3 rounded-lg border bg-card px-4 py-3 shadow-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <CalendarDays class="size-5 shrink-0 text-indigo-600" />
                    <label for="sel_ano" class="text-sm font-medium whitespace-nowrap">Ano Letivo:</label>
                    <select
                        id="sel_ano"
                        :value="anlId ?? ''"
                        class="rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @change="trocarAno"
                    >
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }}{{ a.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                        </option>
                    </select>
                </div>
                <div v-if="unidades.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="u in unidades"
                        :key="u.uni_id"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition',
                            u.uni_id === uniId
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-input bg-background hover:border-indigo-300',
                        ]"
                        @click="trocarUnidade(u)"
                    >
                        {{ u.label }}
                        <span
                            :class="['size-2 rounded-full', u.parametrizada ? (u.uni_id === uniId ? 'bg-white' : 'bg-emerald-500') : 'bg-slate-300 dark:bg-slate-600']"
                            :title="u.parametrizada ? 'Parametrizada' : 'Sem parametrização'"
                        />
                    </button>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nenhuma unidade cadastrada para este ano letivo.</p>
            </div>

            <template v-if="uniId">
                <div class="flex items-start gap-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200">
                    <ClipboardCheck class="mt-0.5 size-4 shrink-0" />
                    <span>
                        Com instrumentos marcados, o professor só cria avaliações com eles nesta unidade, e o valor já vem preenchido.
                        Sem nenhum marcado, a unidade fica livre (todos os instrumentos ativos, valor digitado pelo professor).
                    </span>
                </div>

                <!-- Bloqueio dos valores -->
                <div class="flex items-center justify-between gap-4 rounded-lg border bg-card px-4 py-3 shadow-sm">
                    <div class="flex items-start gap-3">
                        <Lock v-if="form.bloqueado" class="mt-0.5 size-5 shrink-0 text-amber-600" />
                        <LockOpen v-else class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                        <div>
                            <FormLabel for="bloqueado">Valores bloqueados</FormLabel>
                            <p class="text-xs text-muted-foreground">
                                {{ form.bloqueado
                                    ? 'O professor não altera o valor: a avaliação usa sempre o valor parametrizado.'
                                    : 'O valor vem preenchido, mas o professor pode alterá-lo ao criar a avaliação.' }}
                            </p>
                        </div>
                    </div>
                    <Switch id="bloqueado" v-model="form.bloqueado" :disabled="isReadOnly" />
                </div>

                <!-- Instrumentos -->
                <div class="overflow-hidden rounded-lg border bg-card shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3">Instrumento</th>
                                <th class="w-44 px-4 py-3">Valor máximo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!instrumentos.length">
                                <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">
                                    Nenhum instrumento avaliativo ativo. Cadastre em Diário Online › Cadastro › Instrumentos Avaliativos.
                                </td>
                            </tr>
                            <tr
                                v-for="i in instrumentos"
                                :key="i.iav_id"
                                :class="selecionado(i.iav_id) ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''"
                            >
                                <td class="px-4 py-2 align-top">
                                    <input
                                        :id="`sel_${i.iav_id}`"
                                        type="checkbox"
                                        class="mt-1 size-4 accent-indigo-600"
                                        :checked="selecionado(i.iav_id)"
                                        :disabled="isReadOnly"
                                        @change="alternar(i)"
                                    />
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <label :for="`sel_${i.iav_id}`" class="cursor-pointer font-medium">{{ i.iav_nome }}</label>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span v-if="i.iav_fl_recuperacao" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                            Recuperação — fora da soma
                                        </span>
                                        <span v-if="!i.iav_fl_ativo" class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            Inativo
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Input
                                        :id="`valor_${i.iav_id}`"
                                        v-model="form[`valor_${i.iav_id}`]"
                                        type="number"
                                        step="0.1"
                                        min="0.1"
                                        max="10"
                                        placeholder="0,0"
                                        :disabled="!selecionado(i.iav_id) || isReadOnly"
                                        :class="form.errors[`valor_${i.iav_id}`] ? 'border-rose-500' : ''"
                                    />
                                    <!-- Só com erro: na tabela, a altura reservada do InputError deixaria as linhas altas. -->
                                    <InputError v-if="form.errors[`valor_${i.iav_id}`]" :message="form.errors[`valor_${i.iav_id}`]" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/30 px-4 py-2 text-xs text-muted-foreground">
                        <span>
                            Soma dos instrumentos regulares marcados:
                            <b :class="somaRegulares > 10 ? 'text-amber-600' : 'text-foreground'">{{ somaRegulares.toFixed(1) }}</b> / 10
                            <template v-if="somaRegulares > 10"> — no diário a soma das avaliações regulares continua limitada a 10.</template>
                        </span>
                        <span v-if="updatedBy">Alterado em {{ fmtDateTime(updatedAt) }} · {{ updatedBy }}</span>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
