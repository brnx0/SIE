<script setup lang="ts">
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { RecebimentoLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    recebimentos: Paginated<RecebimentoLinha>;
    situacoes: string[];
    tipos: string[];
    filters: { search: string; situacao: string; tipo: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const situacao = ref(props.filters.situacao ?? '');
const tipo     = ref(props.filters.tipo ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, situacao: situacao.value, tipo: tipo.value, per_page: perPage.value };
    if (!resetPage) params.page = props.recebimentos.current_page;
    router.get('/alimentacao/recebimentos', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([situacao, tipo, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Recebimento de Itens', href: '/alimentacao/recebimentos' }];

const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const CORES: Record<string, string> = {
    PENDENTE:  'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    ACEITO:    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    PARCIAL:   'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    REJEITADO: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
};

const remove = (r: RecebimentoLinha) => {
    if (!confirm(`Remover o recebimento da ordem "${r.ordem_numero}"?`)) return;
    router.delete(`/alimentacao/recebimentos/${r.rcb_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Recebimento de Itens" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Recebimento de Itens</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Conferência da entrega do fornecedor contra a ordem. Ao aceitar, os itens dão entrada no estoque central.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/recebimentos/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo recebimento
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por ordem ou nota..." class="pl-9" />
                </div>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                </select>
                <select v-model="tipo" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os tipos</option>
                    <option v-for="t in tipos" :key="t" :value="t">{{ t.replace('_', ' ') }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Ordem</th>
                            <th class="px-4 py-3">Fornecedor</th>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Nota</th>
                            <th class="px-4 py-3 text-center">Conferência</th>
                            <th class="px-4 py-3 text-center">Tipo</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="recebimentos.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">Nenhum recebimento registrado.</td>
                        </tr>
                        <tr v-for="r in recebimentos.data" :key="r.rcb_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <ClipboardCheck class="size-4" />
                                    </div>
                                    <span class="font-medium">{{ r.ordem_numero }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ r.fornecedor_nome ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(r.rcb_dt) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ r.rcb_nr_nota ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', r.conferidos === r.itens_count ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']">
                                    {{ r.conferidos }}/{{ r.itens_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="r.rcb_tipo === 'AGRICULTURA_FAMILIAR'"
                                    class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                >
                                    Agric. familiar
                                </span>
                                <span v-else class="text-xs text-muted-foreground">Ordinário</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[r.rcb_situacao]]">
                                    {{ r.rcb_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/recebimentos/${r.rcb_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button
                                        v-if="!isReadOnly"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="r.rcb_situacao !== 'PENDENTE'"
                                        :title="r.rcb_situacao !== 'PENDENTE' ? 'Recebimento já aceito não pode ser excluído' : 'Remover'"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                        @click="remove(r)"
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
                        <template v-if="recebimentos.total > 0">{{ recebimentos.from }}–{{ recebimentos.to }} de {{ recebimentos.total }} registro{{ recebimentos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="recebimentos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in recebimentos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
