<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import type { BreadcrumbItem } from '@/types';
import type { DemandaCabecalho, DemandaItemLinha } from '@/types/alimentacao';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Calculator, Check, ChevronLeft, CircleCheck, Lock, LoaderCircle, LockOpen, Pencil, RotateCcw, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    demanda: DemandaCabecalho;
    itens: DemandaItemLinha[];
    emRascunho: boolean;
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.itens ?? []);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Demanda de Merenda', href: '/alimentacao/demandas' },
    { title: `${props.demanda.mes_nome}/${props.demanda.dmd_ano}`, href: `/alimentacao/demandas/${props.demanda.dmd_id}/edit` },
]);

const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });

// Ajuste manual inline. O calculado nunca some — é o comparativo.
const editandoId = ref<number | null>(null);
const formItem = useForm<Record<string, any>>({
    dmi_qtd_ajustada: '' as number | string,
    dmi_justificativa: '',
    _method: 'put',
});

const abrirAjuste = (l: DemandaItemLinha) => {
    editandoId.value = l.dmi_id;
    formItem.clearErrors();
    formItem.dmi_qtd_ajustada = l.qtd_ajustada ?? l.qtd_calculada;
    formItem.dmi_justificativa = l.justificativa ?? '';
};

const salvarAjuste = (l: DemandaItemLinha) => {
    formItem.post(`/alimentacao/demandas/${props.demanda.dmd_id}/itens/${l.dmi_id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editandoId.value = null; },
    });
};

// Voltar ao calculado = limpar o ajuste.
const removerAjuste = (l: DemandaItemLinha) => {
    if (!confirm(`Remover o ajuste de "${l.item_nome}" e voltar ao valor calculado?`)) return;
    formItem.dmi_qtd_ajustada = '';
    formItem.dmi_justificativa = '';
    formItem.post(`/alimentacao/demandas/${props.demanda.dmd_id}/itens/${l.dmi_id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editandoId.value = null; },
    });
};

const formAcao = useForm({});

const recalcular = () => {
    const aviso = linhas.value.some((l) => l.ajustada)
        ? 'Recalcular vai apagar os ajustes manuais desta demanda e refazer tudo pelo cardápio. Continuar?'
        : 'Recalcular a demanda pelo cardápio e pelo calendário letivo?';
    if (!confirm(aviso)) return;
    formAcao.post(`/alimentacao/demandas/${props.demanda.dmd_id}/calcular`, { preserveScroll: true });
};

const fechar = () => {
    if (!confirm('Fechar a demanda? Ela vira a base da compra e deixa de ser recalculada.')) return;
    formAcao.post(`/alimentacao/demandas/${props.demanda.dmd_id}/fechar`, { preserveScroll: true });
};

const reabrir = () => {
    formAcao.post(`/alimentacao/demandas/${props.demanda.dmd_id}/reabrir`, { preserveScroll: true });
};

const ajustados = computed(() => linhas.value.filter((l) => l.ajustada).length);
</script>

