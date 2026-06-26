<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import Switch from '@/components/common/Switch.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, ArrowRightLeft, Loader2, Search, TriangleAlert, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Segmento { seg_id: number; seg_nome_reduzido: string; seg_nome_completo: string }
interface Serie { ser_id: number; ser_nome: string; ser_fl_multi: boolean }
interface Turma {
    tur_id: number; tur_nome: string; tur_turno: string; tur_situacao: string;
    tur_seg_id: number; tur_ser_id: number; serie: Serie | null;
    capacidade: number | null; total_ativos: number; vagas: number | null;
}
interface AlunoLinha { tma_id: number; aln_id: number; aln_nome: string; matricula: string | null; ser_id: number; ser_nome: string | null }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    segmentos: Segmento[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Remanejar Turmas', href: '/remanejamento-turmas' }];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];
const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');
const segId = ref<number | ''>('');

const turmas = ref<Turma[]>([]);
const carregandoTurmas = ref(false);

const aId = ref<number | null>(null);
const bId = ref<number | null>(null);
const alunosA = ref<AlunoLinha[]>([]);
const alunosB = ref<AlunoLinha[]>([]);
const selA = ref<Set<number>>(new Set());
const selB = ref<Set<number>>(new Set());
const buscaA = ref('');
const buscaB = ref('');
const carregandoA = ref(false);
const carregandoB = ref(false);

const dtMov = ref<string>(new Date().toISOString().slice(0, 10));
const migrarNotas = ref(false);
const migrarFaltas = ref(false);
const processando = ref(false);
const erro = ref<string | null>(null);
const ok = ref<string | null>(null);

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};

const turA = computed(() => turmas.value.find((t) => t.tur_id === aId.value) ?? null);
const turB = computed(() => turmas.value.find((t) => t.tur_id === bId.value) ?? null);
// B só pode ser turma da MESMA série de A (e diferente de A).
const turmasB = computed(() => {
    if (!turA.value) return [];
    return turmas.value.filter((t) => t.tur_id !== turA.value!.tur_id && t.tur_ser_id === turA.value!.tur_ser_id && t.tur_situacao === 'ABERTA');
});

const filtrar = (lista: AlunoLinha[], q: string) => {
    const t = q.trim().toLowerCase();
    if (!t) return lista;
    return lista.filter((a) => a.aln_nome.toLowerCase().includes(t) || (a.matricula ?? '').toLowerCase().includes(t));
};
const alunosAF = computed(() => filtrar(alunosA.value, buscaA.value));
const alunosBF = computed(() => filtrar(alunosB.value, buscaB.value));
const turnoLabel = (t: string) => ({ MATUTINO: 'Matutino', VESPERTINO: 'Vespertino', NOTURNO: 'Noturno', INTEGRAL: 'Integral' } as Record<string, string>)[t] ?? t;

const carregarTurmas = async () => {
    if (!anlId.value || !escId.value) return;
    carregandoTurmas.value = true;
    aId.value = null; bId.value = null;
    alunosA.value = []; alunosB.value = [];
    selA.value = new Set(); selB.value = new Set();
    erro.value = null; ok.value = null;
    try {
        const url = new URL('/remanejamento-turmas/turmas', window.location.origin);
        url.searchParams.set('anl_id', String(anlId.value));
        url.searchParams.set('esc_id', String(escId.value));
        if (segId.value) url.searchParams.set('seg_id', String(segId.value));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        turmas.value = r.ok ? await r.json() : [];
    } finally {
        carregandoTurmas.value = false;
    }
};

const fetchAlunos = async (turId: number): Promise<AlunoLinha[]> => {
    const url = new URL('/remanejamento-turmas/alunos', window.location.origin);
    url.searchParams.set('tur_id', String(turId));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};

const onSelA = async () => {
    selA.value = new Set();
    erro.value = null; ok.value = null;
    if (turB.value && turB.value.tur_ser_id !== turA.value?.tur_ser_id) { bId.value = null; alunosB.value = []; }
    if (!aId.value) { alunosA.value = []; return; }
    carregandoA.value = true;
    try { alunosA.value = await fetchAlunos(aId.value); } finally { carregandoA.value = false; }
};
const onSelB = async () => {
    selB.value = new Set();
    erro.value = null; ok.value = null;
    if (!bId.value) { alunosB.value = []; return; }
    carregandoB.value = true;
    try { alunosB.value = await fetchAlunos(bId.value); } finally { carregandoB.value = false; }
};

const toggleSel = (which: 'a' | 'b', id: number) => {
    const target = which === 'a' ? selA : selB;
    const s = new Set(target.value);
    if (s.has(id)) s.delete(id); else s.add(id);
    target.value = s;
};

