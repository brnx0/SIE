<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { EscolaResumo, Turma } from '@/types/turma';
import { TURNOS } from '@/types/turma';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    turmas: Paginated<Turma>;
    filters: {
        esc_id?: number | null;
        anl_id?: number | null;
        seg_id?: number | null;
        turno?: string;
        situacao?: string;
        per_page?: number;
    };
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Turmas', href: '/turmas' }];

const fEscId    = ref<number | ''>(props.filters.esc_id ?? '');
const fAnlId    = ref<number | ''>(props.filters.anl_id ?? '');
const fTurno    = ref(props.filters.turno ?? '');
const fSituacao = ref(props.filters.situacao ?? '');
const perPage   = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const params: Record<string, string | number> = { per_page: perPage.value };
        if (fEscId.value)    params.esc_id   = fEscId.value;
        if (fAnlId.value)    params.anl_id   = fAnlId.value;
        if (fTurno.value)    params.turno    = fTurno.value;
        if (fSituacao.value) params.situacao = fSituacao.value;
        if (!resetPage)      params.page     = props.turmas.current_page;
        router.get('/turmas', params, { preserveState: true, replace: true });
    }, 100);
};

watch([fEscId, fAnlId, fTurno, fSituacao], () => apply(true));
watch(perPage, () => apply(true));

const remove = (t: Turma) => {
    if (!confirm(`Remover turma "${t.tur_nome}" (${t.serie?.ser_nome ?? ''} — ${t.turno_label ?? t.tur_turno})?`)) return;
    router.delete(`/turmas/${t.tur_id}`, { preserveScroll: true });
};

const turnoLabel = (t: string) => TURNOS[t as keyof typeof TURNOS] ?? t;

const exportFilters = () => ({
    esc_id:   fEscId.value || undefined,
    anl_id:   fAnlId.value || undefined,
    turno:    fTurno.value || undefined,
    situacao: fSituacao.value || undefined,
});
</script>

<template>
    <Head title="Turmas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Turmas</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie as turmas da rede de ensino.</p>
                </div>
                <Link href="/turmas/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                        <Plus class="mr-2 size-4" /> Nova Turma
                    </Button>
                </Link>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-3">
                <template v-if="isAdmin">
                    <select v-model="fEscId" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                        <option value="">Todas as escolas</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </template>
                <template v-else-if="userEscola">
                    <span class="flex h-9 items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground">{{ userEscola.esc_nome }}</span>
                </template>
                <select v-model="fAnlId" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todos os anos</option>
                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">{{ anl.anl_ano }}</option>
                </select>
                <select v-model="fTurno" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todos os turnos</option>
                    <option v-for="(label, val) in TURNOS" :key="val" :value="val">{{ label }}</option>
                </select>
                <select v-model="fSituacao" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todas as situações</option>
                    <option value="ABERTA">Aberta</option>
                    <option value="ENCERRADA">Encerrada</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/turmas/export" :filters="exportFilters()" />
                </div>
            </div>

            <!-- Tabela -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Turma</th>
                            <th class="px-4 py-3">Série</th>
                            <th class="px-4 py-3">Escola</th>
                            <th class="px-4 py-3">Ano</th>
                            <th class="px-4 py-3 text-center">Sem.</th>
                            <th class="px-4 py-3">Segmento</th>
                            <th class="px-4 py-3">Turno</th>
                            <th class="px-4 py-3 text-center">Cap.</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="turmas.data.length === 0">
                            <td colspan="10" class="px-4 py-12 text-center text-muted-foreground">Nenhuma turma encontrada.</td>
                        </tr>
                        <tr v-for="t in turmas.data" :key="t.tur_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <BookOpen class="size-4" />
                                    </div>
                                    <span class="font-semibold">{{ t.tur_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ t.serie?.ser_nome ?? '—' }}</td>
                            <td class="max-w-[220px] truncate px-4 py-3 text-muted-foreground">{{ t.escola?.esc_nome ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ t.anoLetivo?.anl_ano ?? '—' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ t.tur_semestre ?? '—' }}º</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ t.segmento?.seg_nome_reduzido ?? '—' }}</td>
                            <td class="px-4 py-3">{{ turnoLabel(t.tur_turno) }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ t.tur_capacidade ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', t.tur_situacao === 'ABERTA' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ t.tur_situacao }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/turmas/${t.tur_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(t)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="turmas.total > 0">{{ turmas.from }}–{{ turmas.to }} de {{ turmas.total }} registro{{ turmas.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="turmas.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in turmas.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
