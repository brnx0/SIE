<script setup lang="ts">
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import PdfPreviewModal from '@/components/common/PdfPreviewModal.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import RevisarPlanoModal from '@/components/coordenador/RevisarPlanoModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { PlanoStatus } from '@/types/diario';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, FileText, Printer, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface PlanoListaItem {
    dpa_id: number;
    dpa_tema: string;
    dpa_dt_inicio: string;
    dpa_dt_fim: string;
    dpa_status: PlanoStatus;
    turma?: {
        tur_id: number;
        tur_nome: string;
        serie?: { ser_id: number; ser_nome: string; segmento?: { seg_nome_reduzido: string } };
    };
    disciplina?: { dis_id: number; dis_nome: string };
    unidade?: { uni_id: number; uni_numero: number; uni_tipo: string };
    funcionario?: { fun_id: number; fun_nome: string };
}

const props = defineProps<{
    planos: Paginated<PlanoListaItem>;
    filters: {
        anl_id: number | null;
        esc_id: number | string;
        seg_id: number | string;
        ser_id: number | string;
        tur_id: number | string;
        dis_id: number | string;
        status: string;
        search: string;
        per_page: number;
    };
    anoVigenteId: number | null;
    statuses: PlanoStatus[];
}>();

const anlId = ref<number | null>(props.filters.anl_id ?? props.anoVigenteId);
const escId = ref<number | null>(props.filters.esc_id ? Number(props.filters.esc_id) : null);
const segId = ref<number | null>(props.filters.seg_id ? Number(props.filters.seg_id) : null);
const serId = ref<number | null>(props.filters.ser_id ? Number(props.filters.ser_id) : null);
const turId = ref<number | null>(props.filters.tur_id ? Number(props.filters.tur_id) : null);
const status = ref<string>(props.filters.status ?? '');
const search = ref<string>(props.filters.search ?? '');
const perPage = ref<number>(props.filters.per_page ?? 10);

const anos = ref<{ anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }[]>([]);
const escolas = ref<{ esc_id: number; esc_nome: string }[]>([]);
const segmentos = ref<{ seg_id: number; seg_nome_reduzido: string }[]>([]);
const series = ref<{ ser_id: number; ser_nome: string }[]>([]);
const turmas = ref<{ tur_id: number; tur_nome: string }[]>([]);

const fetchJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? r.json() : [];
};

const fetchAnos = async () => {
    anos.value = await fetchJson('/api/coordenador/planos-lookup/anos');
};
const fetchEscolas = async () => {
    if (!anlId.value) { escolas.value = []; return; }
    escolas.value = await fetchJson(`/api/coordenador/planos-lookup/escolas?anl_id=${anlId.value}`);
};
const fetchSegmentos = async () => {
    if (!escId.value || !anlId.value) { segmentos.value = []; return; }
    segmentos.value = await fetchJson(`/api/coordenador/planos-lookup/segmentos?esc_id=${escId.value}&anl_id=${anlId.value}`);
};
const fetchSeries = async () => {
    if (!segId.value || !escId.value || !anlId.value) { series.value = []; return; }
    series.value = await fetchJson(`/api/coordenador/planos-lookup/series?seg_id=${segId.value}&esc_id=${escId.value}&anl_id=${anlId.value}`);
};
const fetchTurmas = async () => {
    if (!escId.value || !anlId.value || !serId.value) { turmas.value = []; return; }
    turmas.value = await fetchJson(`/api/coordenador/planos-lookup/turmas?esc_id=${escId.value}&anl_id=${anlId.value}&ser_id=${serId.value}`);
};

const apply = (resetPage = true) => {
    const params: Record<string, string | number> = {
        anl_id: anlId.value ?? '',
        esc_id: escId.value ?? '',
        seg_id: segId.value ?? '',
        ser_id: serId.value ?? '',
        tur_id: turId.value ?? '',
        status: status.value,
        search: search.value,
        per_page: perPage.value,
    };
    if (!resetPage) params.page = props.planos.current_page;
    router.get('/coordenador/planos', params, { preserveState: true, replace: true });
};

let timer: ReturnType<typeof setTimeout> | null = null;
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([status, perPage], () => apply(true));

watch(anlId, async () => {
    escId.value = null; segId.value = null; serId.value = null; turId.value = null;
    segmentos.value = []; series.value = []; turmas.value = [];
    await fetchEscolas();
    apply(true);
});
watch(escId, async () => {
    segId.value = null; serId.value = null; turId.value = null;
    series.value = []; turmas.value = [];
    await fetchSegmentos();
    apply(true);
});
watch(segId, async () => {
    serId.value = null; turId.value = null;
    turmas.value = [];
    await fetchSeries();
    apply(true);
});
watch(serId, async () => {
    turId.value = null;
    await fetchTurmas();
    apply(true);
});
watch(turId, () => apply(true));

onMounted(async () => {
    await fetchAnos();
    if (anlId.value) await fetchEscolas();
    if (escId.value) await fetchSegmentos();
    if (segId.value) await fetchSeries();
    if (serId.value) await fetchTurmas();
});

