<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { CheckCircle2, ChevronDown, Circle, Loader2, RefreshCw, Search, TriangleAlert, Users } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    anlId: number;
    escId: number;
    turId: number;
    disId: number;
    uniId: number;
}>();

type Status = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

interface AlunoRow {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    descricao: string;
    dt_saida: string | null;
    bloqueado: boolean;
    _original: string;
    _status: Status;
    _timer: number | null;
}

const carregando = ref(true);
const erroCarregar = ref<string | null>(null);
const alunos = ref<AlunoRow[]>([]);
const modo = ref<'por_aluno' | 'por_disciplina'>('por_disciplina');
const periodoAberto = ref(true);
const tipoConfigurado = ref(true);
const busca = ref('');
const abertoId = ref<number | null>(null);
const textareaEl = ref<HTMLTextAreaElement | null>(null);

// CSRF p/ POST em rota web (cookie XSRF-TOKEN setado pelo Laravel).
const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};

const carregar = async () => {
    carregando.value = true;
    erroCarregar.value = null;
    abertoId.value = null;
    try {
        const url = new URL('/api/diario/avaliacao-descritiva/alunos', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        url.searchParams.set('dis_id', String(props.disId));
        url.searchParams.set('uni_id', String(props.uniId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) {
            let msg = 'Não foi possível carregar os alunos desta turma.';
            try {
                const err = await r.json();
                if (err?.message) msg = err.message;
            } catch {
                /* mantém mensagem genérica */
            }
            erroCarregar.value = msg;
            alunos.value = [];
            return;
        }
        const data = await r.json();
        modo.value = data.modo ?? 'por_disciplina';
        periodoAberto.value = data.periodo_aberto ?? true;
        tipoConfigurado.value = data.tipo_configurado ?? true;
        alunos.value = ((data.alunos ?? []) as any[]).map((a) => ({
            aln_id: a.aln_id,
            aln_nome: a.aln_nome,
            aln_nr_matricula: a.aln_nr_matricula,
            descricao: a.descricao ?? '',
            dt_saida: a.dt_saida ?? null,
            bloqueado: !!a.bloqueado_saida,
            _original: a.descricao ?? '',
            _status: (a.descricao ?? '').trim() ? 'saved' : 'idle',
            _timer: null,
        }));
    } finally {
        carregando.value = false;
    }
};

onMounted(carregar);
watch(() => [props.turId, props.disId, props.uniId], carregar);

const salvar = async (row: AlunoRow) => {
    if (row._timer) {
        clearTimeout(row._timer);
        row._timer = null;
    }
    if (!tipoConfigurado.value) return; // série sem tipo de avaliação descritiva configurado
    if (!periodoAberto.value) return; // fora da janela de lançamento
    if (row.bloqueado) return; // aluno saiu antes do período
    if (row.descricao === row._original) return; // nada mudou
    row._status = 'saving';
    try {
        const r = await fetch('/api/diario/avaliacao-descritiva', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({
                dad_esc_id: props.escId,
                dad_anl_id: props.anlId,
                dad_tur_id: props.turId,
                dad_dis_id: props.disId,
                dad_uni_id: props.uniId,
                dad_aln_id: row.aln_id,
                dad_descricao: row.descricao,
            }),
        });
        if (!r.ok) {
            row._status = 'error';
            return;
        }
        row._original = row.descricao;
        row._status = 'saved';
    } catch {
        row._status = 'error';
    }
};

// Autosave: debounce enquanto digita + flush imediato ao sair do campo.
const aoDigitar = (e: Event, row: AlunoRow) => {
    const el = e.target as HTMLTextAreaElement;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
    if (!periodoAberto.value) return;
    row._status = row.descricao === row._original ? 'saved' : 'dirty';
    if (row._timer) clearTimeout(row._timer);
    row._timer = window.setTimeout(() => salvar(row), 1500);
};

const fmtData = (dt: string | null) => {
    if (!dt) return '';
    const [y, m, d] = dt.split('-');
    return `${d}/${m}/${y}`;
};

// ref do textarea aberto (só 1 por vez) — função-ref evita array do v-for.
const bindTextarea = (el: any) => {
    textareaEl.value = (el as HTMLTextAreaElement) ?? null;
};

// Abre/fecha o aluno (acordeão). Ao abrir, foca e ajusta a altura.
const abrir = (row: AlunoRow) => {
    abertoId.value = abertoId.value === row.aln_id ? null : row.aln_id;
    if (abertoId.value !== null) {
        nextTick(() => {
            const el = textareaEl.value;
            if (el) {
                el.focus();
                el.style.height = 'auto';
                el.style.height = `${el.scrollHeight}px`;
            }
        });
    }
};

const iniciais = (nome: string) =>
    nome
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();

const filtrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter(
        (a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q),
    );
});