const recarregar = async () => {
    await carregarTurmasKeep();
    if (aId.value) { carregandoA.value = true; try { alunosA.value = await fetchAlunos(aId.value); } finally { carregandoA.value = false; } }
    if (bId.value) { carregandoB.value = true; try { alunosB.value = await fetchAlunos(bId.value); } finally { carregandoB.value = false; } }
    selA.value = new Set(); selB.value = new Set();
};
const carregarTurmasKeep = async () => {
    if (!anlId.value || !escId.value) return;
    const url = new URL('/remanejamento-turmas/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    url.searchParams.set('esc_id', String(escId.value));
    if (segId.value) url.searchParams.set('seg_id', String(segId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};

const post = async (url: string, body: Record<string, any>): Promise<boolean> => {
    processando.value = true;
    erro.value = null; ok.value = null;
    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ ...body, dt_movimentacao: dtMov.value, migrar_notas: migrarNotas.value, migrar_faltas: migrarFaltas.value }),
        });
        const j = await r.json().catch(() => null);
        if (r.ok) { ok.value = `${j?.ok ?? 0} aluno(s) remanejado(s).`; await recarregar(); return true; }
        erro.value = j?.message ?? `Erro (${r.status}).`;
        return false;
    } catch { erro.value = 'Falha de conexão.'; return false; }
    finally { processando.value = false; }
};

const moverAB = () => { if (!turB.value || !selA.value.size) return; post('/remanejamento-turmas/remanejar', { tur_id_destino: turB.value.tur_id, tma_ids: [...selA.value] }); };
const moverBA = () => { if (!turA.value || !selB.value.size) return; post('/remanejamento-turmas/remanejar', { tur_id_destino: turA.value.tur_id, tma_ids: [...selB.value] }); };
const trocar = () => {
    if (!turA.value || !turB.value || (!selA.value.size && !selB.value.size)) return;
    post('/remanejamento-turmas/trocar', { tur_a: turA.value.tur_id, tur_b: turB.value.tur_id, tma_ids_a: [...selA.value], tma_ids_b: [...selB.value] });
};
</script>

