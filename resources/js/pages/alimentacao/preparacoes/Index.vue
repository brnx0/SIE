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
import type { PreparacaoLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChefHat, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    preparacoes: Paginated<PreparacaoLinha>;
    /** Escola do filtro atual, para o combobox mostrar o nome já selecionado. */
    escolaFiltro: { esc_id: number; esc_nome: string } | null;
    situacoes: string[];
    turnos: string[];
    filters: { esc_id: string; situacao: string; turno: string; dt_ini: string; dt_fim: string; per_page: number };
}>();

const escId    = ref<number | null>(props.filters.esc_id ? Number(props.filters.esc_id) : null);
const situacao = ref(props.filters.situacao ?? '');
const turno    = ref(props.filters.turno ?? '');
const dtIni    = ref(props.filters.dt_ini ?? '');
const dtFim    = ref(props.filters.dt_fim ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        esc_id: escId.value ?? '', situacao: situacao.value, turno: turno.value,
        dt_ini: dtIni.value, dt_fim: dtFim.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.preparacoes.current_page;
    router.get('/alimentacao/preparacoes', params, { preserveState: true, replace: true });
};
watch([escId, situacao, turno, dtIni, dtFim, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Controle Diário da Preparação', href: '/alimentacao/preparacoes' }];

const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const CORES: Record<string, string> = {
    RASCUNHO:   'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    CONFIRMADA: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    CANCELADA:  'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const remove = (p: PreparacaoLinha) => {
    if (!confirm(`Remover a preparação de ${dataBr(p.prd_dt)} (${p.prd_refeicao})?`)) return;
    router.delete(`/alimentacao/preparacoes/${p.prd_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Controle Diário da Preparação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Controle Diário da Preparação</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        O que a escola preparou por dia, turno e refeição. Confirmar baixa os itens do estoque da unidade.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/preparacoes/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Nova preparação
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Limpar o combobox tira o filtro: volta a mostrar todas as escolas. -->
                <div class="w-64">
                    <EscolaCombobox v-model="escId" :initial="escolaFiltro" placeholder="Todas as escolas" />
                </div>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                </select>
                <select v-model="turno" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os turnos</option>
                    <option v-for="t in turnos" :key="t" :value="t">{{ t }}</option>
                </select>
                <Input v-model="dtIni" type="date" class="w-40" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <Input v-model="dtFim" type="date" class="w-40" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu
                        base-url="/alimentacao/preparacoes/export"
                        :filters="{ esc_id: escId, situacao, turno, dt_ini: dtIni, dt_fim: dtFim }"
                    />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Prato</th>
                            <th class="px-4 py-3">Turno / Refeição</th>
                            <th class="px-4 py-3 text-center">Porções</th>
                            <th class="px-4 py-3 text-center">Itens</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="preparacoes.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhuma preparação registrada.</td>
                        </tr>
                        <tr v-for="p in preparacoes.data" :key="p.prd_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(p.prd_dt) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <ChefHat class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ p.prato_nome }}</span>
                                        <span v-if="p.escola_nome" class="text-xs text-muted-foreground">{{ p.escola_nome }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ p.prd_turno }} · {{ p.prd_refeicao }}</td>
                            <td class="px-4 py-3 text-center font-medium tabular-nums">{{ p.prd_qtd_servida }}</td>
                            <td class="px-4 py-3 text-center text-muted-foreground">{{ p.itens_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[p.prd_situacao]]">
                                    {{ p.prd_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/preparacoes/${p.prd_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button
                                        v-if="!isReadOnly"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="p.prd_situacao !== 'RASCUNHO'"
                                        :title="p.prd_situacao !== 'RASCUNHO' ? 'Preparação confirmada já baixou estoque' : 'Remover'"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                        @click="remove(p)"
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
                        <template v-if="preparacoes.total > 0">{{ preparacoes.from }}–{{ preparacoes.to }} de {{ preparacoes.total }} registro{{ preparacoes.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="preparacoes.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in preparacoes.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
