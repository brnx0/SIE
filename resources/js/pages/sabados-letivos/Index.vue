<script setup lang="ts">
import EscolasExclusaoPicker from '@/components/sabado/EscolasExclusaoPicker.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface AnoLetivoResumo {
    anl_id: number;
    anl_ano: number;
    anl_fl_em_exercicio: boolean;
    anl_dt_inicio_ano: string;
    anl_dt_fim: string;
}

interface SabadoLetivo {
    sbl_id: number;
    sbl_dt_sabado: string; // 'YYYY-MM-DD'
    sbl_dia_semana: number; // 1–5
    escolas_excluidas: number[]; // esc_id das escolas SEM este sábado
}

interface EscolaResumo {
    esc_id: number;
    esc_nome: string;
}

const props = defineProps<{
    anosLetivos: AnoLetivoResumo[];
    anoLetivoId: number | null;
    sabados: SabadoLetivo[];
    sabadosDisponiveis: string[]; // datas 'YYYY-MM-DD' de sábados livres no período letivo
    escolas: EscolaResumo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sábados Letivos', href: '/sabados-letivos' },
];

const DIAS_SEMANA: Record<number, string> = {
    1: 'Segunda-feira',
    2: 'Terça-feira',
    3: 'Quarta-feira',
    4: 'Quinta-feira',
    5: 'Sexta-feira',
};

const MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

// ── Seleção de ano ──────────────────────────────────────────────────────────
const selectedAnoId = ref<number | ''>(props.anoLetivoId ?? '');

function changeAno() {
    if (!selectedAnoId.value) return;
    router.get('/sabados-letivos', { anl_id: selectedAnoId.value }, {
        preserveState: false,
    });
}

// ── Agrupamento por mês ─────────────────────────────────────────────────────
// Filtro de exibição: só meses com sábados cadastrados, ou todos os do período letivo.
const mostrarTodosMeses = ref(false);

// Índices de mês (0–11) do período letivo, p/ o modo "todos".
const mesesDoPeriodo = computed<number[]>(() => {
    const ano = selectedAno.value;
    if (!ano?.anl_dt_inicio_ano || !ano?.anl_dt_fim) return [];

    const [, mIni] = ano.anl_dt_inicio_ano.split('-').map(Number);
    const [, mFim] = ano.anl_dt_fim.split('-').map(Number);
    const out: number[] = [];
    for (let m = mIni; m <= mFim; m++) out.push(m - 1);
    return out;
});

const sabadosPorMes = computed(() => {
    const grouped = new Map<number, SabadoLetivo[]>();

    for (const s of props.sabados) {
        // Parse sem timezone: 'YYYY-MM-DD' → month index 0–11
        const [, m] = s.sbl_dt_sabado.split('-').map(Number);
        const mes = m - 1;
        if (!grouped.has(mes)) grouped.set(mes, []);
        grouped.get(mes)!.push(s);
    }

    // Modo "todos": semeia meses do período mesmo sem sábados.
    if (mostrarTodosMeses.value) {
        for (const mes of mesesDoPeriodo.value) {
            if (!grouped.has(mes)) grouped.set(mes, []);
        }
    }

    return [...grouped.entries()]
        .sort(([a], [b]) => a - b)
        .map(([mes, items]) => ({ mes, label: MESES[mes], items }));
});

// ── Formulário de adição ────────────────────────────────────────────────────
const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({
    sbl_anl_id:    props.anoLetivoId ?? null as number | null,
    sbl_dt_sabado: '',
    sbl_dia_semana: '' as number | '',
    escolas_excluidas: [] as number[],
});

watch(() => props.anoLetivoId, (v) => { form.sbl_anl_id = v ?? null; });

// Opções do dropdown: somente sábados livres do período letivo, com label dd/mm/aaaa.
const opcoesSabado = computed(() =>
    props.sabadosDisponiveis.map(iso => ({ value: iso, label: formatDate(iso) })),
);

function openForm() {
    form.sbl_dt_sabado = '';
    form.sbl_dia_semana = '';
    form.escolas_excluidas = [];
    errors.value = {};
    showForm.value = true;
}

function cancelForm() {
    showForm.value = false;
    errors.value = {};
}

function submit() {
    processing.value = true;
    errors.value = {};

    router.post('/sabados-letivos', { ...form }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.sbl_dt_sabado = '';
            form.sbl_dia_semana = '';
            form.escolas_excluidas = [];
            errors.value = {};
            showForm.value = false;
        },
        onError: (e) => { errors.value = e as Record<string, string>; },
        onFinish: () => { processing.value = false; },
    });
}

