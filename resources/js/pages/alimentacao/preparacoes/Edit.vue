<script setup lang="ts">
import RefreshButton from '@/components/common/RefreshButton.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import type { BreadcrumbItem } from '@/types';
import type { PreparacaoCabecalho, PreparacaoItemLinha } from '@/types/alimentacao';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, CircleCheck, Lock, LoaderCircle, PackageMinus, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    preparacao: PreparacaoCabecalho;
    itens: PreparacaoItemLinha[];
    emRascunho: boolean;
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.itens ?? []);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Controle Diário da Preparação', href: '/alimentacao/preparacoes' },
    { title: props.preparacao.prato_nome ?? 'Preparação', href: `/alimentacao/preparacoes/${props.preparacao.prd_id}/edit` },
]);

const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

// Ajuste inline do utilizado: cada linha edita a sua quantidade.
const editandoId = ref<number | null>(null);
const formItem = useForm<Record<string, any>>({ pdi_qtd_utilizada: 0, _method: 'put' });

const abrirEdicao = (l: PreparacaoItemLinha) => {
    editandoId.value = l.pdi_id;
    formItem.clearErrors();
    formItem.pdi_qtd_utilizada = l.qtd_utilizada;
};

const salvarItem = (l: PreparacaoItemLinha) => {
    formItem.post(`/alimentacao/preparacoes/${props.preparacao.prd_id}/itens/${l.pdi_id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editandoId.value = null; },
    });
};

const restaurarPrevisto = (l: PreparacaoItemLinha) => {
    formItem.pdi_qtd_utilizada = l.qtd_prevista;
};

const formConfirmar = useForm({});
const confirmar = () => {
    if (!confirm('Confirmar a preparação? Os itens utilizados vão baixar do estoque da escola, e isso não pode ser desfeito por aqui.')) return;
    formConfirmar.post(`/alimentacao/preparacoes/${props.preparacao.prd_id}/confirmar`, { preserveScroll: true });
};

const totalUtilizado = computed(() => linhas.value.filter((l) => l.qtd_utilizada > 0).length);
const comDesvio = computed(() => linhas.value.filter((l) => Math.abs(l.desvio) > 0.0001).length);
</script>

<template>
    <Head :title="`Preparação — ${preparacao.prato_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        {{ preparacao.prato_nome }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ dataBr(preparacao.prd_dt) }} · {{ preparacao.prd_turno }} · {{ preparacao.prd_refeicao }}
                        · {{ preparacao.prd_qtd_servida }} porções
                        <template v-if="preparacao.escola_nome"> · {{ preparacao.escola_nome }}</template>
                    </p>
                </div>
                <Link href="/alimentacao/preparacoes">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <div
                v-if="!emRascunho"
                class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200"
            >
                <CircleCheck class="mt-0.5 size-4 shrink-0" />
                <p>
                    Preparação <strong>{{ preparacao.prd_situacao }}</strong>. Os itens já foram baixados do estoque da
                    escola e não podem mais ser alterados — para corrigir, estorne os movimentos no histórico de
                    movimentação.
                </p>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Itens a baixar</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ totalUtilizado }} / {{ linhas.length }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Fora da ficha técnica</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', comDesvio > 0 && 'text-amber-600 dark:text-amber-400']">
                        {{ comDesvio }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Porções servidas</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ preparacao.prd_qtd_servida }}</p>
                </div>
            </div>

            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Itens utilizados</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            O previsto vem da ficha técnica (per capita × porções). Ajuste o que a cozinha realmente
                            gastou — é o utilizado que baixa do estoque.
                        </p>
                    </div>
                    <RefreshButton />
                </div>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 text-right">Previsto</th>
                                <th class="px-4 py-3 text-right">Utilizado</th>
                                <th class="px-4 py-3 text-right">Desvio</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="l in linhas" :key="l.pdi_id">
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-3 font-medium">{{ l.item_nome }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                        {{ qtd(l.qtd_prevista) }} {{ l.umd_sigla }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums">
                                        {{ qtd(l.qtd_utilizada) }} {{ l.umd_sigla }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span
                                            v-if="Math.abs(l.desvio) > 0.0001"
                                            :class="l.desvio > 0 ? 'font-medium text-rose-600 dark:text-rose-400' : 'font-medium text-sky-600 dark:text-sky-400'"
                                        >
                                            {{ l.desvio > 0 ? '+' : '' }}{{ qtd(l.desvio) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end">
                                            <Button
                                                v-if="!isReadOnly && emRascunho"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="abrirEdicao(l)"
                                            >
                                                <Check class="mr-1 size-3.5" /> Ajustar
                                            </Button>
                                            <Lock v-else-if="!emRascunho" class="size-4 text-muted-foreground" />
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="editandoId === l.pdi_id">
                                    <td colspan="5" class="bg-muted/30 px-4 py-4">
                                        <div class="flex flex-wrap items-end gap-3">
                                            <div class="grid gap-2">
                                                <label :for="`pdi_${l.pdi_id}`" class="text-sm font-medium">
                                                    Quantidade utilizada ({{ l.umd_sigla }})
                                                </label>
                                                <Input
                                                    :id="`pdi_${l.pdi_id}`"
                                                    type="number"
                                                    step="0.0001"
                                                    min="0"
                                                    inputmode="decimal"
                                                    class="w-40"
                                                    v-model.number="formItem.pdi_qtd_utilizada"
                                                />
                                                <InputError :message="formItem.errors.pdi_qtd_utilizada" />
                                            </div>
                                            <Button type="button" variant="outline" @click="restaurarPrevisto(l)">
                                                <RotateCcw class="mr-1 size-3.5" /> Usar o previsto ({{ qtd(l.qtd_prevista) }})
                                            </Button>
                                            <Button
                                                type="button"
                                                :disabled="formItem.processing"
                                                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                                @click="salvarItem(l)"
                                            >
                                                <LoaderCircle v-if="formItem.processing" class="mr-2 size-4 animate-spin" />
                                                <Check v-else class="mr-2 size-4" />
                                                Salvar
                                            </Button>
                                            <Button type="button" variant="outline" @click="editandoId = null">Cancelar</Button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="emRascunho && !isReadOnly" class="flex items-center justify-between rounded-xl border bg-card p-6 shadow-sm">
                <div>
                    <p class="text-sm font-medium">Confirmar a preparação</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Baixa os itens utilizados do estoque da escola, pelos lotes que vencem antes (FEFO). Não dá para desfazer por aqui.
                    </p>
                </div>
                <Button
                    type="button"
                    :disabled="formConfirmar.processing || totalUtilizado === 0"
                    :title="totalUtilizado === 0 ? 'Informe ao menos um item utilizado' : ''"
                    class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40"
                    @click="confirmar"
                >
                    <LoaderCircle v-if="formConfirmar.processing" class="mr-2 size-4 animate-spin" />
                    <PackageMinus v-else class="mr-2 size-4" />
                    Confirmar e baixar estoque
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
