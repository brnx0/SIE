<script setup lang="ts">
import { Loader2, RefreshCw, Search, TriangleAlert } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps<{ anlId: number; escId: number; turId: number; disId: number; uniId: number }>();

interface Indicador { ind_id: number; ind_descricao: string }
interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null }

const carregando = ref(true);
const recarregando = ref(false);
const erro = ref<string | null>(null);
const indicadores = ref<Indicador[]>([]);
const alunos = ref<Aluno[]>([]);
const opcoes = ref<Record<string, string>>({});
const tipoDisponivel = ref(true);
const periodoAberto = ref(true);
const turmaAberta = ref(true);
const busca = ref('');

const valorMap = reactive<Record<string, string>>({});
const status = reactive<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});

const editavel = computed(() => periodoAberto.value && turmaAberta.value);
const ck = (aln: number, ind: number) => `${aln}|${ind}`;

// Cor por opção (feedback rápido).
const corOpcao: Record<string, string> = {
    autonomia:    'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    apoio:        'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
    nao_realiza:  'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
    nao_trabalha: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};

const carregar = async (silent = false) => {
    if (silent) recarregando.value = true; else carregando.value = true;
    erro.value = null;
    try {
        const url = new URL('/api/diario/diagnostico/contexto', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        url.searchParams.set('dis_id', String(props.disId));
        url.searchParams.set('uni_id', String(props.uniId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) { erro.value = 'Não foi possível carregar o diagnóstico.'; return; }
        const data = await r.json();
        Object.keys(valorMap).forEach((k) => delete valorMap[k]);
        Object.keys(status).forEach((k) => delete status[k]);
        indicadores.value = data.indicadores ?? [];
        alunos.value = data.alunos ?? [];
        opcoes.value = data.opcoes ?? {};
        tipoDisponivel.value = data.tipo_disponivel ?? false;
        periodoAberto.value = data.periodo_aberto ?? false;
        turmaAberta.value = data.turma_aberta ?? false;
        for (const v of data.valores ?? []) valorMap[ck(v.aln_id, v.ind_id)] = v.opcao;
    } finally {
        carregando.value = false;
        recarregando.value = false;
    }
};

onMounted(() => carregar());

const alunosFiltrados = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return alunos.value;
    return alunos.value.filter((a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q));
});

const opcoesKeys = computed(() => Object.keys(opcoes.value));
const valorDe = (aln: number, ind: number) => valorMap[ck(aln, ind)] ?? '';

const salvar = async (aln: number, ind: number, val: string) => {
    if (!editavel.value) return;
    const key = ck(aln, ind);
    if (val) valorMap[key] = val; else delete valorMap[key];
    status[key] = 'saving';
    try {
        const r = await fetch('/api/diario/diagnostico/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ tur_id: props.turId, dis_id: props.disId, uni_id: props.uniId, aln_id: aln, ind_id: ind, opcao: val || null }),
        });
        status[key] = r.ok ? 'saved' : 'error';
    } catch {
        status[key] = 'error';
    }
};
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-card/95 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Avaliação por Diagnóstico</h2>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="busca" type="text" placeholder="Filtrar aluno..." class="h-9 w-44 rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                </div>
                <Button variant="outline" size="sm" class="gap-1.5" :disabled="carregando || recarregando" @click="carregar(true)">
                    <RefreshCw :class="['size-4', recarregando && 'animate-spin']" /> Atualizar
                </Button>
            </div>
        </div>

        <div class="p-4">
            <div v-if="carregando" class="py-12 text-center text-sm text-muted-foreground">Carregando...</div>
            <div v-else-if="erro" class="py-12 text-center text-sm text-rose-600">{{ erro }}</div>
            <div v-else-if="!tipoDisponivel" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" /><span>A série desta turma não usa avaliação por diagnóstico.</span>
            </div>
            <div v-else-if="!indicadores.length" class="py-12 text-center text-sm text-muted-foreground">Nenhum indicador cadastrado para esta disciplina/série.</div>
            <div v-else-if="!alunos.length" class="py-12 text-center text-sm text-muted-foreground">Nenhum aluno na turma.</div>

            <template v-else>
                <div v-if="!editavel" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ !turmaAberta ? 'Turma não está aberta.' : 'Fora do período de lançamento.' }} Diagnóstico em modo leitura.</span>
                </div>

                <p class="mb-2 text-xs text-muted-foreground">
                    Ao marcar <b>Realiza com autonomia</b>, os períodos seguintes são preenchidos com a mesma opção (editável).
                </p>

                <div class="max-h-[70vh] overflow-auto rounded-lg border">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-30 min-w-[220px] border-b bg-muted/90 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur">Aluno</th>
                                <th v-for="ind in indicadores" :key="ind.ind_id" class="sticky top-0 z-20 min-w-[180px] border-b border-l bg-muted/90 px-2 py-2 text-left text-xs font-semibold text-slate-700 backdrop-blur dark:text-slate-200" :title="ind.ind_descricao">
                                    {{ ind.ind_descricao }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(a, i) in alunosFiltrados" :key="a.aln_id" class="group">
                                <td :class="['sticky left-0 z-10 border-b px-3 py-2', i % 2 ? 'bg-muted/30' : 'bg-card']">
                                    <div class="truncate text-sm font-medium text-slate-900 dark:text-slate-50">{{ a.aln_nome }}</div>
                                    <div v-if="a.aln_nr_matricula" class="text-[10px] text-muted-foreground">Mat. {{ a.aln_nr_matricula }}</div>
                                </td>
                                <td v-for="ind in indicadores" :key="ind.ind_id" class="border-b border-l px-2 py-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <select
                                            :value="valorDe(a.aln_id, ind.ind_id)"
                                            :disabled="!editavel"
                                            :class="['h-9 w-full rounded-md border bg-background px-2 text-xs disabled:opacity-60', corOpcao[valorDe(a.aln_id, ind.ind_id)] ?? '']"
                                            @change="salvar(a.aln_id, ind.ind_id, ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">—</option>
                                            <option v-for="k in opcoesKeys" :key="k" :value="k">{{ opcoes[k] }}</option>
                                        </select>
                                        <Loader2 v-if="status[ck(a.aln_id, ind.ind_id)] === 'saving'" class="size-3.5 shrink-0 animate-spin text-amber-600" />
                                        <span v-else-if="status[ck(a.aln_id, ind.ind_id)] === 'error'" class="text-[10px] text-rose-600">erro</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Legenda -->
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                    <span v-for="k in opcoesKeys" :key="k" class="inline-flex items-center gap-1">
                        <span :class="['inline-block size-3 rounded', corOpcao[k]]"></span> {{ opcoes[k] }}
                    </span>
                </div>
            </template>
        </div>
    </section>
</template>
