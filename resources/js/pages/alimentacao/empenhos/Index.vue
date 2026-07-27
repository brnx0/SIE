<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { EmpenhoLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { Banknote, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    empenhos: Paginated<EmpenhoLinha>;
    situacoes: string[];
    filters: { search: string; situacao: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, situacao: situacao.value, per_page: perPage.value };
    if (!resetPage) params.page = props.empenhos.current_page;
    router.get('/alimentacao/empenhos', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Empenhos', href: '/alimentacao/empenhos' }];

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const remove = (e: EmpenhoLinha) => {
    if (!confirm(`Remover o empenho "${e.emp_nr_empenho}"?`)) return;
    router.delete(`/alimentacao/empenhos/${e.emp_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Empenhos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Empenhos</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Reserva orçamentária que custeia as ordens de fornecimento. O saldo é o que ainda pode ser gasto.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/empenhos/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo empenho
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por número do empenho..." class="pl-9" />
                </div>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/alimentacao/empenhos/export" :filters="{ search, situacao }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Empenho</th>
                            <th class="px-4 py-3">Contrato</th>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3 text-right">Empenhado</th>
                            <th class="px-4 py-3 text-right">Consumido</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="empenhos.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">Nenhum empenho cadastrado.</td>
                        </tr>
                        <tr v-for="e in empenhos.data" :key="e.emp_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <Banknote class="size-4" />
                                    </div>
                                    <span class="font-medium">{{ e.emp_nr_empenho }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-col">
                                    <span>{{ e.contrato_numero }}/{{ e.contrato_ano }}</span>
                                    <span class="text-xs">{{ e.fornecedor_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(e.emp_dt) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums">{{ brl(e.emp_vl_empenhado) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ brl(e.vl_consumido) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span :class="e.vl_saldo <= 0 ? 'font-medium text-rose-600 dark:text-rose-400' : 'font-medium text-emerald-700 dark:text-emerald-400'">
                                    {{ brl(e.vl_saldo) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', e.emp_situacao === 'ATIVO' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ e.emp_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/empenhos/${e.emp_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(e)">
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
                        <template v-if="empenhos.total > 0">{{ empenhos.from }}–{{ empenhos.to }} de {{ empenhos.total }} registro{{ empenhos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="empenhos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in empenhos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
