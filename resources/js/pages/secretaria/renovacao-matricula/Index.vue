<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, RefreshCw, Search, Users } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface SegmentoOpc { seg_id: number; seg_nome: string }
interface TurmaLinha { tur_id: number; turma: string; serie: string | null; seg_id: number | null; segmento: string | null }
interface AlunoLinha { aln_id: number; nome: string; matricula: string | null; renovar: boolean | null }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Renovação de Matrícula', href: '/secretaria/renovacao-matricula' }];

// Padrão: o MAIOR ano em exercício (sem depender da ordem da lista).
const anoDefault = [...props.anosLetivos].filter((a) => a.anl_fl_em_exercicio).sort((a, b) => b.anl_ano - a.anl_ano)[0] ?? props.anosLetivos[0];
const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');

const carregando = ref(false);
const carregado = ref(false);
const erro = ref<string | null>(null);
const turmas = ref<TurmaLinha[]>([]);
const segmentos = ref<SegmentoOpc[]>([]);
const segId = ref<number | ''>('');

const busca = ref('');
const turmasFiltradas = computed(() => {
    const q = busca.value.trim().toLowerCase();
    return turmas.value.filter((t) => {
        if (segId.value !== '' && t.seg_id !== segId.value) return false;
        if (q && !`${t.serie ?? ''} ${t.turma}`.toLowerCase().includes(q)) return false;
        return true;
    });
});

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};

const listar = async () => {
    if (!anlId.value || !escId.value) return;
    carregando.value = true;
    erro.value = null;
    selectedTurId.value = null;
    segId.value = '';
    try {
        const url = new URL('/secretaria/renovacao-matricula/turmas', window.location.origin);
        url.searchParams.set('anl_id', String(anlId.value));
        url.searchParams.set('esc_id', String(escId.value));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (r.ok) {
            const j = await r.json();
            turmas.value = j.turmas ?? [];
            segmentos.value = j.segmentos ?? [];
            carregado.value = true;
        } else {
            erro.value = `Não foi possível listar as turmas (erro ${r.status}).`;
        }
    } catch {
        erro.value = 'Falha de conexão ao listar as turmas.';
    } finally {
        carregando.value = false;
    }
};

// ===== Detalhe: alunos da turma =====
const selectedTurId = ref<number | null>(null);
const turmaSel = computed(() => turmas.value.find((t) => t.tur_id === selectedTurId.value) ?? null);
const alunos = ref<AlunoLinha[]>([]);
const carregandoAlunos = ref(false);
const buscaAluno = ref('');
const status = reactive<Record<number, 'saving' | 'saved' | 'error'>>({});

const alunosFiltrados = computed(() => {
    const q = buscaAluno.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter((a) => a.nome.toLowerCase().includes(q) || String(a.matricula ?? '').includes(q));
});
const totalRenovar = computed(() => alunos.value.filter((a) => a.renovar === true).length);
const totalNaoInformado = computed(() => alunos.value.filter((a) => a.renovar === null).length);

const carregarAlunos = async (tur: number) => {
    carregandoAlunos.value = true;
    erro.value = null;
    try {
        const url = new URL('/secretaria/renovacao-matricula/alunos', window.location.origin);
        url.searchParams.set('tur_id', String(tur));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (r.ok) { alunos.value = (await r.json()).alunos ?? []; }
        else { erro.value = `Não foi possível carregar os alunos (erro ${r.status}).`; }
    } catch {
        erro.value = 'Falha de conexão ao carregar os alunos.';
    } finally {
        carregandoAlunos.value = false;
    }
};

const abrirTurma = (t: TurmaLinha) => {
    selectedTurId.value = t.tur_id;
    Object.keys(status).forEach((k) => delete status[Number(k)]);
    carregarAlunos(t.tur_id);
};
const voltar = () => { selectedTurId.value = null; alunos.value = []; };

const marcarRenovar = async (a: AlunoLinha, val: boolean) => {
    const prev = a.renovar;
    a.renovar = val; // otimista
    status[a.aln_id] = 'saving';
    try {
        const r = await fetch('/secretaria/renovacao-matricula/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: selectedTurId.value, aln_id: a.aln_id, renovar: val }),
        });
        if (r.ok) { status[a.aln_id] = 'saved'; }
        else { a.renovar = prev; status[a.aln_id] = 'error'; }
    } catch {
        a.renovar = prev;
        status[a.aln_id] = 'error';
    }
};
</script>