const itemsAno = computed(() => anos.value.map((a) => ({
    id: a.anl_id,
    label: a.anl_fl_em_exercicio ? `${a.anl_ano} (vigente)` : String(a.anl_ano),
})));
const itemsEscola = computed(() => escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsSegmento = computed(() => segmentos.value.map((s) => ({ id: s.seg_id, label: s.seg_nome_reduzido })));
const itemsSerie = computed(() => series.value.map((s) => ({ id: s.ser_id, label: s.ser_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: t.tur_nome })));

const statusBadge = (s: PlanoStatus) => ({
    pendente: 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
    aprovado: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300',
    reprovado: 'bg-rose-100 text-rose-800 ring-1 ring-rose-300',
    correcao: 'bg-sky-100 text-sky-800 ring-1 ring-sky-300',
}[s]);

const statusLabel = (s: PlanoStatus) => ({
    pendente: 'Pendente',
    aprovado: 'Aprovado',
    reprovado: 'Reprovado',
    correcao: 'Em correção',
}[s]);

const fmtDate = (d: string) => {
    if (!d) return '—';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

const previewUrl = ref<string | null>(null);
const previewFilename = ref<string>('plano.pdf');
const imprimir = (id: number) => {
    previewUrl.value = `/coordenador/planos/${id}/pdf`;
    previewFilename.value = `plano_${id}.pdf`;
};

const planoSelecionado = ref<number | null>(null);
const abrirRevisao = (id: number) => { planoSelecionado.value = id; };
const fecharRevisao = () => { planoSelecionado.value = null; };
const aposRevisar = () => {
    fecharRevisao();
    router.reload({ only: ['planos', 'flash'], preserveScroll: true, preserveState: true });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Validação de Planos', href: '/coordenador/planos' },
];
</script>

<template>
    <Head title="Validação de Planos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        <ClipboardCheck class="size-6 text-indigo-600" />
                        Validação de Planos de Aula — Turmas Regulares
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Revise os planos pendentes das turmas regulares dos professores das suas escolas.
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                    <div class="md:col-span-2">
                        <Label>Ano Letivo</Label>
                        <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Ano..." />
                    </div>
                    <div class="md:col-span-3">
                        <Label>Escola</Label>
                        <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Escola..." :disabled="!anlId" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Segmento</Label>
                        <LocalCombobox v-model="segId" :items="itemsSegmento" placeholder="Segmento..." :disabled="!escId" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Série</Label>
                        <LocalCombobox v-model="serId" :items="itemsSerie" placeholder="Série..." :disabled="!segId" />
                    </div>
                    <div class="md:col-span-3">
                        <Label>Turma</Label>
                        <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Turma..." :disabled="!serId" />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Buscar por tema ou professor..." class="pl-9" />
                    </div>
                    <select v-model="status" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todos os status</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
                    </select>
                    <div class="ml-auto flex items-center gap-3">
                        <RefreshButton />
                        <PerPageSelect v-model="perPage" />
                    </div>
                </div>
            </section>

            <!-- Tabela -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3">Série / Segmento</th>
                            <th class="px-4 py-3">Turma</th>
                            <th class="px-4 py-3">Disciplina</th>
                            <th class="px-4 py-3">Professor</th>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3">Tema</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!escId">
                            <td colspan="9" class="px-4 py-12 text-center text-muted-foreground">
                                Selecione um ano e uma escola para listar os planos.
                            </td>
                        </tr>
                        <tr v-else-if="planos.data.length === 0">
                            <td colspan="9" class="px-4 py-12 text-center text-muted-foreground">
                                Nenhum plano encontrado.
                            </td>
                        </tr>
                        <tr v-for="p in planos.data" :key="p.dpa_id"
                            class="cursor-pointer transition-colors hover:bg-muted/40"
                            @click="abrirRevisao(p.dpa_id)">
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', statusBadge(p.dpa_status)]">
                                    {{ statusLabel(p.dpa_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ p.turma?.serie?.ser_nome ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ p.turma?.serie?.segmento?.seg_nome_reduzido ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ p.turma?.tur_nome ?? '—' }}</td>
                            <td class="px-4 py-3">{{ p.disciplina?.dis_nome ?? '—' }}</td>
                            <td class="px-4 py-3">{{ p.funcionario?.fun_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.unidade?.uni_numero }}º {{ (p.unidade?.uni_tipo || '').toLowerCase() }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">
                                {{ fmtDate(p.dpa_dt_inicio) }} – {{ fmtDate(p.dpa_dt_fim) }}
                            </td>
                            <td class="px-4 py-3">{{ p.dpa_tema }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button variant="ghost" size="sm" title="Imprimir" @click.stop="imprimir(p.dpa_id)">
                                        <Printer class="size-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" title="Revisar" @click.stop="abrirRevisao(p.dpa_id)">
                                        <FileText class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="planos.total > 0">{{ planos.from }}–{{ planos.to }} de {{ planos.total }} registro{{ planos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="planos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in planos.links" :key="i" :href="link.url ?? '#'" v-html="link.label"
                            :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']"
                            preserve-scroll />
                    </div>
                </div>
            </div>
        </div>

        <RevisarPlanoModal v-if="planoSelecionado"
            :plano-id="planoSelecionado"
            @close="fecharRevisao"
            @saved="aposRevisar" />
        <PdfPreviewModal v-if="previewUrl" :url="previewUrl" :filename="previewFilename" title="Pré-visualização do Plano" @close="previewUrl = null" />
    </AppLayout>
</template>
