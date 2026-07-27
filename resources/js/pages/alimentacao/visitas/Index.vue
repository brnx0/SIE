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
import type { VisitaLinha } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    visitas: Paginated<VisitaLinha>;
    escolaFiltro: { esc_id: number; esc_nome: string } | null;
    tiposVisita: { tpv_id: number; tpv_nome: string }[];
    situacoes: string[];
    filters: { search: string; esc_id: string; tpv_id: string; situacao: string; dt_ini: string; dt_fim: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const escId    = ref<number | null>(props.filters.esc_id ? Number(props.filters.esc_id) : null);
const tpvId    = ref(props.filters.tpv_id ?? '');
const situacao = ref(props.filters.situacao ?? '');
const dtIni    = ref(props.filters.dt_ini ?? '');
const dtFim    = ref(props.filters.dt_fim ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value, esc_id: escId.value ?? '', tpv_id: tpvId.value,
        situacao: situacao.value, dt_ini: dtIni.value, dt_fim: dtFim.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.visitas.current_page;
    router.get('/alimentacao/visitas', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([escId, tpvId, situacao, dtIni, dtFim, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Visitas Técnicas', href: '/alimentacao/visitas' }];

const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const CORES: Record<string, string> = {
    PROGRAMADA: 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    REALIZADA:  'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    ATESTADA:   'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    CANCELADA:  'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const remove = (v: VisitaLinha) => {
    if (!confirm(`Remover a visita programada para ${dataBr(v.vis_dt_programada)}?`)) return;
    router.delete(`/alimentacao/visitas/${v.vis_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Visitas Técnicas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Visitas Técnicas</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Programação, execução do questionário e ateste das visitas às unidades escolares.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/visitas/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Programar visita
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por escola..." class="pl-9" />
                </div>
                <div class="w-56">
                    <EscolaCombobox v-model="escId" :initial="escolaFiltro" placeholder="Todas as escolas" />
                </div>
                <select v-model="tpvId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os tipos</option>
                    <option v-for="t in tiposVisita" :key="t.tpv_id" :value="String(t.tpv_id)">{{ t.tpv_nome }}</option>
                </select>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                </select>
                <Input v-model="dtIni" type="date" class="w-40" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <Input v-model="dtFim" type="date" class="w-40" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu
                        base-url="/alimentacao/visitas/export"
                        :filters="{ search, esc_id: escId ?? '', tpv_id: tpvId, situacao, dt_ini: dtIni, dt_fim: dtFim }"
                    />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Escola</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Programada</th>
                            <th class="px-4 py-3">Realizada</th>
                            <th class="px-4 py-3 text-center">Questionário</th>
                            <th class="px-4 py-3">Ateste</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="visitas.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">Nenhuma visita registrada.</td>
                        </tr>
                        <tr v-for="v in visitas.data" :key="v.vis_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <ClipboardCheck class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ v.escola_nome }}</span>
                                        <span v-if="v.tecnico_nome" class="text-xs text-muted-foreground">Téc.: {{ v.tecnico_nome }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ v.tipo_visita }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(v.vis_dt_programada) }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(v.vis_dt_realizada) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', v.respondidas === v.respostas_count && v.respostas_count > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']">
                                    {{ v.respondidas }}/{{ v.respostas_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div v-if="v.atestador_nome" class="flex flex-col">
                                    <span class="text-xs">{{ v.atestador_nome }}</span>
                                    <span class="text-xs tabular-nums">{{ dataBr(v.vis_dt_ateste) }}</span>
                                </div>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CORES[v.vis_situacao]]">
                                    {{ v.vis_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/visitas/${v.vis_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button
                                        v-if="!isReadOnly"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="v.vis_situacao !== 'PROGRAMADA'"
                                        :title="v.vis_situacao !== 'PROGRAMADA' ? 'Visita com histórico — cancele em vez de excluir' : 'Remover'"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                        @click="remove(v)"
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
                        <template v-if="visitas.total > 0">{{ visitas.from }}–{{ visitas.to }} de {{ visitas.total }} visita{{ visitas.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="visitas.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in visitas.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