<template>
    <Head title="Renovação de Matrícula" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <RefreshCw class="size-5 text-indigo-600" /> Renovação de Matrícula
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Marque, por aluno, o interesse em <strong>renovar a matrícula</strong> para o ano seguinte. Escolha o ano e a escola, selecione a turma e marque cada aluno.
            </p>

            <!-- Filtros -->
            <div class="mb-4 grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }}<span v-if="a.anl_fl_em_exercicio"> (em exercício)</span>
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button :disabled="!anlId || !escId || carregando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="listar">
                        <Loader2 v-if="carregando" class="mr-1 size-4 animate-spin" />
                        Listar Turmas
                    </Button>
                </div>
            </div>

            <div v-if="erro" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ erro }}</div>

            <!-- ===== Lista de turmas ===== -->
            <template v-if="carregado && !turmaSel">
                <div v-if="!turmas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                    Nenhuma turma encontrada para esta escola/ano.
                </div>
                <template v-else>
                    <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
                        <select v-if="segmentos.length" v-model="segId" class="h-9 rounded-md border border-input bg-background px-3 text-sm" title="Filtrar por segmento">
                            <option value="">Todos os segmentos</option>
                            <option v-for="sg in segmentos" :key="sg.seg_id" :value="sg.seg_id">{{ sg.seg_nome }}</option>
                        </select>
                        <div class="relative w-full max-w-xs sm:w-56">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="busca" type="text" placeholder="Buscar turma..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                    </div>
                    <div v-if="!turmasFiltradas.length" class="rounded-xl border bg-card py-10 text-center text-sm text-muted-foreground">Nenhuma turma encontrada.</div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="t in turmasFiltradas"
                            :key="t.tur_id"
                            type="button"
                            class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-card p-4 text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800"
                            @click="abrirTurma(t)"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ t.serie ? `${t.serie} — ` : '' }}Turma {{ t.turma }}</div>
                                <div v-if="t.segmento" class="mt-0.5 text-xs text-muted-foreground">{{ t.segmento }}</div>
                            </div>
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300">
                                <Users class="size-4" />
                            </span>
                        </button>
                    </div>
                </template>
            </template>

            <!-- ===== Detalhe: alunos ===== -->
            <template v-else-if="turmaSel">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="voltar"><ArrowLeft class="mr-1 size-4" /> Turmas</Button>
                        <h2 class="text-lg font-semibold">{{ turmaSel.serie ? `${turmaSel.serie} — ` : '' }}Turma {{ turmaSel.turma }}</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">{{ totalRenovar }} querem renovar · {{ totalNaoInformado }} não informado</span>
                        <div class="relative w-48">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input v-model="buscaAluno" type="text" placeholder="Filtrar aluno..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                    </div>
                </div>

                <div v-if="carregandoAlunos" class="rounded-xl border bg-card py-12 text-center text-sm text-muted-foreground">Carregando alunos...</div>
                <div v-else-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</div>

                <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">#</th>
                                    <th class="px-3 py-2 font-semibold">Aluno</th>
                                    <th class="px-3 py-2 text-center font-semibold">Renovação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-if="!alunosFiltrados.length"><td colspan="3" class="px-3 py-6 text-center text-muted-foreground">Nenhum aluno encontrado.</td></tr>
                                <tr v-for="(a, i) in alunosFiltrados" :key="a.aln_id" class="hover:bg-muted/20">
                                    <td class="px-3 py-2 text-muted-foreground">{{ i + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium">{{ a.nome }}</div>
                                        <div v-if="a.matricula" class="text-[11px] text-muted-foreground">Matr. {{ a.matricula }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-center gap-2">
                                            <div class="inline-flex overflow-hidden rounded-md border border-input">
                                                <button type="button" class="px-3 py-1 text-xs font-medium transition-colors" :class="a.renovar === true ? 'bg-emerald-600 text-white' : 'bg-background hover:bg-muted'" @click="marcarRenovar(a, true)">Sim</button>
                                                <button type="button" class="border-l border-input px-3 py-1 text-xs font-medium transition-colors" :class="a.renovar === false ? 'bg-rose-600 text-white' : 'bg-background hover:bg-muted'" @click="marcarRenovar(a, false)">Não</button>
                                            </div>
                                            <span v-if="a.renovar === null" class="text-[10px] italic text-muted-foreground">não informado</span>
                                            <Loader2 v-if="status[a.aln_id] === 'saving'" class="size-3.5 animate-spin text-amber-600" />
                                            <span v-else-if="status[a.aln_id] === 'error'" class="text-[11px] text-rose-600">erro</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <div v-else class="rounded-xl border bg-card px-6 py-10 text-center text-sm text-muted-foreground shadow-sm">
                Selecione o ano letivo e a escola para listar as turmas.
            </div>
        </div>
    </AppLayout>
</template>