function remove(s: SabadoLetivo) {
    const dtFormatada = formatDate(s.sbl_dt_sabado);
    if (!confirm(`Remover sábado ${dtFormatada}?`)) return;
    router.delete(`/sabados-letivos/${s.sbl_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

// ── Edição de exceções por escola ───────────────────────────────────────────
const editId = ref<number | null>(null);
const editProcessing = ref(false);
const editForm = reactive({ sbl_dia_semana: 0 as number, escolas_excluidas: [] as number[] });

function startEdit(s: SabadoLetivo) {
    editId.value = s.sbl_id;
    editForm.sbl_dia_semana = s.sbl_dia_semana;
    editForm.escolas_excluidas = [...(s.escolas_excluidas ?? [])];
}
function cancelEdit() {
    editId.value = null;
}
function saveEdit(s: SabadoLetivo) {
    editProcessing.value = true;
    router.put(`/sabados-letivos/${s.sbl_id}`, { ...editForm }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editId.value = null; },
        onFinish: () => { editProcessing.value = false; },
    });
}

const totalEscolas = computed(() => props.escolas.length);

// ── Helpers de formatação ───────────────────────────────────────────────────
function formatDate(iso: string): string {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

const selectedAno = computed(() =>
    props.anosLetivos.find(a => a.anl_id === props.anoLetivoId),
);
</script>

<template>
    <Head title="Sábados Letivos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">

            <!-- Cabeçalho -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Sábados Letivos
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Defina os sábados com reposição e o dia da semana que cada um espelha.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <RefreshButton />
                    <Button
                        v-if="!showForm && anoLetivoId"
                        type="button"
                        class="bg-indigo-600 hover:bg-indigo-700"
                        @click="openForm"
                    >
                        <Plus class="mr-2 size-4" /> Adicionar Sábado
                    </Button>
                </div>
            </div>

            <!-- Seletor de Ano Letivo + filtro de meses -->
            <div class="flex flex-wrap items-center gap-3 rounded-lg border bg-card px-4 py-3 shadow-sm">
                <CalendarDays class="size-5 text-indigo-600 shrink-0" />
                <label for="sel_ano" class="text-sm font-medium whitespace-nowrap">Ano Letivo:</label>
                <select
                    id="sel_ano"
                    v-model="selectedAnoId"
                    class="rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    @change="changeAno"
                >
                    <option value="" disabled>Selecione...</option>
                    <option
                        v-for="a in anosLetivos"
                        :key="a.anl_id"
                        :value="a.anl_id"
                    >
                        {{ a.anl_ano }}{{ a.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                    </option>
                </select>

                <!-- Filtro: só cadastrados × todos os meses do período -->
                <div v-if="anoLetivoId" class="ml-auto inline-flex overflow-hidden rounded-md border border-input">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="!mostrarTodosMeses ? 'bg-indigo-600 text-white' : 'bg-background hover:bg-muted'"
                        @click="mostrarTodosMeses = false"
                    >
                        Meses cadastrados
                    </button>
                    <button
                        type="button"
                        class="border-l border-input px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="mostrarTodosMeses ? 'bg-indigo-600 text-white' : 'bg-background hover:bg-muted'"
                        @click="mostrarTodosMeses = true"
                    >
                        Todos os meses
                    </button>
                </div>
            </div>

            <!-- Formulário de adição -->
            <div
                v-if="showForm"
                class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold">Novo Sábado Letivo</h4>
                    <button
                        type="button"
                        class="rounded p-1 hover:bg-muted"
                        aria-label="Fechar"
                        @click="cancelForm"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Data do sábado: dropdown só com sábados livres do período -->
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Data (sábado)</FormLabel>
                        <select
                            v-model="form.sbl_dt_sabado"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="{ 'border-red-500': errors.sbl_dt_sabado }"
                            :disabled="opcoesSabado.length === 0"
                        >
                            <option value="" disabled>
                                {{ opcoesSabado.length ? 'Selecione um sábado...' : 'Nenhum sábado disponível' }}
                            </option>
                            <option v-for="o in opcoesSabado" :key="o.value" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                        <InputError :message="errors.sbl_dt_sabado" />
                    </div>

                    <!-- Dia espelhado -->
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Referente ao dia da semana</FormLabel>
                        <select
                            v-model="form.sbl_dia_semana"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="{ 'border-red-500': errors.sbl_dia_semana }"
                        >
                            <option value="" disabled>Selecione...</option>
                            <option v-for="(label, num) in DIAS_SEMANA" :key="num" :value="num">
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.sbl_dia_semana" />
                    </div>

                </div>

                <!-- Escolas que NÃO terão este sábado (opcional) -->
                <div class="mt-4 grid gap-1.5">
                    <FormLabel>Escolas que NÃO terão este sábado (opcional)</FormLabel>
                    <EscolasExclusaoPicker v-model="form.escolas_excluidas" :escolas="escolas" />
                    <p class="text-[11px] text-muted-foreground">Deixe vazio para valer em todas as escolas.</p>
                </div>

                <!-- Botões -->
                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="cancelForm">
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-indigo-600 hover:bg-indigo-700"
                        :disabled="processing || !form.sbl_dt_sabado || !form.sbl_dia_semana"
                        @click="submit"
                    >
                        <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                        Adicionar
                    </Button>
                </div>

                <InputError :message="errors.sbl_anl_id" class="mt-2" />
            </div>

            <!-- Sem ano selecionado -->
            <div
                v-if="!anoLetivoId"
                class="rounded-xl border bg-card px-6 py-10 text-center text-muted-foreground shadow-sm"
            >
                Selecione um ano letivo para visualizar os sábados.
            </div>

            <!-- Sem registros -->
            <div
                v-else-if="sabadosPorMes.length === 0"
                class="rounded-xl border bg-card px-6 py-10 text-center text-muted-foreground shadow-sm"
            >
                Nenhum sábado letivo cadastrado para {{ selectedAno?.anl_ano }}.
            </div>

            <!-- Agrupado por mês -->
            <template v-else>
                <div
                    v-for="grupo in sabadosPorMes"
                    :key="grupo.mes"
                    class="overflow-hidden rounded-xl border bg-card shadow-sm"
                >
                    <!-- Cabeçalho do mês -->
                    <div class="flex items-center gap-2 bg-indigo-600 px-4 py-2">
                        <CalendarDays class="size-4 text-indigo-100" />
                        <span class="text-sm font-semibold text-white">
                            {{ grupo.label }}
                            <span class="ml-2 rounded-full bg-indigo-500 px-2 py-0.5 text-xs font-normal">
                                {{ grupo.items.length }} {{ grupo.items.length === 1 ? 'sábado' : 'sábados' }}
                            </span>
                        </span>
                    </div>

                    <!-- Tabela do mês (table-fixed p/ alinhar colunas entre meses) -->
                    <table class="w-full table-fixed text-left text-sm">
                        <colgroup>
                            <col class="w-36" />
                            <col class="w-40" />
                            <col />
                            <col class="w-24" />
                        </colgroup>
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-2 font-medium text-slate-600 dark:text-slate-300">Data (Sábado)</th>
                                <th class="px-4 py-2 font-medium text-slate-600 dark:text-slate-300">Referente ao dia</th>
                                <th class="px-4 py-2 font-medium text-slate-600 dark:text-slate-300">Escolas</th>
                                <th class="px-4 py-2 text-right font-medium text-slate-600 dark:text-slate-300">
                                    Ação
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="grupo.items.length === 0">
                                <td colspan="4" class="px-4 py-3 text-center text-xs text-muted-foreground">
                                    Nenhum sábado cadastrado neste mês.
                                </td>
                            </tr>
                            <template v-for="(s, idx) in grupo.items" :key="s.sbl_id">
                                <tr :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                                    <td class="px-4 py-2 font-mono tabular-nums">
                                        {{ formatDate(s.sbl_dt_sabado) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-full bg-fuchsia-100 px-2.5 py-0.5 text-xs font-medium text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-300">
                                            {{ DIAS_SEMANA[s.sbl_dia_semana] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span v-if="!s.escolas_excluidas?.length" class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Todas as escolas
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                            :title="`${s.escolas_excluidas.length} escola(s) sem este sábado`"
                                        >
                                            {{ s.escolas_excluidas.length }} sem · {{ totalEscolas - s.escolas_excluidas.length }} com
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                                                aria-label="Editar exceções"
                                                @click="editId === s.sbl_id ? cancelEdit() : startEdit(s)"
                                            >
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                                aria-label="Remover"
                                                @click="remove(s)"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="editId === s.sbl_id" class="bg-indigo-50/40 dark:bg-indigo-950/20">
                                    <td colspan="4" class="px-4 py-3">
                                        <div class="grid gap-3 md:grid-cols-[14rem_1fr]">
                                            <div class="grid gap-1.5">
                                                <FormLabel>Referente ao dia</FormLabel>
                                                <select
                                                    v-model.number="editForm.sbl_dia_semana"
                                                    class="h-9 rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                >
                                                    <option v-for="(label, num) in DIAS_SEMANA" :key="num" :value="Number(num)">{{ label }}</option>
                                                </select>
                                            </div>
                                            <div class="grid gap-1.5">
                                                <FormLabel>Escolas que NÃO terão este sábado</FormLabel>
                                                <EscolasExclusaoPicker v-model="editForm.escolas_excluidas" :escolas="escolas" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex justify-end gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="cancelEdit">Cancelar</Button>
                                            <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="editProcessing" @click="saveEdit(s)">
                                                <Loader2 v-if="editProcessing" class="mr-2 size-4 animate-spin" /> Salvar
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Resumo total -->
                <p class="text-right text-xs text-muted-foreground">
                    Total: {{ sabados.length }} sábado{{ sabados.length !== 1 ? 's' : '' }} letivo{{ sabados.length !== 1 ? 's' : '' }}
                </p>
            </template>

        </div>
    </AppLayout>
</template>
