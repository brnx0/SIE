<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { CategoriaAlimento, Item, TipoItem } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { Apple, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    itens: Paginated<Item>;
    tipos: Pick<TipoItem, 'tpi_id' | 'tpi_nome'>[];
    categorias: Pick<CategoriaAlimento, 'cat_id' | 'cat_nome'>[];
    filters: { search: string; tpi_id: string; cat_id: string; situacao: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const tpiId    = ref(props.filters.tpi_id ?? '');
const catId    = ref(props.filters.cat_id ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value, tpi_id: tpiId.value, cat_id: catId.value,
        situacao: situacao.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.itens.current_page;
    router.get('/alimentacao/itens', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([tpiId, catId, situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Itens', href: '/alimentacao/itens' }];

const remove = (i: Item) => {
    if (!confirm(`Remover o item "${i.itm_nome}"?`)) return;
    router.delete(`/alimentacao/itens/${i.itm_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Itens" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Itens</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Gêneros alimentícios usados na alimentação escolar, com especificação técnica e unidade de medida.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/itens/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo item
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome, código ou especificação..." class="pl-9" />
                </div>
                <select v-model="tpiId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os tipos</option>
                    <option v-for="t in tipos" :key="t.tpi_id" :value="String(t.tpi_id)">{{ t.tpi_nome }}</option>
                </select>
                <select v-model="catId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as categorias</option>
                    <option v-for="c in categorias" :key="c.cat_id" :value="String(c.cat_id)">{{ c.cat_nome }}</option>
                </select>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option value="ativo">Ativos</option>
                    <option value="inativo">Inativos</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/alimentacao/itens/export" :filters="{ search, tpi_id: tpiId, cat_id: catId, situacao }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Categoria</th>
                            <th class="px-4 py-3 text-center">Unid.</th>
                            <th class="px-4 py-3 text-center">Marcadores</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="itens.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhum item cadastrado.</td>
                        </tr>
                        <tr v-for="i in itens.data" :key="i.itm_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <Apple class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ i.itm_nome }}</span>
                                        <span v-if="i.itm_cd_item" class="text-xs text-muted-foreground">Cód: {{ i.itm_cd_item }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ i.tipoItem?.tpi_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ i.categoria?.cat_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-center font-medium">{{ i.unidadeMedida?.umd_sigla ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-center gap-1">
                                    <span v-if="i.itm_fl_perecivel" class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                        Perecível<template v-if="i.itm_validade_dias"> · {{ i.itm_validade_dias }}d</template>
                                    </span>
                                    <span v-if="i.itm_fl_agricultura_familiar" class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        Agric. familiar
                                    </span>
                                    <span v-if="!i.itm_fl_perecivel && !i.itm_fl_agricultura_familiar" class="text-xs text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', i.itm_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ i.itm_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/itens/${i.itm_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(i)">
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
                        <template v-if="itens.total > 0">{{ itens.from }}–{{ itens.to }} de {{ itens.total }} registro{{ itens.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="itens.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in itens.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