const total = computed(() => alunos.value.length);
const avaliados = computed(() => alunos.value.filter((a) => a.descricao.trim().length > 0).length);
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <!-- Barra fixa: título + progresso + busca -->
        <div
            class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-t-xl border-b bg-card/95 px-4 py-3 backdrop-blur"
        >
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Avaliação Descritiva</h2>
                    <span
                        class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                    >
                        {{ modo === 'por_aluno' ? 'Por aluno' : 'Por disciplina' }}
                    </span>
                </div>
                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Users class="size-3.5" />
                    <span class="font-medium text-foreground">{{ avaliados }}</span> de {{ total }} avaliados
                    <span class="text-muted-foreground/60">· salvamento automático</span>
                </p>
                <p v-if="modo === 'por_aluno'" class="mt-0.5 text-[11px] text-muted-foreground">
                    Parecer geral do aluno no período — vale para todas as disciplinas.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="busca" placeholder="Buscar aluno..." class="pl-8" />
                </div>
                <button
                    type="button"
                    :disabled="carregando"
                    title="Atualizar lista"
                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md border border-input px-3 text-sm font-medium transition hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="carregar"
                >
                    <RefreshCw :class="['size-4', carregando && 'animate-spin']" />
                    Atualizar
                </button>
            </div>
        </div>

        <div class="p-4">
            <div
                v-if="tipoConfigurado && !periodoAberto && !carregando"
                class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>
                    Este período está fechado para lançamento (fora da janela, incluindo a extensão) — apenas consulta.
                    Se houver outro período em andamento, selecione-o acima para fazer os lançamentos nele.
                </span>
            </div>

            <div v-if="carregando" class="py-12 text-center text-sm text-muted-foreground">Carregando alunos...</div>
            <div v-else-if="erroCarregar" class="py-12 text-center text-sm text-rose-600">{{ erroCarregar }}</div>
            <div
                v-else-if="!tipoConfigurado"
                class="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>
                    A série desta turma não tem o <strong>tipo de avaliação descritiva</strong> configurado. Defina
                    "Por Disciplina" ou "Por Aluno" no Cadastro de Série para liberar o lançamento.
                </span>
            </div>
            <div v-else-if="!alunos.length" class="py-12 text-center text-sm text-muted-foreground">
                Nenhum aluno ativo matriculado nesta turma.
            </div>
            <div v-else-if="!filtrados.length" class="py-12 text-center text-sm text-muted-foreground">
                Nenhum aluno encontrado para "{{ busca }}".
            </div>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="row in filtrados"
                    :key="row.aln_id"
                    class="overflow-hidden rounded-lg border bg-background transition-colors"
                    :class="abertoId === row.aln_id ? 'border-indigo-300' : 'hover:border-indigo-200'"
                >
                    <!-- Cabeçalho clicável -->
                    <button type="button" class="flex w-full items-center justify-between gap-3 p-3 text-left" @click="abrir(row)">
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                {{ iniciais(row.aln_nome) }}
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-slate-900 dark:text-slate-50">{{ row.aln_nome }}</div>
                                <div v-if="row.aln_nr_matricula" class="text-xs text-muted-foreground">
                                    Matrícula {{ row.aln_nr_matricula }}
                                </div>
                                <div v-if="row.dt_saida" class="mt-0.5 inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saída {{ fmtData(row.dt_saida) }}</div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-xs">
                            <span v-if="row.bloqueado" :title="row.dt_saida ? `Saiu em ${fmtData(row.dt_saida)}` : 'Aluno saiu da turma'" class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Saiu</span>
                            <span v-else-if="row._status === 'saving'" class="inline-flex items-center gap-1 text-amber-600">
                                <Loader2 class="size-3.5 animate-spin" /> Salvando
                            </span>
                            <span v-else-if="row._status === 'error'" class="inline-flex items-center gap-1 text-rose-600">
                                <TriangleAlert class="size-3.5" /> Erro
                            </span>
                            <span v-else-if="row.descricao.trim()" class="inline-flex items-center gap-1 font-medium text-emerald-600">
                                <CheckCircle2 class="size-3.5" /> Preenchido
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-muted-foreground">
                                <Circle class="size-3.5" /> Pendente
                            </span>
                            <ChevronDown
                                class="size-4 text-muted-foreground transition-transform"
                                :class="abertoId === row.aln_id && 'rotate-180'"
                            />
                        </div>
                    </button>

                    <!-- Input (abre ao clicar no aluno) -->
                    <div v-if="abertoId === row.aln_id" class="px-3 pb-3">
                        <textarea
                            :ref="bindTextarea"
                            v-model="row.descricao"
                            rows="3"
                            :disabled="!periodoAberto || row.bloqueado"
                            :placeholder="row.bloqueado ? 'Aluno saiu antes deste período — lançamento bloqueado.' : 'Descreva o desenvolvimento do aluno neste período...'"
                            class="w-full resize-none overflow-hidden rounded-md border bg-background p-2.5 text-sm leading-relaxed outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:opacity-60 dark:focus:ring-indigo-950"
                            @input="(e) => aoDigitar(e, row)"
                            @blur="salvar(row)"
                        />
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
