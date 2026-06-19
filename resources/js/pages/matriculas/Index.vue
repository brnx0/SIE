<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import MatriculaModal from '@/components/matricula/MatriculaModal.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { AlunoResumo, TurmaMatricula } from '@/types/matricula';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Loader2, RefreshCw, Search, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import Switch from '@/components/common/Switch.vue';

const props = defineProps<{
    anosLetivos: Pick<AnoLetivo, 'anl_id' | 'anl_ano' | 'anl_dt_corte'>[];
    escolas: { esc_id: number; esc_nome: string; esc_cd_escola: string }[];
    parametros: { par_fl_validar_idade_serie: boolean } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Matrículas', href: '/matriculas' }];

// ── Filtros ──────────────────────────────────────────────────────────────────
const fAnlId    = ref<number | ''>(props.anosLetivos[0]?.anl_id ?? '');
const fEscId    = ref<number | ''>('');
const fSegId    = ref<number | ''>('');
const fSerId    = ref<number | ''>('');
const fSemestre = ref<number | ''>('');

// ── Segmentos (cascata escola) ────────────────────────────────────────────────
interface SegmentoOpt { esg_id: number; seg_id: number; seg_nome: string }
const segmentos        = ref<SegmentoOpt[]>([]);
const loadingSegmentos = ref(false);

const carregarSegmentos = async () => {
    segmentos.value  = [];
    fSegId.value     = '';
    fSerId.value     = '';
    series.value     = [];
    turmas.value     = [];
    if (!fEscId.value || !fAnlId.value) return;
    loadingSegmentos.value = true;
    try {
        const r = await fetch(`/api/segmentos/by-escola?esc_id=${fEscId.value}&anl_id=${fAnlId.value}`);
        segmentos.value = await r.json();
    } finally {
        loadingSegmentos.value = false;
    }
};

watch([fEscId, fAnlId], carregarSegmentos);

// ── Séries (cascata segmento) ─────────────────────────────────────────────────
interface SerieOpt { ser_id: number; ser_nome: string; ser_idade: number }
const series        = ref<SerieOpt[]>([]);
const loadingSeries = ref(false);

const carregarSeries = async () => {
    series.value = [];
    fSerId.value = '';
    turmas.value = [];
    if (!fSegId.value || !fEscId.value || !fAnlId.value) return;
    loadingSeries.value = true;
    try {
        const r = await fetch(`/api/series/by-turmas-abertas?esc_id=${fEscId.value}&anl_id=${fAnlId.value}&seg_id=${fSegId.value}`);
        series.value = await r.json();
    } finally {
        loadingSeries.value = false;
    }
};

watch([fSegId, fEscId, fAnlId], carregarSeries);

// ── Turmas ────────────────────────────────────────────────────────────────────
const turmas        = ref<TurmaMatricula[]>([]);
const loadingTurmas = ref(false);

const podeBuscarTurmas = computed(() => !!fAnlId.value && !!fEscId.value && !!fSegId.value && !!fSerId.value && !!fSemestre.value);

const carregarTurmas = async () => {
    turmas.value = [];
    if (!podeBuscarTurmas.value) return;
    loadingTurmas.value = true;
    try {
        const params = new URLSearchParams();
        params.set('anl_id', String(fAnlId.value));
        params.set('esc_id', String(fEscId.value));
        if (fSegId.value) params.set('seg_id', String(fSegId.value));
        if (fSerId.value) params.set('ser_id', String(fSerId.value));
        if (fSemestre.value) params.set('semestre', String(fSemestre.value));
        const r = await fetch(`/api/matriculas/turmas?${params}`);
        turmas.value = await r.json();
    } finally {
        loadingTurmas.value = false;
    }
};

watch([fAnlId, fEscId, fSegId, fSerId, fSemestre], carregarTurmas);

// ── Aluno lookup ──────────────────────────────────────────────────────────────
const alunoNaoCadastrado = ref(false);
const alunoQuery         = ref('');
const alunoResultados    = ref<AlunoResumo[]>([]);
const alunoSelecionado   = ref<AlunoResumo | null>(null);
const loadingAlunos      = ref(false);
const showAlunoDrop      = ref(false);
const alunoDefinidoParaMatricula = computed(() => !!alunoSelecionado.value || alunoNaoCadastrado.value);

let alunoTimer: ReturnType<typeof setTimeout> | null = null;

watch(alunoQuery, (v) => {
    if (alunoTimer) clearTimeout(alunoTimer);
    if (!v || v.length < 2) { alunoResultados.value = []; showAlunoDrop.value = false; return; }
    alunoTimer = setTimeout(async () => {
        loadingAlunos.value = true;
        try {
            const r = await fetch(`/api/alunos/search?q=${encodeURIComponent(v)}`);
            alunoResultados.value = await r.json();
            showAlunoDrop.value = true;
        } finally {
            loadingAlunos.value = false;
        }
    }, 300);
});

watch(alunoNaoCadastrado, (v) => {
    erroIdade.value = null;
    if (v) {
        alunoSelecionado.value = null;
        alunoQuery.value = '';
        alunoResultados.value = [];
        showAlunoDrop.value = false;
    }
});

const selecionarAluno = (a: AlunoResumo) => {
    alunoSelecionado.value = a;
    erroIdade.value = null;
    alunoQuery.value = '';
    showAlunoDrop.value = false;
    alunoResultados.value = [];
};

const limparAluno = () => {
    alunoSelecionado.value = null;
    alunoQuery.value = '';
};

const ocultarAlunoDropDepoisDoBlur = () => {
    window.setTimeout(() => {
        showAlunoDrop.value = false;
    }, 200);
};

// ── Validação de idade ────────────────────────────────────────────────────────
const erroIdade = ref<string | null>(null);

const parseDateLocal = (date: string): Date => {
    const [year, month, day] = date.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatarData = (date: string): string =>
    parseDateLocal(date).toLocaleDateString('pt-BR');

const calcularIdadeCompleta = (dtNascimento: string, dtCorte: string): number => {
    const nascimento = parseDateLocal(dtNascimento);
    const corte = parseDateLocal(dtCorte);
    let idade = corte.getFullYear() - nascimento.getFullYear();

    const aniversarioNoAnoCorte = new Date(
        corte.getFullYear(),
        nascimento.getMonth(),
        nascimento.getDate(),
    );

    if (corte < aniversarioNoAnoCorte) idade--;

    return idade;
};

const validarIdadeParaTurma = (turma: TurmaMatricula): boolean => {
    erroIdade.value = null;

    // série sem requisito de idade → sem restrição
    if (turma.serie?.ser_idade == null || !turma.ano_letivo?.anl_dt_corte) return true;
    // aluno não cadastrado → valida no modal (data de nascimento ainda não preenchida)
    if (alunoNaoCadastrado.value) return true;
    if (!alunoSelecionado.value?.aln_dt_nascimento) return true;

    const idade = calcularIdadeCompleta(
        alunoSelecionado.value.aln_dt_nascimento,
        turma.ano_letivo.anl_dt_corte,
    );

    if (idade < turma.serie.ser_idade) {
        erroIdade.value = `A idade do aluno na data de corte (${formatarData(turma.ano_letivo.anl_dt_corte)}) é ${idade} ano${idade !== 1 ? 's' : ''}. A série "${turma.serie.ser_nome}" exige ter completado ${turma.serie.ser_idade} ano${turma.serie.ser_idade !== 1 ? 's' : ''} até essa data. Matrícula não permitida.`;
        return false;
    }
    return true;
};

// ── Modal de matrícula ────────────────────────────────────────────────────────
const modalAberto  = ref(false);
const turmaAlvo    = ref<TurmaMatricula | null>(null);

const verificandoMatricula = ref(false);

const abrirMatricula = async (turma: TurmaMatricula) => {
    erroIdade.value = null;

    if (!alunoDefinidoParaMatricula.value) {
        erroIdade.value = 'Selecione um aluno ou marque "Aluno não cadastrado na base" antes de matricular.';
        return;
    }

    if (!validarIdadeParaTurma(turma)) return;

    if (alunoSelecionado.value) {
        verificandoMatricula.value = true;
        try {
            const params = new URLSearchParams({
                aln_id: String(alunoSelecionado.value.aln_id),
                anl_id: String(turma.ano_letivo?.anl_id ?? ''),
                esc_id: String(turma.escola?.esc_id ?? ''),
            });
            const r = await fetch(`/api/matriculas/verificar?${params}`);
            const { matriculado, mesma_escola } = await r.json();
            if (matriculado) {
                erroIdade.value = mesma_escola
                    ? `O aluno já possui matrícula ativa nesta escola neste ano letivo.`
                    : `O aluno já possui matrícula ativa em outra escola neste ano letivo.`;
                return;
            }
        } catch {
            // falha silenciosa — deixa o backend rejeitar se necessário
        } finally {
            verificandoMatricula.value = false;
        }
    }

    turmaAlvo.value = turma;
    modalAberto.value = true;
};

const onMatriculaSalva = () => {
    modalAberto.value = false;
    carregarTurmas();
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-indigo-500';

const labelSituacao = (s: string) => ({
    ABERTA:   { text: 'Aberta',   cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
    FECHADA:  { text: 'Fechada',  cls: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' },
    SUSPENSA: { text: 'Suspensa', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' },
}[s] ?? { text: s, cls: 'bg-slate-100 text-slate-500' });

const formatarNomeAluno = (a: AlunoResumo) => {
    const mat = a.aln_nr_matricula ? `Mat. ${a.aln_nr_matricula} — ` : '';
    const nasc = a.aln_dt_nascimento
        ? ` · Nasc. ${new Date(a.aln_dt_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}`
        : '';
    return `${mat}${a.aln_nome}${nasc}`;
};
</script>

<template>
    <Head title="Matrículas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">

            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Matrículas</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Localize uma turma e realize a matrícula do aluno.</p>
            </div>

            <!-- Filtros -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                    <!-- Ano Letivo -->
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Ano Letivo</FormLabel>
                        <select v-model.number="fAnlId" :class="selectClass">
                            <option value="">Selecione...</option>
                            <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">
                                {{ anl.anl_ano }}
                            </option>
                        </select>
                    </div>

                    <!-- Escola (obrigatória) -->
                    <div class="grid gap-1.5 lg:col-span-2">
                        <FormLabel :required="true">Escola</FormLabel>
                        <select v-model.number="fEscId" :class="selectClass">
                            <option value="">Selecione uma escola...</option>
                            <option v-for="esc in escolas" :key="esc.esc_id" :value="esc.esc_id">
                                {{ esc.esc_cd_escola ? `${esc.esc_cd_escola} — ` : '' }}{{ esc.esc_nome }}
                            </option>
                        </select>
                    </div>

                    <!-- Segmento (obrigatório) -->
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Segmento</FormLabel>
                        <select
                            v-model.number="fSegId"
                            :disabled="!fEscId || loadingSegmentos"
                            :class="selectClass"
                        >
                            <option value="" disabled>
                                {{ !fEscId ? 'Selecione a escola primeiro' : loadingSegmentos ? 'Carregando...' : 'Selecione o segmento...' }}
                            </option>
                            <option v-for="s in segmentos" :key="s.seg_id" :value="s.seg_id">
                                {{ s.seg_nome }}
                            </option>
                        </select>
                    </div>

                    <!-- Série -->
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Série / Ano Escolar</FormLabel>
                        <select
                            v-model.number="fSerId"
                            :disabled="!fSegId || loadingSeries"
                            :class="selectClass"
                        >
                            <option value="" disabled>
                                {{ !fSegId ? 'Selecione o segmento primeiro' : loadingSeries ? 'Carregando...' : 'Selecione a série...' }}
                            </option>
                            <option v-for="s in series" :key="s.ser_id" :value="s.ser_id">
                                {{ s.ser_nome }}
                            </option>
                        </select>
                    </div>

                    <!-- Semestre -->
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Semestre</FormLabel>
                        <select v-model.number="fSemestre" :class="selectClass">
                            <option value="" disabled>Selecione...</option>
                            <option :value="1">1º Semestre</option>
                            <option :value="2">2º Semestre</option>
                        </select>
                    </div>

                    <!-- Aluno lookup -->
                    <div class="grid gap-1.5 sm:col-span-2 lg:col-span-2">
                        <FormLabel>Aluno a ser matriculado</FormLabel>

                        <!-- Aluno selecionado -->
                        <div v-if="alunoSelecionado" class="flex items-center gap-2 rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm dark:border-indigo-700 dark:bg-indigo-900/20">
                            <CheckCircle2 class="size-4 shrink-0 text-indigo-600" />
                            <span class="flex-1 font-medium text-indigo-800 dark:text-indigo-200">
                                {{ formatarNomeAluno(alunoSelecionado) }}
                            </span>
                            <button type="button" @click="limparAluno" class="text-indigo-400 hover:text-indigo-700">
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Campo de busca -->
                        <div v-else-if="!alunoNaoCadastrado" class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="alunoQuery"
                                type="text"
                                placeholder="Buscar por nome, matrícula ou CPF..."
                                class="flex h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                @blur="ocultarAlunoDropDepoisDoBlur"
                                @focus="showAlunoDrop = alunoResultados.length > 0"
                            />
                            <Loader2 v-if="loadingAlunos" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />

                            <!-- Dropdown resultados -->
                            <div
                                v-if="showAlunoDrop && alunoResultados.length"
                                class="absolute z-20 mt-1 w-full rounded-md border bg-popover shadow-lg"
                            >
                                <button
                                    v-for="a in alunoResultados"
                                    :key="a.aln_id"
                                    type="button"
                                    class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-muted focus:bg-muted focus:outline-none"
                                    @mousedown.prevent="selecionarAluno(a)"
                                >
                                    <span class="font-medium">{{ a.aln_nome }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        <template v-if="a.aln_nr_matricula">Mat. {{ a.aln_nr_matricula }} · </template>
                                        <template v-if="a.aln_dt_nascimento">
                                            Nasc. {{ new Date(a.aln_dt_nascimento + 'T00:00:00').toLocaleDateString('pt-BR') }}
                                        </template>
                                    </span>
                                </button>
                            </div>
                            <p v-else-if="showAlunoDrop && !loadingAlunos" class="absolute z-20 mt-1 w-full rounded-md border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-lg">
                                Nenhum aluno encontrado.
                            </p>
                        </div>

                        <!-- Estado: não cadastrado -->
                        <div v-else class="flex h-10 items-center rounded-md border border-dashed border-amber-400 bg-amber-50 px-3 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                            <UserPlus class="mr-2 size-4 shrink-0" />
                            Aluno não cadastrado — dados serão informados na matrícula
                        </div>
                    </div>

                    <!-- Checkbox não cadastrado -->
                    <div class="flex items-center gap-2 self-end pb-1">
                        <Switch id="nao_cadastrado" v-model="alunoNaoCadastrado" />
                        <label for="nao_cadastrado" class="cursor-pointer text-sm">
                            Aluno não cadastrado na base
                        </label>
                    </div>
                </div>
            </div>

            <!-- Erro de idade -->
            <div
                v-if="erroIdade"
                class="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-700 dark:bg-rose-900/20 dark:text-rose-300"
            >
                <AlertCircle class="mt-0.5 size-4 shrink-0" />
                <span>{{ erroIdade }}</span>
                <button type="button" class="ml-auto shrink-0 text-rose-400 hover:text-rose-700" @click="erroIdade = null">
                    <X class="size-4" />
                </button>
            </div>

            <!-- Aviso: filtros obrigatórios -->
            <div
                v-if="!fEscId || !fSegId || !fSerId || !fSemestre"
                class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground shadow-sm"
            >
                Selecione um <strong>Ano Letivo</strong>, uma <strong>Escola</strong>, um <strong>Segmento</strong>, uma <strong>Série</strong> e o <strong>Semestre</strong> para visualizar as turmas disponíveis.
            </div>

            <!-- Tabela de turmas -->
            <template v-else>
                <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-2.5">
                        <span class="text-sm font-medium">Turmas disponíveis</span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-muted-foreground">
                                <template v-if="loadingTurmas">Carregando...</template>
                                <template v-else>{{ turmas.length }} turma{{ turmas.length !== 1 ? 's' : '' }}</template>
                            </span>
                            <Button type="button" size="sm" variant="outline" class="gap-1.5" :disabled="loadingTurmas" title="Atualizar turmas" @click="carregarTurmas">
                                <RefreshCw :class="['size-4', loadingTurmas && 'animate-spin']" />
                                Atualizar
                            </Button>
                        </div>
                    </div>

                    <div v-if="loadingTurmas" class="flex items-center justify-center py-12 text-muted-foreground">
                        <Loader2 class="mr-2 size-5 animate-spin" /> Carregando turmas...
                    </div>

                    <div v-else-if="turmas.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                        Nenhuma turma encontrada para os filtros selecionados.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full table-fixed text-sm">
                            <colgroup>
                                <col style="width:8%" />
                                <col style="width:9%" />
                                <col style="width:24%" />
                                <col style="width:7%" />
                                <col style="width:12%" />
                                <col style="width:10%" />
                                <col style="width:12%" />
                                <col style="width:18%" />
                            </colgroup>
                            <thead class="bg-indigo-600 text-white">
                                <tr>
                                    <th class="px-3 py-2.5 text-center font-semibold">Semestre</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Situação</th>
                                    <th class="px-3 py-2.5 font-semibold">Série / Ano Escolar</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Turma</th>
                                    <th class="px-3 py-2.5 font-semibold">Turno</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Vagas disp.</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Matriculados</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="(turma, idx) in turmas"
                                    :key="turma.tur_id"
                                    :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                                >
                                    <td class="px-3 py-2.5 text-center text-xs font-medium text-muted-foreground">
                                        {{ turma.tur_semestre }}º Sem.
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', labelSituacao(turma.tur_situacao).cls]">
                                            {{ labelSituacao(turma.tur_situacao).text }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 font-medium truncate">{{ turma.serie?.ser_nome ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-center font-semibold">{{ turma.tur_nome }}</td>
                                    <td class="px-3 py-2.5 capitalize text-muted-foreground truncate">{{ turma.tur_turno.toLowerCase() }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span
                                            v-if="turma.vagas_disponiveis !== null"
                                            :class="[
                                                'font-semibold tabular-nums',
                                                turma.vagas_disponiveis === 0 ? 'text-rose-600' : turma.vagas_disponiveis <= 3 ? 'text-amber-600' : 'text-emerald-600',
                                            ]"
                                        >
                                            {{ turma.vagas_disponiveis }}
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center tabular-nums text-muted-foreground">
                                        {{ turma.total_matriculados }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="turma.vagas_disponiveis === 0"
                                            :class="turma.vagas_disponiveis === 0
                                                ? 'opacity-50 cursor-not-allowed'
                                                : 'bg-indigo-600 hover:bg-indigo-700'"
                                            @click="abrirMatricula(turma)"
                                        >
                                            Matricular
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

        </div>
    </AppLayout>

    <MatriculaModal
        v-model:open="modalAberto"
        :turma="turmaAlvo"
        :aluno="alunoSelecionado"
        :aluno-nao-cadastrado="alunoNaoCadastrado"
        @saved="onMatriculaSalva"
    />
</template>
