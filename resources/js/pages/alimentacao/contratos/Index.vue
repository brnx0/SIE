<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Contrato } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    contratos: Paginated<Contrato>;
    situacoes: string[];
    filters: { search: string; ano: string; situacao: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const ano      = ref(props.filters.ano ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, ano: ano.value, situacao: situacao.value, per_page: perPage.value };
    if (!resetPage) params.page = props.contratos.current_page;
    router.get('/alimentacao/contratos', params, { preserveState: true, replace: true });
};
watch([search, ano], () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contratos', href: '/alimentacao/contratos' }];

const brl = (v: string | number) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataBr = (iso: string) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '');

const CORES: Record<string, string> = {
    VIGENTE:    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    SUSPENSO:   'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    ENCERRADO:  'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    RESCINDIDO: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
};

const remove = (c: Contrato) => {
    if (!confirm(`Remover o contrato "${c.ctr_nr_contrato}/${c.ctr_ano}"?`)) return;
    router.delete(`/alimentacao/contratos/${c.ctr_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Contratos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Contratos</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Contratos de fornecimento da alimentação escolar, com itens, aditivos e ordens.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/contratos/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo contrato
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por número, objeto ou fornecedor..." class="pl-9" />
                </div>
                <Input v-model="ano" type="number" min="2000" max="2099" placeholder="Ano" class="w-28" />
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/alimentacao/contratos/export" :filters="{ search, ano, situacao }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Contrato</th>
                            <th class="px-4 py-3">Fornecedor</th>
                            <th class="px-4 py-3">Vigência</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-center">Itens</th>
                            <th class="px-4 py-3 text-center">Aditivos</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="contratos.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">Nenhum contrato cadastrado.</td>
                        </tr>
                        <tr v-for="c in contratos.data" :key="c.ctr_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <FileText class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ c.ctr_nr_contrato }}/{{ c.ctr_ano }}</span>
                                        <span v-if="c.ctr_modalidade" class="text-xs text-muted-foreground">{{ c.ctr_modalidade }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ c.fornecedor?.frn_razao_social ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">
                                {{ dataBr(c.ctr_dt_inicio) }} – {{ dataBr(c.ctr_dt_fim) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ brl(c.ctr_vl_total) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="c.itens_count" class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ c.itens_count }}
                                </span>
                                <span v-else class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" title="Sem itens não é possível emitir ordens">
                                    Sem itens
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-muted-foreground">{{ c.aditivos_count || '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[c.ctr_situacao]]">
                                    {{ c.ctr_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/contratos/${c.ctr_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button
                                        v-if="!isReadOnly"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="!!c.aditivos_count || !!c.ordens_count"
                                        :title="c.aditivos_count || c.ordens_count ? 'Contrato com movimento não pode ser excluído' : 'Remover'"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                        @click="remove(c)"
                                    >
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
                        <template v-if="contratos.total > 0">{{ contratos.from }}–{{ contratos.to }} de {{ contratos.total }} registro{{ contratos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="contratos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in contratos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
