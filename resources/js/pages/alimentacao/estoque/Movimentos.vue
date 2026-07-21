<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { MovimentoLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, FileText, Package, Search, Undo2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    movimentos: Paginated<MovimentoLinha>;
    /** Escola do estoque em exibição; nula = estoque central. */
    escolaFiltro: { esc_id: number; esc_nome: string } | null;
    podeVerCentral: boolean;
    tipos: string[];
    filters: { search: string; esc_id: string; tipo: string; dt_ini: string; dt_fim: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const escId   = ref<number | null>(props.filters.esc_id ? Number(props.filters.esc_id) : null);
const tipo    = ref(props.filters.tipo ?? '');
const dtIni   = ref(props.filters.dt_ini ?? '');
const dtFim   = ref(props.filters.dt_fim ?? '');
const perPage = ref(props.filters.per_page ?? 25);

// Central e escola são dois estoques distintos — ver nota em estoque/Index.vue.
const modo = ref<'central' | 'escola'>(escId.value ? 'escola' : 'central');
watch(modo, (m) => {
    if (m === 'central') escId.value = null;
});

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value, esc_id: escId.value ?? '', tipo: tipo.value,
        dt_ini: dtIni.value, dt_fim: dtFim.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.movimentos.current_page;
    router.get('/alimentacao/estoque/movimentos', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([escId, tipo, dtIni, dtFim, perPage], () => apply(true));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Consulta de Estoque', href: '/alimentacao/estoque' }];

const brl = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const CORES: Record<string, string> = {
    ENTRADA:  'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    TRANSFERENCIA: 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    RETIRADA: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    CONSUMO:  'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    AJUSTE:   'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ESTORNO:  'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
};

// O tipo é gravado sem acento; a tela mostra o rótulo de gente.
const ROTULOS: Record<string, string> = {
    ENTRADA:       'Entrada',
    TRANSFERENCIA: 'Transferência',
    RETIRADA:      'Retirada',
    CONSUMO:       'Consumo',
    AJUSTE:        'Ajuste',
    ESTORNO:       'Estorno',
};
const rotulo = (t: string) => ROTULOS[t] ?? t;

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};
</script>

<template>
    <Head title="Histórico de Movimentação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Histórico de Movimentação</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Todo movimento do estoque, em ordem. É daqui que o saldo é derivado — nada é apagado, correção
                        aparece como estorno.
                    </p>
                </div>
                <Link href="/alimentacao/estoque">
                    <Button variant="outline">
                        <Package class="mr-2 size-4" /> Ver saldo
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-xs">
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
                <div v-if="!podeVerCentral || modo === 'escola'" class="w-56">
                    <EscolaCombobox v-model="escId" :initial="escolaFiltro" placeholder="Buscar escola..." />
                </div>
                <select v-model="tipo" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os tipos</option>
                    <option v-for="t in tipos" :key="t" :value="t">{{ rotulo(t) }}</option>
                </select>
                <Input v-model="dtIni" type="date" class="w-40" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <Input v-model="dtFim" type="date" class="w-40" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu
                        base-url="/alimentacao/estoque/movimentos/export"
                        :filters="{ search, esc_id: escId, tipo, dt_ini: dtIni, dt_fim: dtFim }"
                    />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Lote</th>
                            <th class="px-4 py-3 text-right">Quantidade</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3">Origem / Responsável</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="movimentos.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                Nenhum movimento neste estoque.
                            </td>
                        </tr>
                        <tr v-for="m in movimentos.data" :key="m.mov_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(m.mov_dt) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[m.mov_tipo]]">
                                        {{ rotulo(m.mov_tipo) }}
                                    </span>
                                    <ArrowLeftRight v-if="m.transferencia" class="size-3.5 text-muted-foreground" title="Transferência entre estoques" />
                                    <a
                                        v-if="m.transferencia_id"
                                        :href="`/alimentacao/transferencias/${m.transferencia_id}/pdf`"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                        title="Guia de remessa (PDF)"
                                    >
                                        <FileText class="size-3.5" />
                                    </a>
                                    <Undo2 v-if="m.estorno_de" class="size-3.5 text-rose-500" :title="`Estorno do movimento #${m.estorno_de}`" />
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ m.item_nome }}</span>
                                    <span v-if="m.escola_nome" class="text-xs text-muted-foreground">{{ m.escola_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-col">
                                    <span>{{ m.lote_codigo ?? '—' }}</span>
                                    <span v-if="m.lote_validade" class="text-xs tabular-nums">Val.: {{ dataBr(m.lote_validade) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="['font-medium', m.sinal > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400']">
                                    {{ m.sinal > 0 ? '+' : '−' }}{{ qtd(m.qtd_base) }} {{ m.umd_base }}
                                </span>
                                <!-- A unidade digitada só aparece quando difere da base:
                                     é a informação que explica "entrou 2, virou 60". -->
                                <div v-if="m.umd_sigla !== m.umd_base" class="text-xs text-muted-foreground">
                                    lançado: {{ qtd(m.qtd) }} {{ m.umd_sigla }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ brl(m.vl_total) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-col text-xs">
                                    <span v-if="m.fornecedor_nome">{{ m.fornecedor_nome }}</span>
                                    <span v-if="m.tipo_retirada">{{ m.tipo_retirada }}</span>
                                    <span v-if="m.usuario_nome">{{ m.usuario_nome }}</span>
                                    <span v-if="m.mov_obs" class="italic">{{ m.mov_obs }}</span>
                                    <span v-if="!m.fornecedor_nome && !m.tipo_retirada && !m.usuario_nome && !m.mov_obs">—</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="movimentos.total > 0">{{ movimentos.from }}–{{ movimentos.to }} de {{ movimentos.total }} movimento{{ movimentos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="movimentos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in movimentos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
