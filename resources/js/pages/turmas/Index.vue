<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { EscolaResumo, Turma } from '@/types/turma';
import { TURNOS } from '@/types/turma';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { usePermissao } from '@/composables/usePermissao';
import { useFiltrosListagem } from '@/composables/useFiltrosListagem';

const { isReadOnly } = usePermissao();

const props = defineProps<{
    turmas: Paginated<Turma>;
    filters: {
        esc_id?: number | null;
        anl_id?: number | null;
        seg_id?: number | null;
        turno?: string;
        situacao?: string;
        search?: string;
        per_page?: number;
    };
    anosLetivos: AnoLetivo[];
    escolas: EscolaResumo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Turmas', href: '/turmas' }];

// ── Filtros mantidos ao abrir um registro e voltar ─────────────────────────
// A listagem remonta sem query ao voltar do formulário (mesma aba no shell).
// Inicializa os refs do que foi salvo (senão, das props do servidor) e reaplica
// no onMounted quando chegou sem query — controles E dados restauram juntos.
type FiltrosTurmas = {
    esc_id: number | ''; anl_id: number | ''; turno: string;
    situacao: string; search: string; per_page: number; page: number;
};
const { salvar: salvarFiltros, restaurar: restaurarFiltros } = useFiltrosListagem<FiltrosTurmas>('turmas');
const salvos = restaurarFiltros();

// Backend devolve 'all' quando o usuário escolheu "Todos os anos" → mapeia p/ '' no select.
const anlDasProps = (props.filters.anl_id as unknown) === 'all' ? '' : (props.filters.anl_id ?? '');

const fEscId    = ref<number | ''>(salvos?.esc_id ?? props.filters.esc_id ?? '');
const fAnlId    = ref<number | ''>(salvos?.anl_id ?? anlDasProps);
const fTurno    = ref(salvos?.turno ?? props.filters.turno ?? '');
const fSituacao = ref(salvos?.situacao ?? props.filters.situacao ?? '');
const fSearch   = ref(salvos?.search ?? props.filters.search ?? '');
const perPage   = ref(salvos?.per_page ?? props.filters.per_page ?? 10);

// Guarda que silencia os watches de filtro enquanto restauramos os refs, para
// não disparar um apply extra (que zeraria a página) durante o restore.
let restaurando = false;

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (opts: { resetPage?: boolean; page?: number } = {}) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const pagina = opts.page ?? (opts.resetPage ? 1 : props.turmas.current_page);
        const params: Record<string, string | number> = { per_page: perPage.value };
        if (fEscId.value)    params.esc_id   = fEscId.value;
        // Sempre envia anl_id: 'all' = "Todos os anos" (senão backend força o ano mais recente).
        params.anl_id = fAnlId.value === '' ? 'all' : fAnlId.value;
        if (fTurno.value)    params.turno    = fTurno.value;
        if (fSituacao.value) params.situacao = fSituacao.value;
        if (fSearch.value.trim()) params.search = fSearch.value.trim();
        if (pagina && pagina > 1) params.page = pagina;

        // Snapshot para restaurar ao voltar de um registro.
        salvarFiltros({
            esc_id: fEscId.value, anl_id: fAnlId.value, turno: fTurno.value,
            situacao: fSituacao.value, search: fSearch.value, per_page: perPage.value, page: pagina,
        });

        router.get('/turmas', params, { preserveState: true, replace: true });
    }, 100);
};

watch([fEscId, fAnlId, fTurno, fSituacao, fSearch], () => { if (!restaurando) apply({ resetPage: true }); });
watch(perPage, () => { if (!restaurando) apply({ resetPage: true }); });

// Filtro que o SERVIDOR renderizou (normalizado igual ao snapshot).
const propEsc = () => (props.filters.esc_id as number | '' | null | undefined) ?? '';
const propAnl = () => ((props.filters.anl_id as unknown) === 'all' ? '' : ((props.filters.anl_id as number | null | undefined) ?? ''));

// Compara tolerante a tipo: o servidor devolve os filtros como STRING (query
// string), o snapshot guarda NÚMERO — sem coagir, "5" !== 5 e o mesmoFiltro daria
// sempre false, gerando loop infinito de apply → router.get → sincronizar.
const eq = (a: unknown, b: unknown) => String(a ?? '') === String(b ?? '');