<template>
    <Head title="Remanejar Turmas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ArrowRightLeft class="size-5 text-indigo-600" /> Remanejar Turmas
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Move ou troca alunos entre turmas da mesma série (mesma escola e segmento).
            </p>

            <!-- Filtros -->
            <div class="mb-4 grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}<span v-if="a.anl_fl_em_exercicio"> (em exercício)</span></option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Segmento</FormLabel>
                    <select v-model="segId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <option v-for="s in segmentos" :key="s.seg_id" :value="s.seg_id">{{ s.seg_nome_reduzido ?? s.seg_nome_completo }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button :disabled="!anlId || !escId || carregandoTurmas" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="carregarTurmas">
                        <Loader2 v-if="carregandoTurmas" class="mr-1 size-4 animate-spin" /> Carregar Turmas
                    </Button>
                </div>
            </div>

            <div v-if="ok" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ ok }}</div>
            <div v-if="erro" class="mb-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" /> {{ erro }}
            </div>

            <template v-if="turmas.length">
                <!-- Controles globais -->
                <div class="mb-4 flex flex-wrap items-center gap-4 rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <div class="grid gap-1">
                        <FormLabel class="text-xs">Data do remanejamento</FormLabel>
                        <input v-model="dtMov" type="date" class="h-9 rounded-md border bg-background px-2 text-sm" title="Data efetiva: saída da turma atual e entrada na nova turma." />
                        <span class="text-[11px] text-muted-foreground">Data da saída da turma atual e da entrada na nova (padrão: hoje).</span>
                    </div>
                    <div class="flex items-center gap-2 self-end text-xs"><Switch :model-value="migrarNotas" @update:model-value="migrarNotas = $event" /> <span>Migrar notas</span></div>
                    <div class="flex items-center gap-2 self-end text-xs"><Switch :model-value="migrarFaltas" @update:model-value="migrarFaltas = $event" /> <span>Migrar faltas</span></div>
                    <div class="ml-auto flex items-center gap-2 self-end">
                        <Button size="sm" variant="outline" :disabled="processando || !turB || !selA.size" class="gap-1" @click="moverAB"><ArrowRight class="size-4" /> A→B</Button>
                        <Button size="sm" :disabled="processando || !turA || !turB || (!selA.size && !selB.size)" class="gap-1 bg-indigo-600 text-white hover:bg-indigo-700" @click="trocar">
                            <Loader2 v-if="processando" class="size-4 animate-spin" /><ArrowRightLeft v-else class="size-4" /> Trocar
                        </Button>
                        <Button size="sm" variant="outline" :disabled="processando || !turA || !selB.size" class="gap-1" @click="moverBA"><ArrowLeft class="size-4" /> B→A</Button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <!-- Painel A -->
                    <section class="rounded-xl border bg-card shadow-sm">
                        <header class="border-b px-4 py-2.5"><h2 class="text-sm font-semibold">Turma A</h2></header>
                        <div class="p-4">
                            <select v-model="aId" class="mb-3 w-full rounded-md border bg-background px-3 py-2 text-sm" @change="onSelA">
                                <option :value="null">Selecione a turma A...</option>
                                <option v-for="t in turmas" :key="t.tur_id" :value="t.tur_id">{{ t.serie?.ser_nome ?? '' }} — {{ t.tur_nome }} ({{ turnoLabel(t.tur_turno) }})</option>
                            </select>
                            <div v-if="turA" class="mb-2 text-xs text-muted-foreground">
                                {{ turA.total_ativos }} aluno(s)<span v-if="turA.vagas !== null"> · <b :class="turA.vagas > 0 ? 'text-emerald-600' : 'text-rose-600'">{{ turA.vagas }} vaga(s)</b></span>
                                <span v-if="turA.serie?.ser_fl_multi" class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-950/40">multi</span>
                            </div>
                            <div v-if="carregandoA" class="py-8 text-center"><Loader2 class="mx-auto size-4 animate-spin" /></div>
                            <template v-else-if="turA">
                                <div class="relative mb-2 max-w-xs">
                                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <input v-model="buscaA" type="text" placeholder="Buscar..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1" />
                                </div>
                                <div class="max-h-[50vh] overflow-auto rounded-lg border">
                                    <label v-for="a in alunosAF" :key="a.tma_id" class="flex cursor-pointer items-center gap-3 border-b px-3 py-2 text-sm last:border-0 hover:bg-muted/40">
                                        <input type="checkbox" :checked="selA.has(a.tma_id)" @change="toggleSel('a', a.tma_id)" />
                                        <span class="min-w-0 flex-1 truncate">{{ a.aln_nome }}<span v-if="a.matricula" class="text-[11px] text-muted-foreground"> · {{ a.matricula }}</span></span>
                                    </label>
                                    <div v-if="!alunosAF.length" class="px-3 py-6 text-center text-sm text-muted-foreground">Nenhum aluno.</div>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground"><b>{{ selA.size }}</b> selecionado(s)</p>
                            </template>
                        </div>
                    </section>

                    <!-- Painel B -->
                    <section class="rounded-xl border bg-card shadow-sm">
                        <header class="border-b px-4 py-2.5"><h2 class="text-sm font-semibold">Turma B (mesma série de A)</h2></header>
                        <div class="p-4">
                            <select v-model="bId" :disabled="!turA" class="mb-3 w-full rounded-md border bg-background px-3 py-2 text-sm disabled:opacity-60" @change="onSelB">
                                <option :value="null">{{ turA ? 'Selecione a turma B...' : 'Escolha a turma A primeiro' }}</option>
                                <option v-for="t in turmasB" :key="t.tur_id" :value="t.tur_id">{{ t.serie?.ser_nome ?? '' }} — {{ t.tur_nome }} ({{ turnoLabel(t.tur_turno) }})</option>
                            </select>
                            <div v-if="turB" class="mb-2 text-xs text-muted-foreground">
                                {{ turB.total_ativos }} aluno(s)<span v-if="turB.vagas !== null"> · <b :class="turB.vagas > 0 ? 'text-emerald-600' : 'text-rose-600'">{{ turB.vagas }} vaga(s)</b></span>
                                <span v-if="turB.serie?.ser_fl_multi" class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-950/40">multi</span>
                            </div>
                            <div v-if="carregandoB" class="py-8 text-center"><Loader2 class="mx-auto size-4 animate-spin" /></div>
                            <template v-else-if="turB">
                                <div class="relative mb-2 max-w-xs">
                                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <input v-model="buscaB" type="text" placeholder="Buscar..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1" />
                                </div>
                                <div class="max-h-[50vh] overflow-auto rounded-lg border">
                                    <label v-for="a in alunosBF" :key="a.tma_id" class="flex cursor-pointer items-center gap-3 border-b px-3 py-2 text-sm last:border-0 hover:bg-muted/40">
                                        <input type="checkbox" :checked="selB.has(a.tma_id)" @change="toggleSel('b', a.tma_id)" />
                                        <span class="min-w-0 flex-1 truncate">{{ a.aln_nome }}<span v-if="a.matricula" class="text-[11px] text-muted-foreground"> · {{ a.matricula }}</span></span>
                                    </label>
                                    <div v-if="!alunosBF.length" class="px-3 py-6 text-center text-sm text-muted-foreground">Nenhum aluno.</div>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground"><b>{{ selB.size }}</b> selecionado(s)</p>
                            </template>
                            <p v-else class="py-8 text-center text-sm text-muted-foreground">Selecione a turma B.</p>
                        </div>
                    </section>
                </div>
            </template>
            <div v-else-if="!carregandoTurmas" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                <Users class="mx-auto mb-2 size-8 text-muted-foreground" />
                Selecione ano, escola e segmento e carregue as turmas.
            </div>
        </div>
    </AppLayout>
</template>