<template>
    <Head :title="`Demanda ${demanda.mes_nome}/${demanda.dmd_ano}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Demanda — {{ demanda.mes_nome }}/{{ demanda.dmd_ano }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ demanda.escola_nome }}
                        <template v-if="demanda.ano_letivo"> · Ano letivo {{ demanda.ano_letivo }}</template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <ExportMenu :base-url="`/alimentacao/demandas/${demanda.dmd_id}/export`" :filters="{}" />
                    <Link href="/alimentacao/demandas">
                        <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                </div>
            </div>

            <div
                v-if="!emRascunho"
                class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200"
            >
                <CircleCheck class="mt-0.5 size-4 shrink-0" />
                <p>Demanda <strong>fechada</strong> — é a base da compra deste mês. Reabra para ajustar ou recalcular.</p>
            </div>

            <!-- De onde vieram os números -->
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Dias letivos no mês</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ demanda.dmd_dias_letivos }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm sm:col-span-2">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Cardápio usado</p>
                    <p class="mt-1 truncate text-sm font-medium">{{ demanda.cardapio_nome ?? '—' }}</p>
                    <p v-if="demanda.cardapio_da_rede !== null" class="text-xs text-muted-foreground">
                        {{ demanda.cardapio_da_rede ? 'Padrão da rede' : 'Cardápio próprio da escola' }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Itens ajustados</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', ajustados > 0 && 'text-amber-600 dark:text-amber-400']">
                        {{ ajustados }} / {{ linhas.length }}
                    </p>
                </div>
            </div>

            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Itens da demanda</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            O calculado sai do cardápio; o ajustado é a sua correção. O calculado permanece para o
                            comparativo — quem vale na compra é a coluna "a comprar".
                        </p>
                    </div>
                    <RefreshButton />
                </div>

                <div
                    v-if="linhas.length === 0"
                    class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <p>Esta demanda não tem itens. Recalcule para montá-la a partir do cardápio.</p>
                </div>

                <div v-else class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 text-right">Calculado</th>
                                <th class="px-4 py-3 text-right">Ajustado</th>
                                <th class="px-4 py-3 text-right">A comprar</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="l in linhas" :key="l.dmi_id">
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ l.item_nome }}</span>
                                            <span v-if="l.item_codigo" class="text-xs text-muted-foreground">Cód: {{ l.item_codigo }}</span>
                                            <span v-if="l.justificativa" class="text-xs italic text-amber-600 dark:text-amber-400">
                                                {{ l.justificativa }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                        {{ qtd(l.qtd_calculada) }} {{ l.umd_sigla }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span v-if="l.ajustada" class="font-medium text-amber-600 dark:text-amber-400">
                                            {{ qtd(l.qtd_ajustada!) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums">
                                        {{ qtd(l.qtd_final) }} {{ l.umd_sigla }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                v-if="!isReadOnly && emRascunho"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="abrirAjuste(l)"
                                            >
                                                <Pencil class="mr-1 size-3.5" /> Ajustar
                                            </Button>
                                            <Button
                                                v-if="!isReadOnly && emRascunho && l.ajustada"
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                title="Voltar ao valor calculado"
                                                @click="removerAjuste(l)"
                                            >
                                                <RotateCcw class="size-3.5" />
                                            </Button>
                                            <Lock v-if="!emRascunho" class="size-4 text-muted-foreground" />
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="editandoId === l.dmi_id">
                                    <td colspan="5" class="bg-muted/30 px-4 py-4">
                                        <div class="grid gap-4 sm:grid-cols-6 sm:items-start">
                                            <div class="sm:col-span-6">
                                                <p class="text-sm font-medium">{{ l.item_nome }}</p>
                                                <p class="text-xs text-muted-foreground">
                                                    O cálculo pelo cardápio deu {{ qtd(l.qtd_calculada) }} {{ l.umd_sigla }}.
                                                </p>
                                            </div>

                                            <div class="grid gap-2 sm:col-span-2">
                                                <label :for="`dmi_${l.dmi_id}`" class="text-sm font-medium">
                                                    Quantidade ajustada ({{ l.umd_sigla }})
                                                </label>
                                                <Input
                                                    :id="`dmi_${l.dmi_id}`"
                                                    type="number"
                                                    step="0.0001"
                                                    min="0"
                                                    inputmode="decimal"
                                                    v-model.number="formItem.dmi_qtd_ajustada"
                                                />
                                                <InputError :message="formItem.errors.dmi_qtd_ajustada" />
                                            </div>

                                            <div class="grid gap-2 sm:col-span-4">
                                                <label :for="`just_${l.dmi_id}`" class="text-sm font-medium">
                                                    Justificativa <span class="text-rose-500">*</span>
                                                </label>
                                                <Input
                                                    :id="`just_${l.dmi_id}`"
                                                    v-model="formItem.dmi_justificativa"
                                                    placeholder="Ex.: evento na escola, sobra do mês anterior"
                                                />
                                                <InputError :message="formItem.errors.dmi_justificativa" />
                                            </div>

                                            <div class="flex items-center gap-2 sm:col-span-6">
                                                <Button
                                                    type="button"
                                                    :disabled="formItem.processing"
                                                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                                    @click="salvarAjuste(l)"
                                                >
                                                    <LoaderCircle v-if="formItem.processing" class="mr-2 size-4 animate-spin" />
                                                    <Check v-else class="mr-2 size-4" />
                                                    Salvar ajuste
                                                </Button>
                                                <Button type="button" variant="outline" @click="editandoId = null">Cancelar</Button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ações -->
            <div v-if="!isReadOnly" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card p-6 shadow-sm">
                <div>
                    <p class="text-sm font-medium">{{ emRascunho ? 'Fechar a demanda' : 'Demanda fechada' }}</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ emRascunho
                            ? 'Depois de fechada, ela vira a base da compra e não é mais recalculada.'
                            : 'Reabra para ajustar itens ou recalcular pelo cardápio.' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="emRascunho"
                        type="button"
                        variant="outline"
                        :disabled="formAcao.processing"
                        @click="recalcular"
                    >
                        <LoaderCircle v-if="formAcao.processing" class="mr-2 size-4 animate-spin" />
                        <Calculator v-else class="mr-2 size-4" />
                        Recalcular
                    </Button>
                    <Button
                        v-if="emRascunho"
                        type="button"
                        :disabled="formAcao.processing || linhas.length === 0"
                        class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40"
                        @click="fechar"
                    >
                        <Lock class="mr-2 size-4" /> Fechar demanda
                    </Button>
                    <Button v-else type="button" variant="outline" :disabled="formAcao.processing" @click="reabrir">
                        <LockOpen class="mr-2 size-4" /> Reabrir
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