/** O filtro salvo é o mesmo que o servidor está exibindo? */
const mesmoFiltro = (s: FiltrosTurmas): boolean =>
    eq(s.esc_id, propEsc())
    && eq(s.anl_id, propAnl())
    && eq(s.turno, props.filters.turno)
    && eq(s.situacao, props.filters.situacao)
    && eq(s.search, props.filters.search)
    && eq(s.per_page ?? 10, props.filters.per_page ?? 10);

// Sincroniza a listagem com o filtro salvo. Não olha a URL (o shell de abas
// restaura a URL da aba, então ela mente sobre o estado); compara os filtros
// que o servidor renderizou com o snapshot salvo. Se divergem (voltou do
// formulário / entrou pelo menu → servidor veio padrão), restaura.
const sincronizar = () => {
    const s = restaurarFiltros();
    if (!s) return;

    if (mesmoFiltro(s)) {
        // Servidor já reflete o filtro salvo: mantém só a página em dia (paginação).
        const pg = props.turmas.current_page ?? 1;
        if (pg !== s.page) salvarFiltros({ ...s, page: pg });
        return;
    }

    restaurando = true;
    fEscId.value = s.esc_id ?? '';
    fAnlId.value = s.anl_id ?? '';
    fTurno.value = s.turno ?? '';
    fSituacao.value = s.situacao ?? '';
    fSearch.value = s.search ?? '';
    perPage.value = s.per_page ?? 10;
    apply({ page: s.page });
    // Libera os watches só depois do flush, senão a mudança dos refs dispara apply.
    nextTick(() => { restaurando = false; });
};

onMounted(sincronizar);
// Cobre remontagem E reuso da instância pelo shell: em ambos as props mudam.
watch(() => [props.filters, props.turmas.current_page], sincronizar, { deep: true });

const remove = (t: Turma) => {
    if (!confirm(`Remover turma "${t.tur_nome}" (${t.serie?.ser_nome ?? ''} — ${turnoLabel(t.tur_turno)})?`)) return;
    router.delete(`/turmas/${t.tur_id}`, { preserveScroll: true });
};

const turnoLabel = (t: string) => TURNOS[t as keyof typeof TURNOS] ?? t;

const escolaItems = computed(() =>
    props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })),
);

const exportFilters = () => ({
    esc_id:   fEscId.value || undefined,
    anl_id:   fAnlId.value === '' ? 'all' : fAnlId.value,
    turno:    fTurno.value || undefined,
    situacao: fSituacao.value || undefined,
    search:   fSearch.value.trim() || undefined,
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
                <Link v-if="!isReadOnly" href="/turmas/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                        <Plus class="mr-2 size-4" /> Nova Turma
                    </Button>
                </Link>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-3">
                <template v-if="isAdmin">
                    <div class="w-72">
                        <LocalCombobox
                            :model-value="fEscId === '' ? null : fEscId"
                            :items="escolaItems"
                            placeholder="Todas as escolas"
                            @update:model-value="(v) => (fEscId = v ?? '')"
                        />
                    </div>
                </template>
                <template v-else-if="userEscola">
                    <span class="flex h-9 items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground">{{ userEscola.esc_nome }}</span>
                </template>
                <input
                    v-model="fSearch"
                    type="text"
                    placeholder="Buscar por série ou nome da turma..."
                    class="h-9 w-64 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                />
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
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/turmas/export" :filters="exportFilters()" />
                </div>
            </div>

            <!-- Tabela -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Série</th>
                            <th class="px-4 py-3">Turma</th>
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
                            <td class="px-4 py-3">{{ t.serie?.ser_nome ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <BookOpen class="size-4" />
                                    </div>
                                    <span class="font-semibold">{{ t.tur_nome }}</span>
                                </div>
                            </td>
                            <td class="break-words px-4 py-3 text-muted-foreground">{{ t.escola?.esc_nome ?? '—' }}</td>
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
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(t)">
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
                        <Link v-for="(link, i) in turmas.links" :key="i" :href="link.url ?? '#'" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll><span v-html="link.label" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
