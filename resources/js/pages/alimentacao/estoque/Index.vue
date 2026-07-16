<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { CategoriaAlimento, SaldoLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { History, Package, Search, TriangleAlert } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    saldos: Paginated<SaldoLinha>;
    /** Escola do estoque em exibição; nula = estoque central. */
    escolaFiltro: { esc_id: number; esc_nome: string } | null;
    podeVerCentral: boolean;
    categorias: Pick<CategoriaAlimento, 'cat_id' | 'cat_nome'>[];
    resumo: { itens: number; valor_total: number; vencidos: number; vencendo: number };
    filters: { search: string; esc_id: string; cat_id: string; alerta: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const escId   = ref<number | null>(props.filters.esc_id ? Number(props.filters.esc_id) : null);
const catId   = ref(props.filters.cat_id ?? '');
const alerta  = ref(props.filters.alerta ?? '');
const perPage = ref(props.filters.per_page ?? 25);

// Central e escola são dois estoques distintos, não "filtro vazio" e "filtro
// preenchido": um seletor explícito evita que limpar o combobox pareça
// "todos os estoques" quando na verdade mostra só o central.
const modo = ref<'central' | 'escola'>(escId.value ? 'escola' : 'central');

watch(modo, (m) => {
    if (m === 'central') escId.value = null;
});

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value, esc_id: escId.value ?? '', cat_id: catId.value,
        alerta: alerta.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.saldos.current_page;
    router.get('/alimentacao/estoque', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([escId, catId, alerta, perPage], () => apply(true));

usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Consulta de Estoque', href: '/alimentacao/estoque' }];

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

// Vencido, vence em ≤30 dias, ou ok. É o que a nutrição olha primeiro.
const validadeClasse = (l: SaldoLinha) => {
    if (l.vencido) return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
    if (l.dias_vencer !== null && l.dias_vencer <= 30) return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
    return 'text-muted-foreground';
};

const validadeTexto = (l: SaldoLinha) => {
    if (!l.lote_validade) return 'Sem validade';
    if (l.vencido) return `Vencido em ${dataBr(l.lote_validade)}`;
    if (l.dias_vencer !== null && l.dias_vencer <= 30) return `${dataBr(l.lote_validade)} · ${l.dias_vencer}d`;
    return dataBr(l.lote_validade);
};
</script>

<template>
    <Head title="Consulta de Estoque" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Consulta de Estoque</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Saldo por item e lote, com validade e valorização. Atualizado a cada movimento.
                    </p>
                </div>
                <Link href="/alimentacao/estoque/movimentos">
                    <Button variant="outline">
                        <History class="mr-2 size-4" /> Histórico de movimentação
                    </Button>
                </Link>
            </div>

            <!-- Resumo -->
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Itens com saldo</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ resumo.itens }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Valor do estoque</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(resumo.valor_total) }}</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border bg-card px-4 py-3 text-left shadow-sm transition-colors hover:bg-muted/40"
                    @click="alerta = alerta === 'vencendo' ? '' : 'vencendo'"
                >
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Vencem em 30 dias</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', resumo.vencendo > 0 && 'text-amber-600 dark:text-amber-400']">
                        {{ resumo.vencendo }}
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border bg-card px-4 py-3 text-left shadow-sm transition-colors hover:bg-muted/40"
                    @click="alerta = alerta === 'vencido' ? '' : 'vencido'"
                >
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Vencidos</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', resumo.vencidos > 0 && 'text-rose-600 dark:text-rose-400']">
                        {{ resumo.vencidos }}
                    </p>
                </button>
            </div>

            <div
                v-if="resumo.vencidos > 0"
                class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    Há <strong>{{ resumo.vencidos }}</strong> lote(s) vencido(s) com saldo. Item vencido não deve ser
                    servido — registre a retirada por vencimento para tirá-lo do saldo.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar item..." class="pl-9" />
                </div>
                <div v-if="podeVerCentral" class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                    <button
                        type="button"
                        :class="['rounded-md px-3 py-1.5 text-xs font-medium transition-colors', modo === 'central' ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                        @click="modo = 'central'"
                    >
                        Central
                    </button>
                    <button
                        type="button"
                        :class="['rounded-md px-3 py-1.5 text-xs font-medium transition-colors', modo === 'escola' ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                        @click="modo = 'escola'"
                    >
                        Escola
                    </button>
                </div>
                <div v-if="!podeVerCentral || modo === 'escola'" class="w-64">
                    <EscolaCombobox v-model="escId" :initial="escolaFiltro" placeholder="Buscar escola..." />
                </div>
                <select v-model="catId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as categorias</option>
                    <option v-for="c in categorias" :key="c.cat_id" :value="String(c.cat_id)">{{ c.cat_nome }}</option>
                </select>
                <select v-model="alerta" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Toda a validade</option>
                    <option value="vencendo">Vencem em 30 dias</option>
                    <option value="vencido">Vencidos</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/alimentacao/estoque/export" :filters="{ search, esc_id: escId, cat_id: catId, alerta }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Lote</th>
                            <th class="px-4 py-3">Validade</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3 text-right">Custo médio</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="saldos.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                Nenhum item com saldo neste estoque.
                            </td>
                        </tr>
                        <tr v-for="l in saldos.data" :key="l.sld_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <Package class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ l.item_nome }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            <template v-if="l.categoria">{{ l.categoria }}</template>
                                            <template v-if="l.escola_nome"> · {{ l.escola_nome }}</template>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-col">
                                    <span>{{ l.lote_codigo ?? '—' }}</span>
                                    <span v-if="l.marca" class="text-xs">{{ l.marca }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', validadeClasse(l)]">
                                    {{ validadeTexto(l) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ qtd(l.qtd) }} {{ l.umd_sigla }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ brl(l.custo_medio) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ brl(l.valor_total) }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="saldos.total > 0">{{ saldos.from }}–{{ saldos.to }} de {{ saldos.total }} registro{{ saldos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="saldos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in saldos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
