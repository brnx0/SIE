<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { DemandaLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarRange, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    demandas: Paginated<DemandaLinha>;
    /** Escola do filtro atual, para o combobox mostrar o nome já selecionado. */
    escolaFiltro: { esc_id: number; esc_nome: string } | null;
    anosLetivos: { anl_id: number; anl_ano: number }[];
    situacoes: string[];
    meses: Record<number, string>;
    filters: { esc_id: string; anl_id: string; situacao: string; per_page: number };
}>();

const escId    = ref<number | null>(props.filters.esc_id ? Number(props.filters.esc_id) : null);
const anlId    = ref(props.filters.anl_id ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        esc_id: escId.value ?? '', anl_id: anlId.value, situacao: situacao.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.demandas.current_page;
    router.get('/alimentacao/demandas', params, { preserveState: true, replace: true });
};
watch([escId, anlId, situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Demanda de Merenda', href: '/alimentacao/demandas' }];

const remove = (d: DemandaLinha) => {
    if (!confirm(`Remover a demanda de ${d.mes_nome}/${d.dmd_ano}?`)) return;
    router.delete(`/alimentacao/demandas/${d.dmd_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Demanda de Merenda" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Demanda de Merenda</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quanto de cada item a unidade precisa no mês, calculado do cardápio, do calendário letivo e dos
                        alunos matriculados.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/demandas/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Nova demanda
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Limpar o combobox tira o filtro: volta a mostrar todas as escolas. -->
                <div class="w-64">
                    <EscolaCombobox v-model="escId" :initial="escolaFiltro" placeholder="Todas as escolas" />
                </div>
                <select v-model="anlId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os anos letivos</option>
                    <option v-for="a in anosLetivos" :key="a.anl_id" :value="String(a.anl_id)">{{ a.anl_ano }}</option>
                </select>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
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
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3">Escola</th>
                            <th class="px-4 py-3 text-center">Dias letivos</th>
                            <th class="px-4 py-3 text-center">Itens</th>
                            <th class="px-4 py-3 text-center">Ajustados</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="demandas.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhuma demanda montada.</td>
                        </tr>
                        <tr v-for="d in demandas.data" :key="d.dmd_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <CalendarRange class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ d.mes_nome }}/{{ d.dmd_ano }}</span>
                                        <span v-if="d.ano_letivo" class="text-xs text-muted-foreground">Ano letivo {{ d.ano_letivo }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ d.escola_nome }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-muted-foreground">{{ d.dmd_dias_letivos }}</td>
                            <td class="px-4 py-3 text-center text-muted-foreground">{{ d.itens_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="d.ajustados > 0"
                                    class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                    title="Itens com ajuste manual sobre o calculado"
                                >
                                    {{ d.ajustados }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', d.dmd_situacao === 'FECHADA' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300']">
                                    {{ d.dmd_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/demandas/${d.dmd_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button
                                        v-if="!isReadOnly"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="d.dmd_situacao !== 'RASCUNHO'"
                                        :title="d.dmd_situacao !== 'RASCUNHO' ? 'Demanda fechada — reabra antes de excluir' : 'Remover'"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                        @click="remove(d)"
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
                        <template v-if="demandas.total > 0">{{ demandas.from }}–{{ demandas.to }} de {{ demandas.total }} registro{{ demandas.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="demandas.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in demandas.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
