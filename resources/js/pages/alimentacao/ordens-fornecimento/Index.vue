<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { OrdemLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    ordens: Paginated<OrdemLinha>;
    situacoes: string[];
    filters: { search: string; situacao: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, situacao: situacao.value, per_page: perPage.value };
    if (!resetPage) params.page = props.ordens.current_page;
    router.get('/alimentacao/ordens-fornecimento', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ordens de Fornecimento', href: '/alimentacao/ordens-fornecimento' }];

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const CORES: Record<string, string> = {
    EMITIDA:   'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    PARCIAL:   'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    ENTREGUE:  'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    CANCELADA: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

const remove = (o: OrdemLinha) => {
    if (!confirm(`Remover a ordem "${o.ofn_nr_ordem}"?`)) return;
    router.delete(`/alimentacao/ordens-fornecimento/${o.ofn_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Ordens de Fornecimento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Ordens de Fornecimento</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Pedidos ao fornecedor, dentro do saldo do contrato e do empenho.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/ordens-fornecimento/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Nova ordem
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por número da ordem..." class="pl-9" />
                </div>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/alimentacao/ordens-fornecimento/export" :filters="{ search, situacao }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Ordem</th>
                            <th class="px-4 py-3">Contrato</th>
                            <th class="px-4 py-3">Empenho</th>
                            <th class="px-4 py-3">Entrega prevista</th>
                            <th class="px-4 py-3 text-center">Itens</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="ordens.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">Nenhuma ordem cadastrada.</td>
                        </tr>
                        <tr v-for="o in ordens.data" :key="o.ofn_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <ClipboardList class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ o.ofn_nr_ordem }}</span>
                                        <span class="text-xs text-muted-foreground">Emitida em {{ dataBr(o.ofn_dt_emissao) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-col">
                                    <span>{{ o.contrato_numero }}/{{ o.contrato_ano }}</span>
                                    <span class="text-xs">{{ o.fornecedor_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ o.empenho_numero ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">
                                {{ dataBr(o.ofn_dt_entrega_prevista) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="o.itens_count" class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ o.itens_count }}
                                </span>
                                <span v-else class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                    Sem itens
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ brl(o.vl_total) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[o.ofn_situacao]]">
                                    {{ o.ofn_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/ordens-fornecimento/${o.ofn_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(o)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="ordens.total > 0">{{ ordens.from }}–{{ ordens.to }} de {{ ordens.total }} registro{{ ordens.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="ordens.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in ordens.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
